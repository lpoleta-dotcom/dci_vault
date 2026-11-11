<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'customer-session-init.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$redirect = (int) ($_SESSION['position'] ?? 0) === 2 ? 'admin-login.php' : 'login.php';
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookie['path'],
        $cookie['domain'],
        $cookie['secure'],
        $cookie['httponly']
    );
}

session_destroy();
header('Location: ' . $redirect);
exit();
