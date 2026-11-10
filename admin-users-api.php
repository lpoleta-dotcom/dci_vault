<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function adminUsersRespond($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-api-session.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query(
        'SELECT u.user_id, u.first_name, u.middle_name, u.last_name, u.username,
                u.contact_no, u.street, u.barangay, u.city, u.province, u.postal,
                u.position, u.is_active, COUNT(o.order_id) AS order_count
         FROM users u LEFT JOIN orders o ON o.user_id = u.user_id
         GROUP BY u.user_id
         ORDER BY u.is_active DESC, u.position DESC, u.last_name, u.first_name'
    );
    if (!$result) {
        error_log('Unable to load admin user list: ' . $conn->error);
        adminUsersRespond(500, ['success' => false, 'message' => 'Unable to load user accounts.']);
    }

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $row['user_id'] = (int) $row['user_id'];
        $row['position'] = (int) $row['position'];
        $row['is_active'] = (bool) $row['is_active'];
        $row['order_count'] = (int) $row['order_count'];
        $users[] = $row;
    }
    adminUsersRespond(200, [
        'success' => true,
        'csrf_token' => $_SESSION['admin_csrf_token'],
        'current_user_id' => (int) $_SESSION['user_id'],
        'users' => $users
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    adminUsersRespond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$expectedOrigin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? '');
if (
    empty($_SERVER['HTTP_ORIGIN']) ||
    $expectedOrigin === '://' ||
    !hash_equals($expectedOrigin, $_SERVER['HTTP_ORIGIN'])
) {
    adminUsersRespond(403, ['success' => false, 'message' => 'Request origin could not be verified.']);
}
if (!hash_equals($_SESSION['admin_csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    adminUsersRespond(403, ['success' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
}
if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    adminUsersRespond(415, ['success' => false, 'message' => 'Account changes must be sent as JSON.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) adminUsersRespond(400, ['success' => false, 'message' => 'Invalid account update.']);
$action = $input['action'] ?? '';
$userId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($userId === false) adminUsersRespond(422, ['success' => false, 'message' => 'Choose a valid user account.']);

if ($action === 'status') {
    $active = filter_var($input['is_active'] ?? null, FILTER_VALIDATE_INT);
    if (!in_array($active, [0, 1], true)) {
        adminUsersRespond(422, ['success' => false, 'message' => 'Choose a valid account status.']);
    }
    if ((int) $userId === (int) $_SESSION['user_id'] && $active === 0) {
        adminUsersRespond(409, ['success' => false, 'message' => 'You cannot deactivate the account you are using.']);
    }

    $conn->begin_transaction();
    try {
        $userStmt = $conn->prepare('SELECT position, is_active FROM users WHERE user_id = ? FOR UPDATE');
        if (!$userStmt) throw new Exception('Unable to prepare account status lookup.');
        $userStmt->bind_param('i', $userId);
        if (!$userStmt->execute()) throw new Exception('Unable to load account status.');
        $user = $userStmt->get_result()->fetch_assoc();
        $userStmt->close();
        if (!$user) {
            $conn->rollback();
            adminUsersRespond(404, ['success' => false, 'message' => 'User account was not found.']);
        }

        if ($active === 0 && (int) $user['position'] === 2 && (int) $user['is_active'] === 1) {
            $countResult = $conn->query('SELECT user_id FROM users WHERE position = 2 AND is_active = 1 FOR UPDATE');
            if (!$countResult) throw new Exception('Unable to check administrator accounts.');
            if ($countResult->num_rows <= 1) {
                $conn->rollback();
                adminUsersRespond(409, ['success' => false, 'message' => 'The last active administrator cannot be deactivated.']);
            }
        }

        $updateStmt = $conn->prepare('UPDATE users SET is_active = ? WHERE user_id = ?');
        if (!$updateStmt) throw new Exception('Unable to prepare account status update.');
        $updateStmt->bind_param('ii', $active, $userId);
        if (!$updateStmt->execute()) throw new Exception('Unable to update account status.');
        $updateStmt->close();
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('Admin account status update failed: ' . $error->getMessage());
        adminUsersRespond(500, ['success' => false, 'message' => 'Unable to update account status.']);
    }

    adminUsersRespond(200, ['success' => true, 'is_active' => (bool) $active]);
}

if (!in_array($action, ['update', 'role'], true)) {
    adminUsersRespond(400, ['success' => false, 'message' => 'Choose a valid account action.']);
}

$role = filter_var($input['role'] ?? null, FILTER_VALIDATE_INT);
if (!in_array($role, [1, 2], true)) adminUsersRespond(422, ['success' => false, 'message' => 'Choose a valid account role.']);

$conn->begin_transaction();
try {
    $userStmt = $conn->prepare(
        'SELECT position, is_active, street, barangay, city, province, postal
         FROM users WHERE user_id = ? FOR UPDATE'
    );
    if (!$userStmt) throw new Exception('Unable to prepare account lookup.');
    $userStmt->bind_param('i', $userId);
    if (!$userStmt->execute()) throw new Exception('Unable to load account.');
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();
    if (!$user) {
        $conn->rollback();
        adminUsersRespond(404, ['success' => false, 'message' => 'User account was not found.']);
    }
    if ($role === 1 && (int) $user['position'] === 2 && (int) $user['is_active'] === 1) {
        if ((int) $userId === (int) $_SESSION['user_id']) {
            $conn->rollback();
            adminUsersRespond(409, ['success' => false, 'message' => 'You cannot remove your own administrator access.']);
        }
        $countResult = $conn->query('SELECT user_id FROM users WHERE position = 2 AND is_active = 1 FOR UPDATE');
        if (!$countResult) throw new Exception('Unable to check administrator accounts.');
        if ($countResult->num_rows <= 1) {
            $conn->rollback();
            adminUsersRespond(409, ['success' => false, 'message' => 'The last active administrator cannot be changed to a customer.']);
        }
    }

    if ($action === 'role') {
        if (
            $role === 1 &&
            (
                trim((string) $user['street']) === '' ||
                trim((string) $user['barangay']) === '' ||
                trim((string) $user['city']) === '' ||
                trim((string) $user['province']) === '' ||
                trim((string) $user['postal']) === ''
            )
        ) {
            $conn->rollback();
            adminUsersRespond(422, [
                'success' => false,
                'message' => 'Customer accounts require complete address details. Edit the account to add an address first.'
            ]);
        }
        $updateStmt = $conn->prepare('UPDATE users SET position = ? WHERE user_id = ?');
        if (!$updateStmt) throw new Exception('Unable to prepare role update.');
        $updateStmt->bind_param('ii', $role, $userId);
    } else {
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $middleName = trim((string) ($input['middle_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $username = trim((string) ($input['username'] ?? ''));
        $phone = trim((string) ($input['contact_no'] ?? ''));
        $street = trim((string) ($input['street'] ?? ''));
        $barangay = trim((string) ($input['barangay'] ?? ''));
        $city = trim((string) ($input['city'] ?? ''));
        $province = trim((string) ($input['province'] ?? ''));
        $postal = trim((string) ($input['postal'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');

        if (
            $firstName === '' || $lastName === '' || $username === '' ||
            $phone === '' ||
            ($role === 1 && (
                $street === '' || $barangay === '' || $city === '' ||
                $province === '' || $postal === ''
            )) ||
            strlen($firstName) > 100 || strlen($middleName) > 100 ||
            strlen($lastName) > 100 || strlen($username) > 50 ||
            strlen($phone) > 20 || strlen($street) > 150 ||
            strlen($barangay) > 100 || strlen($city) > 100 ||
            strlen($province) > 100 || strlen($postal) > 10
        ) {
            $conn->rollback();
            adminUsersRespond(422, ['success' => false, 'message' => 'Complete all required fields; customer accounts also need a full address. Keep each value within its allowed length.']);
        }

        if (
            ($password !== '' || $confirmPassword !== '') &&
            ($password === '' || strlen($password) < 8 || $password !== $confirmPassword)
        ) {
            $conn->rollback();
            $passwordError = $password === '' || $password !== $confirmPassword
                ? 'Passwords do not match.'
                : 'Password must be at least 8 characters.';
            adminUsersRespond(422, ['success' => false, 'message' => $passwordError]);
        }

        if ($password !== '') {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare(
                'UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, username = ?,
                    contact_no = ?, street = ?, barangay = ?, city = ?, province = ?, postal = ?,
                    password = ?, position = ? WHERE user_id = ?'
            );
            if (!$updateStmt) throw new Exception('Unable to prepare profile update.');
            $updateStmt->bind_param(
                'sssssssssssii',
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
                $role,
                $userId
            );
        } else {
            $updateStmt = $conn->prepare(
                'UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, username = ?,
                    contact_no = ?, street = ?, barangay = ?, city = ?, province = ?, postal = ?, position = ?
                 WHERE user_id = ?'
            );
            if (!$updateStmt) throw new Exception('Unable to prepare profile update.');
            $updateStmt->bind_param(
                'ssssssssssii',
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
                $role,
                $userId
            );
        }
    }

    if (!$updateStmt->execute()) {
        $duplicateUsername = $updateStmt->errno === 1062;
        $updateStmt->close();
        $conn->rollback();
        if ($duplicateUsername) adminUsersRespond(409, ['success' => false, 'message' => 'This username is already in use.']);
        throw new Exception('Unable to save account changes.');
    }
    $updateStmt->close();
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    error_log('Admin account update failed: ' . $error->getMessage());
    adminUsersRespond(500, ['success' => false, 'message' => 'Unable to save account changes.']);
}

adminUsersRespond(200, ['success' => true, 'role' => $role]);
