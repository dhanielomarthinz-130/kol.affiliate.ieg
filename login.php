<?php
// login.php
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    $user = getCurrentUser();
    header('Location: ' . (in_array($user['role'], ['admin', 'superadmin'], true) ? 'admin' : 'packing'));
    exit;
}

$error = '';
$isMaintenance = isMaintenanceActive();

$db = getDB();
$operators = [];
try {
    $stmtOps = $db->query("SELECT id, username, name FROM users WHERE role = 'operator' AND (is_active = 1 OR is_active IS NULL) ORDER BY name ASC");
    $operators = $stmtOps->fetchAll();
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi!';
    } else {
        $maintError = null;
        if (loginUser($username, $password, $maintError)) {
            $user = getCurrentUser();
            header('Location: ' . (in_array($user['role'], ['admin', 'superadmin'], true) ? 'admin' : 'packing'));
            exit;
        } else {
            // Jika maintenance aktif dan akun bukan Daniel / Superadmin, arahkan langsung ke halaman Maintenance
            if (!empty($maintError) || ($isMaintenance && strtolower(trim($username)) !== 'daniel')) {
                header('Location: maintenance_notice');
                exit;
            }
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
    <title>Login — KOL Packing Station</title>
    <meta name="description" content="Login ke sistem perekaman &amp; audit otomatis video packaging KOL Affiliate.">
    <link rel="icon" type="image/svg+xml" href="assets/image/favicon.svg">
    <link rel="icon" type="image/png" href="assets/image/favicon.png">
    <link rel="shortcut icon" href="favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --font: 'Plus Jakarta Sans', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --blue-500: #3b82f6;
            --blue-600: #2563eb;
            --blue-700: #1d4ed8;
            --text-primary: #0f172a;
            --text-secondary: #334155;
            --text-muted: #64748b;
            --text-dim: #94a3b8;
            --border: #e2e8f0;
            --card-bg: #ffffff;
            --input-bg: #ffffff;
        }

        body {
            font-family: var(--font);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background-color: #f8fafc;
            background-image:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(219, 234, 254, 0.75) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 85% 95%, rgba(224, 231, 255, 0.6) 0%, transparent 55%),
                radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 28px 28px;
            position: relative;
            overflow-x: hidden;
            color: var(--text-primary);
        }

        /* Ambient subtle pastel orbs */
        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(85px);
            pointer-events: none;
            z-index: 0;
            animation: orbFloat 14s ease-in-out infinite;
        }
        body::before {
            width: 420px; height: 420px;
            background: rgba(191, 219, 254, 0.65);
            top: -100px; left: 50%;
            transform: translateX(-50%);
        }
        body::after {
            width: 320px; height: 320px;
            background: rgba(221, 214, 254, 0.55);
            bottom: -60px; right: -50px;
            animation-delay: -7s;
        }

        @keyframes orbFloat {
            0%,100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(-20px); }
        }

        /* Card */
        .login-wrap {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 426px;
        }

        .login-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 2.25rem 2rem 2rem;
            box-shadow:
                0 20px 45px -12px rgba(15, 23, 42, 0.08),
                0 2px 8px rgba(15, 23, 42, 0.04);
            position: relative;
            overflow: hidden;
            animation: cardIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(16px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Brand */
        .login-brand {
            text-align: center;
            margin-bottom: 1.35rem;
        }

        .login-title {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.03em;
            line-height: 1.2;
            white-space: nowrap;
        }
        .login-subtitle {
            font-size: 0.81rem;
            color: var(--text-muted);
            margin-top: 5px;
            line-height: 1.45;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Divider */
        .divider {
            height: 1px;
            background: #f1f5f9;
            margin: 1.2rem 0 1.3rem;
        }

        /* Error alert */
        .error-alert {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 0.75rem 1rem;
            border-radius: 11px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            font-size: 0.82rem;
            font-weight: 500;
            margin-bottom: 1.25rem;
            animation: shake 0.4s ease;
        }
        @keyframes shake {
            0%,100% { transform:translateX(0); }
            20%,60% { transform:translateX(-4px); }
            40%,80% { transform:translateX(4px); }
        }

        /* Form */
        .form-group { margin-bottom: 1.15rem; }

        .form-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
            letter-spacing: 0.01em;
            white-space: nowrap;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 13px;
            color: #94a3b8;
            font-size: 18px;
            pointer-events: none;
            transition: color 0.15s;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.65rem;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 11px;
            font-size: 0.88rem;
            font-family: var(--font);
            color: var(--text-primary);
            outline: none;
            transition: all 0.2s ease;
        }
        .form-input::placeholder { color: #94a3b8; }
        .form-input:focus {
            background: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 3.5px rgba(37, 99, 235, 0.12);
        }
        .input-wrap:focus-within .input-icon { color: #2563eb; }

        .eye-btn {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            transition: all 0.15s;
        }
        .eye-btn:hover { color: #334155; background: #f1f5f9; }

        /* Submit */
        .submit-btn {
            width: 100%;
            padding: 0.85rem;
            margin-top: 1.15rem;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
            border: none;
            border-radius: 11px;
            font-size: 0.92rem;
            font-weight: 700;
            font-family: var(--font);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: -0.01em;
            box-shadow: 0 8px 18px -3px rgba(37, 99, 235, 0.35);
            white-space: nowrap;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .submit-btn span {
            white-space: nowrap;
        }
        .submit-btn:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            transform: translateY(-2px);
            box-shadow: 0 12px 22px -4px rgba(37, 99, 235, 0.45);
        }
        .submit-btn:active { transform: translateY(0); }

        /* Mode Switcher Tabs */
        .login-tabs {
            display: flex;
            background: #f1f5f9;
            padding: 5px;
            border-radius: 14px;
            margin-bottom: 1.35rem;
            border: 1px solid #e2e8f0;
            gap: 6px;
        }
        .tab-btn {
            flex: 1 1 0;
            min-width: 0;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 12px;
            border: none;
            background: transparent;
            color: #64748b;
            font-size: 0.84rem;
            font-weight: 600;
            font-family: var(--font);
            border-radius: 10px;
            cursor: pointer;
            white-space: nowrap;
            user-select: none;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .tab-btn span {
            white-space: nowrap;
            line-height: 1;
        }
        .tab-btn:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.6);
        }
        .tab-btn.active {
            background: #ffffff;
            color: #2563eb;
            font-weight: 700;
            border: 1px solid #e2e8f0;
            box-shadow: 0 3px 10px rgba(15, 23, 42, 0.08), 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .tab-btn .tab-icon {
            font-size: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            flex-shrink: 0;
        }

        @media (max-width: 440px) {
            body { padding: 1rem 0.75rem; }
            .login-card { padding: 1.75rem 1.25rem 1.5rem; }
            .tab-btn { font-size: 0.79rem; gap: 6px; padding: 0 8px; }
            .login-subtitle { font-size: 0.76rem; }
        }

        /* PIN Mode Elements */
        .pin-dots-container {
            display: flex;
            justify-content: center;
            gap: 14px;
            margin: 1.1rem 0 0.8rem;
        }
        .pin-dot-view {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid #cbd5e1;
            background: #f8fafc;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .pin-dot-view.filled {
            background: #2563eb;
            border-color: #2563eb;
            box-shadow: 0 0 10px rgba(37, 99, 235, 0.45);
            transform: scale(1.18);
        }
        .login-numpad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 0.85rem;
        }
        .login-pin-key {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 700;
            font-family: var(--font);
            padding: 12px 0;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
            user-select: none;
        }
        .login-pin-key:hover {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1d4ed8;
            transform: translateY(-1px);
        }
        .login-pin-key:active {
            transform: translateY(1px);
            background: #dbeafe;
        }
        .login-pin-key.key-empty {
            visibility: hidden;
            pointer-events: none;
        }
    </style>
</head>
<body>

    <div class="login-wrap">
        <div class="login-card">

            <!-- Brand -->
            <div class="login-brand">
                <h1 class="login-title">KOL Packing</h1>
                <p class="login-subtitle">Perekaman &amp; Audit Otomatis Video Packaging</p>
            </div>

            <div class="divider"></div>

            <?php if (!empty($error)): ?>
                <div class="error-alert" role="alert">
                    <span class="material-symbols-outlined" style="font-size:18px;color:#dc2626;flex-shrink:0;">error</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Tabs: Standard Login vs PIN -->
            <div class="login-tabs">
                <button type="button" class="tab-btn active" id="tabStandard" onclick="switchLoginMode('standard')">
                    <span class="material-symbols-outlined tab-icon">lock</span>
                    <span>Password Akun</span>
                </button>
                <button type="button" class="tab-btn" id="tabPin" onclick="switchLoginMode('pin')">
                    <span class="material-symbols-outlined tab-icon">dialpad</span>
                    <span>PIN Operator</span>
                </button>
            </div>

            <!-- Standard Form (Mendukung Password maupun PIN) -->
            <form method="POST" action="login" id="loginForm" novalidate>
                <div class="form-group">
                    <label class="form-label" for="inputUsername">Username</label>
                    <div class="input-wrap">
                        <span class="material-symbols-outlined input-icon">person</span>
                        <input
                            type="text"
                            name="username"
                            id="inputUsername"
                            class="form-input"
                            placeholder="Masukkan username"
                            required
                            autofocus
                            autocomplete="username"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="inputPassword">Password atau PIN</label>
                    <div class="input-wrap">
                        <span class="material-symbols-outlined input-icon">lock</span>
                        <input
                            type="password"
                            name="password"
                            id="inputPassword"
                            class="form-input"
                            placeholder="Masukkan password atau PIN (cth: 0000)"
                            required
                            autocomplete="current-password"
                            style="padding-right: 2.8rem;"
                        >
                        <button type="button" class="eye-btn" id="togglePwdBtn" onclick="togglePwd()" tabindex="-1" title="Tampilkan/Sembunyikan">
                            <span class="material-symbols-outlined" id="eyeIcon" style="font-size:18px;">visibility</span>
                        </button>
                    </div>
                    <div style="font-size:0.74rem; color:var(--text-muted); margin-top:7px; display:flex; align-items:center; gap:6px; white-space:nowrap;">
                        <span class="material-symbols-outlined" style="font-size:15px; color:#2563eb; flex-shrink:0;">info</span>
                        <span style="white-space:nowrap;">Bisa login menggunakan password atau PIN 4-digit</span>
                    </div>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <span class="material-symbols-outlined" style="font-size:19px;">login</span>
                    <span>Masuk ke Sistem</span>
                </button>
            </form>

            <!-- PIN Cepat Numpad Form (Touchscreen / Station friendly) -->
            <form method="POST" action="login" id="pinForm" style="display:none;" novalidate>
                <div class="form-group">
                    <label class="form-label" for="pinUsernameSelect">Pilih Akun Operator</label>
                    <div class="input-wrap">
                        <span class="material-symbols-outlined input-icon">badge</span>
                        <?php if (!empty($operators)): ?>
                            <select name="username" id="pinUsernameSelect" class="form-input" style="appearance:none; -webkit-appearance:none; cursor:pointer; text-overflow:ellipsis; white-space:nowrap; padding-right:2.5rem;">
                                <?php foreach ($operators as $op): ?>
                                    <option value="<?= htmlspecialchars($op['username']) ?>">
                                        <?= htmlspecialchars($op['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="material-symbols-outlined" style="position:absolute; right:12px; pointer-events:none; color:var(--text-muted); font-size:18px;">expand_more</span>
                        <?php else: ?>
                            <input
                                type="text"
                                name="username"
                                id="pinUsernameSelect"
                                class="form-input"
                                placeholder="Username operator"
                                value="operator"
                                required
                            >
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Hidden Password/PIN input -->
                <input type="hidden" name="password" id="pinSecretInput" value="">

                <!-- PIN Dots Display -->
                <div style="text-align:center;">
                    <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.04em; white-space:nowrap;">
                        Masukkan PIN 4-Digit
                    </div>
                    <div class="pin-dots-container">
                        <div class="pin-dot-view" id="dot0"></div>
                        <div class="pin-dot-view" id="dot1"></div>
                        <div class="pin-dot-view" id="dot2"></div>
                        <div class="pin-dot-view" id="dot3"></div>
                    </div>
                </div>

                <!-- Touch Numpad -->
                <div class="login-numpad">
                    <?php foreach ([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                        <button type="button" class="login-pin-key<?= ($k === '') ? ' key-empty' : '' ?>" onclick="pressPinKey('<?= $k ?>')">
                            <?= ($k === '⌫') ? '<span class="material-symbols-outlined" style="font-size:20px;">backspace</span>' : $k ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="submit-btn" id="pinSubmitBtn" style="margin-top:1.1rem;">
                    <span class="material-symbols-outlined" style="font-size:19px;">login</span>
                    <span>Masuk dengan PIN</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        let currentMode = 'standard';
        let currentPin = '';
        const PIN_LEN = 4;

        function switchLoginMode(mode) {
            currentMode = mode;
            const tabStd = document.getElementById('tabStandard');
            const tabPin = document.getElementById('tabPin');
            const formStd = document.getElementById('loginForm');
            const formPin = document.getElementById('pinForm');

            if (mode === 'pin') {
                tabStd.classList.remove('active');
                tabPin.classList.add('active');
                formStd.style.display = 'none';
                formPin.style.display = 'block';
                currentPin = '';
                updatePinDisplay();
            } else {
                tabPin.classList.remove('active');
                tabStd.classList.add('active');
                formPin.style.display = 'none';
                formStd.style.display = 'block';
                document.getElementById('inputPassword').focus();
            }
        }

        function pressPinKey(k) {
            if (k === '⌫') {
                currentPin = currentPin.slice(0, -1);
            } else if (k !== '' && currentPin.length < PIN_LEN) {
                currentPin += k;
            }
            updatePinDisplay();

            // Auto submit saat 4 digit lengkap
            if (currentPin.length === PIN_LEN) {
                setTimeout(() => {
                    const pinForm = document.getElementById('pinForm');
                    if (pinForm) {
                        const btn = document.getElementById('pinSubmitBtn');
                        btn.disabled = true;
                        btn.innerHTML = `
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="animation:spin 0.8s linear infinite;">
                                <circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.3)" stroke-width="3"/>
                                <path d="M12 2a10 10 0 0 1 10 10" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
                            </svg>
                            <span>Memverifikasi PIN...</span>
                        `;
                        pinForm.submit();
                    }
                }, 160);
            }
        }

        function updatePinDisplay() {
            document.getElementById('pinSecretInput').value = currentPin;
            for (let i = 0; i < PIN_LEN; i++) {
                const dot = document.getElementById('dot' + i);
                if (dot) {
                    if (i < currentPin.length) {
                        dot.classList.add('filled');
                    } else {
                        dot.classList.remove('filled');
                    }
                }
            }
        }

        // Support physical keyboard on PIN mode
        document.addEventListener('keydown', function(e) {
            if (currentMode !== 'pin') return;

            if (e.key >= '0' && e.key <= '9') {
                e.preventDefault();
                pressPinKey(e.key);
            } else if (e.key === 'Backspace') {
                e.preventDefault();
                pressPinKey('⌫');
            } else if (e.key === 'Enter' && currentPin.length === PIN_LEN) {
                e.preventDefault();
                document.getElementById('pinForm').submit();
            }
        });

        function togglePwd() {
            const inp = document.getElementById('inputPassword');
            const ico = document.getElementById('eyeIcon');
            const isHidden = inp.type === 'password';
            inp.type = isHidden ? 'text' : 'password';
            ico.textContent = isHidden ? 'visibility_off' : 'visibility';
        }

        // Submit loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = `
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="animation:spin 0.8s linear infinite;">
                    <circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.3)" stroke-width="3"/>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
                </svg>
                <span>Memverifikasi...</span>
            `;
        });
    </script>
    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</body>
</html>
