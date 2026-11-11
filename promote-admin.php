<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

if ($argc !== 2 || trim($argv[1]) === '' || strlen($argv[1]) > 50) {
    fwrite(STDERR, "Usage: php promote-admin.php <username>\n");
    exit(2);
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'connect.php';

$username = trim($argv[1]);
$lookup = $conn->prepare('SELECT user_id, position FROM users WHERE username = ?');
if (!$lookup) {
    fwrite(STDERR, "Unable to look up that account.\n");
    exit(1);
}
$lookup->bind_param('s', $username);
if (!$lookup->execute()) {
    $lookup->close();
    fwrite(STDERR, "Unable to look up that account.\n");
    exit(1);
}
$account = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$account) {
    fwrite(STDERR, "No account was found with that username.\n");
    exit(1);
}
if ((int) $account['position'] === 2) {
    fwrite(STDOUT, "That account is already an administrator.\n");
    exit(0);
}

$userId = (int) $account['user_id'];
$update = $conn->prepare('UPDATE users SET position = 2 WHERE user_id = ?');
if (!$update) {
    fwrite(STDERR, "Unable to promote that account.\n");
    exit(1);
}
$update->bind_param('i', $userId);
if (!$update->execute()) {
    $update->close();
    fwrite(STDERR, "Unable to promote that account.\n");
    exit(1);
}
$update->close();

fwrite(STDOUT, "Account promoted. Sign in again to access the admin area.\n");
