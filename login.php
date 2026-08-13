<?php
require_once __DIR__ . '/src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $_SESSION['flash_error'] = 'Invalid or expired form token. Please try again.';
    header("Location: " . BASE_URL . "/login");
    exit();
}

if (isset($_POST['email'], $_POST['password'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $isActive = 1;
        if (array_key_exists('is_active', $user)) {
            $isActive = (int)$user['is_active'];
        }
        if ($isActive !== 1) {
            $error = "Account is deactivated. Please contact the administrator.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_success'] = "Login successful! Welcome back.";

            if ($user['role'] === 'admin') {
                header("Location: " . BASE_URL . "/admin_dashboard");
            } elseif ($user['role'] === 'employee') {
                header("Location: " . BASE_URL . "/employee_dashboard");
            } elseif ($user['role'] === 'focal') {
                header("Location: " . BASE_URL . "/focal_dashboard");
            } else {
                header("Location: " . BASE_URL . "/employee_dashboard");
            }
            exit;
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Performance Governance System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= asset('css/pages/login.css') ?>">
</head>

<body>
    <div class="login-container">
        <!-- Left Side -->
        <div class="login-left">
            
            <div class="trc-logo-area">
                <img src="img/final_login.png" alt="Department of Health TRC San Fernando, La Union">
            </div>

            <div class="building-area">
                <img src="img/bldg_img55.png" alt="TRC Building">
            </div>
        </div>

        <!-- Right Side - Login Panel -->
        <div class="login-right">
            <div class="login-panel">
                <div class="login-logos">
                    <img src="img/final_logo.png" alt="PGS Login Logo">
                </div>

                <div class="login-title">WELCOME<br>PGS CHAMPIONS</div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?= h($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($_SESSION['login_success'])): ?>
                    <div class="alert alert-success">
                        <?= h($_SESSION['login_success']) ?>
                    </div>
                    <?php unset($_SESSION['login_success']); ?>
                <?php endif; ?>

                <form action="login" method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="email" class="form-control" placeholder="Enter your username" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="position-relative">
                            <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Enter your password" required>
                            <span class="password-toggle" onclick="togglePassword()" title="Show password">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-login w-100">LOGIN</button>

                    <div class="privacy-section">
                        <p class="privacy-text">
                            By logging in, you agree that your professional data will be collected, processed, and stored in accordance with the 
                            <span class="privacy-link-wrapper">
                                <a class="privacy-link-text" href="https://privacy.gov.ph/data-privacy-act/" target="_blank" rel="noopener noreferrer">Data Privacy Act of 2012 (RA 10173)</a>
                                <span class="privacy-tooltip">
                                    <span class="privacy-tooltip-title">Data Privacy Act of 2012</span>
                                    <span class="privacy-tooltip-content">
                                        The Data Privacy Act of 2012 (Republic Act No. 10173) is a Philippine law that protects the fundamental human right of privacy, particularly in relation to personal data processed in information and communication systems. It applies to both government and private organizations that collect, store, and process personal information. The law ensures that personal data is secured, handled properly, and used only for legitimate purposes with the consent of the individual (data subject). It also establishes the National Privacy Commission to enforce compliance, regulate data processing practices, and protect individuals against unauthorized access, misuse, or disclosure of their personal information.
                                    </span>
                                </span>
                            </span>
                            and applicable government regulations.
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Chatbot Support -->
    <div class="chatbot">
        <div class="support-widget">
            <div class="support-header">
                <div class="support-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
                    Customer Support
                </div>
                <span class="support-status"><span class="status-dot"></span>Available</span>
            </div>
            <div class="support-divider"></div>
            <a class="support-item" href="tel:+639171143562">
                <div class="support-icon"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
                <div>+63 917 114 3562</div>
            </a>
            <a class="support-item" href="https://m.me/doh.sflutrc" target="_blank" rel="noopener noreferrer">
                <div class="support-icon"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></div>
                <div>Via Messenger</div>
            </a>
            <a class="support-item" href="mailto:doh.sflutrc@gmail.com">
                <div class="support-icon"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
                <div>doh.sflutrc@gmail.com</div>
            </a>
        </div>
        <button class="chatbot-btn" id="chatbotToggleBtn" type="button" aria-label="Open customer support"><svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= asset('js/pages/login_1.js') ?>"></script>
</body>
</html>
