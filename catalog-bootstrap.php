<?php
/**
 * Ensure the bundled starter catalog exists in the connected database.
 * INSERT IGNORE keeps this safe to run on each customer catalog request.
 */
function ensureStarterCatalog(mysqli $conn): void
{
    $imageTableSql = "CREATE TABLE IF NOT EXISTS product_images (
        image_id int(11) NOT NULL AUTO_INCREMENT,
        product_id int(11) NOT NULL,
        image_url varchar(255) NOT NULL,
        view_label varchar(40) NOT NULL DEFAULT 'Front',
        sort_order int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (image_id),
        UNIQUE KEY uq_product_image_path (product_id, image_url(190)),
        KEY idx_product_images_product (product_id),
        CONSTRAINT fk_product_images_product FOREIGN KEY (product_id)
            REFERENCES products (product_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($imageTableSql)) {
        error_log('Catalog bootstrap could not ensure the product_images table: ' . $conn->error);
        return;
    }

    $categories = [
        ['Tops', 'tshirt'],
        ['Bottom', 'bottom'],
        ['Outerwear', 'outerwear'],
        ['Accessories', 'accessory']
    ];
    $categoryInsert = $conn->prepare('INSERT IGNORE INTO categories (name, slug) VALUES (?, ?)');
    $categoryUpdate = $conn->prepare('UPDATE categories SET name = ? WHERE slug = ?');
    $categoryLookup = $conn->prepare('SELECT category_id FROM categories WHERE slug = ? LIMIT 1');
    $productInsert = $conn->prepare('INSERT IGNORE INTO products (category_id, name, description, price, sku) VALUES (?, ?, ?, ?, ?)');
    $productLookup = $conn->prepare('SELECT product_id FROM products WHERE sku = ? LIMIT 1');
    $imageInsert = $conn->prepare('INSERT IGNORE INTO product_images (product_id, image_url, view_label, sort_order) VALUES (?, ?, ?, ?)');
    $variantInsert = $conn->prepare('INSERT IGNORE INTO product_variants (product_id, size, color, stock_quantity) VALUES (?, ?, ?, ?)');

    if (!$categoryInsert || !$categoryUpdate || !$categoryLookup || !$productInsert || !$productLookup || !$imageInsert || !$variantInsert) {
        error_log('Catalog bootstrap could not prepare its statements: ' . $conn->error);
        return;
    }

    $categoryIds = [];
    foreach ($categories as [$name, $slug]) {
        $categoryInsert->bind_param('ss', $name, $slug);
        if (!$categoryInsert->execute()) {
            error_log('Catalog category seed failed: ' . $categoryInsert->error);
            return;
        }
        $categoryUpdate->bind_param('ss', $name, $slug);
        if (!$categoryUpdate->execute()) {
            error_log('Catalog category name update failed: ' . $categoryUpdate->error);
            return;
        }
        $categoryLookup->bind_param('s', $slug);
        $categoryLookup->execute();
        $category = $categoryLookup->get_result()->fetch_assoc();
        if ($category) $categoryIds[$slug] = (int) $category['category_id'];
    }

    $products = [
        ['tshirt', 'CodeBreaker T-Shirt', 'DCI graphic T-shirt.', 250.00, 'DCI-TS-CODEBREAKER', [
            ['assets/tshirt/CodeBreaker/CodeBreaker_Front.jpg', 'Front', 1],
            ['assets/tshirt/CodeBreaker/CodeBreaker_Back.jpg', 'Back', 2],
            ['assets/tshirt/CodeBreaker/CodeBreaker_Side.jpg', 'Side', 3]
        ], [['S', 'Black', 12], ['M', 'Black', 18], ['L', 'Black', 15], ['XL', 'Black', 8]]],
        ['bottom', 'AlgoArk Bottoms', 'Casual DCI bottoms.', 650.00, 'DCI-BT-ALGOARK', [
            ['assets/bottom/AlgoArk/AlgoArk_Front.jpg', 'Front', 1],
            ['assets/bottom/AlgoArk/AlgoArk_Back.jpg', 'Back', 2],
            ['assets/bottom/AlgoArk/AlgoArk_Side.jpg', 'Side', 3]
        ], [['S', 'Black', 6], ['M', 'Black', 10], ['L', 'Black', 8], ['XL', 'Black', 5]]],
        ['outerwear', 'AlgoWeave Hoodie', 'Warm DCI outerwear.', 850.00, 'DCI-OW-ALGOWEAVE', [
            ['assets/outerwear/AlgoWeave/AlgoWeave_Front.jpg', 'Front', 1],
            ['assets/outerwear/AlgoWeave/AlgoWeave_Back.jpg', 'Back', 2],
            ['assets/outerwear/AlgoWeave/AlgoWeave_Side.jpg', 'Side', 3]
        ], [['M', 'Gray', 7], ['L', 'Gray', 9], ['XL', 'Gray', 4]]],
        ['accessory', 'AlgoRhythm Accessory', 'DCI branded accessory.', 180.00, 'DCI-AC-ALGORHYTHM', [
            ['assets/accessory/AlgoRhythm/AlgoRhythm_Front.jpg', 'Front', 1],
            ['assets/accessory/AlgoRhythm/AlgoRhythm_Back.jpg', 'Back', 2],
            ['assets/accessory/AlgoRhythm/AlgoRhythm_Zoom.jpg', 'Detail', 3]
        ], [['One Size', 'Default', 25]]]
    ];

    foreach ($products as [$categorySlug, $name, $description, $price, $sku, $images, $variants]) {
        if (!isset($categoryIds[$categorySlug])) continue;
        $categoryId = $categoryIds[$categorySlug];
        $productInsert->bind_param('issds', $categoryId, $name, $description, $price, $sku);
        if (!$productInsert->execute()) {
            error_log('Catalog product seed failed: ' . $productInsert->error);
            continue;
        }
        $productLookup->bind_param('s', $sku);
        $productLookup->execute();
        $product = $productLookup->get_result()->fetch_assoc();
        if (!$product) continue;
        $productId = (int) $product['product_id'];

        foreach ($images as [$imageUrl, $viewLabel, $sortOrder]) {
            $imageInsert->bind_param('issi', $productId, $imageUrl, $viewLabel, $sortOrder);
            if (!$imageInsert->execute()) error_log('Catalog image seed failed: ' . $imageInsert->error);
        }
        foreach ($variants as [$size, $color, $stock]) {
            $variantInsert->bind_param('issi', $productId, $size, $color, $stock);
            if (!$variantInsert->execute()) error_log('Catalog variant seed failed: ' . $variantInsert->error);
        }
    }

    // Import every bundled asset product folder. Category prices use the matching
    // starter product price already defined in dci_db.sql as an initial catalog price.
    $assetCategories = [
        'tshirt' => ['folder' => 'tshirt', 'prefix' => 'DCI-TS-', 'suffix' => 'T-Shirt', 'label' => 'T-Shirt', 'price' => 250.00, 'sizes' => ['S', 'M', 'L', 'XL']],
        'bottom' => ['folder' => 'bottom', 'prefix' => 'DCI-BT-', 'suffix' => 'Bottoms', 'label' => 'Bottoms', 'price' => 650.00, 'sizes' => ['S', 'M', 'L', 'XL']],
        'outerwear' => ['folder' => 'outerwear', 'prefix' => 'DCI-OW-', 'suffix' => 'Outerwear', 'label' => 'Outerwear', 'price' => 850.00, 'sizes' => ['S', 'M', 'L', 'XL']],
        'accessory' => ['folder' => 'accessory', 'prefix' => 'DCI-AC-', 'suffix' => 'Accessory', 'label' => 'Accessory', 'price' => 180.00, 'sizes' => ['One Size']]
    ];
    $assetRoot = __DIR__ . DIRECTORY_SEPARATOR . 'assets';
    $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

    foreach ($assetCategories as $categorySlug => $settings) {
        if (!isset($categoryIds[$categorySlug])) continue;
        $categoryPath = $assetRoot . DIRECTORY_SEPARATOR . $settings['folder'];
        $productFolders = glob($categoryPath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        foreach ($productFolders as $productFolder) {
            $folderName = basename($productFolder);
            $productToken = strtoupper((string) preg_replace('/[^a-zA-Z0-9]/', '', $folderName));
            if ($productToken === '') continue;
            $sku = $settings['prefix'] . $productToken;
            $displayName = trim((string) preg_replace('/([a-z])([A-Z])/', '$1 $2', $folderName));
            $productName = $displayName . ' ' . $settings['suffix'];
            $description = 'DCI Vault ' . strtolower($settings['label']) . ' design.';
            $categoryId = $categoryIds[$categorySlug];

            $productInsert->bind_param('issds', $categoryId, $productName, $description, $settings['price'], $sku);
            if (!$productInsert->execute()) {
                error_log('Asset product seed failed for ' . $folderName . ': ' . $productInsert->error);
                continue;
            }
            $productLookup->bind_param('s', $sku);
            $productLookup->execute();
            $productRow = $productLookup->get_result()->fetch_assoc();
            if (!$productRow) continue;
            $productId = (int) $productRow['product_id'];

            $imagePaths = glob($productFolder . DIRECTORY_SEPARATOR . '*') ?: [];
            $sortOrder = 0;
            foreach ($imagePaths as $imagePath) {
                if (!is_file($imagePath)) continue;
                $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
                if (!in_array($extension, $imageExtensions, true)) continue;
                $filename = strtolower(basename($imagePath));
                if (strpos($filename, 'front') !== false) $viewLabel = 'Front';
                elseif (strpos($filename, 'back') !== false) $viewLabel = 'Back';
                elseif (strpos($filename, 'side') !== false || strpos($filename, '3-4') !== false || strpos($filename, 'threequarter') !== false) $viewLabel = 'Side';
                elseif (strpos($filename, 'detail') !== false || strpos($filename, 'zoom') !== false) $viewLabel = 'Detail';
                else $viewLabel = 'View';
                $imageUrl = str_replace(DIRECTORY_SEPARATOR, '/', substr($imagePath, strlen(__DIR__) + 1));
                $sortOrder++;
                $imageInsert->bind_param('issi', $productId, $imageUrl, $viewLabel, $sortOrder);
                if (!$imageInsert->execute()) error_log('Asset image seed failed for ' . $imagePath . ': ' . $imageInsert->error);
            }

            foreach ($settings['sizes'] as $size) {
                $color = 'Default';
                $stock = 0;
                $variantInsert->bind_param('issi', $productId, $size, $color, $stock);
                if (!$variantInsert->execute()) error_log('Asset variant seed failed for ' . $productName . ': ' . $variantInsert->error);
            }
        }
    }

    $categoryInsert->close();
    $categoryLookup->close();
    $productInsert->close();
    $productLookup->close();
    $imageInsert->close();
    $variantInsert->close();
}

ensureStarterCatalog($conn);
?>
