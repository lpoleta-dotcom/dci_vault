<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function adminOrdersRespond($status, $data)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-api-session.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'catalog-bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'notifications-bootstrap.php';
if (!ensureNotificationTables($conn)) {
    adminOrdersRespond(500, ['success' => false, 'message' => 'Order notifications are temporarily unavailable.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expectedOrigin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    if (
        empty($_SERVER['HTTP_ORIGIN']) ||
        $expectedOrigin === '://' ||
        !hash_equals($expectedOrigin, $_SERVER['HTTP_ORIGIN'])
    ) {
        adminOrdersRespond(403, ['success' => false, 'message' => 'Request origin could not be verified.']);
    }
    if (!hash_equals($_SESSION['admin_csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        adminOrdersRespond(403, ['success' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
    }
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        adminOrdersRespond(415, ['success' => false, 'message' => 'Order updates must be sent as JSON.']);
    }
    $rawPayload = file_get_contents('php://input');
    if ($rawPayload === false || strlen($rawPayload) > 16384) {
        adminOrdersRespond(413, ['success' => false, 'message' => 'The order update is too large.']);
    }
    $payload = json_decode($rawPayload, true);
    if (!is_array($payload)) adminOrdersRespond(400, ['success' => false, 'message' => 'Invalid order update request.']);
    $orderId = filter_var($payload['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $status = isset($payload['status']) ? $payload['status'] : '';
    if ($orderId === false || !in_array($status, ['Processing', 'Completed', 'Cancelled'], true)) {
        adminOrdersRespond(400, ['success' => false, 'message' => 'Invalid order or status.']);
    }
    if (!$conn->begin_transaction()) {
        adminOrdersRespond(500, ['success' => false, 'message' => 'Unable to start the order update.']);
    }
    try {
        $orderStmt = $conn->prepare('SELECT user_id, order_number, status FROM orders WHERE order_id = ? FOR UPDATE');
        if (!$orderStmt) throw new Exception('Unable to prepare order status lookup.');
        $orderStmt->bind_param('i', $orderId);
        if (!$orderStmt->execute()) throw new Exception('Unable to load order status.');
        $order = $orderStmt->get_result()->fetch_assoc();
        $orderStmt->close();
        if (!$order) {
            $conn->rollback();
            adminOrdersRespond(404, ['success' => false, 'message' => 'Order was not found.']);
        }
        $oldStatus = $order['status'];
        $allowedTransitions = [
            'Processing' => ['Processing', 'Completed', 'Cancelled'],
            'Cancelled' => ['Cancelled'],
            'Completed' => ['Completed']
        ];
        if (!in_array($status, $allowedTransitions[$oldStatus] ?? [], true)) {
            $conn->rollback();
            adminOrdersRespond(409, ['success' => false, 'message' => 'This order status cannot be changed that way.']);
        }
        if ($oldStatus === 'Processing' && $status === 'Cancelled') {
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

            $stockKeys = [];
            $stockLookup = $conn->prepare(
                'SELECT SUM(pv.stock_quantity) AS stock_quantity
                 FROM product_variants pv
                 JOIN products p ON p.product_id = pv.product_id AND p.is_active = 1
                 JOIN categories c ON c.category_id = p.category_id AND c.is_active = 1
                 WHERE pv.product_id = ? AND pv.size = ? AND pv.is_active = 1'
            );
            if (!$stockLookup) throw new Exception('Unable to prepare product stock notification lookup.');
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
            "UPDATE orders
             SET status = ?, payment_status = CASE WHEN ? = 'Completed' THEN 'Paid' ELSE payment_status END
             WHERE order_id = ?"
        );
        if (!$updateStmt) throw new Exception('Unable to prepare status update.');
        $updateStmt->bind_param('ssi', $status, $status, $orderId);
        if (!$updateStmt->execute()) throw new Exception('Unable to update order status.');
        $updateStmt->close();
        if ($oldStatus !== $status && !createNotification(
            $conn,
            (int) $order['user_id'],
            'order_status',
            'Order status updated',
            'Your order ' . $order['order_number'] . ' is now ' . $status . '.',
            'cstmr-order.php?highlight-order=' . $orderId
        )) {
            throw new Exception('Unable to save customer order status notification.');
        }
        if (!$conn->commit()) throw new Exception('Unable to complete the order update.');
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('Admin order status update failed: ' . $error->getMessage());
        adminOrdersRespond(500, ['success' => false, 'message' => 'Unable to update the order status.']);
    }
    adminOrdersRespond(200, ['success' => true, 'status' => $status]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    adminOrdersRespond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$ordersResult = $conn->query('SELECT o.order_id, o.order_number, o.user_id AS customer_id, u.username AS customer_username, o.status, o.payment_method, o.subtotal, o.vat_amount, o.shipping_fee, o.total_amount, o.shipping_contact, o.placed_at, CONCAT_WS(" ", NULLIF(u.first_name, ""), NULLIF(u.last_name, "")) AS customer_name FROM orders o JOIN users u ON u.user_id = o.user_id ORDER BY o.placed_at DESC, o.order_id DESC');
if (!$ordersResult) adminOrdersRespond(500, ['success' => false, 'message' => 'Unable to load orders.']);
$orders = [];
$orderIds = [];
while ($row = $ordersResult->fetch_assoc()) {
    $row['items'] = [];
    $row['order_id'] = (int) $row['order_id'];
    $row['subtotal'] = (float) $row['subtotal'];
    $row['vat_amount'] = (float) $row['vat_amount'];
    $row['shipping_fee'] = (float) $row['shipping_fee'];
    $row['total_amount'] = (float) $row['total_amount'];
    $row['placed_at'] = date('M j, Y', strtotime($row['placed_at']));
    $orders[$row['order_id']] = $row;
    $orderIds[] = $row['order_id'];
}
if ($orderIds) {
    $itemsResult = $conn->query('SELECT oi.order_id, oi.product_id, oi.product_name, oi.size, oi.color, oi.unit_price, oi.quantity, oi.line_total, (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = oi.product_id ORDER BY pi.sort_order, pi.image_id LIMIT 1) AS image_url FROM order_items oi ORDER BY oi.order_item_id');
    if (!$itemsResult) adminOrdersRespond(500, ['success' => false, 'message' => 'Unable to load order items.']);
    while ($item = $itemsResult->fetch_assoc()) {
        $id = (int) $item['order_id'];
        if (!isset($orders[$id])) continue;
        $orders[$id]['items'][] = [
            'name' => $item['product_name'],
            'size' => $item['size'],
            'color' => $item['color'],
            'price' => (float) $item['unit_price'],
            'quantity' => (int) $item['quantity'],
            'image' => $item['image_url'] ?: 'assets/product.jpg'
        ];
    }
}
adminOrdersRespond(200, ['success' => true, 'csrf_token' => $_SESSION['admin_csrf_token'], 'orders' => array_values($orders)]);
?>
