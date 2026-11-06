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
    <link rel="stylesheet" href="style/style.css">
</head>

<!-- Russian Nesting Doll Layout -->
<body class="customer-page customer-orders-page vh-100 overflow-hidden d-flex flex-column bg-light">

    <!-- HEADER CONTAINER WITH INTEGRATED NAVIGATION -->
    <header class="main-header d-flex flex-row align-items-center justify-content-between flex-shrink-0 px-4 py-2 gap-3 bg-white border-bottom">
        
        <!-- Brand Logo / Title -->
        <div class="main-name d-flex align-items-center gap-2">
            <img src="assets/logo.png" alt="DCI Vault" class="brand-logo">
            <span class="fw-bold fs-5 text-dark">DCI Vault</span>
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

        <!-- Customer Profile / Account Section & Logout -->
        <div class="d-flex align-items-center gap-3">
            <div class="user-profile d-flex align-items-center gap-2">
                <div class="d-flex flex-column text-end lh-sm">
                    <a id="userName" data-session-managed="true" class="mb-0 fs-6 fw-bold text-decoration-none text-dark" href="cstmr-account.php"><?php echo $customerEscape($customerHeaderUsername); ?></a>
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
                </ul>
            </div>

            <!-- Customer Orders List Container -->
            <div class="d-flex flex-column gap-3" id="orderListContainer">
                <?php 
                /* 
                  PHP INJECTION EXAMPLE:
                  foreach ($customerOrders as $order) {
                      // Render card dynamically for each order
                  }
                */ 
                ?>

                <!-- Order Card 1: Processing Status -->
                <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: var(--prod-rd, 0.75rem);">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center border-bottom pb-3 mb-3 gap-2">
                        <div>
                            <span class="fw-bold text-dark">Order #ORD-2026-001</span>
                            <span class="text-muted small ms-2">Placed on Oct 26, 2026</span>
                        </div>
                        <!-- Status Badge -->
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 align-self-start align-self-sm-center rounded-pill">Processing</span>
                    </div>

                    <!-- Item Preview Inside Order -->
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img src="assets/product.jpg" alt="Product" class="rounded object-fit-cover shadow-sm"
                            style="width: 65px; height: 65px;">
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-semibold text-dark">Classic T-Shirt</h6>
                            <small class="text-muted">Size: M | Qty: 2</small>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold text-dark">P500.00</span>
                        </div>
                    </div>

                    <!-- Card Footer: Total & Actions -->
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center pt-3 border-top gap-3">
                        <div>
                            <small class="text-secondary">Payment Method: <span class="fw-semibold text-dark">GCash</span></small>
                            <div class="h6 fw-bold mb-0 mt-1" style="color: var(--contrast-primary, #2563EB);">Total Amount: P530.00</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="view-order-btn btn text-white btn-sm px-3 shadow-sm fw-semibold" 
                                style="background-color: var(--primary-color, #0F172A);"
                                data-order-id="1"
                                data-order-date="Oct 26, 2026"
                                data-order-status="Processing"
                                data-order-payment="GCash"
                                data-order-subtotal="P500.00"
                                data-order-vat="P53.58"
                                data-order-shipping="P30.00"
                                data-order-total="P530.00">
                                View Details
                            </button>
                        </div>
                    </div>
                </div>
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
                                <!-- Dynamically injected via JS or PHP -->
                                <tr>
                                    <td class="fw-semibold text-dark">Classic T-Shirt</td>
                                    <td>M</td>
                                    <td>P250.00</td>
                                    <td>2</td>
                                    <td class="text-end fw-semibold text-dark">P500.00</td>
                                </tr>
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

    <!-- Custom JavaScript file -->
    <script src="backend/cstmr.js?v=vat-inclusive"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
