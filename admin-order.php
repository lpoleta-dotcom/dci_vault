<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-session.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DCI Vault | Manage Orders</title>

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
            } catch (error) { }
        })();
    </script>
</head>

<!-- Russian Nesting Doll Layout -->

<body class="admin-page admin-orders-page vh-100 overflow-hidden d-flex flex-column bg-light">

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
            <!-- Administrator Profile Section -->
            <?php $notificationRole = 'admin';
            require __DIR__ . DIRECTORY_SEPARATOR . 'notification-widget.php'; ?>

            <div class="user-profile d-flex align-items-center gap-2">
                <div class="d-flex flex-column text-end lh-sm">
                    <!-- PHP INJECTION: Dynamically output admin name -->
                    <h4 id="adminNameDisplay" class="mb-0 fs-6 fw-bold text-dark">
                        <?php echo htmlspecialchars($adminSessionName, ENT_QUOTES, 'UTF-8'); ?></h4>
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
            aria-label="Admin navigation" style="width: 260px;">
            <div class="d-flex flex-column gap-2">
                <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white rounded"
                    href="admin-dashboard.php">
                    <i class="bi bi-speedometer2 fs-5"></i>Dashboard
                </a>
                <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white rounded" href="admin-sales.php">
                    <i class="bi bi-graph-up-arrow fs-5"></i>Sales
                </a>
                <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white rounded" href="admin-prod.php">
                    <i class="bi bi-tag fs-5"></i>Products
                </a>
                <!-- Active page highlight -->
                <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white rounded active fw-semibold"
                    href="admin-order.php" aria-current="page">
                    <i class="bi bi-bag-check fs-5"></i>Orders
                </a>
                <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white rounded" href="admin-users.php">
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

        <!-- MANAGE ORDERS CONTENT PANEL (Scrollable Area) -->
        <main class="main-control admin-main flex-grow-1 d-flex flex-column overflow-y-auto p-4">

            <!-- Section Header & Filter Pills -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <span class="admin-eyebrow">FULFILLMENT</span>
                    <h2 class="h3 fw-bold text-dark m-0">Manage Orders</h2>
                    <p class="text-secondary small m-0">Monitor customer purchases and update fulfillment statuses.</p>
                </div>

                <div class="admin-order-controls d-flex flex-column flex-lg-row align-items-lg-center gap-2">
                    <label class="visually-hidden" for="adminOrderSearch">Search orders or customers</label>
                    <input id="adminOrderSearch" class="form-control form-control-sm" type="search"
                        placeholder="Search order, name, or username" style="min-width: 240px;" autocomplete="off">
                    <!-- Status Filter Pills -->
                    <ul class="nav nav-pills admin-order-filter gap-1 bg-white p-1 rounded shadow-sm border"
                        id="adminOrderFilterTab" role="tablist" aria-label="Filter orders by status">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active btn-sm px-3 py-1 text-white shadow-sm" id="admin-all-tab"
                                data-bs-toggle="pill" type="button"
                                style="background-color: var(--contrast-primary, #2563EB);">All</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link btn-sm px-3 py-1 text-dark" id="admin-processing-tab"
                                data-bs-toggle="pill" type="button">Processing</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link btn-sm px-3 py-1 text-dark" id="admin-completed-tab"
                                data-bs-toggle="pill" type="button">Completed</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link btn-sm px-3 py-1 text-dark" id="admin-cancelled-tab"
                                data-bs-toggle="pill" type="button">Cancelled</button>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Orders Management Table Container -->
            <div class="card border-0 shadow-sm flex-grow-1 overflow-hidden bg-white"
                style="border-radius: var(--prod-rd, 0.75rem);">
                <div class="table-responsive flex-grow-1 m-0">
                    <table class="table align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase">
                            <tr>
                                <th class="ps-4">Order ID</th>
                                <th>Customer Name</th>
                                <th>Date Placed</th>
                                <th>Total Amount</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="adminOrdersTableBody">
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-4">Loading orders…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- Admin Order Details Modal -->
    <div class="modal fade" id="adminOrderDetailsModal" tabindex="-1" aria-labelledby="adminOrderDetailsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg modal-custom-content">
                <div class="modal-header border-bottom px-4 py-3">
                    <h5 class="modal-title fw-bold text-dark" id="adminOrderDetailsModalLabel">Order Review (<span
                            id="adminModalOrderId">#ORD-000</span>)</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <!-- Customer & Order Meta -->
                    <div class="row g-3 mb-4 bg-light p-3 rounded mx-0 border">
                        <div class="col-sm-6 col-lg-4">
                            <small class="text-secondary d-block">Customer</small>
                            <span id="adminModalCustomerName" class="fw-semibold text-dark">John Doe</span>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <small class="text-secondary d-block">Contact</small>
                            <span id="adminModalCustomerContact" class="fw-semibold text-dark">09123456789</span>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <small class="text-secondary d-block">Payment Method</small>
                            <span id="adminModalPayment" class="fw-semibold text-dark">GCash</span>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <small class="text-secondary d-block">Date Placed</small>
                            <span id="adminModalDatePlaced" class="fw-semibold text-dark">Oct 28, 2026</span>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <small class="text-secondary d-block">Current Status</small>
                            <span id="adminModalStatusBadge"
                                class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 rounded-pill">Processing</span>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <h6 class="fw-bold mb-3 text-dark">Order Items</h6>
                    <div class="table-responsive mb-4">
                        <table class="table align-middle mb-0">
                            <thead class="table-light text-secondary small text-uppercase">
                                <tr>
                                    <th>Product</th>
                                    <th>Size</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="adminModalItemsTable">
                                <tr>
                                    <td class="fw-semibold text-dark">Classic T-Shirt</td>
                                    <td>M</td>
                                    <td>₱250.00</td>
                                    <td>2</td>
                                    <td class="text-end fw-semibold text-dark">₱500.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary Totals -->
                    <div class="d-flex justify-content-end">
                        <div style="width: 260px;" class="d-flex flex-column gap-2">
                            <div class="d-flex justify-content-between text-secondary small">
                                <span>Subtotal</span>
                                <span id="adminModalSubtotal" class="fw-semibold text-dark">₱500.00</span>
                            </div>
                            <div class="d-flex justify-content-between text-secondary small">
                                <span>VAT included in prices</span>
                                <span id="adminModalVat" class="fw-semibold text-dark">₱53.58</span>
                            </div>
                            <div class="d-flex justify-content-between text-secondary small">
                                <span>Shipping Fee</span>
                                <span id="adminModalShipping" class="fw-semibold text-dark">₱30.00</span>
                            </div>
                            <hr class="text-secondary opacity-25 my-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark">Total Due</span>
                                <span id="adminModalTotal" class="fw-bold fs-5"
                                    style="color: var(--contrast-primary, #2563EB);">₱530.00</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-4"
                        data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="adminOrderStatusErrorModal" tabindex="-1" aria-labelledby="adminOrderStatusErrorTitle"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h2 class="modal-title fs-5 fw-bold" id="adminOrderStatusErrorTitle">Unable to update order</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="adminOrderStatusErrorMessage" class="mb-0" role="alert" aria-live="assertive"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark px-4" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom JavaScript file -->
    <script src="backend/admin.js?v=notification-targets-3"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="backend/theme.js?v=theme-header-order-2"></script>
</body>

</html>