<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'customer-session-init.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0 || (int) ($_SESSION['position'] ?? 0) !== 1) {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: login.php');
    exit();
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'connect.php';

$headerUserStmt = $conn->prepare(
    'SELECT username, position, is_active, verification_status
     FROM users WHERE user_id = ?'
);
if ($headerUserStmt === false) {
    die('Customer session query prepare error: ' . $conn->error);
}
$headerUserStmt->bind_param('i', $userId);
if (!$headerUserStmt->execute()) {
    die('Customer session query error: ' . $headerUserStmt->error);
}
$headerUser = $headerUserStmt->get_result()->fetch_assoc();
$headerUserStmt->close();

if (!$headerUser || (int) $headerUser['position'] !== 1 || (int) $headerUser['is_active'] !== 1) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit();
}

$customerHeaderUsername = $headerUser['username'];
$customerVerificationStatus = $headerUser['verification_status'];
$_SESSION['username'] = $customerHeaderUsername;
if (empty($_SESSION['customer_csrf_token'])) {
    $_SESSION['customer_csrf_token'] = bin2hex(random_bytes(32));
}
$customerCsrfToken = $_SESSION['customer_csrf_token'];
$customerAvatarUrl = 'assets/user.jpg';
$avatarDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
foreach (['jpg', 'png', 'webp'] as $avatarExtension) {
    $avatarFilename = 'user_' . $userId . '.' . $avatarExtension;
    $avatarPath = $avatarDirectory . DIRECTORY_SEPARATOR . $avatarFilename;
    if (is_file($avatarPath)) {
        $customerAvatarUrl = 'uploads/avatars/' . $avatarFilename . '?v=' . filemtime($avatarPath);
        break;
    }
}

$customerEscape = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
