<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-session.php';
$adminName = htmlspecialchars($adminSessionName, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DCI Vault | Manage Products</title>

    <!-- Bootstrap 5.3.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="style/style.css?v=admin-theme-3">
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

<!-- Russian Nesting Doll Layout -->

<body class="admin-page admin-products-page vh-100 overflow-hidden d-flex flex-column bg-light">

    <!-- HEADER CONTAINER -->
    <header
        class="main-header d-flex flex-row align-items-center justify-content-between flex-shrink-0 px-4 py-2 bg-white border-bottom">
        <div class="main-name d-flex align-items-center gap-2">
            <a href="admin-dashboard.php">
                <img src="assets/logo.png" alt="DCI Vault" class="brand-logo rounded border">
            </a>
            <a href="admin-dashboard.php" class="text-decoration-none">
                <span class="fw-bold fs-5 text-dark">DCI Vault <span
                        class="badge bg-primary bg-opacity-15 text-primary border border-primary border-opacity-25 ms-2 fs-6 px-2 py-1 rounded-pill">Admin</span></span>
            </a>
        </div>

        <div class="admin-header-actions">
            <?php $notificationRole = 'admin'; require __DIR__ . DIRECTORY_SEPARATOR . 'notification-widget.php'; ?>

            <!-- Administrator Profile Section -->
            <div class="user-profile d-flex align-items-center gap-2">
                <div class="d-flex flex-column text-end lh-sm">
                    <!-- PHP INJECTION: Dynamically output admin name -->
                    <h4 id="adminNameDisplay" class="mb-0 fs-6 fw-bold text-dark"><?php echo $adminName; ?></h4>
                    <small id="adminPosDisplay" class="text-secondary">System Administrator</small>
                </div>
                <!-- PHP INJECTION: Dynamically output admin avatar -->
                <img id="adminAvatarDisplay" src="assets/user.jpg" alt="Admin Avatar"
                    class="user-avatar rounded-circle object-fit-cover shadow-sm" width="40" height="40">
            </div>
        </div>
    </header>

    <!-- MAIN APP WRAPPER -->
    <div class="d-flex flex-row flex-grow-1 overflow-hidden">

        <!-- NAVIGATION PANEL -->
        <nav class="main-navigation admin-sidebar d-flex flex-column justify-content-between p-3 flex-shrink-0 text-white"
            aria-label="Admin navigation"
            style="width: 260px;">
            <div class="d-flex flex-column gap-2">
                <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white rounded"
                    href="admin-dashboard.php">
                    <i class="bi bi-speedometer2 fs-5"></i>Dashboard
                </a>
                <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white rounded" href="admin-sales.php">
                    <i class="bi bi-graph-up-arrow fs-5"></i>Sales
                </a>
                <!-- Active page highlight -->
                <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white rounded active fw-semibold"
                    href="admin-prod.php" aria-current="page">
                    <i class="bi bi-tag fs-5"></i>Products
                </a>
                <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white rounded"
                    href="admin-order.php">
                    <i class="bi bi-bag-check fs-5"></i>Orders
                </a>
                <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white rounded"
                    href="admin-users.php">
                    <i class="bi bi-people fs-5"></i>Users
                </a>
            </div>

            <div class="border-top border-secondary pt-3">
                <a id="adminLogoutBtn" class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-danger rounded"
                    href="admin-logout.php" style="color: #dc3545 !important;">
                    <i class="bi bi-box-arrow-right fs-5 text-danger"></i>Log Out
                </a>
            </div>
        </nav>

        <main class="main-control admin-main flex-grow-1 d-flex flex-column overflow-y-auto p-4">

            <!-- Section Header & Add Product Action -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <span class="admin-eyebrow">CATALOG MANAGEMENT</span>
                    <h2 class="h3 fw-bold text-dark m-0">Manage Products</h2>
                    <p class="text-secondary small m-0">Add, edit, or remove store products and catalog listings.</p>
                </div>
                <!-- JS TARGET: Opens Add Product Modal -->
                <button type="button" class="btn fw-semibold px-4 shadow-sm text-white" id="openAddProductModalBtn"
                    data-bs-toggle="modal" data-bs-target="#productModal"
                    style="background-color: var(--contrast-primary, #2563EB);">
                    <i class="bi bi-plus-lg me-1"></i> Add New Product
                </button>
            </div>

            <div class="nav nav-pills admin-product-category-filter mb-3" id="adminProductCategoryFilter"
                role="group" aria-label="Filter products by category">
                <button type="button" class="nav-link active" data-product-category="all"
                    data-product-status="active" aria-pressed="true">All Active</button>
                <button type="button" class="nav-link" data-product-category="tshirt"
                    data-product-status="active" aria-pressed="false">Tops</button>
                <button type="button" class="nav-link" data-product-category="bottom"
                    data-product-status="active" aria-pressed="false">Bottom</button>
                <button type="button" class="nav-link" data-product-category="outerwear"
                    data-product-status="active" aria-pressed="false">Outerwear</button>
                <button type="button" class="nav-link" data-product-category="accessory"
                    data-product-status="active" aria-pressed="false">Accessories</button>
                <button type="button" class="nav-link" data-product-category="all" data-product-status="archived"
                    aria-pressed="false">Archived</button>
            </div>

            <!-- Products Table Container -->
            <div class="card border-0 shadow-sm flex-grow-1 overflow-hidden bg-white"
                style="border-radius: var(--prod-rd, 0.75rem);">
                <div class="table-responsive flex-grow-1 m-0">
                    <table class="table align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase">
                            <tr>
                                <th class="ps-4">Product</th>
                                <th>Product ID</th>
                                <th>Category</th>
                                <th>Price (VAT included)</th>
                                <th>Inventory</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                            <tr><td colspan="6" class="text-center text-secondary py-4">Loading products…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>

        <!-- Add/Edit Product Modal -->
        <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg modal-custom-content">
                    <div class="modal-header border-bottom px-4 py-3">
                        <h5 class="modal-title fw-bold text-dark" id="productModalLabel">Add New Product</h5>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body px-4 py-4">
                        <form id="productForm">
                            <div class="mb-3">
                                <label for="productNameInput" class="form-label small fw-semibold text-secondary">Product Name</label>
                                <input type="text" class="form-control shadow-none" id="productNameInput"
                                    placeholder="Enter product name" maxlength="150" required>
                            </div>
                            <div class="mb-3">
                                <label for="productDescriptionInput" class="form-label small fw-semibold text-secondary">Description</label>
                                <textarea class="form-control shadow-none" id="productDescriptionInput" rows="2" maxlength="2000"
                                    placeholder="Describe this product"></textarea>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">Category</label>
                                    <select class="form-select shadow-none" id="productCategoryInput" required>
                                        <option value="" selected disabled>Select category</option>
                                        <option value="tshirt">Tops</option>
                                        <option value="bottom">Bottom</option>
                                        <option value="outerwear">Outerwear</option>
                                        <option value="accessory">Accessories</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="productPriceInput" class="form-label small fw-semibold text-secondary">Product price (VAT included, ₱)</label>
                                <input type="number" step="0.01" min="0.01" class="form-control shadow-none"
                                    id="productPriceInput" placeholder="0.00" required>
                            </div>

                            <!-- Available Sizes Selector (Checkboxes) -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary d-block">Available
                                    Sizes</label>
                                <div class="d-flex flex-wrap gap-3 pt-1">
                                    <div class="form-check">
                                        <input class="form-check-input shadow-none product-size-checkbox"
                                            type="checkbox" value="XS" id="sizeXS">
                                        <label class="form-check-label small" for="sizeXS">XS</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input shadow-none product-size-checkbox"
                                            type="checkbox" value="S" id="sizeS">
                                        <label class="form-check-label small" for="sizeS">S</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input shadow-none product-size-checkbox"
                                            type="checkbox" value="M" id="sizeM" checked>
                                        <label class="form-check-label small" for="sizeM">M</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input shadow-none product-size-checkbox"
                                            type="checkbox" value="L" id="sizeL" checked>
                                        <label class="form-check-label small" for="sizeL">L</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input shadow-none product-size-checkbox"
                                            type="checkbox" value="XL" id="sizeXL">
                                        <label class="form-check-label small" for="sizeXL">XL</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input shadow-none product-size-checkbox"
                                            type="checkbox" value="XXL" id="sizeXXL">
                                        <label class="form-check-label small" for="sizeXXL">XXL</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input shadow-none product-size-checkbox"
                                            type="checkbox" value="One Size" id="sizeOne">
                                        <label class="form-check-label small" for="sizeOne">One Size</label>
                                    </div>
                                </div>
                                <div id="variantStockInputs" class="d-flex flex-column gap-2 mt-3"></div>
                            </div>

                            <div class="mb-3">
                                <label for="productFrontImageInput" class="form-label small fw-semibold text-secondary">Front View Image</label>
                                <input type="file" class="form-control shadow-none" id="productFrontImageInput"
                                    accept="image/*" required>
                            </div>
                            <div class="mb-3">
                                <label for="productThreeQuarterImageInput" class="form-label small fw-semibold text-secondary">Side / 3/4 View Image (optional)</label>
                                <input type="file" class="form-control shadow-none" id="productThreeQuarterImageInput"
                                    accept="image/jpeg,image/png,image/webp">
                            </div>
                            <div class="mb-3">
                                <label for="productBackImageInput" class="form-label small fw-semibold text-secondary">Back View Image (optional)</label>
                                <input type="file" class="form-control shadow-none" id="productBackImageInput"
                                    accept="image/jpeg,image/png,image/webp">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer border-top px-4 py-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-4"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="saveProductBtn"
                            class="btn btn-sm fw-semibold text-white px-4 shadow-sm"
                            style="background-color: var(--contrast-primary, #2563EB);">Save Product</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="archiveProductModal" tabindex="-1" aria-labelledby="archiveProductModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header border-bottom px-4 py-3">
                        <h5 class="modal-title fw-bold text-dark" id="archiveProductModalLabel">Archive product?</h5>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body px-4 py-4">
                        <p class="mb-0">This product will no longer be available to customers.</p>
                        <p class="small text-secondary mt-2 mb-0" id="archiveProductName"></p>
                    </div>
                    <div class="modal-footer border-top px-4 py-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-4"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger btn-sm px-4" id="confirmArchiveProductBtn">
                            <i class="bi bi-archive me-1" aria-hidden="true"></i>Archive product
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom JavaScript file -->
        <script src="backend/admin.js?v=product-archive-workflow-1"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="backend/theme.js?v=theme-header-order-2"></script>
</body>

</html>
