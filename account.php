<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'customer-session-init.php';

require_once 'connect.php';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) === 'multipart/form-data' &&
    !$_POST &&
    !$_FILES &&
    isset($_SERVER['CONTENT_LENGTH']) &&
    ctype_digit($_SERVER['CONTENT_LENGTH']) &&
    (int) $_SERVER['CONTENT_LENGTH'] > 0
) {
    $_SESSION['signup_error'] = 'The ID photo exceeds the upload limit. Choose an image no larger than 5 MB.';
    header('Location: sign-up.php');
    exit();
}

if (isset($_POST['signUp']) || isset($_POST['logIn'])) {
    $submittedToken = $_POST['auth_csrf_token'] ?? '';
    if (
        !is_string($submittedToken) ||
        empty($_SESSION['auth_csrf_token']) ||
        !hash_equals($_SESSION['auth_csrf_token'], $submittedToken)
    ) {
        if (isset($_POST['signUp'])) {
            $_SESSION['signup_error'] = 'Your session expired. Refresh the page and try again.';
            header('Location: sign-up.php');
        } else {
            header('Location: login.php?error=session');
        }
        exit();
    }
}

$postString = function ($key) {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
};

// ---------------------------------------------------------------------
// SIGN-UP LOGIC
// ---------------------------------------------------------------------
if (isset($_POST['signUp'])) {
    $firstName = $postString('fName');
    $middleName = $postString('mName');
    $lastName = $postString('lName');
    $userName = $postString('uName');
    $conNumber = $postString('conNumber');
    $addStreet = $postString('addStreet');
    $addBrgy = $postString('addBrgy');
    $addCity = $postString('addCity');
    $addProv = $postString('addProv');
    $addPost = $postString('addPost');
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'id-verification.php';
    $pWord = isset($_POST['pWord']) && is_string($_POST['pWord']) ? $_POST['pWord'] : '';
    $confirmPassword = isset($_POST['confirmPassword']) && is_string($_POST['confirmPassword'])
        ? $_POST['confirmPassword']
        : '';

    $signupError = '';
    if (
        $firstName === '' || $lastName === '' || $userName === '' ||
        $conNumber === '' || $addStreet === '' || $addBrgy === '' ||
        $addCity === '' || $addProv === '' || $addPost === ''
    ) {
        $signupError = 'Complete all required account details.';
    } elseif (
        strlen($firstName) > 100 || strlen($middleName) > 100 ||
        strlen($lastName) > 100 || strlen($userName) > 50 ||
        strlen($conNumber) > 20 || strlen($addStreet) > 150 ||
        strlen($addBrgy) > 100 || strlen($addCity) > 100 ||
        strlen($addProv) > 100 || strlen($addPost) > 10
    ) {
        $signupError = 'One or more account details exceed the allowed length.';
    } elseif (!preg_match('/^[0-9+() .-]{7,20}$/', $conNumber)) {
        $signupError = 'Enter a valid contact number.';
    } elseif (!preg_match('/^[A-Za-z0-9 -]{1,10}$/', $addPost)) {
        $signupError = 'Enter a valid postal code.';
    } elseif (strlen($pWord) < 8 || strlen($pWord) > 72) {
        $signupError = 'Password must be between 8 and 72 bytes.';
    } elseif ($pWord !== $confirmPassword) {
        $signupError = 'Passwords do not match.';
    }

    if ($signupError !== '') {
        $_SESSION['signup_error'] = $signupError;
        header('Location: sign-up.php');
        exit();
    }

    require_once __DIR__ . DIRECTORY_SEPARATOR . 'notifications-bootstrap.php';
    if (!ensureNotificationTables($conn)) {
        $_SESSION['signup_error'] = 'Account creation is temporarily unavailable.';
        header('Location: sign-up.php');
        exit();
    }

    $pWordHash = password_hash($pWord, PASSWORD_DEFAULT);
    if ($pWordHash === false) {
        error_log('Unable to hash a new customer password.');
        $_SESSION['signup_error'] = 'Account creation is temporarily unavailable.';
        header('Location: sign-up.php');
        exit();
    }
    $insertStmt = $conn->prepare(
        'INSERT INTO users
            (first_name, middle_name, last_name, username, contact_no, street, barangay, city, province, postal, password, position, id_document, verification_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, \'Pending\')'
    );
    if ($insertStmt === false) {
        error_log('Customer account insert prepare failed: ' . $conn->error);
        $_SESSION['signup_error'] = 'Account creation is temporarily unavailable.';
        header('Location: sign-up.php');
        exit();
    }
    $idDocumentFilename = '';
    $insertStmt->bind_param(
        'ssssssssssss',
        $firstName,
        $middleName,
        $lastName,
        $userName,
        $conNumber,
        $addStreet,
        $addBrgy,
        $addCity,
        $addProv,
        $addPost,
        $pWordHash,
        $idDocumentFilename
    );
    if (!$conn->begin_transaction()) {
        $insertStmt->close();
        error_log('Unable to start customer account registration transaction.');
        $_SESSION['signup_error'] = 'Account creation is temporarily unavailable.';
        header('Location: sign-up.php');
        exit();
    }
    $idDocumentFilename = storeVerificationIdUpload($_FILES['id_document'] ?? null, $idUploadError);
    if ($idDocumentFilename === false) {
        $insertStmt->close();
        $conn->rollback();
        $_SESSION['signup_error'] = $idUploadError;
        header('Location: sign-up.php');
        exit();
    }
    if (!$insertStmt->execute()) {
        $duplicateUsername = $insertStmt->errno === 1062;
        error_log('Customer account insert failed: ' . $insertStmt->error);
        $insertStmt->close();
        $conn->rollback();
        deleteVerificationIdUpload($idDocumentFilename);
        $_SESSION['signup_error'] = $duplicateUsername
            ? 'Username already exists. Please choose another username.'
            : 'Account creation is temporarily unavailable.';
        header('Location: sign-up.php');
        exit();
    }
    $newUserId = (int) $conn->insert_id;
    $insertStmt->close();
    if (
        !createNotification($conn, $newUserId, 'welcome', 'Welcome to DCI Vault', 'Your account is ready. Happy shopping!', 'cstmr-prod.php') ||
        !notifyActiveAdmins(
            $conn,
            'new_signup',
            'New customer signed up',
            $userName . ' created a customer account.',
            'admin-users.php?highlight-user=' . $newUserId
        ) ||
        !$conn->commit()
    ) {
        $conn->rollback();
        deleteVerificationIdUpload($idDocumentFilename);
        error_log('Customer account registration could not save its notifications.');
        $_SESSION['signup_error'] = 'Account creation is temporarily unavailable.';
        header('Location: sign-up.php');
        exit();
    }
    header('Location: login.php');
    exit();
}

/// ---------------------------------------------------------------------
// LOG-IN LOGIC
// ---------------------------------------------------------------------
if (isset($_POST['logIn'])) {
    $userName = $postString('uName');
    $pWord = isset($_POST['pWord']) && is_string($_POST['pWord']) ? $_POST['pWord'] : '';
    if ($userName === '' || strlen($userName) > 50 || $pWord === '' || strlen($pWord) > 72) {
        header('Location: login.php?error=invalid');
        exit();
    }

    $loginStmt = $conn->prepare('SELECT user_id, username, password, position, is_active FROM users WHERE username = ?');
    if ($loginStmt === false) {
        error_log('Customer login query prepare failed: ' . $conn->error);
        header('Location: login.php?error=unavailable');
        exit();
    }

    $loginStmt->bind_param('s', $userName);
    if (!$loginStmt->execute()) {
        error_log('Customer login query failed: ' . $loginStmt->error);
        $loginStmt->close();
        header('Location: login.php?error=unavailable');
        exit();
    }
    $row = $loginStmt->get_result()->fetch_assoc();
    $loginStmt->close();

    if ($row && password_verify($pWord, $row['password'])) {
        if ((int) ($row['position'] ?? 0) === 2) {
            header("Location: login.php?error=admin_login_required");
            exit();
        }
        if ((int) $row['position'] !== 1 || (int) $row['is_active'] !== 1) {
            header("Location: login.php?error=invalid");
            exit();
        }

        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['position'] = 1;

        session_write_close();
        header("Location: cstmr-prod.php");
        exit();
    }

    header("Location: login.php?error=invalid");
    exit();
}