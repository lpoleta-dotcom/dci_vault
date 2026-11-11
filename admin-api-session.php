<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-session-init.php';

$adminApiUserId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($adminApiUserId === false || $adminApiUserId === null || (int) ($_SESSION['position'] ?? 0) !== 2) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Administrator access is required.']);
    exit();
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'connect.php';
$adminApiUserStmt = $conn->prepare('SELECT position, is_active FROM users WHERE user_id = ?');
if (!$adminApiUserStmt) {
    error_log('Unable to prepare administrator session check: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to verify administrator access.']);
    exit();
}
$adminApiUserStmt->bind_param('i', $adminApiUserId);
if (!$adminApiUserStmt->execute()) {
    error_log('Unable to execute administrator session check: ' . $adminApiUserStmt->error);
    $adminApiUserStmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to verify administrator access.']);
    exit();
}
$adminApiUser = $adminApiUserStmt->get_result()->fetch_assoc();
$adminApiUserStmt->close();
if (!$adminApiUser || (int) $adminApiUser['position'] !== 2 || (int) $adminApiUser['is_active'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This administrator account is inactive or no longer authorized.']);
    exit();
}

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}
