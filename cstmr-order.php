<?php require_once 'customer-session.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DCI Vault | My Orders</title>

    <!-- Bootstrap 5.3.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="style/style.css?v=customer-nav-3">
    <meta name="csrf-token" content="<?php echo $customerEscape($customerCsrfToken); ?>">
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
<body class="customer-page customer-orders-page vh-100 overflow-hidden d-flex flex-column bg-light"
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
            <a class="nav-link px-3 py-2 rounded text-secondary hover-dark" href="cstmr-prod.php">
                <i class="bi bi-tag me-1"></i>Products
            </a>
            <a class="nav-link px-3 py-2 rounded text-secondary hover-dark" href="cstmr-cart.php">
                <i class="bi bi-cart me-1"></i>My Cart
            </a>
            <a class="nav-link px-3 py-2 rounded fw-semibold active text-white" href="cstmr-order.php" style="background-color: var(--contrast-primary, #2563EB);">
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
                    <span id="userName" data-session-managed="true" class="customer-profile-name"><?php echo $customerEscape($customerHeaderUsername); ?></span>
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

    <!-- MAIN APP WRAPPER -->
    <div class="d-flex flex-row flex-grow-1 overflow-hidden">

        <!-- ORDERS PANEL (Scrollable Area) -->
        <main class="main-control customer-main flex-grow-1 d-flex flex-column overflow-y-auto p-4">

            <!-- Section Header & Filter Pills -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <span class="customer-eyebrow">YOUR PURCHASES</span>
                    <h2 class="h3 fw-bold text-dark m-0">My Orders</h2>
                    <p class="customer-page-intro mb-0 mt-1">Keep track of your latest and past orders.</p>
                </div>

                <!-- Customer Order Status Filter Tabs -->
                <ul class="nav nav-pills flex-nowrap gap-1 bg-white p-1 customer-filter" id="orderFilterTab" role="tablist" aria-label="Filter orders by status">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active btn-sm px-3 py-1" id="all-tab" data-bs-toggle="pill"
                            type="button">All</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link btn-sm px-3 py-1 text-dark" id="processing-tab" data-bs-toggle="pill"
                            type="button">Processing</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link btn-sm px-3 py-1 text-dark" id="completed-tab" data-bs-toggle="pill"
                            type="button">Completed</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link btn-sm px-3 py-1 text-dark" id="cancelled-tab" data-bs-toggle="pill"
                            type="button">Cancelled</button>
                    </li>
                </ul>
            </div>

            <!-- Customer Orders List Container -->
            <div class="d-flex flex-column gap-3" id="orderListContainer">
            </div>
        </main>
    </div>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg modal-custom-content">

                <!-- Modal Header -->
                <div class="modal-header border-bottom px-4 py-3">
                    <h5 class="modal-title fw-bold text-dark" id="orderDetailsModalLabel">Order Details (<span
                            id="modalOrderId">#ORD-000</span>)</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body px-4 py-4">

                    <!-- Metadata Row -->
                    <div class="row g-3 mb-4 bg-light p-3 rounded mx-0 border">
                        <div class="col-sm-4">
                            <small class="text-secondary d-block">Date Placed</small>
                            <span id="modalOrderDate" class="fw-semibold text-dark">Oct 26, 2026</span>
                        </div>
                        <div class="col-sm-4">
                            <small class="text-secondary d-block">Status</small>
                            <span id="modalOrderstatus" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3">Processing</span>
                        </div>
                        <div class="col-sm-4">
                            <small class="text-secondary d-block">Payment Method</small>
                            <span id="modalOrderPayment" class="fw-semibold text-dark">GCash</span>
                        </div>
                    </div>

                    <!-- Itemized List Table -->
                    <h6 class="fw-bold mb-3 text-dark">Purchased Items</h6>
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
                            <tbody id="modalItemsTableBody">
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary Breakdown -->
                    <div class="d-flex justify-content-end">
                        <div style="width: 260px;" class="d-flex flex-column gap-2">
                            <div class="d-flex justify-content-between text-secondary small">
                                <span>Subtotal</span>
                                <span id="modalSubtotal" class="fw-semibold text-dark">P500.00</span>
                            </div>
                            <div class="d-flex justify-content-between text-secondary small">
                                <span>VAT included in prices</span>
                                <span id="modalVat" class="fw-semibold text-dark">P53.58</span>
                            </div>
                            <div class="d-flex justify-content-between text-secondary small">
                                <span>Shipping Fee</span>
                                <span id="modalShipping" class="fw-semibold text-dark">P30.00</span>
                            </div>
                            <hr class="text-secondary opacity-25 my-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark">Total Amount</span>
                                <span id="modalTotal" class="fw-bold fs-5" style="color: var(--contrast-primary, #2563EB);">P530.00</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer border-top px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-4"
                        data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Order Confirmation Modal -->
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-dark" id="cancelOrderModalLabel">Cancel this order?</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0 text-secondary">Are you sure you want to cancel <span id="cancelOrderNumber"
                            class="fw-semibold text-dark">this order</span>? This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep Order</button>
                    <button type="button" class="btn btn-danger" id="confirmCancelOrderBtn">Cancel Order</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom JavaScript file -->
    <script src="backend/cstmr.js?v=customer-reset-state-1"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="backend/theme.js?v=theme-header-order-2"></script>
</body>

</html>
