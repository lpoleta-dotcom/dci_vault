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

function cleanupExistingAdminCustomerData(mysqli $conn)
{
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'notifications-bootstrap.php';
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'id-verification.php';

    if (!ensureNotificationTables($conn)) {
        throw new Exception('Unable to prepare notification cleanup for existing administrators.');
    }
    if (!$conn->begin_transaction()) throw new Exception('Unable to start cleanup for existing administrators.');

    try {
        $legacyAdminsResult = $conn->query(
            "SELECT u.user_id, u.id_document
             FROM users u
             WHERE u.position = 2
               AND (
                   (u.id_document IS NOT NULL AND u.id_document <> '')
                   OR u.verification_status <> 'Pending'
                   OR EXISTS (SELECT 1 FROM orders o WHERE o.user_id = u.user_id)
                   OR EXISTS (
                       SELECT 1 FROM notifications n
                       WHERE n.recipient_user_id = u.user_id
                         AND n.notification_type IN ('order_placed', 'order_status')
                         AND n.target_url LIKE 'cstmr-order.php?highlight-order=%'
                   )
               )
             ORDER BY u.user_id
             FOR UPDATE"
        );
        if (!$legacyAdminsResult) throw new Exception('Unable to find existing administrator customer records.');

        $legacyAdmins = [];
        $idDocuments = [];
        while ($admin = $legacyAdminsResult->fetch_assoc()) {
            $adminId = (int) $admin['user_id'];
            $legacyAdmins[] = $adminId;
            if (!empty($admin['id_document'])) $idDocuments[] = $admin['id_document'];
        }
        $legacyAdminsResult->free();
        if (!$legacyAdmins) {
            if (!$conn->commit()) throw new Exception('Unable to complete administrator cleanup check.');
            return ['admins' => 0, 'orders' => 0, 'id_documents' => []];
        }

        $adminIdList = implode(',', $legacyAdmins);
        $orderCountResult = $conn->query(
            'SELECT COUNT(*) AS order_count FROM orders WHERE user_id IN (' . $adminIdList . ')'
        );
        if (!$orderCountResult) throw new Exception('Unable to count existing administrator orders.');
        $orderCount = (int) $orderCountResult->fetch_assoc()['order_count'];
        $orderCountResult->free();

        $inventoryResult = $conn->query(
            "SELECT oi.variant_id, SUM(oi.quantity) AS quantity,
                    pv.product_id, pv.size, p.name AS product_name
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.order_id
             JOIN product_variants pv ON pv.variant_id = oi.variant_id
             JOIN products p ON p.product_id = pv.product_id
             WHERE o.user_id IN (" . $adminIdList . ")
               AND o.status = 'Processing'
               AND oi.variant_id IS NOT NULL
             GROUP BY oi.variant_id, pv.product_id, pv.size, p.name"
        );
        if (!$inventoryResult) throw new Exception('Unable to load existing administrator reserved inventory.');
        $variants = [];
        $restoreStmt = $conn->prepare(
            'UPDATE product_variants SET stock_quantity = stock_quantity + ?
             WHERE variant_id = ? AND stock_quantity <= 2147483647 - ?'
        );
        if (!$restoreStmt) throw new Exception('Unable to prepare existing administrator inventory restoration.');
        while ($item = $inventoryResult->fetch_assoc()) {
            $quantity = (int) $item['quantity'];
            $variantId = (int) $item['variant_id'];
            $restoreStmt->bind_param('iii', $quantity, $variantId, $quantity);
            if (!$restoreStmt->execute() || $restoreStmt->affected_rows !== 1) {
                $restoreStmt->close();
                throw new Exception('Unable to safely restore existing administrator reserved inventory.');
            }
            $variants[] = [
                'product_id' => (int) $item['product_id'],
                'size' => $item['size'],
                'product_name' => $item['product_name']
            ];
        }
        $restoreStmt->close();
        $inventoryResult->free();

        if ($variants) {
            $stockLookup = $conn->prepare(
                'SELECT SUM(pv.stock_quantity) AS stock_quantity
                 FROM product_variants pv
                 JOIN products p ON p.product_id = pv.product_id AND p.is_active = 1
                 JOIN categories c ON c.category_id = p.category_id AND c.is_active = 1
                 WHERE pv.product_id = ? AND pv.size = ? AND pv.is_active = 1'
            );
            if (!$stockLookup) throw new Exception('Unable to prepare stock notification lookup.');
            $stockKeys = [];
            foreach ($variants as $variant) {
                $stockKey = $variant['product_id'] . ':' . $variant['size'];
                if (isset($stockKeys[$stockKey])) continue;
                $stockKeys[$stockKey] = true;
                $productId = $variant['product_id'];
                $size = $variant['size'];
                $stockLookup->bind_param('is', $productId, $size);
                if (!$stockLookup->execute()) throw new Exception('Unable to check restored product stock.');
                $stockRow = $stockLookup->get_result()->fetch_assoc();
                if ($stockRow && $stockRow['stock_quantity'] !== null && !syncLowStockNotification(
                    $conn,
                    $productId,
                    $size,
                    $variant['product_name'],
                    (int) $stockRow['stock_quantity']
                )) {
                    throw new Exception('Unable to update stock notifications.');
                }
            }
            $stockLookup->close();
        }

        $deleteNotificationsStmt = $conn->prepare(
            "DELETE FROM notifications
             WHERE (
                 target_url LIKE 'cstmr-order.php?highlight-order=%'
                 OR target_url LIKE 'admin-order.php?highlight-order=%'
             )
             AND CAST(SUBSTRING_INDEX(target_url, '=', -1) AS UNSIGNED)
                 IN (SELECT order_id FROM orders WHERE user_id IN (" . $adminIdList . "))"
        );
        if (!$deleteNotificationsStmt) throw new Exception('Unable to prepare existing order notification cleanup.');
        if (!$deleteNotificationsStmt->execute()) throw new Exception('Unable to delete existing order notifications.');
        $deleteNotificationsStmt->close();

        $deleteOwnOrderNotificationsStmt = $conn->prepare(
            "DELETE FROM notifications
             WHERE recipient_user_id IN (" . $adminIdList . ")
               AND notification_type IN ('order_placed', 'order_status')
               AND target_url LIKE 'cstmr-order.php?highlight-order=%'"
        );
        if (!$deleteOwnOrderNotificationsStmt) throw new Exception('Unable to prepare existing customer notification cleanup.');
        if (!$deleteOwnOrderNotificationsStmt->execute()) {
            throw new Exception('Unable to delete existing customer notifications.');
        }
        $deleteOwnOrderNotificationsStmt->close();

        $deleteOrdersStmt = $conn->query(
            'DELETE FROM orders WHERE user_id IN (' . $adminIdList . ')'
        );
        if (!$deleteOrdersStmt) throw new Exception('Unable to delete existing administrator orders.');

        $clearVerificationStmt = $conn->query(
            "UPDATE users
             SET id_document = NULL, verification_status = 'Pending'
             WHERE user_id IN (" . $adminIdList . ") AND position = 2"
        );
        if (!$clearVerificationStmt) throw new Exception('Unable to clear existing administrator ID verification data.');

        if (!$conn->commit()) throw new Exception('Unable to complete cleanup for existing administrators.');
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }

    foreach ($idDocuments as $idDocument) {
        if (!deleteVerificationIdUpload($idDocument)) {
            error_log('Existing administrator ID verification image could not be deleted.');
            throw new Exception('The administrator customer records were cleaned, but an ID verification image could not be deleted.');
        }
    }

    return ['admins' => count($legacyAdmins), 'orders' => $orderCount, 'id_documents' => $idDocuments];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $cleanup = cleanupExistingAdminCustomerData($conn);
    } catch (Throwable $error) {
        error_log('Existing administrator customer-history cleanup failed: ' . $error->getMessage());
        adminUsersRespond(500, ['success' => false, 'message' => 'Unable to clean existing administrator customer records.']);
    }
    $result = $conn->query(
        'SELECT u.user_id, u.first_name, u.middle_name, u.last_name, u.username,
                u.contact_no, u.street, u.barangay, u.city, u.province, u.postal,
                u.position, u.is_active, u.verification_status,
                (u.id_document IS NOT NULL AND u.id_document <> \'\') AS has_id_document,
                COUNT(o.order_id) AS order_count
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
        $row['has_id_document'] = (bool) $row['has_id_document'];
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

if ($action === 'verification') {
    $verificationStatus = $input['verification_status'] ?? '';
    if (!in_array($verificationStatus, ['Approved', 'Rejected'], true)) {
        adminUsersRespond(422, ['success' => false, 'message' => 'Choose a valid verification decision.']);
    }

    if (!$conn->begin_transaction()) {
        adminUsersRespond(500, ['success' => false, 'message' => 'Unable to start ID verification review.']);
    }
    try {
        $verificationStmt = $conn->prepare(
            'SELECT position, verification_status, id_document
             FROM users WHERE user_id = ? FOR UPDATE'
        );
        if (!$verificationStmt) throw new Exception('Unable to prepare verification lookup.');
        $verificationStmt->bind_param('i', $userId);
        if (!$verificationStmt->execute()) throw new Exception('Unable to load verification details.');
        $user = $verificationStmt->get_result()->fetch_assoc();
        $verificationStmt->close();
        if (!$user) {
            $conn->rollback();
            adminUsersRespond(404, ['success' => false, 'message' => 'User account was not found.']);
        }
        if ((int) $user['position'] !== 1) {
            $conn->rollback();
            adminUsersRespond(409, ['success' => false, 'message' => 'Only customer accounts can be ID verified.']);
        }
        if ($user['verification_status'] !== 'Pending') {
            $conn->rollback();
            adminUsersRespond(409, ['success' => false, 'message' => 'Only pending ID submissions can be reviewed.']);
        }
        if (empty($user['id_document'])) {
            $conn->rollback();
            adminUsersRespond(409, ['success' => false, 'message' => 'This customer has not submitted an ID image.']);
        }

        $updateVerificationStmt = $conn->prepare(
            'UPDATE users SET verification_status = ? WHERE user_id = ? AND position = 1'
        );
        if (!$updateVerificationStmt) throw new Exception('Unable to prepare verification decision.');
        $updateVerificationStmt->bind_param('si', $verificationStatus, $userId);
        if (!$updateVerificationStmt->execute()) throw new Exception('Unable to save verification decision.');
        $updateVerificationStmt->close();
        if (!$conn->commit()) throw new Exception('Unable to commit verification decision.');
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('Admin ID verification update failed: ' . $error->getMessage());
        adminUsersRespond(500, ['success' => false, 'message' => 'Unable to save the verification decision.']);
    }

    adminUsersRespond(200, ['success' => true, 'verification_status' => $verificationStatus]);
}

if (!in_array($action, ['update', 'role'], true)) {
    adminUsersRespond(400, ['success' => false, 'message' => 'Choose a valid account action.']);
}

$role = filter_var($input['role'] ?? null, FILTER_VALIDATE_INT);
if (!in_array($role, [1, 2], true)) adminUsersRespond(422, ['success' => false, 'message' => 'Choose a valid account role.']);

if ($role === 2) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'notifications-bootstrap.php';
    if (!ensureNotificationTables($conn)) {
        adminUsersRespond(500, ['success' => false, 'message' => 'Unable to prepare notification cleanup for administrator promotion.']);
    }
}
require_once __DIR__ . DIRECTORY_SEPARATOR . 'id-verification.php';

if (!$conn->begin_transaction()) {
    adminUsersRespond(500, ['success' => false, 'message' => 'Unable to start the account update.']);
}
$promotedIdDocument = '';
$removedOrderCount = 0;
try {
    $userStmt = $conn->prepare(
        'SELECT position, is_active, street, barangay, city, province, postal, id_document
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

    $promotingCustomer = $role === 2 && (int) $user['position'] === 1;
    if ($promotingCustomer) {
        $promotedIdDocument = (string) ($user['id_document'] ?? '');
        $ordersStmt = $conn->prepare(
            'SELECT order_id, status FROM orders WHERE user_id = ? FOR UPDATE'
        );
        if (!$ordersStmt) throw new Exception('Unable to prepare customer order cleanup.');
        $ordersStmt->bind_param('i', $userId);
        if (!$ordersStmt->execute()) throw new Exception('Unable to load customer orders for cleanup.');
        $ordersResult = $ordersStmt->get_result();
        $orderCount = 0;
        $processingOrderIds = [];
        while ($order = $ordersResult->fetch_assoc()) {
            $orderCount++;
            if ($order['status'] === 'Processing') $processingOrderIds[] = (int) $order['order_id'];
        }
        $ordersStmt->close();
        $removedOrderCount = $orderCount;

        if ($processingOrderIds) {
            $processingOrderIdList = implode(',', $processingOrderIds);
            $inventoryResult = $conn->query(
                'SELECT oi.variant_id, SUM(oi.quantity) AS quantity,
                        pv.product_id, pv.size, p.name AS product_name
                 FROM order_items oi
                 JOIN product_variants pv ON pv.variant_id = oi.variant_id
                 JOIN products p ON p.product_id = pv.product_id
                 WHERE oi.order_id IN (' . $processingOrderIdList . ')
                   AND oi.variant_id IS NOT NULL
                 GROUP BY oi.variant_id, pv.product_id, pv.size, p.name'
            );
            if (!$inventoryResult) throw new Exception('Unable to load reserved customer order inventory.');
            $variants = [];
            $restoreStmt = $conn->prepare(
                'UPDATE product_variants SET stock_quantity = stock_quantity + ?
                 WHERE variant_id = ? AND stock_quantity <= 2147483647 - ?'
            );
            if (!$restoreStmt) throw new Exception('Unable to prepare reserved inventory restoration.');
            while ($item = $inventoryResult->fetch_assoc()) {
                $quantity = (int) $item['quantity'];
                $variantId = (int) $item['variant_id'];
                $restoreStmt->bind_param('iii', $quantity, $variantId, $quantity);
                if (!$restoreStmt->execute() || $restoreStmt->affected_rows !== 1) {
                    $restoreStmt->close();
                    throw new Exception('Unable to safely restore reserved customer order inventory.');
                }
                $variants[] = [
                    'product_id' => (int) $item['product_id'],
                    'size' => $item['size'],
                    'product_name' => $item['product_name']
                ];
            }
            $restoreStmt->close();
            $inventoryResult->free();

            $stockLookup = $conn->prepare(
                'SELECT SUM(pv.stock_quantity) AS stock_quantity
                 FROM product_variants pv
                 JOIN products p ON p.product_id = pv.product_id AND p.is_active = 1
                 JOIN categories c ON c.category_id = p.category_id AND c.is_active = 1
                 WHERE pv.product_id = ? AND pv.size = ? AND pv.is_active = 1'
            );
            if (!$stockLookup) throw new Exception('Unable to prepare product stock notification lookup.');
            $stockKeys = [];
            foreach ($variants as $variant) {
                $stockKey = $variant['product_id'] . ':' . $variant['size'];
                if (isset($stockKeys[$stockKey])) continue;
                $stockKeys[$stockKey] = true;
                $productId = $variant['product_id'];
                $size = $variant['size'];
                $stockLookup->bind_param('is', $productId, $size);
                if (!$stockLookup->execute()) throw new Exception('Unable to check product stock notification level.');
                $stockRow = $stockLookup->get_result()->fetch_assoc();
                if ($stockRow && $stockRow['stock_quantity'] !== null && !syncLowStockNotification(
                    $conn,
                    $productId,
                    $size,
                    $variant['product_name'],
                    (int) $stockRow['stock_quantity']
                )) {
                    throw new Exception('Unable to save product stock notifications.');
                }
            }
            $stockLookup->close();
        }

        $deleteCustomerNotificationsStmt = $conn->prepare(
            'DELETE FROM notifications WHERE recipient_user_id = ?'
        );
        if (!$deleteCustomerNotificationsStmt) throw new Exception('Unable to prepare customer notification cleanup.');
        $deleteCustomerNotificationsStmt->bind_param('i', $userId);
        if (!$deleteCustomerNotificationsStmt->execute()) {
            throw new Exception('Unable to delete customer notifications.');
        }
        $deleteCustomerNotificationsStmt->close();

        $deleteOrderNotificationsStmt = $conn->prepare(
            "DELETE FROM notifications
             WHERE target_url LIKE 'admin-order.php?highlight-order=%'
               AND CAST(SUBSTRING_INDEX(target_url, '=', -1) AS UNSIGNED)
                   IN (SELECT order_id FROM orders WHERE user_id = ?)"
        );
        if (!$deleteOrderNotificationsStmt) throw new Exception('Unable to prepare order notification cleanup.');
        $deleteOrderNotificationsStmt->bind_param('i', $userId);
        if (!$deleteOrderNotificationsStmt->execute()) {
            throw new Exception('Unable to delete order notifications.');
        }
        $deleteOrderNotificationsStmt->close();

        $deleteOrdersStmt = $conn->prepare('DELETE FROM orders WHERE user_id = ?');
        if (!$deleteOrdersStmt) throw new Exception('Unable to prepare customer order deletion.');
        $deleteOrdersStmt->bind_param('i', $userId);
        if (!$deleteOrdersStmt->execute()) throw new Exception('Unable to delete customer orders.');
        $deleteOrdersStmt->close();
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
    if ($promotingCustomer) {
        $clearVerificationStmt = $conn->prepare(
            "UPDATE users SET id_document = NULL, verification_status = 'Pending'
             WHERE user_id = ? AND position = 2"
        );
        if (!$clearVerificationStmt) throw new Exception('Unable to prepare ID verification cleanup.');
        $clearVerificationStmt->bind_param('i', $userId);
        if (!$clearVerificationStmt->execute() || $clearVerificationStmt->affected_rows !== 1) {
            throw new Exception('Unable to clear customer ID verification data.');
        }
        $clearVerificationStmt->close();
    }
    if (!$conn->commit()) throw new Exception('Unable to complete the account update.');
} catch (Throwable $error) {
    $conn->rollback();
    error_log('Admin account update failed: ' . $error->getMessage());
    adminUsersRespond(500, ['success' => false, 'message' => 'Unable to save account changes.']);
}

if ($role === 2 && $promotedIdDocument !== '' && !deleteVerificationIdUpload($promotedIdDocument)) {
    error_log('Administrator promotion completed but the customer ID image could not be deleted.');
    adminUsersRespond(500, [
        'success' => false,
        'message' => 'The account was promoted and its customer records were removed, but the ID image could not be deleted. Contact support.'
    ]);
}

adminUsersRespond(200, [
    'success' => true,
    'role' => $role,
    'removed_orders' => $role === 2 ? $removedOrderCount : 0
]);
