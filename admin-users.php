<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-session.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DCI Vault | Manage Users</title>

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

<body class="admin-page admin-users-page vh-100 overflow-hidden d-flex flex-column bg-light">

    <!-- HEADER CONTAINER -->
<header class="main-header d-flex flex-row align-items-center justify-content-between flex-shrink-0 px-4 py-2 bg-white border-bottom">
    <div class="main-name d-flex align-items-center gap-2">
        <a href="admin-dashboard.php">
            <img src="assets/logo.png" alt="DCI Vault" class="brand-logo rounded border">
        </a>
        <a href="admin-dashboard.php" class="text-decoration-none">
            <span class="fw-bold fs-5 text-dark">DCI Vault <span class="badge bg-primary bg-opacity-15 text-primary border border-primary border-opacity-25 ms-2 fs-6 px-2 py-1 rounded-pill">Admin</span></span>
        </a>
    </div>

    <div class="admin-header-actions">
        <!-- Administrator Profile Section -->
        <?php $notificationRole = 'admin'; require __DIR__ . DIRECTORY_SEPARATOR . 'notification-widget.php'; ?>

        <div class="user-profile d-flex align-items-center gap-2">
            <div class="d-flex flex-column text-end lh-sm">
                <!-- PHP INJECTION: Dynamically output admin name -->
                <h4 id="adminNameDisplay" class="mb-0 fs-6 fw-bold text-dark"><?php echo htmlspecialchars($adminSessionName, ENT_QUOTES, 'UTF-8'); ?></h4>
                <small id="adminPosDisplay" class="text-secondary">System Administrator</small>
            </div>
            <!-- PHP INJECTION: Dynamically output admin avatar -->
            <img id="adminAvatarDisplay" src="assets/user.jpg" alt="Admin Avatar" class="user-avatar rounded-circle object-fit-cover shadow-sm" width="40" height="40">
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
                    href="admin-dashboard.php"><i class="bi bi-speedometer2 fs-5"></i>Dashboard</a>
                <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white rounded" href="admin-sales.php"><i class="bi bi-graph-up-arrow fs-5"></i>Sales</a>
                <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white rounded"
                    href="admin-prod.php"><i class="bi bi-tag fs-5"></i>Products</a>
                <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white rounded"
                    href="admin-order.php"><i class="bi bi-bag-check fs-5"></i>Orders</a>
                <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-white rounded active fw-semibold"
                    href="admin-users.php" aria-current="page"><i class="bi bi-people fs-5"></i>Users</a>
            </div>
            <div class="border-top border-secondary pt-3">
                <a id="adminLogoutBtn" class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-danger rounded"
                    href="admin-logout.php" style="color: #dc3545 !important;">
                    <i class="bi bi-box-arrow-right fs-5 text-danger"></i>Log Out
                </a>
            </div>
        </nav>

        <!-- USERS CONTENT PANEL -->
        <main class="main-control admin-main flex-grow-1 d-flex flex-column overflow-y-auto p-4">

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <span class="admin-eyebrow">ACCESS & PEOPLE</span>
                    <h2 class="h3 fw-bold text-dark m-0">User Accounts</h2>
                    <p class="text-secondary small m-0">Create and organize customer and administrator accounts.
                    </p>
                </div>
                <button type="button" id="createAccountOpenBtn" class="btn fw-semibold px-4 shadow-sm text-white"
                    style="background-color: var(--contrast-primary, #2563EB);">
                    <i class="bi bi-person-plus me-1"></i>Create Account
                </button>
            </div>
     
            <div class="card border-0 shadow-sm flex-grow-1 overflow-hidden"
                style="border-radius: var(--prod-rd, 1rem);">
                <div class="border-bottom bg-white px-3 pt-3">
                    <div class="mb-3">
                        <label class="visually-hidden" for="usersSearch">Search accounts</label>
                        <input id="usersSearch" class="form-control form-control-sm" type="search"
                            placeholder="Search name, username, phone, or address" autocomplete="off">
                    </div>
                    <div class="nav nav-pills flex-wrap gap-2 mb-3" role="group" aria-label="Filter accounts by role">
                        <button class="nav-link active" type="button" aria-pressed="true"
                            data-role-filter="all">All Accounts <span id="allAccountCount" class="badge bg-light text-dark ms-1">0</span></button>
                        <button class="nav-link" type="button" aria-pressed="false"
                            data-role-filter="2">Administrators <span id="adminAccountCount" class="badge bg-light text-dark ms-1">0</span></button>
                        <button class="nav-link" type="button" aria-pressed="false"
                            data-role-filter="1">Customers <span id="customerAccountCount" class="badge bg-light text-dark ms-1">0</span></button>
                        <button class="nav-link" type="button" aria-pressed="false"
                            data-role-filter="inactive">Deactivated <span id="inactiveAccountCount" class="badge bg-light text-dark ms-1">0</span></button>
                        <button class="nav-link" type="button" aria-pressed="false"
                            data-role-filter="verification-pending">Pending ID Verification <span id="pendingVerificationCount" class="badge bg-light text-dark ms-1">0</span></button>
                    </div>
                </div>
                <div class="table-responsive flex-grow-1 m-0">
                    <table class="table align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase">
                            <tr>
                                <th class="ps-4">Full Name</th>
                                <th>Username</th>
                                <th>Contact No.</th>
                                <th>Address</th>
                                <th>Orders</th>
                                <th style="width: 160px;">Role</th>
                                <th>Account status</th>
                                <th>ID verification</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr><td colspan="9" class="text-center text-secondary py-4">Loading accounts…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <div class="modal fade" id="idDocumentModal" tabindex="-1" aria-labelledby="idDocumentModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h2 class="modal-title fs-5 fw-bold" id="idDocumentModalTitle">Customer ID submission</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="idDocumentPreview" class="img-fluid rounded" alt="Submitted customer ID" style="max-height: 70vh;">
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="accountStatusModal" tabindex="-1" aria-labelledby="accountStatusModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h2 class="modal-title fs-5 fw-bold" id="accountStatusModalTitle">Deactivate account?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="accountStatusModalMessage" class="mb-0">Their order history will be preserved.</p>
                    <p id="accountStatusError" class="text-danger small mt-3 mb-0" role="alert" aria-live="polite"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" id="accountStatusCancel" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="accountStatusConfirm" class="btn btn-danger">Deactivate account</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="customerPromotionModal" tabindex="-1" aria-labelledby="customerPromotionModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h2 class="modal-title fs-5 fw-bold" id="customerPromotionModalTitle">Promote customer to administrator?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">This permanently deletes all of the customer’s orders, including completed orders, and removes their ID verification image and customer notifications. Notifications linked to those orders will also be deleted. Stock reserved by Processing orders will be restored, and completed orders will no longer count toward sales reports. Continue?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="customerPromotionConfirm" class="btn btn-danger">Delete customer history and promote</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createAccountModal" tabindex="-1" aria-labelledby="createAccountModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <form id="createAccountForm">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title fs-5 fw-bold" id="createAccountModalLabel">Create Account</h2>
                            <p class="text-secondary small mb-0">Add a user and assign their role.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="newUserUsername">Username</label>
                                <input class="form-control" id="newUserUsername" name="username" autocomplete="username" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="newUserRole">Role</label>
                                <select class="form-select" id="newUserRole" name="role" required>
                                    <option value="1">Customer</option>
                                    <option value="2">Administrator</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="newUserFirstName">First name</label>
                                <input class="form-control" id="newUserFirstName" name="first_name" autocomplete="given-name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="newUserMiddleName">Middle name</label>
                                <input class="form-control" id="newUserMiddleName" name="middle_name" autocomplete="additional-name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="newUserLastName">Last name</label>
                                <input class="form-control" id="newUserLastName" name="last_name" autocomplete="family-name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="newUserPhone">Contact number</label>
                                <input class="form-control" id="newUserPhone" name="phone" type="tel" autocomplete="tel" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="newUserPassword">Password</label>
                                <input class="form-control" id="newUserPassword" name="password" type="password"
                                    autocomplete="new-password" minlength="8">
                                <div id="passwordHelp" class="form-text">Required for new accounts. Leave blank to keep the current password when editing.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="newUserConfirmPassword">Confirm password</label>
                                <input class="form-control" id="newUserConfirmPassword" name="confirm_password"
                                    type="password" autocomplete="new-password" minlength="8">
                            </div>
                            <div class="col-12"><div id="createAccountError" class="text-danger small" role="alert" aria-live="polite"></div></div>
                            <div class="col-12">
                                <label class="form-label" for="newUserStreet">Street address</label>
                                <input class="form-control" id="newUserStreet" name="street" autocomplete="street-address" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="newUserBarangay">Barangay</label>
                                <input class="form-control" id="newUserBarangay" name="barangay" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="newUserCity">City / Municipality</label>
                                <input class="form-control" id="newUserCity" name="city" autocomplete="address-level2" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="newUserProvince">Province</label>
                                <input class="form-control" id="newUserProvince" name="province" autocomplete="address-level1" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="newUserPostal">Postal code</label>
                                <input class="form-control" id="newUserPostal" name="postal" inputmode="numeric" autocomplete="postal-code" required>
                            </div>
                            <div class="col-12">
                                <div class="form-text">Address is required for customer accounts and optional for administrator accounts.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="createAccountSubmit" class="btn text-white fw-semibold"
                            style="background-color: var(--contrast-primary, #2563EB);">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="backend/admin-users.js?v=admin-customer-promotion-2"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="backend/theme.js?v=theme-header-order-2"></script>
</body>

</html>