<?php require_once 'customer-session.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $customerEscape($customerCsrfToken); ?>">
    <title>DCI Vault | My Cart</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style/style.css?v=cart-empty-modal-1">
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

        <!-- CART & CHECKOUT PANEL -->
        <main class="main-control customer-main flex-grow-1 d-flex flex-column overflow-y-auto p-4">

            <!-- Section Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <span class="customer-eyebrow">ALMOST YOURS</span>
                    <h2 class="h3 fw-bold text-dark m-0">Shopping Cart</h2>
                    <p class="customer-page-intro mb-0 mt-1">Select the items you want to order together and pay shipping once.</p>
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
                                        <th class="ps-3" style="width: 46px;">
                                            <input id="selectAllCartItems" class="form-check-input m-0" type="checkbox"
                                                aria-label="Select all cart items">
                                        </th>
                                        <th>Product</th>
                                        <th>Size</th>
                                        <th>Price</th>
                                        <th style="width: 120px;">Qty</th>
                                        <th>Subtotal</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="cartTableBody">
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
                            <div id="selectedItemsSummary" class="d-flex justify-content-between mb-2 text-secondary small d-none">
                                <span>Selected items</span>
                                <span id="summarySelectedCount" class="fw-semibold">0</span>
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
                                    <?php if ($customerVerificationStatus === 'Approved'): ?>
                                        <option value="cod">Cash on Delivery / Pickup</option>
                                    <?php else: ?>
                                        <option value="cod" disabled>Cash on Delivery / Pickup (ID approval required)</option>
                                    <?php endif; ?>
                                </select>
                                <?php if ($customerVerificationStatus !== 'Approved'): ?>
                                    <small class="form-text text-secondary d-block mt-1">
                                        <?php if ($customerVerificationStatus === 'Rejected'): ?>
                                            Your ID was not approved. Submit a replacement from <a href="cstmr-account.php">Account Settings</a>, or choose another payment method.
                                        <?php else: ?>
                                            Cash on Delivery will be available after an administrator approves your ID. You can check your status in <a href="cstmr-account.php">Account Settings</a>.
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                                <small id="quantityLimitHint" class="form-text text-secondary" aria-live="polite">0 selected items.</small>
                            </div>
                        </div>

                        <button id="placeOrderBtn" class="button w-100 py-2">
                            Proceed to Checkout
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="modal fade" id="emptyCartModal" tabindex="-1" aria-labelledby="emptyCartModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="emptyCartModalTitle">Empty your cart?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-secondary">
                    This will remove all items from your cart. This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep my items</button>
                    <button type="button" id="confirmEmptyCartBtn" class="btn btn-danger">Empty Cart</button>
                </div>
            </div>
        </div>
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

    <script src="backend/cstmr.js?v=cart-id-verification-1"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="backend/theme.js?v=theme-header-order-2"></script>
</body>

</html>
