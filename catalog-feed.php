<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/catalog-bootstrap.php';

$sql = "SELECT p.product_id, p.name, p.price, c.name AS category_name, c.slug AS category_slug,
               (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id
                ORDER BY CASE WHEN LOWER(pi.view_label) = 'front' THEN 0 ELSE 1 END, pi.sort_order, pi.image_id LIMIT 1) AS image_url
        FROM products p
        JOIN categories c ON c.category_id = p.category_id AND c.is_active = 1
        WHERE p.is_active = 1
        ORDER BY FIELD(c.slug, 'tshirt', 'bottom', 'outerwear', 'accessory'), p.name";
$result = $conn->query($sql);
if (!$result) {
   http_response_code(500);
   echo json_encode(['success' => false, 'message' => 'The product catalog could not be loaded.']);
   exit();
}
$products = [];
while ($row = $result->fetch_assoc()) {
   $row['price'] = (float) $row['price'];
   $row['variants'] = [];
   $products[(int) $row['product_id']] = $row;
}
$variantResult = $conn->query(
   'SELECT pv.product_id, pv.size, SUM(pv.stock_quantity) AS stock_quantity
    FROM product_variants pv
    JOIN products p ON p.product_id = pv.product_id
    WHERE p.is_active = 1 AND pv.is_active = 1
    GROUP BY pv.product_id, pv.size
    ORDER BY pv.product_id, pv.size'
);
if (!$variantResult) {
   http_response_code(500);
   echo json_encode(['success' => false, 'message' => 'Product inventory could not be loaded.']);
   exit();
}
while ($variant = $variantResult->fetch_assoc()) {
   $id = (int) $variant['product_id'];
   if (!isset($products[$id])) continue;
   $products[$id]['variants'][] = [
       'size' => $variant['size'],
       'stock' => (int) $variant['stock_quantity']
   ];
}
echo json_encode(array_values($products), JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
?>
