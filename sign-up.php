<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authCsrfToken = $_SESSION['auth_csrf_token'] ?? '';
if ($authCsrfToken === '') {
    $authCsrfToken = bin2hex(random_bytes(32));
    $_SESSION['auth_csrf_token'] = $authCsrfToken;
}

$signupError = $_SESSION['signup_error'] ?? '';
unset($_SESSION['signup_error']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DCI Vault | Create Account</title>

    <!-- Bootstrap 5.3.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="style/style.css?v=dark-all-5">
    <style>
        .login-page {
            min-height: calc(100vh - 65px);
            background: var(--tertiary-color, #F8FAFC);
        }

        .login-layout {
            width: min(1120px, 100%);
            min-height: 620px;
            border: 1px solid var(--border-color, #E2E8F0);
            border-radius: 1rem;
            background: #FFFFFF;
            box-shadow: 0 1.5rem 4rem rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }

        .login-showcase {
            position: relative;
            isolation: isolate;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: clamp(2rem, 5vw, 4.5rem);
            color: #FFFFFF;
            background: linear-gradient(145deg, #0F172A 0%, #173B78 54%, #2563EB 100%);
            overflow: hidden;
        }

        .login-showcase::before,
        .login-showcase::after {
            position: absolute;
            z-index: -1;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 50%;
            content: "";
        }

        .login-showcase::before {
            width: 460px;
            height: 460px;
            top: 20%;
            right: -240px;
        }

        .login-showcase::after {
            width: 340px;
            height: 340px;
            top: 29%;
            right: -180px;
        }

        .showcase-kicker {
            color: #BFDBFE;
            letter-spacing: 0.15em;
        }

        .showcase-title {
            max-width: 440px;
            font-size: clamp(2.4rem, 4.2vw, 3.8rem);
            letter-spacing: -0.045em;
            line-height: 1.06;
        }

        .showcase-copy {
            max-width: 390px;
            color: rgba(255, 255, 255, 0.76);
            line-height: 1.7;
        }

        .collection-board {
            position: relative;
            max-width: 445px;
            padding: 1.25rem;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 1rem 2.5rem rgba(4, 12, 31, 0.18);
            backdrop-filter: blur(12px);
        }

        .collection-tile {
            min-height: 108px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            transition: transform 0.2s ease, background-color 0.2s ease;
        }

        .collection-tile:hover {
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.17);
        }

        .collection-icon {
            display: grid;
            width: 42px;
            height: 42px;
            place-items: center;
            border-radius: 0.75rem;
            background: rgba(255, 255, 255, 0.16);
            font-size: 1.2rem;
        }

        .login-form-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(2rem, 5vw, 5rem);
        }

        .login-form-content {
            width: 100%;
            max-width: 390px;
        }

        .login-mark {
            width: 54px;
            height: 54px;
            object-fit: cover;
            box-shadow: 0 0.5rem 1rem rgba(15, 23, 42, 0.12);
        }

        .login-field .input-group-text,
        .login-field .form-control {
            min-height: 48px;
            background-color: #F8FAFC;
            border-color: var(--border-color, #E2E8F0);
        }

        .login-field .input-group-text {
            color: var(--secondary-color, #64748B);
            padding-inline: 0.9rem;
        }

        .login-field .form-control:focus {
            background-color: #FFFFFF;
            border-color: var(--contrast-primary, #2563EB);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.12) !important;
        }

        .login-submit {
            min-height: 50px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }

        .login-submit:hover {
            background-color: var(--contrast-secondary, #1E40AF) !important;
            box-shadow: 0 0.6rem 1.2rem rgba(37, 99, 235, 0.22) !important;
            transform: translateY(-1px);
        }

        @media (max-width: 767.98px) {
            .login-page {
                padding: 1rem !important;
            }

            .login-layout {
                min-height: 0;
            }

            .login-showcase {
                gap: 1.5rem;
                padding: 2rem;
            }

            .showcase-title {
                max-width: 600px;
                font-size: clamp(2rem, 8vw, 3rem);
            }

            .collection-board {
                max-width: none;
                padding: 0.75rem;
            }

            .collection-tile {
                min-height: 82px;
                padding: 0.65rem !important;
            }

            .collection-icon {
                width: 34px;
                height: 34px;
                font-size: 1rem;
            }

            .login-form-panel {
                padding: 2.5rem 1.5rem;
            }
        }

        @media (max-width: 420px) {
            .collection-board {
                display: none !important;
            }

            .login-showcase {
                padding: 1.5rem;
            }

            .login-form-panel {
                padding: 2rem 1.25rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .collection-tile,
            .login-submit {
                transition: none;
            }
        }
    </style>
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

<body class="auth-page min-vh-100 d-flex flex-column bg-light">

    <!-- HEADER CONTAINER WITH BACK TO HOME BUTTON -->
    <header
        class="main-header d-flex flex-row align-items-center justify-content-between flex-shrink-0 px-3 px-md-4 py-2 bg-white border-bottom shadow-sm"
        style="min-height: 65px;">

        <!-- Brand Logo / Title -->
        <div class="main-name d-flex align-items-center gap-2">
            <img src="assets/logo.png" alt="DCI Vault" class="brand-logo rounded border" width="40" height="40">
            <span class="fw-bold fs-5 text-dark">DCI Vault</span>
        </div>

        <!-- Navigation Action -->
        <div>
            <a href="index.html"
                class="btn btn-outline-dark px-3 fw-semibold shadow-sm d-flex align-items-center gap-1">
                <i class="bi bi-bag me-1"></i><span>Continue shopping</span>
            </a>
        </div>
    </header>

    <main class="login-page flex-grow-1 d-flex align-items-center justify-content-center p-3 p-lg-4">
        <div class="login-layout row g-0">
            <section class="login-showcase col-md-6" aria-labelledby="showcaseTitle">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-5">
                        <span class="d-inline-grid place-items-center rounded-3 bg-white p-1">
                            <img src="assets/logo.png" alt="" width="34" height="34" class="rounded-2">
                        </span>
                        <span class="fw-bold fs-5">DCI Vault</span>
                    </div>
                    <div class="showcase-kicker text-uppercase small fw-semibold mb-3">Join the DCI Community</div>
                    <h1 id="showcaseTitle" class="showcase-title fw-bold mb-3">Wear the Silver with Pride.</h1>
                    <p class="showcase-copy mb-0">Create an account to access official DCI merch, track your orders, and
                        experience your favorite tech gear.</p>
                    <a href="index.html" class="text-decoration-none" style="color:#FFFFFF">
                        <div class="collection-board mt-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="fw-semibold">Explore the store</span>
                                <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                            </div>
                            <div class="row g-2">
                                <div class="col-4">
                                    <div class="collection-tile d-flex flex-column justify-content-between p-3">
                                        <span class="collection-icon"><i class="bi bi-person-standing"
                                                aria-hidden="true"></i></span>
                                        <span class="small fw-semibold">T-Shirts</span>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="collection-tile d-flex flex-column justify-content-between p-3">
                                        <span class="collection-icon"><i class="bi bi-layers" aria-hidden="true"></i></span>
                                        <span class="small fw-semibold">Outerwear</span>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="collection-tile d-flex flex-column justify-content-between p-3">
                                        <span class="collection-icon"><i class="bi bi-handbag"
                                                aria-hidden="true"></i></span>
                                        <span class="small fw-semibold">Accessories</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="d-flex align-items-center gap-2 small" style="color: rgba(255,255,255,0.72);">
                    <i class="bi bi-stars" aria-hidden="true"></i>
                    <span>Good finds for Tech-passionate people</span>
                </div>
            </section>

            <section class="login-form-panel col-md-6" aria-labelledby="loginTitle">
                <div class="login-form-content">
                    <div class="d-flex align-items-center gap-2 mb-4 d-md-none">
                        <img src="assets/logo.png" alt="DCI Vault" class="login-mark rounded-3">
                        <span class="fw-bold fs-5 text-dark">DCI Vault</span>
                    </div>
                    <div class="mb-4">
                        <h2 id="loginTitle" class="h2 fw-bold text-dark mb-2">Create Your Account</h2>
                        <p class="text-secondary mb-0">Sign up to shop your favorites seamlessly.</p>
                    </div>

                    <!-- Form Fields -->
                    <form id="signUpForm" action="account.php" method="post">
                        <input type="hidden" name="auth_csrf_token" value="<?php echo htmlspecialchars($authCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                        <!-- Full Name -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Full Name</label>
                            <div class="row g-2">
                                <div class="col-4">
                                    <input type="text" class="form-control shadow-none" id="new-fnInput" name="fName"
                                        placeholder="First Name" maxlength="100" required>
                                </div>
                                <div class="col-4">
                                    <input type="text" class="form-control shadow-none" id="new-mnInput" name="mName"
                                        placeholder="Middle Name" maxlength="100">
                                </div>
                                <div class="col-4">
                                    <input type="text" class="form-control shadow-none" id="new-lnInput" name="lName"
                                        placeholder="Last Name" maxlength="100" required>
                                </div>
                            </div>
                            <div class="form-text text-danger small text-start mt-1" id="new-fnlnVerify"></div>
                        </div>

                        <!-- Username -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Username</label>
                            <input type="text" class="form-control shadow-none" id="new-userInput" name="uName"
                                placeholder="Choose a username" maxlength="50" required>
                        </div>

                        <!-- Contact Number -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Contact Number</label>
                            <input type="tel" class="form-control shadow-none" id="new-contactInput" name="conNumber"
                                placeholder="e.g., 09123456789" maxlength="20" pattern="[0-9+() .-]{7,20}" required>
                        </div>

                        <!-- Structured Address Fields -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Address Details</label>

                            <!-- Row 1: House/Unit & Street -->
                            <div class="row g-2 mb-2">
                                <div class="col-12">
                                    <input type="text" class="form-control shadow-none" id="new-streetInput" name="addStreet"
                                        placeholder="House / Unit No., Street" maxlength="150" required>
                                </div>
                            </div>

                            <!-- Row 2: Barangay & City/Municipality -->
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <input type="text" class="form-control shadow-none" id="new-barangayInput" name="addBrgy"
                                        placeholder="Barangay" maxlength="100" required>
                                </div>
                                <div class="col-6">
                                    <input type="text" class="form-control shadow-none" id="new-cityInput" name="addCity"
                                        placeholder="City / Municipality" maxlength="100" required>
                                </div>
                            </div>

                            <!-- Row 3: Province & Postal Code -->
                            <div class="row g-2">
                                <div class="col-8">
                                    <input type="text" class="form-control shadow-none" id="new-provinceInput" name="addProv"
                                        placeholder="Province" maxlength="100" required>
                                </div>
                                <div class="col-4">
                                    <input type="text" class="form-control shadow-none" id="new-zipInput" name="addPost"
                                        placeholder="Postal Code" maxlength="10" pattern="[A-Za-z0-9 -]{1,10}" required>
                                </div>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Password</label>
                            <input type="password" class="form-control shadow-none" id="new-pwInput" name="pWord"
                                placeholder="Create your password" autocomplete="new-password" minlength="8" maxlength="72" required>
                            <div class="form-text text-danger small text-start mt-1" id="new-pwVerify"></div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-3">
                            <label for="new-confirmPwInput" class="form-label small fw-semibold text-secondary">Confirm Password</label>
                            <input type="password" class="form-control shadow-none" id="new-confirmPwInput"
                                name="confirmPassword" placeholder="Re-enter your password" autocomplete="new-password" minlength="8" maxlength="72" required>
                            <div class="form-text text-danger small text-start mt-1" id="new-confirmPwVerify"></div>
                        </div>

                        <!-- General Response Message Target -->
                        <div class="text-danger small mb-3 text-center" id="new-resInput" role="alert" aria-live="polite"><?php
                            echo htmlspecialchars($signupError, ENT_QUOTES, 'UTF-8');
                        ?></div>

                        <!-- Submit Button -->
                        <button type="submit" id="new-signButton" name="signUp" value="1"
                            class="btn text-white w-100 fw-semibold py-2 shadow-sm hover-lift"
                            style="background-color: var(--contrast-primary, #2563EB);">
                            Sign up
                        </button>
                    </form>

                    <div class="text-center text-secondary small mt-4">
                        Already have an account?
                        <a href="login.php" class="fw-semibold text-decoration-none"
                            style="color: var(--contrast-primary, #2563EB);">Sign in.</a>
                    </div>
                    <div class="d-flex align-items-center justify-content-center gap-2 text-secondary mt-4 small">
                        <i class="bi bi-shield-check" style="color: var(--contrast-primary, #2563EB);"
                            aria-hidden="true"></i>
                        <span>Your account details stay private</span>
                    </div>
                </div>
        </div>

        <script src="backend/app.js"></script>
    <script src="backend/theme.js?v=6"></script>
</body>

</html>