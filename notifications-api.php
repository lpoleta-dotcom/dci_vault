<?php
if (session_status() === PHP_SESSION_NONE) {
    $role = $_GET['role'] ?? '';
    if ($role === 'admin') {
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-session-init.php';
    } else {
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'customer-session-init.php';
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function notificationsRespond($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

$role = $_GET['role'] ?? '';
$userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$position = $role === 'admin' ? 2 : 1;
if (!in_array($role, ['admin', 'customer'], true) || $userId === false || $userId === null
    || (int) ($_SESSION['position'] ?? 0) !== $position) {
    notificationsRespond(401, ['success' => false, 'message' => 'Sign in to view notifications.']);
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'connect.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'notifications-bootstrap.php';
if (!ensureNotificationTables($conn)) {
    notificationsRespond(500, ['success' => false, 'message' => 'Notifications are temporarily unavailable.']);
}

$activeStmt = $conn->prepare('SELECT user_id FROM users WHERE user_id = ? AND position = ? AND is_active = 1');
if (!$activeStmt) notificationsRespond(500, ['success' => false, 'message' => 'Unable to verify your account.']);
$activeStmt->bind_param('ii', $userId, $position);
if (!$activeStmt->execute()) {
    $activeStmt->close();
    notificationsRespond(500, ['success' => false, 'message' => 'Unable to verify your account.']);
}
$active = $activeStmt->get_result()->fetch_assoc();
$activeStmt->close();
if (!$active) notificationsRespond(401, ['success' => false, 'message' => 'Your account is inactive or unavailable.']);

$csrfKey = $role === 'admin' ? 'admin_csrf_token' : 'customer_csrf_token';
if (empty($_SESSION[$csrfKey])) $_SESSION[$csrfKey] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expectedOrigin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    if (empty($_SERVER['HTTP_ORIGIN']) || $expectedOrigin === '://'
        || !hash_equals($expectedOrigin, $_SERVER['HTTP_ORIGIN'])) {
        notificationsRespond(403, ['success' => false, 'message' => 'Request origin could not be verified.']);
    }
    if (!hash_equals($_SESSION[$csrfKey], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        notificationsRespond(403, ['success' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
    }
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        notificationsRespond(415, ['success' => false, 'message' => 'Notification updates must be sent as JSON.']);
    }
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) notificationsRespond(400, ['success' => false, 'message' => 'Invalid notification update.']);

    if (!empty($payload['mark_all']) && $payload['mark_all'] === true) {
        $updateStmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE recipient_user_id = ? AND is_read = 0');
        if (!$updateStmt) notificationsRespond(500, ['success' => false, 'message' => 'Unable to update notifications.']);
        $updateStmt->bind_param('i', $userId);
    } else {
        $notificationId = filter_var($payload['notification_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($notificationId === false || $notificationId === null) {
            notificationsRespond(400, ['success' => false, 'message' => 'Choose a valid notification.']);
        }
        $updateStmt = $conn->prepare(
            'UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND recipient_user_id = ?'
        );
        if (!$updateStmt) notificationsRespond(500, ['success' => false, 'message' => 'Unable to update notifications.']);
        $updateStmt->bind_param('ii', $notificationId, $userId);
    }
    if (!$updateStmt->execute()) {
        error_log('Notification read update failed: ' . $updateStmt->error);
        $updateStmt->close();
        notificationsRespond(500, ['success' => false, 'message' => 'Unable to update notifications.']);
    }
    $updateStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    notificationsRespond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$countStmt = $conn->prepare('SELECT COUNT(*) AS unread_count FROM notifications WHERE recipient_user_id = ? AND is_read = 0');
if (!$countStmt) notificationsRespond(500, ['success' => false, 'message' => 'Unable to load notifications.']);
$countStmt->bind_param('i', $userId);
if (!$countStmt->execute()) {
    $countStmt->close();
    notificationsRespond(500, ['success' => false, 'message' => 'Unable to load notifications.']);
}
$unreadCount = (int) $countStmt->get_result()->fetch_assoc()['unread_count'];
$countStmt->close();

$listStmt = $conn->prepare(
    'SELECT notification_id, notification_type, title, message, target_url, is_read, created_at
     FROM notifications WHERE recipient_user_id = ? ORDER BY created_at DESC, notification_id DESC LIMIT 30'
);
if (!$listStmt) notificationsRespond(500, ['success' => false, 'message' => 'Unable to load notifications.']);
$listStmt->bind_param('i', $userId);
if (!$listStmt->execute()) {
    $listStmt->close();
    notificationsRespond(500, ['success' => false, 'message' => 'Unable to load notifications.']);
}
$result = $listStmt->get_result();
$notifications = [];
while ($row = $result->fetch_assoc()) {
    $row['notification_id'] = (int) $row['notification_id'];
    $row['is_read'] = (bool) $row['is_read'];
    $row['created_at'] = date('M j, Y g:i A', strtotime($row['created_at']));
    $notifications[] = $row;
}
$listStmt->close();

notificationsRespond(200, [
    'success' => true,
    'csrf_token' => $_SESSION[$csrfKey],
    'unread_count' => $unreadCount,
    'notifications' => $notifications
]);
?>
