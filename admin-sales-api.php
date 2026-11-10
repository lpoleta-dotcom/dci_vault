<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function adminSalesRespond($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    adminSalesRespond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-api-session.php';
$days = filter_var($_GET['days'] ?? 30, FILTER_VALIDATE_INT);
if (!in_array($days, [7, 30, 90], true)) {
    adminSalesRespond(400, ['success' => false, 'message' => 'Choose a valid sales report period.']);
}

$cutoff = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
$summaryStmt = $conn->prepare(
    "SELECT COUNT(*) AS order_count, COALESCE(SUM(total_amount), 0) AS gross
     FROM orders WHERE status = 'Completed' AND placed_at >= ?"
);
if (!$summaryStmt) adminSalesRespond(500, ['success' => false, 'message' => 'Unable to prepare sales summary.']);
$summaryStmt->bind_param('s', $cutoff);
if (!$summaryStmt->execute()) {
    $summaryStmt->close();
    adminSalesRespond(500, ['success' => false, 'message' => 'Unable to load sales summary.']);
}
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

$dailyStmt = $conn->prepare(
    "SELECT DATE(placed_at) AS sale_date, SUM(total_amount) AS revenue, COUNT(*) AS orders
     FROM orders WHERE status = 'Completed' AND placed_at >= ?
     GROUP BY DATE(placed_at) ORDER BY sale_date"
);
if (!$dailyStmt) adminSalesRespond(500, ['success' => false, 'message' => 'Unable to prepare sales chart.']);
$dailyStmt->bind_param('s', $cutoff);
if (!$dailyStmt->execute()) {
    $dailyStmt->close();
    adminSalesRespond(500, ['success' => false, 'message' => 'Unable to load sales chart.']);
}
$dailyResult = $dailyStmt->get_result();
$dailySales = [];
while ($row = $dailyResult->fetch_assoc()) {
    $dailySales[$row['sale_date']] = [
        'revenue' => (float) $row['revenue'],
        'orders' => (int) $row['orders']
    ];
}
$dailyStmt->close();

$categoryStmt = $conn->prepare(
    "SELECT COALESCE(c.name, 'Other') AS category, SUM(oi.line_total) AS revenue
     FROM orders o
     JOIN order_items oi ON oi.order_id = o.order_id
     LEFT JOIN products p ON p.product_id = oi.product_id
     LEFT JOIN categories c ON c.category_id = p.category_id
     WHERE o.status = 'Completed' AND o.placed_at >= ?
     GROUP BY c.category_id, c.name ORDER BY revenue DESC"
);
if (!$categoryStmt) adminSalesRespond(500, ['success' => false, 'message' => 'Unable to prepare category report.']);
$categoryStmt->bind_param('s', $cutoff);
if (!$categoryStmt->execute()) {
    $categoryStmt->close();
    adminSalesRespond(500, ['success' => false, 'message' => 'Unable to load category report.']);
}
$categoryResult = $categoryStmt->get_result();
$categories = [];
while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = ['name' => $row['category'], 'revenue' => (float) $row['revenue']];
}
$categoryStmt->close();

$productStmt = $conn->prepare(
    "SELECT oi.product_name, SUM(oi.quantity) AS units, SUM(oi.line_total) AS revenue
     FROM orders o
     JOIN order_items oi ON oi.order_id = o.order_id
     WHERE o.status = 'Completed' AND o.placed_at >= ?
     GROUP BY oi.product_id, oi.product_name ORDER BY units DESC, revenue DESC LIMIT 10"
);
if (!$productStmt) adminSalesRespond(500, ['success' => false, 'message' => 'Unable to prepare product report.']);
$productStmt->bind_param('s', $cutoff);
if (!$productStmt->execute()) {
    $productStmt->close();
    adminSalesRespond(500, ['success' => false, 'message' => 'Unable to load product report.']);
}
$productResult = $productStmt->get_result();
$products = [];
while ($row = $productResult->fetch_assoc()) {
    $products[] = [
        'name' => $row['product_name'],
        'units' => (int) $row['units'],
        'revenue' => (float) $row['revenue']
    ];
}
$productStmt->close();

$recentStmt = $conn->prepare(
    "SELECT o.order_number, o.placed_at, o.payment_method, o.total_amount,
            CONCAT_WS(' ', NULLIF(u.first_name, ''), NULLIF(u.last_name, '')) AS customer_name,
            u.username
     FROM orders o JOIN users u ON u.user_id = o.user_id
     WHERE o.status = 'Completed' AND o.placed_at >= ?
     ORDER BY o.placed_at DESC, o.order_id DESC LIMIT 20"
);
if (!$recentStmt) adminSalesRespond(500, ['success' => false, 'message' => 'Unable to prepare recent sales.']);
$recentStmt->bind_param('s', $cutoff);
if (!$recentStmt->execute()) {
    $recentStmt->close();
    adminSalesRespond(500, ['success' => false, 'message' => 'Unable to load recent sales.']);
}
$recentResult = $recentStmt->get_result();
$transactions = [];
while ($row = $recentResult->fetch_assoc()) {
    $transactions[] = [
        'order_number' => $row['order_number'],
        'date' => $row['placed_at'],
        'payment' => $row['payment_method'],
        'amount' => (float) $row['total_amount'],
        'customer_name' => $row['customer_name'],
        'username' => $row['username']
    ];
}
$recentStmt->close();
$conn->close();

adminSalesRespond(200, [
    'success' => true,
    'days' => $days,
    'summary' => [
        'gross' => (float) $summary['gross'],
        'orders' => (int) $summary['order_count'],
        'average' => (int) $summary['order_count'] > 0
            ? (float) $summary['gross'] / (int) $summary['order_count']
            : 0
    ],
    'daily_sales' => $dailySales,
    'categories' => $categories,
    'products' => $products,
    'transactions' => $transactions
]);
