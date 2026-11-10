<?php
require_once 'customer-session.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'id-verification.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);

$customerStmt = $conn->prepare(
    'SELECT first_name, middle_name, last_name, username, contact_no, street, barangay, city, province, postal, password,
            verification_status, id_document
     FROM users WHERE user_id = ?'
);
if ($customerStmt === false) {
    error_log('Customer account query prepare failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load account details.');
}
$customerStmt->bind_param('i', $userId);
if (!$customerStmt->execute()) {
    error_log('Customer account query failed: ' . $customerStmt->error);
    $customerStmt->close();
    http_response_code(500);
    exit('Unable to load account details.');
}
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
    $submittedToken = $_POST['csrf_token'] ?? '';
    $expectedOrigin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    $contentLength = $_SERVER['CONTENT_LENGTH'] ?? '';
    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken) ||
        empty($_SERVER['HTTP_ORIGIN']) ||
        $expectedOrigin === '://' ||
        !hash_equals($expectedOrigin, $_SERVER['HTTP_ORIGIN'])
    ) {
        $accountError = 'Your session expired. Refresh the page and try again.';
    } elseif (
        !in_array($contentType, ['application/x-www-form-urlencoded', 'multipart/form-data'], true) ||
        ($contentLength !== '' && (!ctype_digit($contentLength) || (int) $contentLength > 6291456))
    ) {
        http_response_code(400);
        $accountError = 'The account update request is invalid or too large.';
    } elseif (!isset($_POST['account_action']) || !is_string($_POST['account_action'])) {
        http_response_code(400);
        $accountError = 'Choose a valid account action.';
    } else {
        $action = $_POST['account_action'];

        if ($action === 'save_profile') {
            $profileFields = [
                'first_name', 'middle_name', 'last_name', 'username', 'contact_no',
                'street', 'barangay', 'city', 'province', 'postal'
            ];
            $profile = [];
            foreach ($profileFields as $field) {
                if (isset($_POST[$field]) && !is_string($_POST[$field])) {
                    $accountError = 'One or more account details are invalid.';
                    break;
                }
                $profile[$field] = trim($_POST[$field] ?? '');
            }

            $lengthLimits = [
                'first_name' => 100,
                'middle_name' => 100,
                'last_name' => 100,
                'username' => 50,
                'contact_no' => 20,
                'street' => 150,
                'barangay' => 100,
                'city' => 100,
                'province' => 100,
                'postal' => 10
            ];
            if ($accountError === '' && (
                $profile['first_name'] === '' ||
                $profile['last_name'] === '' ||
                $profile['username'] === '' ||
                $profile['contact_no'] === '' ||
                $profile['street'] === '' ||
                $profile['barangay'] === '' ||
                $profile['city'] === '' ||
                $profile['province'] === '' ||
                $profile['postal'] === ''
            )) {
                $accountError = 'Complete all required account details.';
            }
            if ($accountError === '') {
                foreach ($lengthLimits as $field => $limit) {
                    if (strlen($profile[$field]) > $limit) {
                        $accountError = 'One or more account details exceed the allowed length.';
                        break;
                    }
                }
            }
            if ($accountError === '' && !preg_match('/^[0-9+() .-]{7,20}$/', $profile['contact_no'])) {
                $accountError = 'Enter a valid contact number.';
            }
            if ($accountError === '' && !preg_match('/^[A-Za-z0-9 -]{1,10}$/', $profile['postal'])) {
                $accountError = 'Enter a valid postal code.';
            }

            if ($accountError === '') {
                $duplicateStmt = $conn->prepare('SELECT user_id FROM users WHERE username = ? AND user_id <> ?');
                if ($duplicateStmt === false) {
                    error_log('Customer username check prepare failed: ' . $conn->error);
                    $accountError = 'Unable to save your profile right now. Please try again.';
                } else {
                    $duplicateStmt->bind_param('si', $profile['username'], $userId);
                    if (!$duplicateStmt->execute()) {
                        error_log('Customer username check failed: ' . $duplicateStmt->error);
                        $accountError = 'Unable to save your profile right now. Please try again.';
                        $duplicateStmt->close();
                    } else {
                        $duplicate = $duplicateStmt->get_result()->num_rows > 0;
                        $duplicateStmt->close();
                    }
                }

                if ($accountError === '' && $duplicate) {
                    $accountError = 'That username is already in use.';
                } elseif ($accountError === '') {
                    $updateStmt = $conn->prepare(
                        'UPDATE users
                         SET first_name = ?, middle_name = ?, last_name = ?, username = ?, contact_no = ?,
                             street = ?, barangay = ?, city = ?, province = ?, postal = ?
                         WHERE user_id = ? AND position = 1 AND is_active = 1'
                    );
                    if ($updateStmt === false) {
                        error_log('Customer profile update prepare failed: ' . $conn->error);
                        $accountError = 'Unable to save your profile right now. Please try again.';
                    } else {
                        $updateStmt->bind_param(
                            'ssssssssssi',
                            $profile['first_name'],
                            $profile['middle_name'],
                            $profile['last_name'],
                            $profile['username'],
                            $profile['contact_no'],
                            $profile['street'],
                            $profile['barangay'],
                            $profile['city'],
                            $profile['province'],
                            $profile['postal'],
                            $userId
                        );
                        if (!$updateStmt->execute()) {
                            $duplicateUsername = $updateStmt->errno === 1062;
                            error_log('Customer profile update failed: ' . $updateStmt->error);
                            $accountError = $duplicateUsername
                                ? 'That username is already in use.'
                                : 'Unable to save your profile right now. Please try again.';
                        } else {
                            $_SESSION['username'] = $profile['username'];
                            header('Location: cstmr-account.php?updated=profile');
                            exit();
                        }
                        $updateStmt->close();
                    }
                }
            }
        } elseif ($action === 'upload_id_document') {
            if ($customer['verification_status'] === 'Approved') {
                $accountError = 'Your ID is already approved. Contact support if you need to update your verification.';
            } elseif (
                $customer['verification_status'] === 'Pending' &&
                !empty($customer['id_document'])
            ) {
                $accountError = 'Your ID has already been submitted and is awaiting admin review. You can submit another photo if your ID is rejected.';
            } else {
                $idDocumentFilename = storeVerificationIdUpload($_FILES['id_document'] ?? null, $idUploadError);
                if ($idDocumentFilename === false) {
                    $accountError = $idUploadError;
                } else {
                    $verificationStmt = $conn->prepare(
                        'UPDATE users
                         SET id_document = ?, verification_status = \'Pending\'
                         WHERE user_id = ? AND position = 1 AND is_active = 1
                           AND (id_document IS NULL OR id_document = \'\' OR verification_status = \'Rejected\')'
                    );
                    if ($verificationStmt === false) {
                        error_log('Customer ID verification update prepare failed: ' . $conn->error);
                        deleteVerificationIdUpload($idDocumentFilename);
                        $accountError = 'Unable to submit your ID for review right now. Please try again.';
                    } else {
                        $verificationStmt->bind_param('si', $idDocumentFilename, $userId);
                        if (!$verificationStmt->execute()) {
                            error_log('Customer ID verification update failed: ' . $verificationStmt->error);
                            deleteVerificationIdUpload($idDocumentFilename);
                            $accountError = 'Unable to submit your ID for review right now. Please try again.';
                        } elseif ($verificationStmt->affected_rows !== 1) {
                            deleteVerificationIdUpload($idDocumentFilename);
                            $accountError = 'Your ID has already been submitted and is awaiting admin review. You can submit another photo if your ID is rejected.';
                        } else {
                            $oldIdDocument = $customer['id_document'] ?? '';
                            if ($oldIdDocument !== '' && !deleteVerificationIdUpload($oldIdDocument)) {
                                error_log('Unable to remove the replaced customer ID verification image.');
                            }
                            $verificationStmt->close();
                            header('Location: cstmr-account.php?updated=id_verification');
                            exit();
                        }
                        $verificationStmt->close();
                    }
                }
            }
        } elseif ($action === 'upload_avatar') {
            $upload = $_FILES['avatar'] ?? null;
            if (
                !is_array($upload) ||
                !isset($upload['error'], $upload['tmp_name'], $upload['size']) ||
                !is_string($upload['tmp_name']) ||
                $upload['error'] !== UPLOAD_ERR_OK ||
                !is_uploaded_file($upload['tmp_name'])
            ) {
                $accountError = 'Select a valid profile photo to upload.';
            } elseif ($upload['size'] <= 0 || $upload['size'] > 5 * 1024 * 1024) {
                $accountError = 'Choose an image no larger than 5 MB.';
            } else {
                $imageInfo = @getimagesize($upload['tmp_name']);
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
                $avatarTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];
                if (
                    !$imageInfo ||
                    !isset($avatarTypes[$mime]) ||
                    $imageInfo[0] < 1 ||
                    $imageInfo[1] < 1 ||
                    $imageInfo[0] * $imageInfo[1] > 40000000
                ) {
                    $accountError = 'Choose a valid JPG, PNG, or WebP image.';
                } else {
                    $avatarDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
                    if (!is_dir($avatarDirectory) && !mkdir($avatarDirectory, 0755, true) && !is_dir($avatarDirectory)) {
                        error_log('Unable to create the customer avatar directory.');
                        $accountError = 'Unable to save the profile photo right now.';
                    } else {
                        $extension = $avatarTypes[$mime];
                        $avatarFilename = 'user_' . $userId . '.' . $extension;
                        $avatarPath = $avatarDirectory . DIRECTORY_SEPARATOR . $avatarFilename;
                        if (!move_uploaded_file($upload['tmp_name'], $avatarPath)) {
                            error_log('Unable to store a customer avatar.');
                            $accountError = 'Unable to save the profile photo right now.';
                        } else {
                            foreach (array_values($avatarTypes) as $oldExtension) {
                                if ($oldExtension !== $extension) {
                                    $oldAvatar = $avatarDirectory . DIRECTORY_SEPARATOR . 'user_' . $userId . '.' . $oldExtension;
                                    if (is_file($oldAvatar) && !unlink($oldAvatar)) {
                                        error_log('Unable to remove a replaced customer avatar.');
                                    }
                                }
                            }
                            header('Location: cstmr-account.php?updated=avatar');
                            exit();
                        }
                    }
                }
            }
        } elseif ($action === 'remove_avatar') {
            $avatarDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
            $removeFailed = false;
            foreach (['jpg', 'png', 'webp'] as $extension) {
                $avatarPath = $avatarDirectory . DIRECTORY_SEPARATOR . 'user_' . $userId . '.' . $extension;
                if (is_file($avatarPath) && !unlink($avatarPath)) {
                    $removeFailed = true;
                }
            }
            if ($removeFailed) {
                error_log('Unable to remove one or more customer avatar files.');
                $accountError = 'Unable to remove the profile photo right now.';
            } else {
                header('Location: cstmr-account.php?updated=avatar_removed');
                exit();
            }
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            if (!is_string($currentPassword) || !is_string($newPassword) || !is_string($confirmPassword)) {
                $accountError = 'Enter valid password values.';
            } elseif (!password_verify($currentPassword, $customer['password'])) {
                $accountError = 'Your current password is incorrect.';
            } elseif (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
                $accountError = 'New password must be between 8 and 72 bytes.';
            } elseif ($newPassword !== $confirmPassword) {
                $accountError = 'The new passwords do not match.';
            } else {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                if ($passwordHash === false) {
                    error_log('Unable to hash a customer password update.');
                    $accountError = 'Unable to update your password right now. Please try again.';
                } else {
                    $passwordStmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ? AND position = 1 AND is_active = 1');
                    if ($passwordStmt === false) {
                        error_log('Customer password update prepare failed: ' . $conn->error);
                        $accountError = 'Unable to update your password right now. Please try again.';
                    } else {
                        $passwordStmt->bind_param('si', $passwordHash, $userId);
                        if (!$passwordStmt->execute()) {
                            error_log('Customer password update failed: ' . $passwordStmt->error);
                            $accountError = 'Unable to update your password right now. Please try again.';
                        } else {
                            session_regenerate_id(true);
                            $passwordStmt->close();
                            header('Location: cstmr-account.php?updated=password');
                            exit();
                        }
                        $passwordStmt->close();
                    }
                }
            }
        } else {
            $accountError = 'Unknown account action.';
        }
    }

    if ($accountError !== '' && ($_POST['account_action'] ?? '') === 'save_profile') {
        foreach ([
            'first_name', 'middle_name', 'last_name', 'username', 'contact_no',
            'street', 'barangay', 'city', 'province', 'postal'
        ] as $field) {
            if (isset($_POST[$field]) && is_string($_POST[$field])) {
                $customer[$field] = trim($_POST[$field]);
            }
        }
    }
}

