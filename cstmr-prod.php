<?php
require_once 'customer-session.php';

$customerName = trim($customerHeaderUsername);
$profileName = $customerName !== '' ? $customerName : 'Customer';
$welcomeMessage = $customerName !== ''
    ? 'Welcome back, ' . $customerName . '!'
    : 'Welcome to DCI Vault!';
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
    <link rel="stylesheet" href="style/style.css">
</head>

<!-- Russian Nesting Doll Layout: Locks screen height and prevents double scrollbars -->
<body class="customer-page customer-products-page vh-100 overflow-hidden d-flex flex-column bg-light">

    <!-- HEADER CONTAINER WITH INTEGRATED NAVIGATION -->
    <header class="main-header d-flex flex-row align-items-center justify-content-between flex-shrink-0 px-4 py-2 gap-3 bg-white border-bottom">
        
        <!-- Brand Logo / Title -->
        <div class="main-name d-flex align-items-center gap-2">
            <img src="assets/logo.png" alt="DCI Vault" class="brand-logo">
            <span class="fw-bold fs-5 text-dark">DCI Vault</span>
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

        <!-- Customer Profile / Account Section & Logout -->
        <div class="d-flex align-items-center gap-3">
            <div class="user-profile d-flex align-items-center gap-2">
                <div class="d-flex flex-column text-end lh-sm">
                    <a id="userName" data-session-managed="true" class="mb-0 fs-6 fw-bold text-decoration-none text-dark" href="cstmr-account.php"><?php echo $customerEscape($profileName); ?></a>
                    <small id="userStatus" class="text-secondary">Active Shopper</small>
                </div>
                <img id="empAvatar" data-session-managed="true" src="<?php echo $customerEscape($customerAvatarUrl); ?>" alt="User Avatar" class="user-avatar rounded-circle object-fit-cover" width="40" height="40">
            </div>

            <!-- Logout Button Icon / Link -->
            <a id="logoutBtn" class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1 px-2 py-1" href="logout.php" title="Log Out">
                <i class="bi bi-box-arrow-right fs-6"></i>
                <span class="d-none d-lg-inline">Log Out</span>
            </a>
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
                            data-category="tshirt">T-Shirt</button>
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
                            data-category="accessory">Accessory</button>
                    </li>
                </ul>
            </div>

            <!-- Responsive Product Grid -->
            <div id="productGrid" class="row g-3">

                <!-- Product Card Template -->
                <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                    <div class="productBox h-100 d-flex flex-column product-card card border-0 shadow-sm p-3 bg-white" 
                        data-product-id="1" 
                        data-name="Classic T-Shirt"
                        data-category="tshirt"
                        data-price="250.00"
                        data-description="A soft, everyday DCI classic with a comfortable fit. Easy to pair, easy to wear, and made for showing your department pride."
                        data-sizes="S,M,L,XL"
                        data-img-front="assets/product.jpg"
                        data-img-back="assets/product.jpg">
                        <div class="card-body d-flex flex-column p-0">
                            <img src="assets/product.jpg" class="card-img-top object-fit-cover rounded mb-2" alt="product"
                                style="height: 130px;">
                            <h4 id="productName" class="productName fs-6 mb-1 text-truncate fw-semibold text-dark p-0">Classic T-Shirt</h4>
                            <h5 id="productPrice" class="productPrice fs-6 mb-3 p-0">P250.00</h5>
                            <button class="productBuyButton btn btn-sm mt-auto add-to-cart-btn fw-semibold w-100 m-0">Add to Cart</button>
                        </div>
                    </div>
                </div>
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
                                        <div class="carousel-inner rounded overflow-hidden modal-carousel-inner" style="height: 280px;">
                                            <div class="carousel-item active h-100">
                                                <img id="productModalImgFront" src="assets/product.jpg"
                                                    class="d-block w-100 object-fit-cover h-100" alt="Front view">
                                            </div>
                                            <div class="carousel-item h-100">
                                                <img id="productModalImgBack" src="assets/product.jpg"
                                                    class="d-block w-100 object-fit-cover h-100" alt="Back view">
                                            </div>
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
                                            style="background-color: var(--primary-color, #0F172A);">Buy Now</button>
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
    <script src="backend/cstmr.js?v=vat-inclusive"></script>
</body>
</html>
