<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function customerOrdersRespond($status, $data)
{
    http_response_code($status);
    echo json_encode($data);
    exit();
}

if (empty($_SESSION['user_id']) || (int) ($_SESSION['position'] ?? 0) !== 1) {
    customerOrdersRespond(401, ['success' => false, 'message' => 'Please sign in to view your orders.']);
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    customerOrdersRespond(405, ['success' => false, 'message' => 'Method not allowed.']);
}
require_once __DIR__ . DIRECTORY_SEPARATOR . 'connect.php';
$userId = (int) $_SESSION['user_id'];
$activeUserStmt = $conn->prepare('SELECT user_id FROM users WHERE user_id = ? AND position = 1 AND is_active = 1');
if (!$activeUserStmt) customerOrdersRespond(500, ['success' => false, 'message' => 'Unable to verify your account.']);
$activeUserStmt->bind_param('i', $userId);
if (!$activeUserStmt->execute()) {
    $activeUserStmt->close();
    customerOrdersRespond(500, ['success' => false, 'message' => 'Unable to verify your account.']);
}
$activeUser = $activeUserStmt->get_result()->fetch_assoc();
$activeUserStmt->close();
if (!$activeUser) customerOrdersRespond(401, ['success' => false, 'message' => 'Your customer account is inactive or unavailable.']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expectedOrigin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    if (
        empty($_SERVER['HTTP_ORIGIN']) ||
        $expectedOrigin === '://' ||
        !hash_equals($expectedOrigin, $_SERVER['HTTP_ORIGIN'])
    ) {
        customerOrdersRespond(403, ['success' => false, 'message' => 'Request origin could not be verified.']);
    }
    if (empty($_SESSION['customer_csrf_token'])
        || !hash_equals($_SESSION['customer_csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        customerOrdersRespond(403, ['success' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
    }
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        customerOrdersRespond(415, ['success' => false, 'message' => 'Order updates must be sent as JSON.']);
    }
    $rawPayload = file_get_contents('php://input');
    if ($rawPayload === false || strlen($rawPayload) > 16384) {
        customerOrdersRespond(413, ['success' => false, 'message' => 'The order update is too large.']);
    }
    $payload = json_decode($rawPayload, true);
    if (!is_array($payload)) customerOrdersRespond(400, ['success' => false, 'message' => 'Invalid order update request.']);
    $orderId = filter_var($payload['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($orderId === false) customerOrdersRespond(400, ['success' => false, 'message' => 'Choose a valid order.']);

    require_once __DIR__ . DIRECTORY_SEPARATOR . 'notifications-bootstrap.php';
    if (!ensureNotificationTables($conn)) {
        customerOrdersRespond(500, ['success' => false, 'message' => 'Order notifications are temporarily unavailable.']);
    }
    if (!$conn->begin_transaction()) {
        customerOrdersRespond(500, ['success' => false, 'message' => 'Unable to start the order cancellation.']);
    }
    try {
        $orderStmt = $conn->prepare(
            'SELECT order_number, status FROM orders WHERE order_id = ? AND user_id = ? FOR UPDATE'
        );
        if (!$orderStmt) throw new Exception('Unable to prepare order lookup.');
        $orderStmt->bind_param('ii', $orderId, $userId);
        if (!$orderStmt->execute()) throw new Exception('Unable to load order.');
        $order = $orderStmt->get_result()->fetch_assoc();
        $orderStmt->close();
        if (!$order) {
            $conn->rollback();
            customerOrdersRespond(404, ['success' => false, 'message' => 'Order was not found.']);
        }
        if ($order['status'] !== 'Processing') {
            $conn->rollback();
            customerOrdersRespond(409, ['success' => false, 'message' => 'Only Processing orders can be cancelled.']);
        }

        $itemsStmt = $conn->prepare(
            'SELECT oi.variant_id, SUM(oi.quantity) AS quantity, pv.product_id, pv.size, p.name AS product_name
             FROM order_items oi
             JOIN product_variants pv ON pv.variant_id = oi.variant_id
             JOIN products p ON p.product_id = pv.product_id
             WHERE oi.order_id = ? AND oi.variant_id IS NOT NULL
             GROUP BY oi.variant_id, pv.product_id, pv.size, p.name'
        );
        if (!$itemsStmt) throw new Exception('Unable to prepare order inventory lookup.');
        $itemsStmt->bind_param('i', $orderId);
        if (!$itemsStmt->execute()) throw new Exception('Unable to load order inventory.');
        $itemsResult = $itemsStmt->get_result();
        $variants = [];
        while ($item = $itemsResult->fetch_assoc()) {
            $variants[] = [
                'id' => (int) $item['variant_id'],
                'quantity' => (int) $item['quantity'],
                'product_id' => (int) $item['product_id'],
                'size' => $item['size'],
                'product_name' => $item['product_name']
            ];
        }
        $itemsStmt->close();

        $restoreStmt = $conn->prepare(
            'UPDATE product_variants SET stock_quantity = stock_quantity + ?
             WHERE variant_id = ? AND stock_quantity <= 2147483647 - ?'
        );
        if (!$restoreStmt) throw new Exception('Unable to prepare inventory restoration.');
        foreach ($variants as $variant) {
            $restoreStmt->bind_param('iii', $variant['quantity'], $variant['id'], $variant['quantity']);
            if (!$restoreStmt->execute() || $restoreStmt->affected_rows !== 1) {
                throw new Exception('Unable to safely restore cancelled order inventory.');
            }
        }
        $restoreStmt->close();

        if ($variants) {
            $stockLookup = $conn->prepare(
                'SELECT SUM(pv.stock_quantity) AS stock_quantity
                 FROM product_variants pv
                 JOIN products p ON p.product_id = pv.product_id AND p.is_active = 1
                 JOIN categories c ON c.category_id = p.category_id AND c.is_active = 1
                 WHERE pv.product_id = ? AND pv.size = ? AND pv.is_active = 1'
            );
            if (!$stockLookup) throw new Exception('Unable to prepare product stock notification lookup.');
            $stockKeys = [];
            foreach ($variants as $variant) {
                $stockKey = $variant['product_id'] . ':' . $variant['size'];
                if (isset($stockKeys[$stockKey])) continue;
                $stockKeys[$stockKey] = true;
                $productId = $variant['product_id'];
                $size = $variant['size'];
                $stockLookup->bind_param('is', $productId, $size);
                if (!$stockLookup->execute()) throw new Exception('Unable to check product stock notification level.');
                $stockRow = $stockLookup->get_result()->fetch_assoc();
                if ($stockRow && $stockRow['stock_quantity'] !== null && !syncLowStockNotification(
                    $conn,
                    $productId,
                    $size,
                    $variant['product_name'],
                    (int) $stockRow['stock_quantity']
                )) {
                    throw new Exception('Unable to save product stock notifications.');
                }
            }
            $stockLookup->close();
        }

        $updateStmt = $conn->prepare(
            "UPDATE orders SET status = 'Cancelled' WHERE order_id = ? AND user_id = ? AND status = 'Processing'"
        );
        if (!$updateStmt) throw new Exception('Unable to prepare order cancellation.');
        $updateStmt->bind_param('ii', $orderId, $userId);
        if (!$updateStmt->execute() || $updateStmt->affected_rows !== 1) {
            throw new Exception('Unable to cancel the order.');
        }
        $updateStmt->close();

        if (!notifyActiveAdmins(
            $conn,
            'order_status',
            'Customer cancelled an order',
            'A customer cancelled order ' . $order['order_number'] . '.',
            'admin-order.php?highlight-order=' . $orderId
        )) {
            throw new Exception('Unable to notify administrators about the cancellation.');
        }
        if (!$conn->commit()) throw new Exception('Unable to complete the order cancellation.');
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('Customer order cancellation failed: ' . $error->getMessage());
        customerOrdersRespond(500, ['success' => false, 'message' => 'Unable to cancel the order.']);
    }
    customerOrdersRespond(200, ['success' => true, 'status' => 'Cancelled']);
}

$stmt = $conn->prepare('SELECT order_id, order_number, status, payment_method, subtotal, vat_amount, shipping_fee, total_amount, placed_at FROM orders WHERE user_id = ? ORDER BY placed_at DESC, order_id DESC');
if (!$stmt) customerOrdersRespond(500, ['success' => false, 'message' => 'Unable to load orders.']);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $row['order_id'] = (int) $row['order_id'];
    $row['subtotal'] = (float) $row['subtotal'];
    $row['vat'] = (float) $row['vat_amount'];
    $row['shipping'] = (float) $row['shipping_fee'];
    $row['total'] = (float) $row['total_amount'];
    $row['id'] = $row['order_number'];
    $row['date'] = date('M j, Y', strtotime($row['placed_at']));
    $row['payment'] = $row['payment_method'];
    $row['items'] = [];
    $orders[$row['order_id']] = $row;
}
$stmt->close();
if ($orders) {
    $ids = implode(',', array_map('intval', array_keys($orders)));
    $itemsResult = $conn->query('SELECT oi.order_id, oi.product_name, oi.size, oi.color, oi.unit_price, oi.quantity, oi.line_total, (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = oi.product_id ORDER BY pi.sort_order, pi.image_id LIMIT 1) AS image_url FROM order_items oi WHERE oi.order_id IN (' . $ids . ') ORDER BY oi.order_item_id');
    if (!$itemsResult) customerOrdersRespond(500, ['success' => false, 'message' => 'Unable to load order items.']);
    while ($item = $itemsResult->fetch_assoc()) {
        $orderId = (int) $item['order_id'];
        $orders[$orderId]['items'][] = [
            'name' => $item['product_name'],
            'size' => $item['size'],
            'color' => $item['color'],
            'price' => (float) $item['unit_price'],
            'quantity' => (int) $item['quantity'],
            'image' => $item['image_url'] ?: 'assets/product.jpg'
        ];
    }
}
customerOrdersRespond(200, ['success' => true, 'orders' => array_values($orders)]);
?>
