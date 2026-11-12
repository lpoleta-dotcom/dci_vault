<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function adminProductsRespond($status, $data)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-api-session.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'catalog-bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'notifications-bootstrap.php';
if (!ensureNotificationTables($conn)) {
    adminProductsRespond(500, ['success' => false, 'message' => 'Product notifications are temporarily unavailable.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query(
        "SELECT p.product_id, p.name, p.description, p.price, p.is_active,
                c.name AS category_name, c.slug AS category_slug
         FROM products p
         JOIN categories c ON c.category_id = p.category_id
         ORDER BY FIELD(c.slug, 'tshirt', 'bottom', 'outerwear', 'accessory'), p.name"
    );
    if (!$result) adminProductsRespond(500, ['success' => false, 'message' => 'Unable to load products.']);

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $id = (int) $row['product_id'];
        $products[$id] = [
            'id' => $id,
            'name' => $row['name'],
            'description' => $row['description'],
            'price' => (float) $row['price'],
            'active' => (bool) $row['is_active'],
            'category' => $row['category_name'],
            'category_slug' => $row['category_slug'],
            'images' => ['front' => '', 'side' => '', 'back' => ''],
            'variants' => []
        ];
    }

    if ($products) {
        $imageResult = $conn->query('SELECT product_id, image_url, view_label FROM product_images ORDER BY sort_order, image_id');
        if (!$imageResult) adminProductsRespond(500, ['success' => false, 'message' => 'Unable to load product images.']);
        while ($image = $imageResult->fetch_assoc()) {
            $id = (int) $image['product_id'];
            if (!isset($products[$id])) continue;
            $label = strtolower($image['view_label']);
            if (strpos($label, 'front') !== false) $key = 'front';
            elseif (strpos($label, 'back') !== false) $key = 'back';
            else $key = 'side';
            if ($products[$id]['images'][$key] === '') $products[$id]['images'][$key] = $image['image_url'];
        }

        $variantResult = $conn->query(
            'SELECT pv.product_id, pv.size, SUM(pv.stock_quantity) AS stock_quantity
             FROM product_variants pv
             JOIN products p ON p.product_id = pv.product_id
             WHERE pv.is_active = 1 OR p.is_active = 0
             GROUP BY pv.product_id, pv.size
             ORDER BY pv.product_id, pv.size'
        );
        if (!$variantResult) adminProductsRespond(500, ['success' => false, 'message' => 'Unable to load product inventory.']);
        while ($variant = $variantResult->fetch_assoc()) {
            $id = (int) $variant['product_id'];
            if (!isset($products[$id])) continue;
            $products[$id]['variants'][] = [
                'size' => $variant['size'],
                'stock' => (int) $variant['stock_quantity']
            ];
        }
    }

    adminProductsRespond(200, [
        'success' => true,
        'csrf_token' => $_SESSION['admin_csrf_token'],
        'products' => array_values($products)
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminProductsRespond(405, ['success' => false, 'message' => 'Method not allowed.']);
}
if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'multipart/form-data') {
    adminProductsRespond(415, ['success' => false, 'message' => 'Product updates must use a supported form request.']);
}
$contentLength = $_SERVER['CONTENT_LENGTH'] ?? '';
if ($contentLength !== '' && (!ctype_digit($contentLength) || (int) $contentLength > 16777216)) {
    adminProductsRespond(413, ['success' => false, 'message' => 'Product images must be no larger than 15 MB combined.']);
}
if ($contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > 0 && !$_POST && !$_FILES) {
    adminProductsRespond(413, ['success' => false, 'message' => 'Product images must be no larger than 5 MB each (15 MB combined).']);
}
$expectedOrigin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? '');
if (
    empty($_SERVER['HTTP_ORIGIN']) ||
    $expectedOrigin === '://' ||
    !hash_equals($expectedOrigin, $_SERVER['HTTP_ORIGIN'])
) {
    adminProductsRespond(403, ['success' => false, 'message' => 'Request origin could not be verified.']);
}
if (!hash_equals($_SESSION['admin_csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    adminProductsRespond(403, ['success' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
}

$action = $_POST['action'] ?? '';
if (!is_string($action)) adminProductsRespond(400, ['success' => false, 'message' => 'Choose a valid product action.']);
if ($action === 'delete') {
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($productId === false) adminProductsRespond(400, ['success' => false, 'message' => 'Choose a valid product.']);
    if (!$conn->begin_transaction()) {
        adminProductsRespond(500, ['success' => false, 'message' => 'Unable to start product deactivation.']);
    }
    $productStmt = $conn->prepare('UPDATE products SET is_active = 0 WHERE product_id = ? AND is_active = 1');
    if (!$productStmt) {
        $conn->rollback();
        adminProductsRespond(500, ['success' => false, 'message' => 'Unable to prepare product deactivation.']);
    }
    $productStmt->bind_param('i', $productId);
    if (!$productStmt->execute() || $productStmt->affected_rows === 0) {
        $productStmt->close();
        $conn->rollback();
        adminProductsRespond(404, ['success' => false, 'message' => 'Product was not found or is already inactive.']);
    }
    $alertResetStmt = $conn->prepare('UPDATE product_size_stock_alerts SET is_low_stock = 0 WHERE product_id = ?');
    if (!$alertResetStmt) {
        $productStmt->close();
        $conn->rollback();
        adminProductsRespond(500, ['success' => false, 'message' => 'Unable to reset product stock alerts.']);
    }
    $alertResetStmt->bind_param('i', $productId);
    if (!$alertResetStmt->execute()) {
        $productStmt->close();
        $alertResetStmt->close();
        $conn->rollback();
        adminProductsRespond(500, ['success' => false, 'message' => 'Unable to reset product stock alerts.']);
    }
    $alertResetStmt->close();
    $productStmt->close();
    if (!$conn->commit()) {
        $conn->rollback();
        adminProductsRespond(500, ['success' => false, 'message' => 'Unable to complete product deactivation.']);
    }
    adminProductsRespond(200, ['success' => true]);
}
if ($action === 'restore') {
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($productId === false) adminProductsRespond(400, ['success' => false, 'message' => 'Choose a valid product.']);
    if (!$conn->begin_transaction()) {
        adminProductsRespond(500, ['success' => false, 'message' => 'Unable to start product restoration.']);
    }
    $productStmt = $conn->prepare('UPDATE products SET is_active = 1 WHERE product_id = ? AND is_active = 0');
    $activeVariantsStmt = $conn->prepare(
        'SELECT variant_id FROM product_variants WHERE product_id = ? AND is_active = 1 FOR UPDATE'
    );
    $variantStmt = $conn->prepare('UPDATE product_variants SET is_active = 1 WHERE product_id = ?');
    if (!$productStmt || !$activeVariantsStmt || !$variantStmt) {
        $conn->rollback();
        adminProductsRespond(500, ['success' => false, 'message' => 'Unable to prepare product restoration.']);
    }
    $productStmt->bind_param('i', $productId);
    if (!$productStmt->execute() || $productStmt->affected_rows === 0) {
        $productStmt->close();
        $activeVariantsStmt->close();
        $variantStmt->close();
        $conn->rollback();
        adminProductsRespond(404, ['success' => false, 'message' => 'Archived product was not found.']);
    }
    $activeVariantsStmt->bind_param('i', $productId);
    if (!$activeVariantsStmt->execute()) {
        $productStmt->close();
        $activeVariantsStmt->close();
        $variantStmt->close();
        $conn->rollback();
        adminProductsRespond(500, ['success' => false, 'message' => 'Unable to inspect product inventory.']);
    }
    $activeVariantResult = $activeVariantsStmt->get_result();
    $hasActiveVariants = $activeVariantResult && $activeVariantResult->num_rows > 0;
    $activeVariantsStmt->close();
    if (!$hasActiveVariants) {
        $variantStmt->bind_param('i', $productId);
        if (!$variantStmt->execute()) {
            $productStmt->close();
            $variantStmt->close();
            $conn->rollback();
            adminProductsRespond(500, ['success' => false, 'message' => 'Unable to restore product inventory.']);
        }
    }
    $productStmt->close();
    $variantStmt->close();
    if (!$conn->commit()) {
        $conn->rollback();
        adminProductsRespond(500, ['success' => false, 'message' => 'Unable to complete product restoration.']);
    }
    adminProductsRespond(200, ['success' => true]);
}

if ($action !== 'save') adminProductsRespond(400, ['success' => false, 'message' => 'Choose a valid product action.']);
$productJson = $_POST['product'] ?? '';
if (!is_string($productJson) || strlen($productJson) > 65536) {
    adminProductsRespond(400, ['success' => false, 'message' => 'Product details are too large or invalid.']);
}
$payload = json_decode($productJson, true);
if (!is_array($payload)) adminProductsRespond(400, ['success' => false, 'message' => 'Product details are missing or invalid.']);

$productId = null;
if (isset($payload['id']) && $payload['id'] !== '') {
    if (!is_string($payload['id']) && !is_int($payload['id'])) {
        adminProductsRespond(400, ['success' => false, 'message' => 'Choose a valid product.']);
    }
    $productId = filter_var($payload['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($productId === false) adminProductsRespond(400, ['success' => false, 'message' => 'Choose a valid product.']);
}
if (
    !isset($payload['name'], $payload['description'], $payload['category'], $payload['price']) ||
    !is_string($payload['name']) ||
    !is_string($payload['description']) ||
    !is_string($payload['category']) ||
    (!is_string($payload['price']) && !is_int($payload['price']) && !is_float($payload['price']))
) {
    adminProductsRespond(400, ['success' => false, 'message' => 'Product details are missing or invalid.']);
}
$name = trim($payload['name']);
$description = trim($payload['description']);
$categorySlug = $payload['category'];
$categoryIds = ['tshirt' => 'Tops', 'bottom' => 'Bottom', 'outerwear' => 'Outerwear', 'accessory' => 'Accessories'];
$priceText = trim((string) $payload['price']);
if ($name === '' || strlen($name) > 150) adminProductsRespond(400, ['success' => false, 'message' => 'Product name is required and must be 150 characters or fewer.']);
if (strlen($description) > 2000) adminProductsRespond(400, ['success' => false, 'message' => 'Product description must be 2,000 characters or fewer.']);
if (!isset($categoryIds[$categorySlug])) adminProductsRespond(400, ['success' => false, 'message' => 'Choose a valid product category.']);
if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $priceText) || (float) $priceText <= 0) {
    adminProductsRespond(400, ['success' => false, 'message' => 'Enter a positive price with no more than two decimal places.']);
}
$price = (float) $priceText;
$allowedSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'One Size'];
$sizes = $payload['variants'] ?? null;
if (!is_array($sizes) || count($sizes) < 1 || count($sizes) > count($allowedSizes)) {
    adminProductsRespond(400, ['success' => false, 'message' => 'Select at least one valid size or One Size.']);
}
$variants = [];
foreach ($sizes as $variant) {
    if (
        !is_array($variant) ||
        !isset($variant['size'], $variant['stock']) ||
        !is_string($variant['size']) ||
        (!is_string($variant['stock']) && !is_int($variant['stock'])) ||
        !in_array($variant['size'], $allowedSizes, true)
    ) {
        adminProductsRespond(400, ['success' => false, 'message' => 'One or more product sizes are invalid.']);
    }
    $size = $variant['size'];
    $stockText = (string) $variant['stock'];
    if (!preg_match('/^\d{1,6}$/', $stockText)) {
        adminProductsRespond(400, ['success' => false, 'message' => 'Inventory must be a whole number between 0 and 999,999.']);
    }
    if (isset($variants[$size])) adminProductsRespond(400, ['success' => false, 'message' => 'Product sizes must be unique.']);
    $variants[$size] = (int) $stockText;
}

$categoryLookup = $conn->prepare('SELECT category_id FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1');
if (!$categoryLookup) adminProductsRespond(500, ['success' => false, 'message' => 'Unable to validate the product category.']);
$categoryLookup->bind_param('s', $categorySlug);
if (!$categoryLookup->execute()) adminProductsRespond(500, ['success' => false, 'message' => 'Unable to validate the product category.']);
$category = $categoryLookup->get_result()->fetch_assoc();
$categoryLookup->close();
if (!$category) adminProductsRespond(400, ['success' => false, 'message' => 'The selected category is unavailable.']);
$categoryId = (int) $category['category_id'];

$duplicateSql = 'SELECT product_id FROM products WHERE category_id = ? AND LOWER(name) = LOWER(?)' . ($productId ? ' AND product_id <> ?' : '') . ' LIMIT 1';
$duplicateStmt = $conn->prepare($duplicateSql);
if (!$duplicateStmt) adminProductsRespond(500, ['success' => false, 'message' => 'Unable to validate the product name.']);
if ($productId) $duplicateStmt->bind_param('isi', $categoryId, $name, $productId);
else $duplicateStmt->bind_param('is', $categoryId, $name);
if (!$duplicateStmt->execute()) adminProductsRespond(500, ['success' => false, 'message' => 'Unable to validate the product name.']);
$duplicate = $duplicateStmt->get_result()->fetch_assoc();
$duplicateStmt->close();
if ($duplicate) adminProductsRespond(409, ['success' => false, 'message' => 'A product with that name already exists in this category.']);

$currentImages = ['Front' => '', 'Side' => '', 'Back' => ''];
if ($productId) {
    $currentStmt = $conn->prepare('SELECT product_id, is_active FROM products WHERE product_id = ? LIMIT 1');
    if (!$currentStmt) adminProductsRespond(500, ['success' => false, 'message' => 'Unable to verify the product.']);
    $currentStmt->bind_param('i', $productId);
    if (!$currentStmt->execute()) adminProductsRespond(500, ['success' => false, 'message' => 'Unable to verify the product.']);
    $current = $currentStmt->get_result()->fetch_assoc();
    $currentStmt->close();
    if (!$current) adminProductsRespond(404, ['success' => false, 'message' => 'Product was not found.']);
    if ((int) $current['is_active'] !== 1) {
        adminProductsRespond(409, ['success' => false, 'message' => 'Unarchive this product before editing it.']);
    }
    $imageStmt = $conn->prepare('SELECT view_label, image_url FROM product_images WHERE product_id = ?');
    if (!$imageStmt) adminProductsRespond(500, ['success' => false, 'message' => 'Unable to load product images.']);
    $imageStmt->bind_param('i', $productId);
    if (!$imageStmt->execute()) adminProductsRespond(500, ['success' => false, 'message' => 'Unable to load product images.']);
    $imageResult = $imageStmt->get_result();
    while ($image = $imageResult->fetch_assoc()) {
        $label = ucfirst(strtolower($image['view_label']));
        if (isset($currentImages[$label]) && $currentImages[$label] === '') $currentImages[$label] = $image['image_url'];
    }
    $imageStmt->close();
}

$fileFields = ['Front' => 'image_front', 'Side' => 'image_side', 'Back' => 'image_back'];
$savedFiles = [];
$uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
foreach ($fileFields as $label => $field) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) continue;
    $file = $_FILES[$field];
    if (
        $file['error'] === UPLOAD_ERR_INI_SIZE ||
        $file['error'] === UPLOAD_ERR_FORM_SIZE ||
        $file['size'] > 5 * 1024 * 1024
    ) {
        adminProductsRespond(400, ['success' => false, 'message' => 'Product images must be no larger than 5 MB each.']);
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        adminProductsRespond(400, ['success' => false, 'message' => 'One of the product images could not be uploaded.']);
    }
    if ($file['size'] <= 0) {
        adminProductsRespond(400, ['success' => false, 'message' => 'Product images must be no larger than 5 MB.']);
    }
    $imageInfo = @getimagesize($file['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$imageInfo || !isset($extensions[$mime]) || $imageInfo[0] < 1 || $imageInfo[1] < 1 || $imageInfo[0] * $imageInfo[1] > 40000000) {
        adminProductsRespond(400, ['success' => false, 'message' => 'Use a valid JPEG, PNG, or WebP product image.']);
    }
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        adminProductsRespond(500, ['success' => false, 'message' => 'Product image storage is unavailable.']);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        adminProductsRespond(500, ['success' => false, 'message' => 'Unable to store a product image.']);
    }
    $savedFiles[] = $destination;
    $currentImages[$label] = 'uploads/products/' . $filename;
}
if (!$productId && $currentImages['Front'] === '') {
    foreach ($savedFiles as $savedFile) @unlink($savedFile);
    adminProductsRespond(400, ['success' => false, 'message' => 'A front-view image is required for a new product.']);
}