$customerName = trim($customer['first_name'] . ' ' . $customer['last_name']);
$avatarUrl = $customerAvatarUrl;
$updatedMessages = [
    'profile' => 'Account details saved.',
    'avatar' => 'Profile photo updated.',
    'avatar_removed' => 'Profile photo removed.',
    'password' => 'Password updated.',
    'id_verification' => 'Your ID was submitted for review. Cash on Delivery will be available after approval.'
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
    <link rel="stylesheet" href="style/style.css?v=account-profile-crop-2">
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
            <a class="nav-link px-3 py-2 rounded text-secondary hover-dark" href="cstmr-order.php">
                <i class="bi bi-bag-check me-1"></i>My Orders
            </a>
            <a class="nav-link px-3 py-2 rounded fw-semibold active text-white" href="cstmr-account.php"
                style="background-color: var(--contrast-primary, #2563EB);">
                <i class="bi bi-person me-1"></i>Account
            </a>
        </nav>

        <?php $notificationRole = 'customer'; require __DIR__ . DIRECTORY_SEPARATOR . 'notification-widget.php'; ?>

        <div class="customer-header-actions d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="customer-profile-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open profile menu">
                    <img id="empAvatar" data-session-managed="true" src="<?php echo $escape($avatarUrl); ?>" alt="" class="user-avatar rounded-circle object-fit-cover" width="40" height="40">
                    <span id="userName" data-session-managed="true" class="customer-profile-name"><?php echo $escape($customer['username']); ?></span>
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
                            <input type="file" id="accountAvatarInput" class="visually-hidden" name="avatar"
                                accept="image/png,image/jpeg,image/webp" aria-describedby="avatarUploadHelp" required>
                            <label for="accountAvatarInput" class="btn btn-primary w-100 fw-semibold">
                                <i class="bi bi-camera me-1" aria-hidden="true"></i>Choose photo
                            </label>
                            <div id="accountAvatarFileName" class="avatar-selected-file d-none mt-2" aria-live="polite"></div>
                            <div id="avatarUploadHelp" class="form-text mt-2">Crop and preview opens after you choose a JPG, PNG, or WebP photo (up to 5 MB).</div>
                        </form>
                        <form id="removeAvatarForm" data-server-managed="true" action="cstmr-account.php" method="post" class="mt-2">
                            <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                            <input type="hidden" name="account_action" value="remove_avatar">
                            <button type="submit" id="removeAvatarBtn" class="btn btn-outline-danger btn-sm w-100<?php echo $avatarUrl === 'assets/user.jpg' ? ' d-none' : ''; ?>">
                                <i class="bi bi-trash3 me-1" aria-hidden="true"></i>Remove photo
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Right Column: Personal Details & Password Form -->
                <div class="col-lg-8">

                    <div class="card customer-card border-0 shadow-sm p-4 mb-4" style="border-radius: var(--prod-rd, 1rem);">
                        <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                            <div>
                                <h5 class="fw-bold mb-2">Cash on Delivery ID Verification</h5>
                                <p class="text-secondary mb-2">
                                    Status:
                                    <span class="badge <?php echo $customer['verification_status'] === 'Approved' ? 'bg-success' : ($customer['verification_status'] === 'Rejected' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                        <?php echo $escape($customer['verification_status']); ?>
                                    </span>
                                </p>
                                <?php if ($customer['verification_status'] === 'Approved'): ?>
                                    <p class="small text-secondary mb-0">Your ID is approved. Cash on Delivery is available at checkout.</p>
                                <?php elseif ($customer['verification_status'] === 'Rejected'): ?>
                                    <p class="small text-secondary mb-0">Your ID could not be verified. Submit a clear replacement photo to request another review.</p>
                                <?php elseif (!empty($customer['id_document'])): ?>
                                    <p class="small text-secondary mb-0">Your ID has been submitted and is awaiting admin review. You cannot upload another photo while it is under review. Cash on Delivery is unavailable until approval.</p>
                                <?php else: ?>
                                    <p class="small text-secondary mb-0">No ID photo has been submitted yet. Upload a clear photo of a valid ID to begin verification. Cash on Delivery is unavailable until your ID is approved.</p>
                                <?php endif; ?>
                            </div>
                            <?php if (
                                $customer['verification_status'] !== 'Approved' &&
                                ($customer['verification_status'] === 'Rejected' || empty($customer['id_document']))
                            ): ?>
                                <form action="cstmr-account.php" method="post" enctype="multipart/form-data" class="flex-grow-1" style="max-width: 420px;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                                    <input type="hidden" name="account_action" value="upload_id_document">
                                    <label for="idDocumentInput" class="form-label small fw-semibold">Valid ID photo</label>
                                    <input type="file" class="form-control" id="idDocumentInput" name="id_document"
                                        accept="image/jpeg,image/png,image/webp" aria-describedby="idDocumentHelp" required>
                                    <div id="idDocumentHelp" class="form-text">JPG, PNG, or WebP; maximum 5 MB.</div>
                                    <button type="submit" class="btn btn-primary btn-sm mt-2">Submit ID for review</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

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
                                            name="first_name" placeholder="First Name" value="<?php echo $escape($customer['first_name']); ?>" maxlength="100" required>
                                    </div>
                                    <div class="col-4">
                                        <input type="text" class="form-control shadow-none" id="accountMn"
                                            name="middle_name" placeholder="Middle Name" value="<?php echo $escape($customer['middle_name']); ?>" maxlength="100">
                                    </div>
                                    <div class="col-4">
                                        <input type="text" class="form-control shadow-none" id="accountLn"
                                            name="last_name" placeholder="Last Name" value="<?php echo $escape($customer['last_name']); ?>" maxlength="100" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Username & Contact Number -->
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">Username</label>
                                    <input type="text" class="form-control shadow-none" id="accountUsername"
                                        name="username" value="<?php echo $escape($customer['username']); ?>" maxlength="50" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">Contact Number</label>
                                    <input type="tel" class="form-control shadow-none" id="accountContact"
                                        name="contact_no" value="<?php echo $escape($customer['contact_no']); ?>" maxlength="20" pattern="[0-9+() .-]{7,20}" required>
                                </div>
                            </div>

                            <!-- Structured Address Fields -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">Address Details</label>

                                <!-- Row 1: House/Unit & Street -->
                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <input type="text" class="form-control shadow-none" id="accountStreet"
                                            name="street" placeholder="House / Unit No., Street" value="<?php echo $escape($customer['street']); ?>" maxlength="150" required>
                                    </div>
                                </div>

                                <!-- Row 2: Barangay & City/Municipality -->
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <input type="text" class="form-control shadow-none" id="accountBarangay"
                                            name="barangay" placeholder="Barangay" value="<?php echo $escape($customer['barangay']); ?>" maxlength="100" required>
                                    </div>
                                    <div class="col-6">
                                        <input type="text" class="form-control shadow-none" id="accountCity"
                                            name="city" placeholder="City / Municipality" value="<?php echo $escape($customer['city']); ?>" maxlength="100" required>
                                    </div>
                                </div>

                                <!-- Row 3: Province & Postal Code -->
                                <div class="row g-2">
                                    <div class="col-8">
                                        <input type="text" class="form-control shadow-none" id="accountProvince"
                                            name="province" placeholder="Province" value="<?php echo $escape($customer['province']); ?>" maxlength="100" required>
                                    </div>
                                    <div class="col-4">
                                        <input type="text" class="form-control shadow-none" id="accountZip"
                                            name="postal" placeholder="Postal Code" value="<?php echo $escape($customer['postal']); ?>" maxlength="10" pattern="[A-Za-z0-9 -]{1,10}" required>
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
                                <label class="form-label small fw-semibold text-secondary" for="currentPassword">Current Password</label>
                                <div class="input-group account-password-input">
                                    <input type="password" class="form-control shadow-none" id="currentPassword"
                                        name="current_password" placeholder="Enter current password" required>
                                    <button type="button" class="btn btn-outline-secondary password-toggle" data-password-toggle="currentPassword"
                                        aria-label="Show current password" aria-pressed="false">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary" for="newPassword">New Password</label>
                                    <div class="input-group account-password-input">
                                        <input type="password" class="form-control shadow-none" id="newPassword"
                                                name="new_password" placeholder="Enter new password" minlength="8" maxlength="72" required>
                                        <button type="button" class="btn btn-outline-secondary password-toggle" data-password-toggle="newPassword"
                                            aria-label="Show new password" aria-pressed="false">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary" for="confirmPassword">Confirm New Password</label>
                                    <div class="input-group account-password-input">
                                        <input type="password" class="form-control shadow-none" id="confirmPassword"
                                            name="confirm_password" placeholder="Confirm new password" minlength="8" maxlength="72" required>
                                        <button type="button" class="btn btn-outline-secondary password-toggle" data-password-toggle="confirmPassword"
                                            aria-label="Show confirmation password" aria-pressed="false">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
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

    <div class="modal fade avatar-crop-modal" id="avatarCropModal" tabindex="-1" aria-labelledby="avatarCropTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <section class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <span class="customer-eyebrow">PROFILE PHOTO</span>
                        <h5 class="modal-title fw-bold mt-1" id="avatarCropTitle">Adjust your photo</h5>
                    </div>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <p class="text-secondary small text-center mb-3">Drag to reposition. Zoom or rotate until it looks right.</p>
                    <div class="avatar-crop-stage">
                        <canvas id="avatarCropCanvas" width="360" height="360" aria-label="Profile photo crop preview"></canvas>
                    </div>
                    <div class="avatar-crop-controls mt-4">
                        <button type="button" class="btn btn-outline-secondary" id="avatarRotateBtn">
                            <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Rotate
                        </button>
                        <label class="avatar-zoom-control" for="avatarZoom">
                            <i class="bi bi-dash-lg" aria-hidden="true"></i>
                            <input id="avatarZoom" type="range" min="1" max="3" step="0.01" value="1" aria-label="Zoom profile photo">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary fw-semibold px-4" id="avatarCropSaveBtn">
                        <i class="bi bi-check2 me-1" aria-hidden="true"></i>Save Photo
                    </button>
                </div>
            </section>
        </div>
    </div>

    <!-- Custom JavaScript file -->
    <script src="backend/app.js?v=password-visibility-1"></script>
    <script src="backend/cstmr.js?v=profile-crop-2"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="backend/theme.js?v=theme-header-order-2"></script>
</body>

</html>
