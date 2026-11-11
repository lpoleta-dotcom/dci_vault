<?php
require_once 'customer-session.php';
require_once 'catalog-bootstrap.php';

$customerName = trim($customerHeaderUsername);
$profileName = $customerName !== '' ? $customerName : 'Customer';
$welcomeMessage = $customerName !== ''
    ? 'Welcome back, ' . $customerName . '!'
    : 'Welcome to DCI Vault!';

$catalogProducts = [];
$catalogContains = static function ($value, $part) {
    return strpos((string) $value, (string) $part) !== false;
};
$productResult = $conn->query("SELECT p.product_id, p.name, p.description, p.price, c.name AS category_name, c.slug AS category_slug FROM products p JOIN categories c ON c.category_id = p.category_id WHERE p.is_active = 1 AND c.is_active = 1 ORDER BY FIELD(c.slug, 'tshirt', 'bottom', 'outerwear', 'accessory'), p.name");
if ($productResult) {
    while ($product = $productResult->fetch_assoc()) {
        $product['images'] = ['front' => '', 'back' => '', 'side' => ''];
        $product['sizes'] = [];
        $product['variants'] = [];
        $catalogProducts[(int) $product['product_id']] = $product;
    }
}

$imageResult = $conn->query('SELECT product_id, image_url, view_label FROM product_images ORDER BY sort_order, image_id');
if ($imageResult) {
    while ($image = $imageResult->fetch_assoc()) {
        $id = (int) $image['product_id'];
        if (!isset($catalogProducts[$id])) continue;
        $label = strtolower(trim((string) $image['view_label']));
        $url = trim((string) $image['image_url']);
        if ($url === '') continue;
        if ($catalogContains($label, 'front') && $catalogProducts[$id]['images']['front'] === '') $catalogProducts[$id]['images']['front'] = $url;
        elseif ($catalogContains($label, 'back') && $catalogProducts[$id]['images']['back'] === '') $catalogProducts[$id]['images']['back'] = $url;
        elseif (($catalogContains($label, 'side') || $catalogContains($label, '3/4') || $catalogContains($label, 'three quarter') || $catalogContains($label, 'detail') || $catalogContains($label, 'zoom')) && $catalogProducts[$id]['images']['side'] === '') $catalogProducts[$id]['images']['side'] = $url;
    }
}

$variantResult = $conn->query(
    'SELECT product_id, size, SUM(stock_quantity) AS stock_quantity
     FROM product_variants
     WHERE is_active = 1
     GROUP BY product_id, size
     ORDER BY product_id, size'
);
if ($variantResult) {
    while ($variant = $variantResult->fetch_assoc()) {
        $id = (int) $variant['product_id'];
        if (isset($catalogProducts[$id])) {
            $catalogProducts[$id]['sizes'][] = $variant['size'];
            $catalogProducts[$id]['variants'][] = [
                'size' => $variant['size'],
                'stock' => (int) $variant['stock_quantity']
            ];
        }
    }
}

$assetCategoryFolders = ['tshirt' => 'tshirt', 'bottom' => 'bottom', 'outerwear' => 'outerwear', 'accessory' => 'accessory'];
foreach ($catalogProducts as &$product) {
    $categorySlug = strtolower((string) ($product['category_slug'] ?? ''));
    $categoryFolder = $assetCategoryFolders[$categorySlug] ?? '';
    if ($categoryFolder !== '') {
        $assetCategoryPath = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $categoryFolder;
        $productToken = preg_replace('/[^a-z0-9]/', '', strtolower((string) $product['name']));
        foreach (glob($assetCategoryPath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $productFolder) {
            $folderToken = preg_replace('/[^a-z0-9]/', '', strtolower(basename($productFolder)));
            if ($folderToken === '' || (!$catalogContains($productToken, $folderToken) && !$catalogContains($folderToken, $productToken))) continue;
            foreach (glob($productFolder . DIRECTORY_SEPARATOR . '*') ?: [] as $imagePath) {
                if (!is_file($imagePath)) continue;
                $filename = strtolower(basename($imagePath));
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($imagePath, strlen(__DIR__) + 1));
                if ($catalogContains($filename, 'front') && $product['images']['front'] === '') $product['images']['front'] = $relativePath;
                elseif ($catalogContains($filename, 'back') && $product['images']['back'] === '') $product['images']['back'] = $relativePath;
                elseif (($catalogContains($filename, 'side') || $catalogContains($filename, '3-4') || $catalogContains($filename, 'threequarter') || $catalogContains($filename, 'detail') || $catalogContains($filename, 'zoom')) && $product['images']['side'] === '') $product['images']['side'] = $relativePath;
            }
            break;
        }
    }
    if ($product['images']['front'] === '') $product['images']['front'] = $product['images']['back'] ?: ($product['images']['side'] ?: 'assets/placeholder.jpg');
}
unset($product);

$catalogEscape = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DCI Vault | Products</title>

    <!-- Bootstrap 5.3.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="style/style.css?v=customer-modal-carousel-5">
    <script src="backend/session-history.js"></script>
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('dci-vault-theme');
                var preferred = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                document.documentElement.dataset.theme = saved === 'dark' || saved === 'light' ? saved : preferred;
            } catch (error) {}
        })();
    </script>