if (!$conn->begin_transaction()) {
    foreach ($savedFiles as $savedFile) @unlink($savedFile);
    adminProductsRespond(500, ['success' => false, 'message' => 'Unable to start the product update.']);
}
try {
    if ($productId) {
        $productStmt = $conn->prepare('UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, is_active = 1 WHERE product_id = ?');
        if (!$productStmt) throw new Exception('Unable to prepare product update.');
        $productStmt->bind_param('issdi', $categoryId, $name, $description, $price, $productId);
    } else {
        $prefixes = ['tshirt' => 'TS', 'bottom' => 'BT', 'outerwear' => 'OW', 'accessory' => 'AC'];
        $sku = 'DCI-' . $prefixes[$categorySlug] . '-' . strtoupper(bin2hex(random_bytes(6)));
        $productStmt = $conn->prepare('INSERT INTO products (category_id, name, description, price, sku, is_active) VALUES (?, ?, ?, ?, ?, 1)');
        if (!$productStmt) throw new Exception('Unable to prepare product creation.');
        $productStmt->bind_param('issds', $categoryId, $name, $description, $price, $sku);
    }
    if (!$productStmt->execute()) throw new Exception('Unable to save product details.');
    if (!$productId) $productId = (int) $conn->insert_id;
    $productStmt->close();

    $deleteImages = $conn->prepare('DELETE FROM product_images WHERE product_id = ?');
    $insertImage = $conn->prepare('INSERT INTO product_images (product_id, image_url, view_label, sort_order) VALUES (?, ?, ?, ?)');
    if (!$deleteImages || !$insertImage) throw new Exception('Unable to prepare product image save.');
    $deleteImages->bind_param('i', $productId);
    if (!$deleteImages->execute()) throw new Exception('Unable to replace product images.');
    $sortOrder = 0;
    foreach ($currentImages as $label => $url) {
        if ($url === '') continue;
        $sortOrder++;
        $insertImage->bind_param('issi', $productId, $url, $label, $sortOrder);
        if (!$insertImage->execute()) throw new Exception('Unable to save product images.');
    }
    $deleteImages->close();
    $insertImage->close();

    $deactivateVariants = $conn->prepare('UPDATE product_variants SET is_active = 0 WHERE product_id = ?');
    $findVariant = $conn->prepare('SELECT variant_id FROM product_variants WHERE product_id = ? AND size = ? ORDER BY variant_id LIMIT 1');
    $updateVariant = $conn->prepare('UPDATE product_variants SET stock_quantity = ?, is_active = 1 WHERE variant_id = ?');
    $insertVariant = $conn->prepare('INSERT INTO product_variants (product_id, size, color, stock_quantity, is_active) VALUES (?, ?, "Default", ?, 1)');
    $stockLookup = $conn->prepare(
        'SELECT SUM(stock_quantity) AS stock_quantity FROM product_variants
         WHERE product_id = ? AND size = ? AND is_active = 1'
    );
    if (!$deactivateVariants || !$findVariant || !$updateVariant || !$insertVariant || !$stockLookup) {
        throw new Exception('Unable to prepare inventory save.');
    }
    $deactivateVariants->bind_param('i', $productId);
    if (!$deactivateVariants->execute()) throw new Exception('Unable to update product inventory.');
    foreach ($variants as $size => $stock) {
        $findVariant->bind_param('is', $productId, $size);
        if (!$findVariant->execute()) throw new Exception('Unable to find product size.');
        $existingVariant = $findVariant->get_result()->fetch_assoc();
        if ($existingVariant) {
            $variantId = (int) $existingVariant['variant_id'];
            $updateVariant->bind_param('ii', $stock, $variantId);
            if (!$updateVariant->execute()) throw new Exception('Unable to update product stock.');
        } else {
            $insertVariant->bind_param('isi', $productId, $size, $stock);
            if (!$insertVariant->execute()) throw new Exception('Unable to add product size.');
        }
        $stockLookup->bind_param('is', $productId, $size);
        if (!$stockLookup->execute()) throw new Exception('Unable to verify saved inventory.');
        $stockRow = $stockLookup->get_result()->fetch_assoc();
        if (!$stockRow || !syncLowStockNotification(
            $conn,
            $productId,
            $size,
            $name,
            (int) $stockRow['stock_quantity']
        )) {
            throw new Exception('Unable to save product stock notifications.');
        }
    }
    $deactivateVariants->close();
    $findVariant->close();
    $updateVariant->close();
    $insertVariant->close();
    $stockLookup->close();
    if (!$conn->commit()) throw new Exception('Unable to complete the product update.');
} catch (Exception $error) {
    $conn->rollback();
    foreach ($savedFiles as $savedFile) @unlink($savedFile);
    error_log('Admin product save failed: ' . $error->getMessage());
    adminProductsRespond(500, ['success' => false, 'message' => 'The product could not be saved. Check the details and try again.']);
}

adminProductsRespond(200, ['success' => true, 'product_id' => $productId]);
?>
