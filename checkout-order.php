<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function checkoutRespond($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload);
    exit();
}

if (empty($_SESSION['user_id'])) {
    checkoutRespond(401, ['success' => false, 'message' => 'Please sign in before placing an order.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    checkoutRespond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || empty($payload['items']) || !is_array($payload['items'])) {
    checkoutRespond(400, ['success' => false, 'message' => 'Your cart is empty or invalid.']);
}
$paymentMethod = isset($payload['payment_method']) ? $payload['payment_method'] : '';
if (!in_array($paymentMethod, ['gcash', 'card', 'cod'], true)) {
    checkoutRespond(400, ['success' => false, 'message' => 'Choose a valid payment method.']);
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'connect.php';
$userId = (int) $_SESSION['user_id'];
$userStmt = $conn->prepare('SELECT first_name, middle_name, last_name, contact_no, street, barangay, city, province, postal FROM users WHERE user_id = ?');
if (!$userStmt) checkoutRespond(500, ['success' => false, 'message' => 'Unable to prepare customer lookup.']);
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$customer = $userStmt->get_result()->fetch_assoc();
$userStmt->close();
if (!$customer) checkoutRespond(401, ['success' => false, 'message' => 'Customer account was not found.']);

$shippingName = trim($customer['first_name'] . ' ' . $customer['middle_name'] . ' ' . $customer['last_name']);
$shippingAddress = trim(implode(', ', array_filter([
    $customer['street'], $customer['barangay'], $customer['city'], $customer['province'], $customer['postal']
], function ($part) { return trim((string) $part) !== ''; })));
$orderItems = [];
$subtotal = 0.00;
$vat = 0.00;

try {
    foreach ($payload['items'] as $item) {
        $productId = isset($item['id']) ? (int) $item['id'] : 0;
        $size = isset($item['size']) ? trim((string) $item['size']) : 'One Size';
        $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;
        if ($productId <= 0 || $quantity <= 0 || $quantity > 99 || $size === '') {
            checkoutRespond(400, ['success' => false, 'message' => 'One or more cart items are invalid.']);
        }
        $itemStmt = $conn->prepare('SELECT p.name, p.price, v.variant_id, v.color, v.stock_quantity FROM products p JOIN product_variants v ON v.product_id = p.product_id WHERE p.product_id = ? AND p.is_active = 1 AND v.size = ? AND v.is_active = 1 LIMIT 1');
        if (!$itemStmt) throw new Exception('Unable to prepare product lookup.');
        $itemStmt->bind_param('is', $productId, $size);
        $itemStmt->execute();
        $product = $itemStmt->get_result()->fetch_assoc();
        $itemStmt->close();
        if (!$product) checkoutRespond(400, ['success' => false, 'message' => 'A product or selected size is no longer available.']);
        if ((int) $product['stock_quantity'] < $quantity) {
            checkoutRespond(409, ['success' => false, 'message' => 'There is not enough stock for ' . $product['name'] . ' (' . $size . ').']);
        }
        // Catalog prices are already VAT-inclusive.
        $price = (float) $product['price'];
        $lineTotal = round($price * $quantity, 2);
        $unitVat = round($price * 12 / 112, 2);
        $subtotal += $lineTotal;
        $vat += $unitVat * $quantity;
        $orderItems[] = [
            'product_id' => $productId,
            'variant_id' => (int) $product['variant_id'],
            'name' => $product['name'],
            'size' => $size,
            'color' => $product['color'],
            'price' => $price,
            'quantity' => $quantity,
            'line_total' => $lineTotal
        ];
    }
} catch (Exception $error) {
    error_log('Checkout validation error: ' . $error->getMessage());
    checkoutRespond(500, ['success' => false, 'message' => 'Unable to validate your cart. Please try again.']);
}

$subtotal = round($subtotal, 2);
$vat = round($vat, 2);
$shipping = 30.00;
$total = round($subtotal + $shipping, 2);
$orderNumber = 'ORD-' . date('Y') . '-' . date('mdHis') . '-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 5));
$shippingContact = $customer['contact_no'];

$conn->begin_transaction();
try {
    $orderStmt = $conn->prepare("INSERT INTO orders (order_number, user_id, status, payment_method, payment_status, subtotal, vat_amount, shipping_fee, total_amount, shipping_name, shipping_contact, shipping_address) VALUES (?, ?, 'Processing', ?, 'Pending', ?, ?, ?, ?, ?, ?, ?)");
    if (!$orderStmt) throw new Exception('Unable to prepare order insert.');
    $orderStmt->bind_param('sisddddsss', $orderNumber, $userId, $paymentMethod, $subtotal, $vat, $shipping, $total, $shippingName, $shippingContact, $shippingAddress);
    if (!$orderStmt->execute()) throw new Exception('Unable to save order.');
    $orderId = (int) $conn->insert_id;
    $orderStmt->close();

    $stockStmt = $conn->prepare('UPDATE product_variants SET stock_quantity = stock_quantity - ? WHERE variant_id = ? AND stock_quantity >= ?');
    $lineStmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, variant_id, product_name, size, color, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stockStmt || !$lineStmt) throw new Exception('Unable to prepare order item save.');
    foreach ($orderItems as $item) {
        $stockStmt->bind_param('iii', $item['quantity'], $item['variant_id'], $item['quantity']);
        if (!$stockStmt->execute() || $stockStmt->affected_rows !== 1) throw new Exception('Stock changed while placing order.');
        $lineStmt->bind_param('iiisssdid', $orderId, $item['product_id'], $item['variant_id'], $item['name'], $item['size'], $item['color'], $item['price'], $item['quantity'], $item['line_total']);
        if (!$lineStmt->execute()) throw new Exception('Unable to save an order item.');
    }
    $stockStmt->close();
    $lineStmt->close();
    $conn->commit();
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
} catch (Exception $error) {
    $conn->rollback();
    error_log('Checkout save error: ' . $error->getMessage());
    checkoutRespond(500, ['success' => false, 'message' => 'Your order could not be saved. Your cart is unchanged.']);
}
?>
