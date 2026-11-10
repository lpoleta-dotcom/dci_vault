<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function adminDashboardRespond($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-api-session.php';
$result = $conn->query(
    "SELECT
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'Completed') AS sales,
        (SELECT COUNT(*) FROM orders) AS orders,
        (SELECT COUNT(*) FROM orders WHERE status = 'Processing') AS processing_orders,
        (SELECT COUNT(*) FROM products WHERE is_active = 1) AS products,
        (SELECT COUNT(*) FROM users WHERE is_active = 1) AS users,
        (SELECT COUNT(*) FROM users WHERE is_active = 1 AND position = 2) AS admins,
        (SELECT COUNT(*) FROM users WHERE is_active = 1 AND position = 1) AS customers"
);
if (!$result) {
    error_log('Unable to load admin dashboard metrics: ' . $conn->error);
    adminDashboardRespond(500, ['success' => false, 'message' => 'Unable to load dashboard metrics.']);
}

$metrics = $result->fetch_assoc();
$metrics['sales'] = (float) $metrics['sales'];
foreach (['orders', 'processing_orders', 'products', 'users', 'admins', 'customers'] as $key) {
    $metrics[$key] = (int) $metrics[$key];
}
adminDashboardRespond(200, ['success' => true, 'metrics' => $metrics]);
