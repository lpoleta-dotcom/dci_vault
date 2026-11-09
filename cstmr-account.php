<?php
require_once 'customer-session.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);

$customerStmt = $conn->prepare(
    'SELECT first_name, middle_name, last_name, username, contact_no, street, barangay, city, province, postal, password
     FROM users WHERE user_id = ?'
);
if ($customerStmt === false) {
    die('Account query prepare error: ' . $conn->error);
}
$customerStmt->bind_param('i', $userId);
$customerStmt->execute();
$customer = $customerStmt->get_result()->fetch_assoc();
$customerStmt->close();

if (!$customer) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit();
}

$accountError = '';
$csrfToken = $_SESSION['account_csrf'] ?? '';
if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['account_csrf'] = $csrfToken;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        $accountError = 'Your session expired. Refresh the page and try again.';
    } else {
        $action = $_POST['account_action'] ?? '';

        if ($action === 'save_profile') {
            $firstName = trim($_POST['first_name'] ?? '');
            $middleName = trim($_POST['middle_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $contact = trim($_POST['contact_no'] ?? '');
            $street = trim($_POST['street'] ?? '');
            $barangay = trim($_POST['barangay'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $province = trim($_POST['province'] ?? '');
            $postal = trim($_POST['postal'] ?? '');

            if ($firstName === '' || $lastName === '' || $username === '') {
                $accountError = 'First name, last name, and username are required.';
            } else {
                $duplicateStmt = $conn->prepare('SELECT user_id FROM users WHERE username = ? AND user_id <> ?');
                if ($duplicateStmt === false) {
                    die('Username check prepare error: ' . $conn->error);
                }
                $duplicateStmt->bind_param('si', $username, $userId);
                $duplicateStmt->execute();
                $duplicate = $duplicateStmt->get_result()->num_rows > 0;
                $duplicateStmt->close();

                if ($duplicate) {
                    $accountError = 'That username is already in use.';
                } else {
                    $updateStmt = $conn->prepare(
                        'UPDATE users
                         SET first_name = ?, middle_name = ?, last_name = ?, username = ?, contact_no = ?,
                             street = ?, barangay = ?, city = ?, province = ?, postal = ?
                         WHERE user_id = ?'
                    );
                    if ($updateStmt === false) {
                        die('Profile update prepare error: ' . $conn->error);
                    }
                    $updateStmt->bind_param(
                        'ssssssssssi',
                        $firstName,
                        $middleName,
                        $lastName,
                        $username,
                        $contact,
                        $street,
                        $barangay,
                        $city,
                        $province,
                        $postal,
                        $userId
                    );
                    if (!$updateStmt->execute()) {
                        die('Profile update error: ' . $updateStmt->error);
                    }
                    $updateStmt->close();
                    $_SESSION['username'] = $username;
                    header('Location: cstmr-account.php?updated=profile');
                    exit();
                }
            }
        } elseif ($action === 'upload_avatar') {
            $upload = $_FILES['avatar'] ?? null;
            if (!$upload || $upload['error'] !== UPLOAD_ERR_OK) {
                $accountError = 'Select a profile photo to upload.';
            } elseif ($upload['size'] > 5 * 1024 * 1024) {
                $accountError = 'Choose an image smaller than 5 MB.';
            } else {
                $imageInfo = getimagesize($upload['tmp_name']);
                $avatarTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];
                $mime = $imageInfo['mime'] ?? '';
                if (!$imageInfo || !isset($avatarTypes[$mime])) {
                    $accountError = 'Choose a valid JPG, PNG, or WebP image.';
                } else {
                    $avatarDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
                    if (!is_dir($avatarDirectory) && !mkdir($avatarDirectory, 0755, true)) {
                        die('Unable to create the profile photo directory.');
                    }
                    $extension = $avatarTypes[$mime];
                    $avatarFilename = 'user_' . $userId . '.' . $extension;
                    $avatarPath = $avatarDirectory . DIRECTORY_SEPARATOR . $avatarFilename;
                    if (!move_uploaded_file($upload['tmp_name'], $avatarPath)) {
                        die('Unable to save the profile photo.');
                    }
                    foreach (array_values($avatarTypes) as $oldExtension) {
                        if ($oldExtension !== $extension) {
                            $oldAvatar = $avatarDirectory . DIRECTORY_SEPARATOR . 'user_' . $userId . '.' . $oldExtension;
                            if (is_file($oldAvatar) && !unlink($oldAvatar)) {
                                die('Unable to replace the previous profile photo.');
                            }
                        }
                    }
                    header('Location: cstmr-account.php?updated=avatar');
                    exit();
                }
            }
        } elseif ($action === 'remove_avatar') {
            $avatarDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
            foreach (['jpg', 'png', 'webp'] as $extension) {
                $avatarPath = $avatarDirectory . DIRECTORY_SEPARATOR . 'user_' . $userId . '.' . $extension;
                if (is_file($avatarPath) && !unlink($avatarPath)) {
                    die('Unable to remove the profile photo.');
                }
            }
            header('Location: cstmr-account.php?updated=avatar_removed');
            exit();
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            if (!password_verify($currentPassword, $customer['password'])) {
                $accountError = 'Your current password is incorrect.';
            } elseif (strlen($newPassword) < 8) {
                $accountError = 'Use at least 8 characters for the new password.';
            } elseif ($newPassword !== $confirmPassword) {
                $accountError = 'The new passwords do not match.';
            } else {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $passwordStmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
                if ($passwordStmt === false) {
                    die('Password update prepare error: ' . $conn->error);
                }
                $passwordStmt->bind_param('si', $passwordHash, $userId);
                if (!$passwordStmt->execute()) {
                    die('Password update error: ' . $passwordStmt->error);
                }
                $passwordStmt->close();
                header('Location: cstmr-account.php?updated=password');
                exit();
            }
        } else {
            $accountError = 'Unknown account action.';
        }
    }

    if ($accountError !== '' && ($_POST['account_action'] ?? '') === 'save_profile') {
        $customer = array_merge($customer, [
            'first_name' => $_POST['first_name'] ?? $customer['first_name'],
            'middle_name' => $_POST['middle_name'] ?? $customer['middle_name'],
            'last_name' => $_POST['last_name'] ?? $customer['last_name'],
            'username' => $_POST['username'] ?? $customer['username'],
            'contact_no' => $_POST['contact_no'] ?? $customer['contact_no'],
            'street' => $_POST['street'] ?? $customer['street'],
            'barangay' => $_POST['barangay'] ?? $customer['barangay'],
            'city' => $_POST['city'] ?? $customer['city'],
            'province' => $_POST['province'] ?? $customer['province'],
            'postal' => $_POST['postal'] ?? $customer['postal']
        ]);
    }
}

$customerName = trim($customer['first_name'] . ' ' . $customer['last_name']);
$avatarUrl = $customerAvatarUrl;
$updatedMessages = [
    'profile' => 'Account details saved.',
    'avatar' => 'Profile photo updated.',
    'avatar_removed' => 'Profile photo removed.',
    'password' => 'Password updated.'
];
$accountMessage = $updatedMessages[$_GET['updated'] ?? ''] ?? '';
$escape = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DCI Vault | Account Settings</title>

    <!-- Bootstrap 5.3.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom Stylesheet -->
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

<!-- Russian Nesting Doll Layout -->

<body class="customer-page customer-account-page vh-100 overflow-hidden d-flex flex-column bg-light">

    <!-- HEADER CONTAINER -->
    <header
        class="main-header d-flex flex-row align-items-center justify-content-between flex-shrink-0 px-4 py-2 gap-3 bg-white border-bottom">

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
            <a class="nav-link px-3 py-2 rounded text-secondary hover-dark" href="cstmr-order.php">
                <i class="bi bi-bag-check me-1"></i>My Orders
            </a>
            <a class="nav-link px-3 py-2 rounded fw-semibold active text-white" href="cstmr-account.php"
                style="background-color: var(--contrast-primary, #2563EB);">
                <i class="bi bi-person me-1"></i>Account
            </a>
        </nav>

        <!-- Customer Profile / Account Section & Logout -->
        <div class="d-flex align-items-center gap-3">
            <div class="user-profile d-flex align-items-center gap-2">
                <div class="d-flex flex-column text-end lh-sm">
                    <a id="userName" data-session-managed="true" class="mb-0 fs-6 fw-bold text-decoration-none text-dark"
                        href="cstmr-account.php"><?php echo $escape($customer['username']); ?></a>
                    <small id="userStatus" class="text-secondary">Active Shopper</small>
                </div>
                <img id="empAvatar" data-session-managed="true" src="<?php echo $escape($avatarUrl); ?>" alt="User Avatar"
                    class="user-avatar rounded-circle object-fit-cover" width="40" height="40">
            </div>

            <!-- Logout Button Icon / Link -->
            <a id="logoutBtn" class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1 px-2 py-1"
                href="logout.php" title="Log Out">
                <i class="bi bi-box-arrow-right fs-6"></i>
                <span class="d-none d-lg-inline">Log Out</span>
            </a>
        </div>
    </header>

    <!-- MAIN APP WRAPPER -->
    <div class="d-flex flex-row flex-grow-1 overflow-hidden">

        <!-- ACCOUNT SETTINGS PANEL (Scrollable Area) -->
        <main class="main-control customer-main flex-grow-1 d-flex flex-column overflow-y-auto p-4">

            <?php if ($accountError !== '' || $accountMessage !== ''): ?>
                <div class="alert <?php echo $accountError !== '' ? 'alert-danger' : 'alert-success'; ?>" role="status">
                    <?php echo $escape($accountError !== '' ? $accountError : $accountMessage); ?>
                </div>
            <?php endif; ?>

            <!-- Section Header -->
            <div class="mb-4">
                <span class="customer-eyebrow">YOUR PROFILE</span>
                <h2 class="h3 fw-bold text-dark m-0">Account Settings</h2>
                <p class="customer-page-intro mb-0 mt-1">Manage your personal details and security preferences.
                </p>
            </div>

            <!-- Settings Grid Layout -->
            <div class="row g-4 pb-4">

                <!-- Left Column: Profile Picture & Basic Info -->
                <div class="col-lg-4">
                    <div class="card customer-card customer-profile-card border-0 shadow-sm p-4 text-center" style="border-radius: var(--prod-rd, 1rem);">
                        <img id="accountAvatarPreview" data-session-managed="true" src="<?php echo $escape($avatarUrl); ?>" alt="Profile Avatar"
                            class="rounded-circle mx-auto mb-3 object-fit-cover shadow-sm"
                            style="width: 110px; height: 110px;">
                        <h5 class="fw-bold mb-1"><?php echo $escape($customerName !== '' ? $customerName : $customer['username']); ?></h5>
                        <p class="text-secondary small mb-3">Customer Account</p>
                        <form id="avatarForm" data-server-managed="true" action="cstmr-account.php" method="post" enctype="multipart/form-data" class="text-start">
                            <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                            <input type="hidden" name="account_action" value="upload_avatar">
                            <label for="accountAvatarInput" class="form-label small fw-semibold text-secondary">Profile photo</label>
                            <input type="file" id="accountAvatarInput" class="form-control form-control-sm shadow-none"
                                name="avatar" accept="image/png,image/jpeg,image/webp" required>
                            <div class="form-text">Choose a JPG, PNG, or WebP image up to 5 MB.</div>
                            <button type="submit" class="btn btn-primary btn-sm mt-2">Upload photo</button>
                        </form>
                        <form action="cstmr-account.php" method="post" class="text-start">
                            <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                            <input type="hidden" name="account_action" value="remove_avatar">
                            <button type="submit" id="removeAvatarBtn" class="btn btn-link btn-sm text-danger text-decoration-none px-0 mt-1<?php echo $avatarUrl === 'assets/user.jpg' ? ' d-none' : ''; ?>">
                                Remove profile photo
                            </button>
                        </form>
                    </div>

                    <section class="card customer-card appearance-settings-card border-0 shadow-sm p-4 mt-4" aria-labelledby="appearanceSettingsTitle" style="border-radius: var(--prod-rd, 1rem);">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <span class="appearance-settings-icon" aria-hidden="true"><i class="bi bi-palette2"></i></span>
                            <div>
                                <h5 id="appearanceSettingsTitle" class="fw-bold mb-1">Appearance</h5>
                                <p class="text-secondary small mb-0">Choose how DCI Vault looks on this device.</p>
                            </div>
                        </div>
                        <div class="btn-group w-100" role="group" aria-label="Choose light or dark mode">
                            <button type="button" class="btn btn-outline-secondary" data-theme-choice="light" aria-pressed="false">
                                <i class="bi bi-sun me-1" aria-hidden="true"></i>Light
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-theme-choice="dark" aria-pressed="false">
                                <i class="bi bi-moon-stars me-1" aria-hidden="true"></i>Dark
                            </button>
                        </div>
                        <small id="themePreferenceStatus" class="text-secondary mt-2" role="status" aria-live="polite"></small>
                    </section>
                </div>

                <!-- Right Column: Personal Details & Password Form -->
                <div class="col-lg-8">

                    <!-- Personal Information Card -->
                    <div class="card customer-card border-0 shadow-sm p-4 mb-4" style="border-radius: var(--prod-rd, 1rem);">
                        <h5 class="fw-bold mb-3">Personal Information</h5>

                        <form id="accountForm" data-server-managed="true" action="cstmr-account.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                            <input type="hidden" name="account_action" value="save_profile">
                            <!-- Full Name Fields -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">Full Name</label>
                                <div class="row g-2">
                                    <div class="col-4">
                                        <input type="text" class="form-control shadow-none" id="accountFn"
                                            name="first_name" placeholder="First Name" value="<?php echo $escape($customer['first_name']); ?>" required>
                                    </div>
                                    <div class="col-4">
                                        <input type="text" class="form-control shadow-none" id="accountMn"
                                            name="middle_name" placeholder="Middle Name" value="<?php echo $escape($customer['middle_name']); ?>">
                                    </div>
                                    <div class="col-4">
                                        <input type="text" class="form-control shadow-none" id="accountLn"
                                            name="last_name" placeholder="Last Name" value="<?php echo $escape($customer['last_name']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Username & Contact Number -->
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">Username</label>
                                    <input type="text" class="form-control shadow-none" id="accountUsername"
                                        name="username" value="<?php echo $escape($customer['username']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">Contact Number</label>
                                    <input type="tel" class="form-control shadow-none" id="accountContact"
                                        name="contact_no" value="<?php echo $escape($customer['contact_no']); ?>">
                                </div>
                            </div>

                            <!-- Structured Address Fields -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">Address Details</label>

                                <!-- Row 1: House/Unit & Street -->
                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <input type="text" class="form-control shadow-none" id="accountStreet"
                                            name="street" placeholder="House / Unit No., Street" value="<?php echo $escape($customer['street']); ?>">
                                    </div>
                                </div>

                                <!-- Row 2: Barangay & City/Municipality -->
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <input type="text" class="form-control shadow-none" id="accountBarangay"
                                            name="barangay" placeholder="Barangay" value="<?php echo $escape($customer['barangay']); ?>">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" class="form-control shadow-none" id="accountCity"
                                            name="city" placeholder="City / Municipality" value="<?php echo $escape($customer['city']); ?>">
                                    </div>
                                </div>

                                <!-- Row 3: Province & Postal Code -->
                                <div class="row g-2">
                                    <div class="col-8">
                                        <input type="text" class="form-control shadow-none" id="accountProvince"
                                            name="province" placeholder="Province" value="<?php echo $escape($customer['province']); ?>">
                                    </div>
                                    <div class="col-4">
                                        <input type="text" class="form-control shadow-none" id="accountZip"
                                            name="postal" placeholder="Postal Code" value="<?php echo $escape($customer['postal']); ?>">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn text-white fw-semibold px-4 shadow-sm"
                                style="background-color: var(--contrast-primary, #2563EB);">
                                Save Changes
                            </button>
                        </form>
                    </div>

                    <!-- Security / Change Password Card -->
                    <div class="card customer-card border-0 shadow-sm p-4" style="border-radius: var(--prod-rd, 1rem);">
                        <h5 class="fw-bold mb-3">Change Password</h5>

                        <form id="passwordForm" data-server-managed="true" action="cstmr-account.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                            <input type="hidden" name="account_action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">Current Password</label>
                                <input type="password" class="form-control shadow-none" id="currentPassword"
                                    name="current_password" placeholder="Enter current password" required>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">New Password</label>
                                    <input type="password" class="form-control shadow-none" id="newPassword"
                                        name="new_password" placeholder="Enter new password" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">Confirm New
                                        Password</label>
                                    <input type="password" class="form-control shadow-none" id="confirmPassword"
                                        name="confirm_password" placeholder="Confirm new password" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-dark fw-semibold px-4">
                                Update Password
                            </button>
                        </form>
                    </div>

                </div>

            </div>
        </main>
    </div>

    <!-- Custom JavaScript file -->
    <script src="backend/cstmr.js?v=order-max-99"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="backend/theme.js?v=6"></script>
</body>

</html>
