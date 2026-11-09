<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/catalog-bootstrap.php';

$sql = "SELECT p.name, p.price, c.name AS category_name, c.slug AS category_slug,
               (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id
                ORDER BY CASE WHEN LOWER(pi.view_label) = 'front' THEN 0 ELSE 1 END, pi.sort_order, pi.image_id LIMIT 1) AS image_url
        FROM products p
        JOIN categories c ON c.category_id = p.category_id
        WHERE p.is_active = 1
        ORDER BY FIELD(c.slug, 'tshirt', 'bottom', 'outerwear', 'accessory'), p.name";
$result = $conn->query($sql);
$products = [];
if ($result) {
    while ($row = $result->fetch_assoc()) $products[] = $row;
}
echo json_encode($products, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
?>
