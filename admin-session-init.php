<?php
if (session_status() === PHP_SESSION_NONE) {
    $adminSessionCookieName = 'DCI_ADMIN_SESSION';
    session_name($adminSessionCookieName);
    session_start();

    $legacyAdminSession = null;
    $legacySessionId = $_COOKIE['PHPSESSID'] ?? '';
    if (
        (int) ($_SESSION['position'] ?? 0) !== 2 &&
        $legacySessionId !== '' &&
        $legacySessionId !== session_id()
    ) {
        $adminSessionId = session_id();
        session_write_close();

        session_name('PHPSESSID');
        session_id($legacySessionId);
        session_start();
        if (
            (int) ($_SESSION['position'] ?? 0) === 2 &&
            filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false
        ) {
            $legacyAdminSession = [
                'user_id' => (int) $_SESSION['user_id'],
                'username' => (string) ($_SESSION['username'] ?? ''),
                'position' => 2
            ];
            unset(
                $_SESSION['user_id'],
                $_SESSION['username'],
                $_SESSION['position'],
                $_SESSION['admin_csrf_token'],
                $_SESSION['customer_csrf_token'],
                $_SESSION['account_csrf']
            );
        }
        session_write_close();

        session_name($adminSessionCookieName);
        session_id($adminSessionId);
        session_start();
        if ($legacyAdminSession !== null) {
            $_SESSION = $legacyAdminSession;
        }
    }
}
