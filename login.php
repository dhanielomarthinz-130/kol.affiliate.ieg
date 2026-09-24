<?php
// login.php
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    $user = getCurrentUser();
    header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'packing.php'));
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
            header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'packing.php'));
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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">

    <div class="login-card">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="sidebar-brand-icon" style="width: 54px; height: 54px; margin: 0 auto 14px; border-radius: 14px;">
                <span class="material-symbols-outlined" style="font-size: 30px;">inventory_2</span>
            </div>
            <h1 style="font-size: 1.45rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">KOL PACKING STATION</h1>
            <p style="color: #64748b; font-size: 0.82rem; margin-top: 4px;">Perekaman & Kompresi Otomatis Video Hasil Packaging</p>
        </div>

        <?php if (!empty($error)): ?>
            <div style="background: #fff1f2; border: 1px solid #fecdd3; color: #be123c; padding: 10px 14px; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.85rem; display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-outlined" style="font-size: 18px; color: #e11d48;">error</span>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label" style="display: flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined" style="font-size: 16px; color: #2563eb;">person</span>
                    <span>Username</span>
                </label>
                <input type="text" name="username" id="inputUsername" class="form-control" placeholder="Masukkan username" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" style="display: flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined" style="font-size: 16px; color: #60a5fa;">lock</span>
                    <span>Password</span>
                </label>
                <input type="password" name="password" id="inputPassword" class="form-control" placeholder="Masukkan password" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 1rem; margin-top: 0.75rem;">
                <span>Masuk ke Sistem</span>
                <span class="material-symbols-outlined">arrow_forward</span>
            </button>
        </form>

        <div class="demo-accounts">
            <div style="font-weight: 700; margin-bottom: 6px; color: #475569; display: flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined" style="font-size: 16px; color: #f59e0b;">bolt</span>
                <span>Akun Cepat (Klik untuk isi otomatis):</span>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <span class="account-pill" onclick="fillLogin('operator', 'operator123')">
                    <span class="material-symbols-outlined" style="font-size: 15px;">badge</span>
                    <span>Operator: <b>operator</b></span>
                </span>
                <span class="account-pill" onclick="fillLogin('admin', 'admin123')">
                    <span class="material-symbols-outlined" style="font-size: 15px;">shield_person</span>
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
