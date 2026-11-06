<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function adminOrdersRespond($status, $data)
{
    http_response_code($status);
    echo json_encode($data);
    exit();
}

if ((int) ($_SESSION['position'] ?? 0) !== 2) {
    adminOrdersRespond(403, ['success' => false, 'message' => 'Administrator access is required.']);
}
require_once __DIR__ . DIRECTORY_SEPARATOR . 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $orderId = isset($payload['order_id']) ? (int) $payload['order_id'] : 0;
    $status = isset($payload['status']) ? $payload['status'] : '';
    if ($orderId <= 0 || !in_array($status, ['Processing', 'Completed', 'Cancelled'], true)) {
        adminOrdersRespond(400, ['success' => false, 'message' => 'Invalid order or status.']);
    }
    $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE order_id = ?');
    if (!$stmt) adminOrdersRespond(500, ['success' => false, 'message' => 'Unable to prepare status update.']);
    $stmt->bind_param('si', $status, $orderId);
    if (!$stmt->execute() || $stmt->affected_rows < 0) {
        $stmt->close();
        adminOrdersRespond(500, ['success' => false, 'message' => 'Unable to update order status.']);
    }
    $stmt->close();
    adminOrdersRespond(200, ['success' => true, 'status' => $status]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    adminOrdersRespond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$ordersResult = $conn->query('SELECT o.order_id, o.order_number, o.status, o.payment_method, o.subtotal, o.vat_amount, o.shipping_fee, o.total_amount, o.shipping_contact, o.placed_at, CONCAT_WS(" ", NULLIF(u.first_name, ""), NULLIF(u.last_name, "")) AS customer_name FROM orders o JOIN users u ON u.user_id = o.user_id ORDER BY o.placed_at DESC, o.order_id DESC');
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
adminOrdersRespond(200, ['success' => true, 'orders' => array_values($orders)]);
?>
