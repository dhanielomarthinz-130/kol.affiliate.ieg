<?php
// login.php
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    $user = getCurrentUser();
    header('Location: ' . ($user['role'] === 'admin' ? 'admin' : 'packing'));
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
            header('Location: ' . ($user['role'] === 'admin' ? 'admin' : 'packing'));
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
        <div style="text-align: center; margin-bottom: 1.75rem;">
            <div class="sidebar-brand-icon" style="width: 44px; height: 44px; margin: 0 auto 12px; border-radius: 10px;">
                <span class="material-symbols-outlined" style="font-size: 24px;">inventory_2</span>
            </div>
            <h1 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">KOL PACKING STATION</h1>
            <p style="color: #64748b; font-size: 0.78rem; margin-top: 3px;">Perekaman & Audit Otomatis Video Hasil Packaging</p>
        </div>

        <?php if (!empty($error)): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 9px 12px; border-radius: 6px; margin-bottom: 1.25rem; font-size: 0.82rem; display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-outlined" style="font-size: 17px; color: #dc2626;">error</span>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" id="inputUsername" class="form-control" placeholder="Masukkan username akun" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" id="inputPassword" class="form-control" placeholder="Masukkan password" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 0.92rem; margin-top: 0.5rem;">
                <span>Masuk ke Sistem</span>
                <span class="material-symbols-outlined" style="font-size: 17px;">arrow_forward</span>
            </button>
        </form>

        <div class="demo-accounts">
            <div style="font-weight: 700; font-size: 0.72rem; margin-bottom: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em;">
                Akun Cepat (Klik untuk isi):
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <span class="account-pill" onclick="fillLogin('operator', 'operator123')">
                    <span class="material-symbols-outlined" style="font-size: 14px; color: #64748b;">badge</span>
                    <span>Operator: <b>operator</b></span>
                </span>
                <span class="account-pill" onclick="fillLogin('admin', 'admin123')">
                    <span class="material-symbols-outlined" style="font-size: 14px; color: #64748b;">shield_person</span>
                    <span>Admin: <b>admin</b></span>
                </span>
            </div>
        </div>
    </div>

    <script>
        function fillLogin(user, pass) {
            document.getElementById('inputUsername').value = user;
            document.getElementById('inputPassword').value = pass;
        }
    </script>
</body>
</html>
