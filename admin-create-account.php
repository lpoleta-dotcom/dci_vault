<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function respond($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload);
    exit();
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-api-session.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    respond(200, ['csrf_token' => $_SESSION['admin_csrf_token']]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    respond(405, ['message' => 'Method not allowed.']);
}

$expectedOrigin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? '');
if (
    empty($_SERVER['HTTP_ORIGIN']) ||
    $expectedOrigin === '://' ||
    !hash_equals($expectedOrigin, $_SERVER['HTTP_ORIGIN'])
) {
    respond(403, ['message' => 'Request origin could not be verified. Refresh the page and try again.']);
}

if (!hash_equals($_SESSION['admin_csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    respond(403, ['message' => 'Your session expired. Refresh the page and try again.']);
}

$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if ($contentType !== 'application/json') {
    respond(415, ['message' => 'Account details must be sent as JSON.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(400, ['message' => 'Invalid account details.']);
}

$firstName = trim((string) ($input['first_name'] ?? ''));
$middleName = trim((string) ($input['middle_name'] ?? ''));
$lastName = trim((string) ($input['last_name'] ?? ''));
$username = trim((string) ($input['username'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));
$street = trim((string) ($input['street'] ?? ''));
$barangay = trim((string) ($input['barangay'] ?? ''));
$city = trim((string) ($input['city'] ?? ''));
$province = trim((string) ($input['province'] ?? ''));
$postal = trim((string) ($input['postal'] ?? ''));
$password = (string) ($input['password'] ?? '');
$confirmPassword = (string) ($input['confirm_password'] ?? '');
$role = filter_var($input['role'] ?? null, FILTER_VALIDATE_INT);

if (
    $firstName === '' || $lastName === '' || $username === '' ||
    $phone === '' ||
    !in_array($role, [1, 2], true)
) {
    respond(422, ['message' => 'Complete all required account details and choose a valid role.']);
}

if (
    $role === 1 &&
    ($street === '' || $barangay === '' || $city === '' || $province === '' || $postal === '')
) {
    respond(422, ['message' => 'Customer accounts require complete address details.']);
}

if (strlen($password) < 8) {
    respond(422, ['field' => 'password', 'message' => 'Password must be at least 8 characters.']);
}

if ($password !== $confirmPassword) {
    respond(422, ['field' => 'confirm_password', 'message' => 'Passwords do not match.']);
}

$checkStmt = $conn->prepare('SELECT user_id FROM users WHERE username = ?');
if ($checkStmt === false) {
    respond(500, ['message' => 'Unable to verify the username.']);
}
$checkStmt->bind_param('s', $username);
if (!$checkStmt->execute()) {
    $checkStmt->close();
    respond(500, ['message' => 'Unable to verify the username.']);
}
$existingUser = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existingUser) {
    respond(409, ['field' => 'username', 'message' => 'This username is already in use.']);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$insertStmt = $conn->prepare(
    'INSERT INTO users
        (first_name, middle_name, last_name, username, contact_no, street, barangay, city, province, postal, password, position)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
if ($insertStmt === false) {
    respond(500, ['message' => 'Unable to create the account.']);
}

$insertStmt->bind_param(
    'sssssssssssi',
    $firstName,
    $middleName,
    $lastName,
    $username,
    $phone,
    $street,
    $barangay,
    $city,
    $province,
    $postal,
    $passwordHash,
    $role
);

if (!$insertStmt->execute()) {
    $duplicateUsername = $insertStmt->errno === 1062;
    $insertStmt->close();
    if ($duplicateUsername) {
        respond(409, ['field' => 'username', 'message' => 'This username is already in use.']);
    }
    respond(500, ['message' => 'Unable to create the account.']);
}

$userId = $insertStmt->insert_id;
$insertStmt->close();
$conn->close();

respond(201, [
    'account' => [
        'id' => $userId,
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
        'username' => $username,
        'phone' => $phone,
        'street' => $street,
        'barangay' => $barangay,
        'city' => $city,
        'province' => $province,
        'postal' => $postal,
        'role' => $role
    ]
]);
