<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-session-init.php';

$adminCsrfToken = $_SESSION['admin_login_csrf_token'] ?? '';
if (!is_string($adminCsrfToken) || $adminCsrfToken === '') {
    $adminCsrfToken = bin2hex(random_bytes(32));
    $_SESSION['admin_login_csrf_token'] = $adminCsrfToken;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ((int) ($_SESSION['position'] ?? 0) === 2) {
    header('Location: admin-dashboard.php');
    exit();
}

$loginError = '';
if (($_GET['error'] ?? '') === 'invalid') {
    $loginError = 'Invalid administrator username or password.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['admin_login_csrf_token'] ?? '';
    $expectedOrigin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    if (
        !is_string($submittedToken) ||
        !hash_equals($adminCsrfToken, $submittedToken) ||
        empty($_SERVER['HTTP_ORIGIN']) ||
        $expectedOrigin === '://' ||
        !hash_equals($expectedOrigin, $_SERVER['HTTP_ORIGIN'])
    ) {
        http_response_code(403);
        $loginError = 'Your sign-in request could not be verified. Refresh the page and try again.';
    } elseif (
        !isset($_POST['uName'], $_POST['pWord']) ||
        !is_string($_POST['uName']) ||
        !is_string($_POST['pWord']) ||
        trim($_POST['uName']) === '' ||
        $_POST['pWord'] === '' ||
        strlen(trim($_POST['uName'])) > 50 ||
        strlen($_POST['pWord']) > 72
    ) {
        http_response_code(400);
        $loginError = 'Enter a valid username and password.';
    } else {
        $loginUsername = trim($_POST['uName']);
        $loginPassword = $_POST['pWord'];

        require_once __DIR__ . DIRECTORY_SEPARATOR . 'connect.php';

        $loginStmt = $conn->prepare(
            'SELECT user_id, username, password, position, is_active FROM users WHERE username = ?'
        );
        if ($loginStmt === false) {
            error_log('Admin login query prepare failed: ' . $conn->error);
            $loginError = 'Sign-in is temporarily unavailable. Please try again.';
        } else {
            $loginStmt->bind_param('s', $loginUsername);
            if (!$loginStmt->execute()) {
                error_log('Admin login query failed: ' . $loginStmt->error);
                $loginError = 'Sign-in is temporarily unavailable. Please try again.';
            } else {
                $user = $loginStmt->get_result()->fetch_assoc();
                if (
                    $user &&
                    (int) $user['position'] === 2 &&
                    (int) $user['is_active'] === 1 &&
                    password_verify($loginPassword, $user['password'])
                ) {
                    session_regenerate_id(true);
                    $_SESSION = [];
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['position'] = (int) $user['position'];
                    $loginStmt->close();
                    $conn->close();
                    session_write_close();
                    header('Location: admin-dashboard.php');
                    exit();
                }

                $loginError = 'Invalid administrator username or password.';
            }

            $loginStmt->close();
        }

        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DCI Vault | Admin Sign In</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style/style.css?v=admin-signin-1">
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('dci-vault-theme');
                var preferred = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                document.documentElement.dataset.theme = saved === 'dark' || saved === 'light' ? saved : preferred;
            } catch (error) {}
        })();
    </script>
</head>

<body class="admin-login-page min-vh-100 d-flex flex-column">
    <header class="main-header admin-login-header">
        <a class="admin-login-brand" href="index.html" aria-label="DCI Vault home">
            <img src="assets/logo.png" alt="" width="42" height="42">
            <span>DCI <strong>Vault</strong></span>
        </a>
        <a href="login.php" class="admin-login-guest">
            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
            <span>Customer sign in</span>
        </a>
    </header>

    <main class="admin-login-main">
        <section class="admin-login-card" aria-labelledby="adminLoginTitle">
            <div class="admin-login-card-brand">
                <span class="admin-login-emblem" aria-hidden="true">
                    <img src="assets/logo.png" alt="">
                </span>
                <div class="admin-login-wordmark">DCI <span>Vault</span></div>
            </div>

            <h1 id="adminLoginTitle">Administrator Sign In</h1>
            <p class="admin-login-intro">Use an account assigned the Administrator role.</p>

            <form action="admin-login.php" method="post" class="admin-login-form">
                <input type="hidden" name="admin_login_csrf_token" value="<?php echo htmlspecialchars($adminCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="admin-login-field">
                    <label for="adminUsername"><i class="bi bi-person-fill" aria-hidden="true"></i>Username</label>
                    <div class="admin-login-input-wrap">
                        <input type="text" id="adminUsername" name="uName" placeholder="Enter your username..."
                            autocomplete="username" maxlength="50" required autofocus>
                    </div>
                </div>
                <div class="admin-login-field">
                    <label for="adminPassword"><i class="bi bi-lock-fill" aria-hidden="true"></i>Password</label>
                    <div class="admin-login-input-wrap">
                        <input type="password" id="adminPassword" name="pWord" placeholder="Enter your password..."
                            autocomplete="current-password" maxlength="72" required>
                        <button type="button" class="admin-login-password-toggle" data-password-toggle="adminPassword"
                            aria-label="Show password" aria-pressed="false">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <?php if ($loginError !== ''): ?>
                    <div class="admin-login-error" role="alert" aria-live="polite">
                        <?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <button type="submit" class="admin-login-submit">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                    <span>Sign In to Admin</span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <div class="admin-login-divider"><span>or</span></div>
            <p class="admin-login-security">
                <i class="bi bi-shield-fill-check" aria-hidden="true"></i>
                <span>Admin access is restricted to authorized administrator accounts.</span>
            </p>
        </section>
    </main>

    <script src="backend/app.js?v=admin-password-toggle-1"></script>
    <script src="backend/theme.js?v=6"></script>
</body>

</html>
