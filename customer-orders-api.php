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
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
