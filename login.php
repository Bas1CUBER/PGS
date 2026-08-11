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
        $error = "Invalid user ID or password.";
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

    <style>
        :root {
            --pgs-gold: #d4a843;
            --pgs-gold-light: #e8c76a;
            --pgs-blue: #0b4aa2;
            --pgs-blue-dark: #083a7f;
            --pgs-blue-deep: #062f66;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            height: 100%;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1a5fd6 0%, #0b4aa2 50%, #062f66 100%);
            overflow: hidden;
        }

        .login-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* Left Side - Branding Area */
        .login-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 40px 60px;
            position: relative;
        }

        .trc-logo-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
        }

        .trc-logo-area img {
            max-width: min(680px, 50vw);
            height: auto;
            filter: drop-shadow(0 6px 16px rgba(0,0,0,0.3));
        }

        .building-area {
            margin-top: auto;
            margin-left: -60px;
            margin-right: -10px;
            margin-bottom: -40px;
            position: relative;
            z-index: 1;
        }

        .building-area img {
            width: 120%;
            max-height: 55vh;
            object-fit: cover;
            object-position: center bottom;
        }

        /* Right Side - Login Panel */
        .login-right {
            width: min(480px, 40vw);
            min-width: 380px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            position: relative;
            z-index: 10;
        }

        .login-panel {
            background: #ffffff;
            border: 4px solid var(--pgs-blue-deep);
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.35);
            padding: 35px 30px 30px;
            width: 100%;
            max-width: 420px;
        }

        .login-logos {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
        }

        .login-logos img {
            height: 90px;
            width: auto;
            object-fit: contain;
        }

        .login-title {
            text-align: center;
            font-weight: 800;
            letter-spacing: .05em;
            color: var(--pgs-gold);
            font-size: 1.8rem;
            line-height: 1.3;
            margin: 15px 0 25px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 0.95rem;
            text-transform: none;
        }

        .form-control {
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            background: #ffffff;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--pgs-blue);
            box-shadow: 0 0 0 0.25rem rgba(11, 74, 162, 0.15);
        }

        .form-control::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: var(--pgs-blue);
        }

        .btn-login {
            background: #ffffff;
            color: #333;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 14px 16px;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            transition: all 0.2s ease;
            margin-top: 10px;
        }

        .btn-login:hover {
            background: #333;
            color: #ffffff;
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 12px 14px;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .alert-danger { background-color: #ffe6e6; color: #c41e3a; }
        .alert-success { background-color: #e6f7e6; color: #28a745; }

        .privacy-section {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .privacy-text {
            font-size: 0.75rem;
            color: #6b7280;
            line-height: 1.6;
            text-align: center;
        }

        .privacy-link-wrapper {
            position: relative;
            display: inline-block;
        }

        .privacy-link-text {
            color: var(--pgs-blue);
            text-decoration: underline;
            cursor: pointer;
            font-weight: 600;
        }

        .privacy-link-wrapper:hover .privacy-link-text {
            color: var(--pgs-blue-dark);
        }

        .privacy-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            width: 320px;
            max-width: 90vw;
            background: #ffffff;
            border: 2px solid var(--pgs-blue);
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.25);
            z-index: 1000;
            text-align: left;
            opacity: 0;
            pointer-events: none;
            transition: all 0.25s ease;
            margin-bottom: 10px;
        }

        .privacy-link-wrapper:hover .privacy-tooltip {
            opacity: 1;
            pointer-events: auto;
        }

        .privacy-tooltip-title {
            font-weight: 700;
            color: var(--pgs-blue);
            font-size: 0.85rem;
            margin-bottom: 8px;
        }

        .privacy-tooltip-content {
            font-size: 0.75rem;
            color: #374151;
            line-height: 1.6;
        }

        /* Chatbot Support */
        .chatbot {
            position: fixed;
            bottom: 22px;
            right: 22px;
            z-index: 999;
        }

        @keyframes slowBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        @keyframes pulseGlow {
            0% { box-shadow: 0 0 0 0 rgba(11, 74, 162, 0.6); }
            70% { box-shadow: 0 0 0 16px rgba(11, 74, 162, 0); }
            100% { box-shadow: 0 0 0 0 rgba(11, 74, 162, 0); }
        }

        .chatbot-btn {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background-color: var(--pgs-blue);
            color: #fff;
            border: none;
            font-size: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            animation: slowBounce 2.5s ease-in-out infinite, pulseGlow 2.8s ease-out infinite;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
        }

        .support-widget {
            position: absolute;
            bottom: 70px;
            right: 0;
            width: 260px;
            background: #ffffff;
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            border: 1px solid rgba(11, 74, 162, 0.08);
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
            transition: all 0.25s ease;
        }

        /* Caret pointing to the floating button */
        .support-widget::after {
            content: '';
            position: absolute;
            bottom: -7px;
            right: 22px;
            width: 14px;
            height: 14px;
            background: #ffffff;
            border-right: 1px solid rgba(11, 74, 162, 0.08);
            border-bottom: 1px solid rgba(11, 74, 162, 0.08);
            transform: rotate(45deg);
        }

        .chatbot:hover .support-widget,
        .chatbot.open .support-widget {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .support-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 12px;
        }

        .support-title {
            font-weight: 800;
            font-size: 0.95rem;
            margin: 0;
            color: var(--pgs-blue);
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .support-status {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.7rem;
            font-weight: 600;
            color: #16a34a;
            white-space: nowrap;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.18);
            animation: statusPulse 2s ease-in-out infinite;
        }

        @keyframes statusPulse {
            0%, 100% { box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.18); }
            50% { box-shadow: 0 0 0 7px rgba(34, 197, 94, 0.05); }
        }

        .support-divider {
            height: 1px;
            background: #eef2f7;
            margin-bottom: 8px;
        }

        .support-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            padding: 8px;
            color: #333;
            border-radius: 10px;
            text-decoration: none;
            transition: background-color 0.18s ease, color 0.18s ease;
        }

        .support-item:hover {
            background-color: #eef4fc;
            color: var(--pgs-blue);
        }

        .support-item + .support-item {
            margin-top: 2px;
        }

        .support-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #e8f0ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            color: var(--pgs-blue);
            flex-shrink: 0;
            transition: background-color 0.18s ease;
        }

        .support-item:hover .support-icon {
            background-color: #d9e6ff;
        }

        /* Responsive Design */
        @media (max-width: 1100px) {
            .login-right {
                width: min(420px, 45vw);
                min-width: 350px;
            }
        }

        /* Ultrawide / very wide screens — keep the panel compact, logo area balanced */
        @media (min-width: 1920px) {
            .login-left { padding: 40px 80px; }
            .login-panel { max-width: 440px; }
            .trc-logo-area img { max-width: min(720px, 46vw); }
        }

        @media (max-width: 900px) {
            body { overflow: auto; }
            .login-container {
                flex-direction: column;
            }
            .login-left {
                padding: 30px;
                min-height: auto;
            }
            .top-text {
                text-align: center;
                font-size: clamp(18px, 4.5vw, 28px);
                letter-spacing: 0.08em;
                text-shadow:
                    0 1px 0 #b8922f,
                    0 2px 0 #a6822a,
                    0 3px 0 #967225,
                    0 4px 4px rgba(0,0,0,0.4);
            }
            .trc-logo-area {
                align-items: center;
            }
            .trc-logo-area img {
                max-width: min(450px, 75vw);
            }
            .building-area {
                margin-left: -30px;
                margin-right: -60px;
                margin-bottom: -40px;
            }
            .building-area img {
                max-height: 40vh;
                width: 115%;
            }
            .login-right {
                width: 100%;
                min-width: auto;
                padding: 30px;
            }
            .login-panel {
                max-width: 400px;
            }
        }

        @media (max-width: 480px) {
            .login-left { padding: 20px; }
            .login-right { padding: 20px; }
            .login-panel { padding: 25px 20px; }
            .login-title { font-size: 1.5rem; }
            .login-logos img { height: 70px; }
            .trc-logo-area { margin-top: 20px; }
            .trc-logo-area img { max-width: 88vw; }
        }

        /* Very small screens / short-height landscape */
        @media (max-width: 480px) and (max-height: 500px) {
            .login-left { display: none; }
            .login-container { flex-direction: column; }
            .login-right {
                width: 100%;
                min-width: 0;
                padding: 16px;
                align-items: flex-start;
            }
            .login-panel { padding: 20px 16px; }
            .login-logos img { height: 48px; }
            .login-title { font-size: 1.2rem; margin: 10px 0 16px; }
            .privacy-section { margin-top: 16px; padding-top: 12px; }
            .chatbot { bottom: 12px; right: 12px; }
            .chatbot-btn { width: 44px; height: 44px; font-size: 20px; }
        }

        /* Short screens (laptops with small vertical space) */
        @media (max-height: 700px) and (min-width: 481px) {
            .login-panel { padding: 24px 28px 20px; }
            .login-logos img { height: 64px; }
            .login-title { font-size: 1.5rem; margin: 10px 0 18px; }
            .login-left { justify-content: flex-start; }
            .trc-logo-area { margin-top: 16px; }
            .trc-logo-area img { max-width: min(560px, 48vw); }
            .building-area img { max-height: 42vh; }
            .privacy-section { margin-top: 18px; padding-top: 14px; }
        }

        /* Tall / narrow portrait phones */
        @media (min-height: 800px) and (max-width: 480px) {
            .login-container { min-height: 100svh; }
            .login-left { min-height: auto; }
            .login-panel { max-width: 100%; }
            .building-area { display: none; }
        }

        /* Very tall screens */
        @media (min-height: 1000px) {
            .login-container { min-height: 100vh; }
            .login-panel { padding: 40px 34px 34px; }
            .login-logos img { height: 100px; }
            .trc-logo-area { margin-top: 60px; }
        }
    </style>
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

                <form action="login.php" method="POST">
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
    <script>
        function togglePassword() {
            const input = document.getElementById('loginPassword');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            const toggle = document.querySelector('.password-toggle');
            if (isPassword) {
                toggle.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
            } else {
                toggle.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
            }
        }

        // Support widget: toggle on button click (helps touch devices where hover is unavailable)
        (function() {
            const chatbot = document.querySelector('.chatbot');
            const btn = document.getElementById('chatbotToggleBtn');
            if (!chatbot || !btn) return;
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                chatbot.classList.toggle('open');
            });
            document.addEventListener('click', function(e) {
                if (!chatbot.contains(e.target)) {
                    chatbot.classList.remove('open');
                }
            });
        })();
    </script>
</body>
</html>
