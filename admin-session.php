<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-session-init.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

$adminSessionUserId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($adminSessionUserId === false || $adminSessionUserId === null || (int) ($_SESSION['position'] ?? 0) !== 2) {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: admin-login.php');
    exit();
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'connect.php';
$adminSessionStmt = $conn->prepare('SELECT first_name, last_name, position, is_active FROM users WHERE user_id = ?');
if (!$adminSessionStmt) {
    error_log('Unable to prepare administrator page session check: ' . $conn->error);
    http_response_code(503);
    exit('Administrator access is temporarily unavailable.');
}
$adminSessionStmt->bind_param('i', $adminSessionUserId);
if (!$adminSessionStmt->execute()) {
    error_log('Unable to execute administrator page session check: ' . $adminSessionStmt->error);
    $adminSessionStmt->close();
    http_response_code(503);
    exit('Administrator access is temporarily unavailable.');
}
$adminSessionUser = $adminSessionStmt->get_result()->fetch_assoc();
$adminSessionStmt->close();
if (!$adminSessionUser || (int) $adminSessionUser['position'] !== 2 || (int) $adminSessionUser['is_active'] !== 1) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin-login.php');
    exit();
}
$adminSessionName = trim($adminSessionUser['first_name'] . ' ' . $adminSessionUser['last_name']);
?>
