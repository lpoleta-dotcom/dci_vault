<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'customer-session-init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function checkoutRespond($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

if (empty($_SESSION['user_id']) || (int) ($_SESSION['position'] ?? 0) !== 1) {
    checkoutRespond(401, ['success' => false, 'message' => 'Please sign in with a customer account before placing an order.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    checkoutRespond(405, ['success' => false, 'message' => 'Method not allowed.']);
}
if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    checkoutRespond(415, ['success' => false, 'message' => 'Order details must be sent as JSON.']);
}
$expectedOrigin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? '');
if (
    empty($_SERVER['HTTP_ORIGIN']) ||
    $expectedOrigin === '://' ||
    !hash_equals($expectedOrigin, $_SERVER['HTTP_ORIGIN'])
) {
    checkoutRespond(403, ['success' => false, 'message' => 'Request origin could not be verified.']);
}
if (empty($_SESSION['customer_csrf_token'])
    || !hash_equals($_SESSION['customer_csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    checkoutRespond(403, ['success' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
}

$rawPayload = file_get_contents('php://input');
if ($rawPayload === false || strlen($rawPayload) > 65536) {
    checkoutRespond(413, ['success' => false, 'message' => 'The order request is too large.']);
}
$payload = json_decode($rawPayload, true);
if (!is_array($payload) || !isset($payload['items']) || !is_array($payload['items']) || !$payload['items']) {
    checkoutRespond(400, ['success' => false, 'message' => 'Your cart is empty or invalid.']);
}
if (count($payload['items']) > 99) {
    checkoutRespond(400, ['success' => false, 'message' => 'An order can contain up to 99 items total.']);
}
$paymentMethod = $payload['payment_method'] ?? '';
if (!in_array($paymentMethod, ['gcash', 'card', 'cod'], true)) {
    checkoutRespond(400, ['success' => false, 'message' => 'Choose a valid payment method.']);
}

$itemsByVariant = [];
$totalQuantity = 0;
foreach ($payload['items'] as $item) {
    if (!is_array($item)) checkoutRespond(400, ['success' => false, 'message' => 'One or more cart items are invalid.']);
    $productId = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $size = isset($item['size']) && is_string($item['size']) ? trim($item['size']) : '';
    $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
    if ($productId === false || $size === '' || strlen($size) > 20 || $quantity === false) {
        checkoutRespond(400, ['success' => false, 'message' => 'One or more cart items are invalid.']);
    }
    $key = $productId . ':' . $size;
    if (!isset($itemsByVariant[$key])) {
        $itemsByVariant[$key] = ['id' => $productId, 'size' => $size, 'quantity' => 0];
    }
    $itemsByVariant[$key]['quantity'] += $quantity;
    $totalQuantity += $quantity;
    if ($itemsByVariant[$key]['quantity'] > 99 || $totalQuantity > 99) {
        checkoutRespond(400, ['success' => false, 'message' => 'An order can contain up to 99 items total. Reduce your cart quantity and try again.']);
    }
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'connect.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'notifications-bootstrap.php';
if (!ensureNotificationTables($conn)) {
    checkoutRespond(500, ['success' => false, 'message' => 'Checkout is temporarily unavailable. Please try again.']);
}
$userId = (int) $_SESSION['user_id'];
$userStmt = $conn->prepare(
    'SELECT first_name, middle_name, last_name, contact_no, street, barangay, city, province, postal,
            verification_status
     FROM users WHERE user_id = ? AND position = 1 AND is_active = 1'
);
if (!$userStmt) checkoutRespond(500, ['success' => false, 'message' => 'Unable to prepare customer lookup.']);
$userStmt->bind_param('i', $userId);
if (!$userStmt->execute()) checkoutRespond(500, ['success' => false, 'message' => 'Unable to load your account details.']);
$customer = $userStmt->get_result()->fetch_assoc();
$userStmt->close();
if (!$customer) checkoutRespond(401, ['success' => false, 'message' => 'Customer account was not found.']);
if ($paymentMethod === 'cod' && $customer['verification_status'] !== 'Approved') {
    checkoutRespond(403, [
        'success' => false,
        'message' => $customer['verification_status'] === 'Rejected'
            ? 'Cash on Delivery is unavailable because your ID was not approved. Submit a replacement ID or choose another payment method.'
            : 'Cash on Delivery is unavailable until an administrator approves your ID. Choose another payment method for now.'
    ]);
}

$shippingName = trim(implode(' ', array_filter([
    $customer['first_name'], $customer['middle_name'], $customer['last_name']
], function ($part) { return trim((string) $part) !== ''; })));
$shippingAddress = trim(implode(', ', array_filter([
    $customer['street'], $customer['barangay'], $customer['city'], $customer['province'], $customer['postal']
], function ($part) { return trim((string) $part) !== ''; })));
$shippingContact = trim((string) $customer['contact_no']);
if ($shippingName === '' || $shippingAddress === '' || $shippingContact === '') {
    checkoutRespond(400, ['success' => false, 'message' => 'Complete your name, contact number, and shipping address in your account before checkout.']);
}

if (!$conn->begin_transaction()) {
    checkoutRespond(500, ['success' => false, 'message' => 'Unable to start checkout. Please try again.']);
}
try {
    $variantStmt = $conn->prepare(
        'SELECT p.name, p.price, pv.variant_id, pv.color, pv.stock_quantity
         FROM products p
         JOIN categories c ON c.category_id = p.category_id AND c.is_active = 1
         JOIN product_variants pv ON pv.product_id = p.product_id
         WHERE p.product_id = ? AND p.is_active = 1
           AND pv.size = ? AND pv.is_active = 1
        ORDER BY pv.stock_quantity DESC, pv.variant_id
        FOR UPDATE'
    );
    $stockStmt = $conn->prepare('UPDATE product_variants SET stock_quantity = stock_quantity - ? WHERE variant_id = ? AND stock_quantity >= ?');
    if (!$variantStmt || !$stockStmt) throw new Exception('Unable to prepare catalog inventory validation.');

    $orderItems = [];
    $stockAfterByProductSize = [];
    $subtotal = 0.00;
    $vat = 0.00;
    foreach ($itemsByVariant as $item) {
        $productId = $item['id'];
        $size = $item['size'];
        $quantity = $item['quantity'];
        $variantStmt->bind_param('is', $productId, $size);
        if (!$variantStmt->execute()) throw new Exception('Unable to validate a requested product size.');
        $variantResult = $variantStmt->get_result();
        $availableVariants = [];
        $productName = '';
        $price = 0.0;
        $availableStock = 0;
        while ($variant = $variantResult->fetch_assoc()) {
            $productName = $variant['name'];
            $price = (float) $variant['price'];
            $variantStock = (int) $variant['stock_quantity'];
            $availableStock += $variantStock;
            $availableVariants[] = [
                'id' => (int) $variant['variant_id'],
                'color' => $variant['color'],
                'stock' => $variantStock
            ];
        }
        if (!$availableVariants) {
            $conn->rollback();
            checkoutRespond(409, [
                'success' => false,
                'message' => 'Size ' . $size . ' is no longer available for product #' . $productId . '. Refresh the catalog and update your cart.'
            ]);
        }
        if ($availableStock < $quantity) {
            $conn->rollback();
            checkoutRespond(409, [
                'success' => false,
                'message' => 'Only ' . $availableStock . ' available for ' . $productName . ' in size ' . $size . '; your cart requests ' . $quantity . '. Update that size and try again.'
            ]);
        }

        $remainingQuantity = $quantity;
        foreach ($availableVariants as $availableVariant) {
            if ($remainingQuantity === 0) break;
            if ($availableVariant['stock'] < 1) continue;

            $allocatedQuantity = min($remainingQuantity, $availableVariant['stock']);
            $variantId = $availableVariant['id'];
            $stockStmt->bind_param('iii', $allocatedQuantity, $variantId, $allocatedQuantity);
            if (!$stockStmt->execute() || $stockStmt->affected_rows !== 1) {
                $conn->rollback();
                checkoutRespond(409, [
                    'success' => false,
                    'message' => 'Stock changed for ' . $productName . ' in size ' . $size . ' while checking out. Refresh availability for that size and try again.'
                ]);
            }

            $lineTotal = round($price * $allocatedQuantity, 2);
            $subtotal += $lineTotal;
            $vat += round($price * 12 / 112, 2) * $allocatedQuantity;
            $orderItems[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'name' => $productName,
                'size' => $size,
                'color' => $availableVariant['color'],
                'price' => $price,
                'quantity' => $allocatedQuantity,
                'line_total' => $lineTotal
            ];
            $remainingQuantity -= $allocatedQuantity;
        }

        if ($remainingQuantity > 0) {
            $conn->rollback();
            checkoutRespond(409, [
                'success' => false,
                'message' => 'Not enough stock remains for ' . $productName . ' in size ' . $size . '. Refresh availability for that size and try again.'
            ]);
        }
        $stockAfterByProductSize[$productId . ':' . $size] = [
            'product_id' => $productId,
            'size' => $size,
            'name' => $productName,
            'stock' => $availableStock - $quantity
        ];
    }
    $variantStmt->close();
    $stockStmt->close();

    $subtotal = round($subtotal, 2);
    $vat = round($vat, 2);
    $shipping = 30.00;
    $total = round($subtotal + $shipping, 2);
    $orderNumber = 'ORD-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $orderStmt = $conn->prepare(
        "INSERT INTO orders (order_number, user_id, status, payment_method, payment_status, subtotal, vat_amount, shipping_fee, total_amount, shipping_name, shipping_contact, shipping_address)
         VALUES (?, ?, 'Processing', ?, 'Pending', ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$orderStmt) throw new Exception('Unable to prepare order save.');
    $orderStmt->bind_param('sisddddsss', $orderNumber, $userId, $paymentMethod, $subtotal, $vat, $shipping, $total, $shippingName, $shippingContact, $shippingAddress);
    if (!$orderStmt->execute()) throw new Exception('Unable to save the order.');
    $orderId = (int) $conn->insert_id;
    $orderStmt->close();

    $lineStmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, variant_id, product_name, size, color, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$lineStmt) throw new Exception('Unable to prepare order item save.');
    foreach ($orderItems as $item) {
        $lineStmt->bind_param('iiisssdid', $orderId, $item['product_id'], $item['variant_id'], $item['name'], $item['size'], $item['color'], $item['price'], $item['quantity'], $item['line_total']);
        if (!$lineStmt->execute()) throw new Exception('Unable to save an order item.');
    }
    $lineStmt->close();
    if (
        !createNotification(
            $conn,
            $userId,
            'order_placed',
            'Order placed: ' . $orderNumber,
            'Your order is now processing. We’ll keep you updated.',
            'cstmr-order.php?highlight-order=' . $orderId
        ) ||
        !notifyActiveAdmins(
            $conn,
            'new_order',
            'New order received',
            $orderNumber . ' from ' . $shippingName . ' — ₱' . number_format($total, 2) . '.',
            'admin-order.php?highlight-order=' . $orderId
        )
    ) {
        throw new Exception('Unable to save order notifications.');
    }
    foreach ($stockAfterByProductSize as $stockState) {
        if (!syncLowStockNotification(
            $conn,
            $stockState['product_id'],
            $stockState['size'],
            $stockState['name'],
            $stockState['stock']
        )) {
            throw new Exception('Unable to save low-stock notifications.');
        }
    }
    if (!$conn->commit()) throw new Exception('Unable to complete the order transaction.');
    checkoutRespond(201, ['success' => true, 'order' => [
        'id' => $orderNumber,
        'date' => date('M j, Y'),
        'status' => 'Processing',
        'payment' => $paymentMethod,
        'subtotal' => $subtotal,
        'vat' => $vat,
        'shipping' => $shipping,
        'total' => $total,
        'items' => $orderItems
    ]]);
} catch (Throwable $error) {
    $conn->rollback();
    error_log('Checkout save error: ' . $error->getMessage());
    checkoutRespond(500, ['success' => false, 'message' => 'Your order could not be saved. Your cart is unchanged.']);
}
?>