</head>

<!-- Russian Nesting Doll Layout: Locks screen height and prevents double scrollbars -->
<body class="customer-page customer-products-page vh-100 overflow-hidden d-flex flex-column bg-light"
    data-customer-id="<?php echo (int) $userId; ?>">

    <!-- HEADER CONTAINER WITH INTEGRATED NAVIGATION -->
    <header class="main-header d-flex flex-row align-items-center justify-content-between flex-shrink-0 px-4 py-2 gap-3 bg-white border-bottom">
        
        <!-- Brand Logo / Title -->
        <div class="main-name d-flex align-items-center gap-2">
            <a class="text-decoration-none" href="cstmr-prod.php">
                <img src="assets/logo.png" alt="DCI Vault" class="brand-logo">
            </a>
            <a class="text-decoration-none" href="cstmr-prod.php">
                <span class="fw-bold fs-5 text-dark">DCI Vault</span>
            </a>
        </div>

        <!-- Top Navigation Links (Aligned to DCI Tech Blue Palette) -->
        <nav class="customer-nav d-flex align-items-center gap-1" aria-label="Customer navigation">
            <a class="nav-link px-3 py-2 rounded fw-semibold active text-white" href="cstmr-prod.php" style="background-color: var(--contrast-primary, #2563EB);">
                <i class="bi bi-tag me-1"></i>Products
            </a>
            <a class="nav-link px-3 py-2 rounded text-secondary hover-dark" href="cstmr-cart.php">
                <i class="bi bi-cart me-1"></i>My Cart
            </a>
            <a class="nav-link px-3 py-2 rounded text-secondary hover-dark" href="cstmr-order.php">
                <i class="bi bi-bag-check me-1"></i>My Orders
            </a>
            <a class="nav-link px-3 py-2 rounded text-secondary hover-dark" href="cstmr-account.php">
                <i class="bi bi-person me-1"></i>Account
            </a>
        </nav>

        <?php $notificationRole = 'customer'; require __DIR__ . DIRECTORY_SEPARATOR . 'notification-widget.php'; ?>

        <div class="customer-header-actions d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="customer-profile-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open profile menu">
                    <img id="empAvatar" data-session-managed="true" src="<?php echo $customerEscape($customerAvatarUrl); ?>" alt="" class="user-avatar rounded-circle object-fit-cover" width="40" height="40">
                    <span id="userName" data-session-managed="true" class="customer-profile-name"><?php echo $customerEscape($profileName); ?></span>
                    <i class="bi bi-chevron-down customer-profile-chevron" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end customer-profile-menu">
                    <li><a class="dropdown-item" href="cstmr-account.php"><i class="bi bi-person me-2" aria-hidden="true"></i>View Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a id="logoutBtn" class="dropdown-item customer-logout-item" href="logout.php"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Log Out</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- MAIN APP WRAPPER (Full width) -->
    <div class="d-flex flex-row flex-grow-1 overflow-hidden">

        <!-- PRODUCTS PANEL (Scrollable Area) -->
        <main class="main-control customer-main flex-grow-1 d-flex flex-column overflow-y-auto p-4">

            <section class="d-flex align-items-center gap-3 p-4 mb-4 rounded-4 shadow-sm text-white"
                style="background: linear-gradient(120deg, #0F172A, #2563EB);"
                aria-label="Welcome">
                <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                    style="width: 3.25rem; height: 3.25rem; background: rgba(255, 255, 255, 0.16);">
                    <i class="bi bi-bag-heart-fill fs-3" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="mb-1 text-white-50 small fw-semibold">WELCOME TO DCI VAULT</p>
                    <h1 class="h4 mb-1 fw-bold text-white"><?php echo htmlspecialchars($welcomeMessage, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="mb-0 text-white-50">We're glad you're here. Find something you'll love.</p>
                </div>
            </section>

            <!-- Section Header & Category Filters -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <span class="customer-eyebrow">YOUR NEXT FAVORITE</span>
                    <h2 class="h3 fw-bold text-dark m-0">Product Catalog</h2>
                    <p class="customer-page-intro mb-0 mt-1">Explore everyday essentials and find something you love. Prices include 12% VAT.</p>
                </div>

                <ul class="nav nav-pills flex-nowrap gap-1 p-1 bg-white customer-filter" id="categoryFilter" role="tablist" aria-label="Filter products by category">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link btn-sm px-3 py-2 text-dark active" data-bs-toggle="pill"
                            data-category="all">All Products</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link btn-sm px-3 py-2 text-dark" data-bs-toggle="pill"
                            data-category="tshirt">Tops</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link btn-sm px-3 py-2 text-dark" data-bs-toggle="pill"
                            data-category="bottom">Bottom</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link btn-sm px-3 py-2 text-dark" data-bs-toggle="pill"
                            data-category="outerwear">Outerwear</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link btn-sm px-3 py-2 text-dark" data-bs-toggle="pill"
                            data-category="accessory">Accessories</button>
                    </li>
                </ul>
            </div>

            <!-- Responsive Product Grid -->
            <div id="productGrid" class="row g-3">
                <?php if ($catalogProducts): ?>
                    <?php foreach ($catalogProducts as $product): ?>
                        <?php
                        $imageFront = $product['images']['front'];
                        $imageBack = $product['images']['back'];
                        $imageSide = $product['images']['side'];
                        $sizes = implode(',', $product['sizes']);
                        $variants = json_encode($product['variants'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP);
                        $hasStock = false;
                        foreach ($product['variants'] as $variant) {
                            if ($variant['stock'] > 0) $hasStock = true;
                        }
                        ?>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                            <div class="productBox h-100 d-flex flex-column product-card card border-0 shadow-sm p-3 bg-white"
                                data-product-id="<?php echo (int) $product['product_id']; ?>"
                                data-name="<?php echo $catalogEscape($product['name']); ?>"
                                data-category="<?php echo $catalogEscape($product['category_slug'] ?? 'all'); ?>"
                                data-price="<?php echo $catalogEscape($product['price']); ?>"
                                data-description="<?php echo $catalogEscape($product['description'] ?? ''); ?>"
                                data-sizes="<?php echo $catalogEscape($sizes); ?>"
                                data-variants="<?php echo $catalogEscape($variants); ?>"
                                data-img-front="<?php echo $catalogEscape($imageFront); ?>"
                                data-img-back="<?php echo $catalogEscape($imageBack); ?>"
                                data-img-side="<?php echo $catalogEscape($imageSide); ?>">
                                <div class="card-body d-flex flex-column p-0">
                                    <img src="<?php echo $catalogEscape($imageFront); ?>" class="card-img-top object-fit-cover rounded mb-2" alt="<?php echo $catalogEscape($product['name']); ?> front view" style="height: 180px;">
                                    <h4 class="productName fs-6 mb-1 text-truncate fw-semibold text-dark p-0"><?php echo $catalogEscape($product['name']); ?></h4>
                                    <h5 class="productPrice fs-6 mb-3 p-0"><?php echo $catalogEscape($product['price']); ?></h5>
                                    <?php if (!$hasStock): ?><small class="text-danger mb-2">Out of stock</small><?php endif; ?>
                                    <button type="button" class="productBuyButton btn btn-sm mt-auto add-to-cart-btn fw-semibold w-100 m-0" <?php echo $hasStock ? '' : 'disabled'; ?>>View Product</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-light border text-secondary mb-0" role="status">No active products are available in the catalog yet.</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Product Details Modal -->
            <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg modal-custom-content">
                        <div class="modal-header border-0 pb-0">
                            <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="row g-4">
                                <!-- Left: Image Carousel -->
                                <div class="col-md-6">
                                    <div id="productCarousel" class="carousel slide" data-bs-ride="carousel">
                                        <div id="productModalImages" class="carousel-inner rounded overflow-hidden modal-carousel-inner">
                                            <div class="carousel-item active h-100"><img src="assets/placeholder.jpg" class="d-block" alt="Product view"></div>
                                        </div>
                                        <button class="carousel-control-prev" type="button"
                                            data-bs-target="#productCarousel" data-bs-slide="prev">
                                            <span class="carousel-control-prev-icon p-2 rounded-circle" style="background-color: var(--primary-color, #0F172A);" aria-hidden="true"></span>
                                        </button>
                                        <button class="carousel-control-next" type="button"
                                            data-bs-target="#productCarousel" data-bs-slide="next">
                                            <span class="carousel-control-next-icon p-2 rounded-circle" style="background-color: var(--primary-color, #0F172A);" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Right: Details & Actions -->
                                <div class="col-md-6 d-flex flex-column justify-content-between">
                                    <div>
                                        <h3 id="productModalName" class="fw-bold mb-2 fs-4 text-dark">Product Name</h3>
                                        <h4 id="productModalPrice" class="mb-3 fs-5 fw-semibold" style="color: var(--contrast-primary, #2563EB);">P0.00</h4>
                                        <p id="productModalDescription" class="text-secondary mb-4">Product description will appear here.</p>

                                        <!-- Size Selector -->
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold text-secondary small">Select Size</label>
                                            <select id="productModalSize" class="form-select shadow-none">
                                                <!-- Dynamic options will load here -->
                                            </select>
                                        </div>

                                        <!-- Quantity Selector -->
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold text-secondary small">Quantity</label>
                                            <input type="number" id="productModalQty" class="form-control shadow-none"
                                                value="1" min="1">
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="d-flex gap-2 mt-3">
                                        <button id="productAddToCart"
                                            class="btn text-white flex-grow-1 fw-semibold py-2 shadow-sm"
                                            style="background-color: var(--contrast-primary, #2563EB);">Add to Cart</button>
                                        <button id="modalBuyNow" 
                                            class="btn text-white flex-grow-1 fw-semibold py-2 shadow-sm"
                                            style="background-color: var(--contrast-primary, #2563EB);">Buy Now</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="backend/cstmr.js?v=customer-reset-state-1"></script>
    <script src="backend/theme.js?v=theme-header-order-2"></script>
</body>
</html>
