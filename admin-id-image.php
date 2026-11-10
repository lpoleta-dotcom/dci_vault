<?php
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-api-session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Method not allowed.');
}

$userId = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($userId === false) {
    http_response_code(400);
    exit('Choose a valid customer account.');
}

$imageStmt = $conn->prepare(
    'SELECT id_document FROM users WHERE user_id = ? AND position = 1'
);
if (!$imageStmt) {
    error_log('Unable to prepare administrator ID image lookup: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load the ID image.');
}
$imageStmt->bind_param('i', $userId);
if (!$imageStmt->execute()) {
    error_log('Unable to execute administrator ID image lookup: ' . $imageStmt->error);
    $imageStmt->close();
    http_response_code(500);
    exit('Unable to load the ID image.');
}
$user = $imageStmt->get_result()->fetch_assoc();
$imageStmt->close();

if (!$user || !is_string($user['id_document']) ||
    !preg_match('/\A[a-f0-9]{48}\.(?:jpg|png|webp)\z/', $user['id_document'])) {
    http_response_code(404);
    exit('ID image not found.');
}

$imagePath = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR
    . 'id-verification' . DIRECTORY_SEPARATOR . $user['id_document'];
if (!is_file($imagePath)) {
    http_response_code(404);
    exit('ID image not found.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($imagePath);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    error_log('Stored customer ID image has an unsupported content type.');
    http_response_code(415);
    exit('ID image format is not supported.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($imagePath));
header('Content-Disposition: inline; filename="customer-id.' . pathinfo($imagePath, PATHINFO_EXTENSION) . '"');
readfile($imagePath);
