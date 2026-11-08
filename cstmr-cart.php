<?php require_once 'customer-session.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DCI Vault | My Cart</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style/style.css?v=dark-all-5">
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

<body class="customer-page customer-cart-page vh-100 overflow-hidden d-flex flex-column bg-light">

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
            <a class="nav-link px-3 py-2 rounded fw-semibold active text-white" href="cstmr-cart.php"  style="background-color: var(--contrast-primary, #2563EB);">
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

        <!-- CART & CHECKOUT PANEL -->
        <main class="main-control customer-main flex-grow-1 d-flex flex-column overflow-y-auto p-4">

            <!-- Section Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <span class="customer-eyebrow">ALMOST YOURS</span>
                    <h2 class="h3 fw-bold text-dark m-0">Shopping Cart</h2>
                    <p class="customer-page-intro mb-0 mt-1">Review your picks before you check out.</p>
                </div>
                <!-- JS TARGET: Click event to clear entire cart -->
                <button id="clearCartBtn" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash me-1"></i> Empty Cart
                </button>
            </div>

            <!-- Two-Column Checkout Layout -->
            <div class="row g-4 flex-grow-1">

                <!-- Left: Itemized Cart Table -->
                <div class="col-lg-8 d-flex flex-column">
                    <div class="card customer-card border-0 shadow-sm flex-grow-1 overflow-hidden"
                        style="border-radius: var(--prod-rd, 1rem);">
                        <div class="table-responsive flex-grow-1 m-0">
                            <table class="table align-middle mb-0">
                                <thead class="table-light text-secondary small text-uppercase">
                                    <tr>
                                        <th class="ps-4">Product</th>
                                        <th>Size</th>
                                        <th>Price</th>
                                        <th style="width: 120px;">Qty</th>
                                        <th>Subtotal</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <!-- JS TARGET / PHP INJECTION: Cart table body where items are looped or injected -->
                                <tbody id="cartTableBody">
                                    <!-- <?php 
                                    /* 
                                      PHP INJECTION EXAMPLE:
                                      foreach ($cartItems as $item) {
                                          echo '<tr>...item details...</tr>';
                                      }
                                    */ 
                                    ?> -->
                                    <!-- Static prototype row (Will be managed by JS/PHP) -->
                                    <tr data-product-id="1" data-base-price="250.00">
                                        <td class="ps-4">
                                            <div class="fw-semibold">Classic T-Shirt</div>
                                            <small class="text-muted">Product ID: 1</small>
                                        </td>
                                        <td>M</td>
                                        <td class="item-price">P250.00</td>
                                        <td>
                                            <!-- JS TARGET: Input change triggers subtotal & total recalculation -->
                                            <input type="number"
                                                class="form-control form-control-sm shadow-none item-qty" value="1"
                                                min="1" style="width: 70px;">
                                        </td>
                                        <td class="fw-semibold item-subtotal">P250.00</td>
                                        <td class="text-end pe-4">
                                            <!-- JS TARGET: Click event removes this item row -->
                                            <button class="btn btn-sm text-danger remove-item-btn"><i
                                                    class="bi bi-x-lg"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right: Summary & Digital Payment Panel -->
                <div class="col-lg-4 d-flex flex-column">
                    <div class="card customer-card customer-order-summary border-0 shadow-sm p-4 d-flex flex-column justify-content-between flex-grow-1"
                        style="border-radius: var(--prod-rd, 1rem);">
                        <div>
                            <h5 class="fw-bold mb-3">Order Summary</h5>

                            <div class="d-flex justify-content-between mb-2 text-secondary">
                                <span>Subtotal</span>
                                <!-- JS TARGET: Updated dynamically when items/quantities change -->
                                <span id="summarySubtotal" class="fw-semibold text-dark">P250.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2 text-secondary">
                                <span>VAT included in prices</span>
                                <span id="summaryVat" class="fw-semibold text-dark">P26.79</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2 text-secondary">
                                <span>Shipping Fee</span>
                                <!-- JS TARGET: Updated dynamically -->
                                <span id="summaryShip" class="fw-semibold text-dark">P30.00</span>
                            </div>
                            <hr class="text-secondary opacity-25">
                            <div class="d-flex justify-content-between mb-4">
                                <span class="h5 fw-bold text-dark m-0">Total Due</span>
                                <!-- JS TARGET: Updated dynamically -->
                                <span id="summaryTotal" class="h4 fw-bold text-primary m-0">P280.00</span>
                            </div>

                            <!-- Payment Method Selector -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-secondary small">Payment Method</label>
                                <!-- JS TARGET: Value read during checkout submission -->
                                <select id="paymentMethod" class="form-select shadow-none">
                                    <option value="gcash">GCash / Digital Wallet</option>
                                    <option value="card">Credit / Debit Card</option>
                                    <option value="cod">Cash on Delivery / Pickup</option>
                                </select>
                                <small id="quantityLimitHint" class="form-text text-secondary" aria-live="polite">0 of 99 items in this order.</small>
                            </div>
                        </div>

                        <!-- Prototype checkout saves the order locally; replace with the PHP order endpoint during integration. -->
                        <button id="placeOrderBtn" class="button w-100 py-2">
                            Place Order
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Checkout review and success dialog -->
    <div class="modal fade" id="confirmOrderModal" tabindex="-1" aria-labelledby="checkoutModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg checkout-modal">
                <div class="modal-header checkout-modal-header">
                    <div>
                        <span class="customer-eyebrow">SECURE CHECKOUT</span>
                        <h5 class="modal-title fw-bold text-dark mt-1" id="checkoutModalTitle">Review your order</h5>
                    </div>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="checkoutReview">
                        <p class="text-secondary small mb-3">Check your items and payment method before placing your order.</p>
                        <div id="checkoutItems" class="d-flex flex-column gap-3 mb-4"></div>
                        <div class="checkout-summary p-3 rounded-3">
                            <div class="d-flex justify-content-between gap-3 small mb-2">
                                <span class="text-secondary">Payment method</span>
                                <span id="checkoutPayment" class="fw-semibold text-end"></span>
                            </div>
                            <div class="d-flex justify-content-between gap-3 small mb-2">
                                <span class="text-secondary">Subtotal</span>
                                <span id="checkoutSubtotal" class="fw-semibold"></span>
                            </div>
                            <div class="d-flex justify-content-between gap-3 small mb-2">
                                <span class="text-secondary">VAT included in prices</span>
                                <span id="checkoutVat" class="fw-semibold"></span>
                            </div>
                            <div class="d-flex justify-content-between gap-3 small mb-3">
                                <span class="text-secondary">Shipping</span>
                                <span id="checkoutShipping" class="fw-semibold"></span>
                            </div>
                            <div class="d-flex justify-content-between gap-3 border-top pt-3">
                                <span class="fw-bold">Total due</span>
                                <span id="checkoutTotal" class="fw-bold fs-5 text-primary"></span>
                            </div>
                        </div>
                        <p class="text-secondary small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Your order is saved to your account when you confirm checkout.</p>
                    </div>
                    <div id="checkoutSuccess" class="text-center py-4 d-none">
                        <div class="checkout-success-icon mx-auto mb-3"><i class="bi bi-check-lg"></i></div>
                        <h5 class="fw-bold mb-2">Order placed successfully</h5>
                        <p class="text-secondary mb-1">Order <strong id="checkoutOrderId" class="text-dark"></strong></p>
                        <p class="text-secondary small mb-0">You can review its details in My Orders.</p>
                    </div>
                </div>
                <div class="modal-footer checkout-modal-footer">
                    <div id="checkoutReviewActions" class="d-flex flex-column-reverse flex-sm-row justify-content-end gap-2 w-100">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Back to cart</button>
                        <button type="button" id="confirmPlaceOrderBtn" class="btn btn-primary px-4 fw-semibold">
                            <i class="bi bi-bag-check me-1"></i>Confirm &amp; Place Order
                        </button>
                    </div>
                    <div id="checkoutSuccessActions" class="d-none w-100">
                        <a href="cstmr-order.php" class="btn btn-primary w-100 fw-semibold">View My Orders <i class="bi bi-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="backend/cstmr.js?v=order-max-99"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="backend/theme.js?v=6"></script>
</body>

</html>
