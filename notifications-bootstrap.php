<?php
function ensureNotificationTables(mysqli $conn)
{
    $existingTables = $conn->query(
        "SELECT COUNT(*) AS table_count FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name IN ('notifications', 'product_size_stock_alerts')"
    );
    if (!$existingTables) {
        error_log('Unable to verify notification schema: ' . $conn->error);
        return false;
    }
    $tableCount = (int) $existingTables->fetch_assoc()['table_count'];
    $existingTables->free();
    if ($tableCount === 2) return true;

    $notificationsSql = "CREATE TABLE IF NOT EXISTS notifications (
        notification_id bigint(20) NOT NULL AUTO_INCREMENT,
        recipient_user_id int(11) NOT NULL,
        notification_type varchar(40) NOT NULL,
        title varchar(150) NOT NULL,
        message varchar(500) NOT NULL,
        target_url varchar(255) NOT NULL,
        is_read tinyint(1) NOT NULL DEFAULT 0,
        created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (notification_id),
        KEY idx_notifications_recipient_read_date (recipient_user_id, is_read, created_at),
        CONSTRAINT fk_notifications_recipient FOREIGN KEY (recipient_user_id)
            REFERENCES users (user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($notificationsSql)) {
        error_log('Unable to ensure the notifications table: ' . $conn->error);
        return false;
    }

    $lowStockSql = "CREATE TABLE IF NOT EXISTS product_size_stock_alerts (
        product_id int(11) NOT NULL,
        size varchar(20) NOT NULL,
        is_low_stock tinyint(1) NOT NULL DEFAULT 0,
        last_stock int(11) NOT NULL DEFAULT 0,
        updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (product_id, size),
        CONSTRAINT fk_stock_alert_product FOREIGN KEY (product_id)
            REFERENCES products (product_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($lowStockSql)) {
        error_log('Unable to ensure the product size stock alert table: ' . $conn->error);
        return false;
    }
    return true;
}

function createNotification(mysqli $conn, $userId, $type, $title, $message, $targetUrl)
{
    $stmt = $conn->prepare(
        'INSERT INTO notifications (recipient_user_id, notification_type, title, message, target_url)
         VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        error_log('Unable to prepare notification insert: ' . $conn->error);
        return false;
    }
    $stmt->bind_param('issss', $userId, $type, $title, $message, $targetUrl);
    $success = $stmt->execute();
    if (!$success) error_log('Unable to create notification: ' . $stmt->error);
    $stmt->close();
    return $success;
}

function notifyActiveAdmins(mysqli $conn, $type, $title, $message, $targetUrl)
{
    $result = $conn->query('SELECT user_id FROM users WHERE position = 2 AND is_active = 1');
    if (!$result) {
        error_log('Unable to load administrators for notifications: ' . $conn->error);
        return false;
    }
    $adminIds = [];
    while ($row = $result->fetch_assoc()) $adminIds[] = (int) $row['user_id'];
    $result->free();
    foreach ($adminIds as $adminId) {
        if (!createNotification($conn, $adminId, $type, $title, $message, $targetUrl)) return false;
    }
    return true;
}

function syncLowStockNotification(mysqli $conn, $productId, $size, $productName, $stock, $threshold = 5)
{
    $stock = max(0, (int) $stock);
    $stateStmt = $conn->prepare(
        'SELECT is_low_stock FROM product_size_stock_alerts WHERE product_id = ? AND size = ? FOR UPDATE'
    );
    if (!$stateStmt) {
        error_log('Unable to prepare low-stock notification lookup: ' . $conn->error);
        return false;
    }
    $stateStmt->bind_param('is', $productId, $size);
    if (!$stateStmt->execute()) {
        error_log('Unable to load low-stock notification state: ' . $stateStmt->error);
        $stateStmt->close();
        return false;
    }
    $state = $stateStmt->get_result()->fetch_assoc();
    $stateStmt->close();

    if ($stock > $threshold) {
        $resetStmt = $conn->prepare(
            'INSERT INTO product_size_stock_alerts (product_id, size, is_low_stock, last_stock)
             VALUES (?, ?, 0, ?)
             ON DUPLICATE KEY UPDATE is_low_stock = 0, last_stock = VALUES(last_stock)'
        );
        if (!$resetStmt) {
            error_log('Unable to prepare low-stock alert reset: ' . $conn->error);
            return false;
        }
        $resetStmt->bind_param('isi', $productId, $size, $stock);
        $success = $resetStmt->execute();
        if (!$success) error_log('Unable to reset low-stock alert: ' . $resetStmt->error);
        $resetStmt->close();
        return $success;
    }

    if ($state && (int) $state['is_low_stock'] === 1) {
        $updateStmt = $conn->prepare(
            'UPDATE product_size_stock_alerts SET last_stock = ? WHERE product_id = ? AND size = ?'
        );
        if (!$updateStmt) {
            error_log('Unable to prepare low-stock alert update: ' . $conn->error);
            return false;
        }
        $updateStmt->bind_param('iis', $stock, $productId, $size);
        $success = $updateStmt->execute();
        if (!$success) error_log('Unable to update low-stock alert: ' . $updateStmt->error);
        $updateStmt->close();
        return $success;
    }

    $lowStmt = $conn->prepare(
        'INSERT INTO product_size_stock_alerts (product_id, size, is_low_stock, last_stock)
         VALUES (?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE is_low_stock = 1, last_stock = VALUES(last_stock)'
    );
    if (!$lowStmt) {
        error_log('Unable to prepare low-stock alert state: ' . $conn->error);
        return false;
    }
    $lowStmt->bind_param('isi', $productId, $size, $stock);
    $success = $lowStmt->execute();
    if (!$success) {
        error_log('Unable to save low-stock alert state: ' . $lowStmt->error);
        $lowStmt->close();
        return false;
    }
    $lowStmt->close();

    return notifyActiveAdmins(
        $conn,
        'low_stock',
        'Low stock: ' . $productName,
        'Only ' . $stock . ' unit(s) remain in size ' . $size . '.',
        'admin-prod.php?highlight-product=' . $productId . '&highlight-size=' . rawurlencode($size)
    );
}
