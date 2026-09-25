<?php
// login.php
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    $user = getCurrentUser();
    header('Location: ' . (in_array($user['role'], ['admin', 'superadmin'], true) ? 'admin' : 'packing'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi!';
    } else {
        if (loginUser($username, $password)) {
            $user = getCurrentUser();
            header('Location: ' . (in_array($user['role'], ['admin', 'superadmin'], true) ? 'admin' : 'packing'));
            exit;
        } else {
            $error = 'Username atau password salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - KOL Affiliate Video Packing Station</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="login-page">

    <div class="login-card">
        <!-- Brand Header -->
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="login-brand-icon">
                <span class="material-symbols-outlined" style="font-size: 26px;">inventory_2</span>
            </div>
            <h1 class="login-title">KOL PACKING</h1>
            <p class="login-subtitle">Perekaman & Audit Otomatis Video Packaging</p>
            <div>
                <span class="login-badge-status">
                    <span class="login-status-dot"></span>
                    <span>Sistem Siap Operasi</span>
                </span>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="login-error-alert">
                <span class="material-symbols-outlined" style="font-size: 18px; color: #ef4444;">error</span>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="login" id="loginForm">
            <div class="login-form-group">
                <label class="login-label" for="inputUsername">Username Akun</label>
                <div class="login-input-box">
                    <span class="material-symbols-outlined login-input-icon">person</span>
                    <input type="text" 
                           name="username" 
                           id="inputUsername" 
                           class="login-input" 
                           placeholder="Ketik username akun" 
                           required 
                           autofocus 
                           autocomplete="username">
                </div>
            </div>

            <div class="login-form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                    <label class="login-label" for="inputPassword" style="margin-bottom: 0;">Password</label>
                </div>
                <div class="login-input-box">
                    <span class="material-symbols-outlined login-input-icon">lock</span>
                    <input type="password" 
                           name="password" 
                           id="inputPassword" 
                           class="login-input" 
                           placeholder="Ketik password" 
                           required 
                           autocomplete="current-password">
                    <button type="button" 
                            class="login-pwd-toggle" 
                            id="togglePasswordBtn" 
                            onclick="togglePasswordVisibility()" 
                            title="Tampilkan / Sembunyikan Password"
                            tabindex="-1">
                        <span class="material-symbols-outlined" id="eyeIcon" style="font-size: 18px;">visibility</span>
                    </button>
                </div>
            </div>

            <button type="submit" class="login-submit-btn" id="submitBtn">
                <span>Masuk ke Sistem</span>
                <span class="material-symbols-outlined" style="font-size: 18px;">arrow_forward</span>
            </button>
        </form>

        <!-- Quick Demo Accounts -->
        <div class="login-demo-section">
            <div class="login-demo-title">
                <span class="material-symbols-outlined" style="font-size: 14px; color: #60a5fa;">touch_app</span>
                <span>Akses Cepat (Klik untuk Mengisi):</span>
            </div>
            <div class="login-demo-grid">
                <div class="login-quick-card" onclick="fillLogin('operator', 'operator123')">
                    <div class="login-quick-avatar operator">
                        <span class="material-symbols-outlined">badge</span>
                    </div>
                    <div class="login-quick-info">
                        <div class="login-quick-name">Operator</div>
                        <div class="login-quick-role">operator</div>
                    </div>
                </div>

                <div class="login-quick-card" onclick="fillLogin('admin', 'admin123')">
                    <div class="login-quick-avatar admin">
                        <span class="material-symbols-outlined">shield_person</span>
                    </div>
                    <div class="login-quick-info">
                        <div class="login-quick-name">Admin</div>
                        <div class="login-quick-role">admin</div>
                    </div>
                </div>

                <div class="login-quick-card" onclick="fillLogin('Daniel', 'Dh@niel0')">
                    <div class="login-quick-avatar" style="background:#fef3c7; color:#b45309;">
                        <span class="material-symbols-outlined">verified_user</span>
                    </div>
                    <div class="login-quick-info">
                        <div class="login-quick-name">Daniel</div>
                        <div class="login-quick-role" style="color:#b45309; font-weight:700;">superadmin</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security footer -->
        <div class="login-footer-meta">
            <span class="material-symbols-outlined" style="font-size: 14px; color: #10b981;">verified_user</span>
            <span>Local Encrypted Session • Zero Data Leak</span>
        </div>
    </div>

    <script>
        function fillLogin(user, pass) {
            const userInput = document.getElementById('inputUsername');
            const passInput = document.getElementById('inputPassword');
            userInput.value = user;
            passInput.value = pass;
            userInput.focus();
        }

        function togglePasswordVisibility() {
            const passInput = document.getElementById('inputPassword');
            const eyeIcon = document.getElementById('eyeIcon');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                eyeIcon.textContent = 'visibility_off';
            } else {
                passInput.type = 'password';
                eyeIcon.textContent = 'visibility';
            }
        }
    </script>
</body>
</html>
