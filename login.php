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
    <title>Login — IEG Packaging KOL Affiliate</title>
    <meta name="description" content="Portal login resmi sistem perekaman dan audit packaging IEG KOL Affiliate.">
    <link rel="icon" type="image/svg+xml" href="assets/image/favicon.svg">
    <link rel="icon" type="image/png" href="assets/image/favicon.png">
    <link rel="shortcut icon" href="favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --font: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-light: #eff6ff;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --text-dim: #94a3b8;
            --border-ui: #e2e8f0;
            --card-glass: rgba(255, 255, 255, 0.94);
        }

        body.login-body {
            font-family: var(--font);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.25rem;
            background-color: #f8fafc;
            background-image:
                radial-gradient(ellipse 80% 60% at 50% -12%, rgba(219, 234, 254, 0.8) 0%, transparent 60%),
                radial-gradient(ellipse 65% 55% at 90% 95%, rgba(224, 231, 255, 0.7) 0%, transparent 55%),
                radial-gradient(ellipse 55% 45% at 10% 90%, rgba(207, 250, 254, 0.5) 0%, transparent 50%),
                radial-gradient(rgba(148, 163, 184, 0.35) 1.2px, transparent 1.2px);
            background-size: 100% 100%, 100% 100%, 100% 100%, 26px 26px;
            position: relative;
            overflow-x: hidden;
            color: var(--text-dark);
        }

        /* Ambient Glowing Color Orbs */
        .login-ambient-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(95px);
            pointer-events: none;
            z-index: 0;
            animation: orbPulse 16s ease-in-out infinite alternate;
        }
        .orb-top {
            width: 480px;
            height: 480px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.22) 0%, rgba(99, 102, 241, 0.18) 100%);
            top: -120px;
            left: 50%;
            transform: translateX(-50%);
        }
        .orb-bottom-right {
            width: 360px;
            height: 360px;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2) 0%, rgba(217, 70, 239, 0.12) 100%);
            bottom: -60px;
            right: -60px;
            animation-delay: -6s;
        }
        .orb-bottom-left {
            width: 340px;
            height: 340px;
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.18) 0%, rgba(16, 185, 129, 0.14) 100%);
            bottom: -70px;
            left: -60px;
            animation-delay: -11s;
        }

        @keyframes orbPulse {
            0% { transform: scale(1) translateY(0); }
            50% { transform: scale(1.08) translateY(-25px); }
            100% { transform: scale(0.95) translateY(15px); }
        }

        /* Login Card Wrapper */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            animation: cardEntrance 0.55s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes cardEntrance {
            from {
                opacity: 0;
                transform: translateY(22px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .login-glass-card {
            background: var(--card-glass);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.95);
            border-radius: 28px;
            padding: 2.5rem 2.25rem 2.25rem;
            box-shadow:
                0 25px 60px -15px rgba(15, 23, 42, 0.08),
                0 0 0 1px rgba(226, 232, 240, 0.8),
                inset 0 1.5px 2px rgba(255, 255, 255, 1);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        /* Brand Header */
        .login-brand-header {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .login-logo-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            margin-bottom: 1rem;
        }

        .login-logo-badge {
            width: 74px;
            height: 74px;
            background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
            border-radius: 22px;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1.5px solid rgba(226, 232, 240, 0.9);
            box-shadow:
                0 12px 28px -6px rgba(37, 99, 235, 0.15),
                0 2px 6px rgba(15, 23, 42, 0.04),
                inset 0 1px 1px #ffffff;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .login-glass-card:hover .login-logo-badge {
            transform: scale(1.04) rotate(-1deg);
        }

        .login-logo-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 2px 5px rgba(0, 0, 0, 0.12));
        }

        .login-brand-title {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.03em;
            line-height: 1.2;
            margin-bottom: 3px;
        }

        .login-brand-sub {
            font-size: 0.82rem;
            color: var(--text-muted);
            font-weight: 600;
            letter-spacing: 0.01em;
        }

        .login-badge-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            margin-top: 10px;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 9999px;
            color: #2563eb;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .login-badge-chip .chip-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #2563eb;
            animation: chipPulse 1.8s infinite;
        }

        @keyframes chipPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }

        /* Error Alert */
        .login-alert-error {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            font-size: 0.84rem;
            font-weight: 600;
            margin-bottom: 1.35rem;
            animation: alertShake 0.4s ease;
        }

        @keyframes alertShake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-4px); }
            40%, 80% { transform: translateX(4px); }
        }

        /* Mode Switcher Tabs */
        .login-nav-tabs {
            display: flex;
            background: #f1f5f9;
            padding: 5px;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
            gap: 6px;
        }

        .login-tab-btn {
            flex: 1 1 0;
            min-width: 0;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 14px;
            border: none;
            background: transparent;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            font-family: var(--font);
            border-radius: 12px;
            cursor: pointer;
            white-space: nowrap;
            user-select: none;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .login-tab-btn:focus { outline: none; }

        .login-tab-btn:hover {
            color: var(--text-dark);
            background: rgba(255, 255, 255, 0.65);
        }

        .login-tab-btn.active {
            background: #ffffff;
            color: #1d4ed8;
            font-weight: 700;
            border: 1px solid #dbeafe;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.12), 0 1px 3px rgba(15, 23, 42, 0.04);
        }

        .login-tab-btn .tab-ico {
            font-size: 18px;
            display: inline-flex;
        }

        .login-tab-btn.active .tab-ico {
            color: #2563eb;
        }

        /* Form Controls */
        .login-form-group {
            margin-bottom: 1.2rem;
        }

        .login-form-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 7px;
            letter-spacing: 0.01em;
        }

        .login-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .login-input-icon {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            font-size: 20px;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .login-form-input {
            width: 100%;
            height: 48px;
            padding: 0 1rem 0 2.85rem;
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            font-size: 0.9rem;
            font-family: var(--font);
            color: var(--text-dark);
            outline: none;
            transition: all 0.2s ease;
        }

        .login-form-input::placeholder {
            color: #94a3b8;
        }

        .login-form-input:focus {
            background: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .login-input-wrap:focus-within .login-input-icon {
            color: #2563eb;
        }

        .login-eye-toggle {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }

        .login-eye-toggle:hover {
            color: #334155;
            background: #f1f5f9;
        }

        /* Submit Button */
        .login-submit-btn {
            width: 100%;
            height: 48px;
            margin-top: 1.25rem;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 0.92rem;
            font-weight: 700;
            font-family: var(--font);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            letter-spacing: -0.01em;
            box-shadow: 0 10px 24px -4px rgba(37, 99, 235, 0.4);
            white-space: nowrap;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .login-submit-btn:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            transform: translateY(-2px);
            box-shadow: 0 14px 28px -5px rgba(37, 99, 235, 0.5);
        }

        .login-submit-btn:active {
            transform: translateY(0);
        }

        .login-submit-btn:disabled {
            opacity: 0.85;
            cursor: not-allowed;
            transform: none;
        }

        /* PIN Mode Elements */
        .pin-dots-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin: 14px auto 18px;
            padding: 6px 0;
        }

        .pin-dot-view {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid #cbd5e1;
            background: #f8fafc;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .pin-dot-view.filled {
            background: #2563eb;
            border-color: #2563eb;
            box-shadow: 0 0 14px rgba(37, 99, 235, 0.55);
            transform: scale(1.22);
        }

        .login-numpad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 0.5rem;
        }

        .login-pin-key {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            color: var(--text-dark);
            font-size: 1.25rem;
            font-weight: 700;
            font-family: var(--font);
            height: 52px;
            border-radius: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
            user-select: none;
            box-shadow: 0 2px 4px rgba(15, 23, 42, 0.03);
        }

        .login-pin-key:focus { outline: none; }

        .login-pin-key:hover {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
            transform: translateY(-1.5px);
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.12);
        }

        .login-pin-key:active {
            transform: translateY(1px) scale(0.97);
            background: #dbeafe;
        }

        .login-pin-key.key-empty {
            background: transparent;
            border-color: transparent;
            box-shadow: none;
            cursor: default;
            pointer-events: none;
        }

        /* Footer */
        .login-footer {
            margin-top: 1.5rem;
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .login-footer-security {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.7);
            padding: 4px 12px;
            border-radius: 9999px;
            border: 1px solid rgba(226, 232, 240, 0.8);
            margin-bottom: 6px;
        }

        .login-footer-copy {
            font-size: 0.72rem;
            color: #94a3b8;
            font-weight: 500;
        }
    </style>
</head>
<body class="login-body">

    <!-- Ambient Glowing Background Elements -->
    <div class="login-ambient-orb orb-top"></div>
    <div class="login-ambient-orb orb-bottom-right"></div>
    <div class="login-ambient-orb orb-bottom-left"></div>

    <div class="login-wrapper">
        <div class="login-glass-card">

            <!-- Brand Header with Logo -->
            <div class="login-brand-header">
                <div class="login-logo-container">
                    <div class="login-logo-badge">
                        <img src="assets/image/logo-IEG.png" alt="IEG Logo" class="login-logo-img">
                    </div>
                </div>
                <h1 class="login-brand-title">IEG Packaging</h1>
                <div class="login-brand-sub">KOL Affiliate System</div>
                <div class="login-badge-chip">
                    <span class="chip-dot"></span>
                    <span>Portal Masuk Sistem</span>
                </div>
            </div>

            <!-- Maintenance Alert Banner -->
            <?php if ($isMaintenance): ?>
                <div style="background:#fef2f2; border:1px solid #fca5a5; border-radius:14px; padding:12px 16px; margin-bottom:1.35rem; display:flex; align-items:center; gap:12px; color:#991b1b; font-size:0.82rem; font-weight:600; box-shadow:0 4px 12px rgba(239,68,68,0.12);">
                    <span class="material-symbols-outlined" style="font-size:22px; color:#dc2626; flex-shrink:0;">warning</span>
                    <span>Sistem dalam Mode Pemeliharaan. Hanya Superadmin <strong>Daniel</strong> yang dapat login.</span>
                </div>
            <?php endif; ?>

            <!-- Error Notification -->
            <?php if (!empty($error)): ?>
                <div class="login-alert-error" role="alert">
                    <span class="material-symbols-outlined" style="font-size:20px; color:#dc2626; flex-shrink:0;">error</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Mode Switcher Tabs -->
            <div class="login-nav-tabs">
                <button type="button" class="login-tab-btn active" id="tabStandard" onclick="switchLoginMode('standard')">
                    <span class="material-symbols-outlined tab-ico">admin_panel_settings</span>
                    <span>Password Admin</span>
                </button>
                <button type="button" class="login-tab-btn" id="tabPin" onclick="switchLoginMode('pin')">
                    <span class="material-symbols-outlined tab-ico">pin</span>
                    <span>PIN Operator</span>
                </button>
            </div>

            <!-- Mode 1: Standard Password Form -->
            <form method="POST" action="login" id="loginForm" novalidate>
                <div class="login-form-group">
                    <label class="login-form-label" for="inputUsername">Username Akun</label>
                    <div class="login-input-wrap">
                        <span class="material-symbols-outlined login-input-icon">person</span>
                        <input
                            type="text"
                            name="username"
                            id="inputUsername"
                            class="login-form-input"
                            placeholder="Ketik username Anda..."
                            required
                            autofocus
                            autocomplete="username"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="login-form-group">
                    <label class="login-form-label" for="inputPassword">Password</label>
                    <div class="login-input-wrap">
                        <span class="material-symbols-outlined login-input-icon">lock</span>
                        <input
                            type="password"
                            name="password"
                            id="inputPassword"
                            class="login-form-input"
                            placeholder="Masukkan password akun..."
                            required
                            autocomplete="current-password"
                            style="padding-right: 2.85rem;"
                        >
                        <button type="button" class="login-eye-toggle" id="togglePwdBtn" onclick="togglePwd()" tabindex="-1" title="Tampilkan / Sembunyikan Password">
                            <span class="material-symbols-outlined" id="eyeIcon" style="font-size:19px;">visibility</span>
                        </button>
                    </div>
                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:7px; display:flex; align-items:center; gap:6px;">
                        <span class="material-symbols-outlined" style="font-size:16px; color:#2563eb; flex-shrink:0;">verified_user</span>
                        <span>Mendukung password admin maupun PIN 4-digit operator</span>
                    </div>
                </div>

                <button type="submit" class="login-submit-btn" id="submitBtn">
                    <span class="material-symbols-outlined" style="font-size:20px;">login</span>
                    <span>Masuk ke Sistem</span>
                </button>
            </form>

            <!-- Mode 2: Operator PIN Form (Touchpad / Numpad) -->
            <form method="POST" action="login" id="pinForm" style="display:none;" novalidate>
                <div class="login-form-group">
                    <label class="login-form-label" for="pinUsernameSelect">Pilih Nama Operator</label>
                    <div class="login-input-wrap">
                        <span class="material-symbols-outlined login-input-icon">badge</span>
                        <?php if (!empty($operators)): ?>
                            <select name="username" id="pinUsernameSelect" class="login-form-input" style="appearance:none; -webkit-appearance:none; cursor:pointer; padding-right:2.5rem; font-weight:600;">
                                <?php foreach ($operators as $op): ?>
                                    <option value="<?= htmlspecialchars($op['username']) ?>">
                                        <?= htmlspecialchars($op['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="material-symbols-outlined" style="position:absolute; right:14px; pointer-events:none; color:var(--text-muted); font-size:20px;">expand_more</span>
                        <?php else: ?>
                            <input
                                type="text"
                                name="username"
                                id="pinUsernameSelect"
                                class="login-form-input"
                                placeholder="Username operator"
                                value="operator"
                                required
                            >
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Hidden PIN Secret Field -->
                <input type="hidden" name="password" id="pinSecretInput" value="">

                <!-- 4-Digit PIN Indicator Dots -->
                <div style="text-align:center; margin-top:1.15rem;">
                    <div style="font-size:0.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.06em;">
                        Masukkan PIN 4-Digit
                    </div>
                    <div class="pin-dots-container">
                        <div class="pin-dot-view" id="dot0"></div>
                        <div class="pin-dot-view" id="dot1"></div>
                        <div class="pin-dot-view" id="dot2"></div>
                        <div class="pin-dot-view" id="dot3"></div>
                    </div>
                </div>

                <!-- Touch Numeric Keypad -->
                <div class="login-numpad">
                    <?php foreach ([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                        <button type="button" class="login-pin-key<?= ($k === '') ? ' key-empty' : '' ?>" onclick="pressPinKey('<?= $k ?>')">
                            <?= ($k === '⌫') ? '<span class="material-symbols-outlined" style="font-size:22px; color:#ef4444;">backspace</span>' : $k ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="login-submit-btn" id="pinSubmitBtn" style="margin-top:1.25rem;">
                    <span class="material-symbols-outlined" style="font-size:20px;">dialpad</span>
                    <span>Masuk dengan PIN</span>
                </button>
            </form>

        </div>

        <!-- Security Footer -->
        <div class="login-footer">
            <div class="login-footer-copy">© 2026 Dhanielo-Marthinz | IMS</div>
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
                const pwd = document.getElementById('inputPassword');
                if (pwd) pwd.focus();
            }
        }

        function pressPinKey(k) {
            if (k === '⌫') {
                currentPin = currentPin.slice(0, -1);
            } else if (k !== '' && currentPin.length < PIN_LEN) {
                currentPin += k;
            }
            updatePinDisplay();

            // Auto submit ketika 4 digit lengkap
            if (currentPin.length === PIN_LEN) {
                setTimeout(() => {
                    const pinForm = document.getElementById('pinForm');
                    if (pinForm) {
                        const btn = document.getElementById('pinSubmitBtn');
                        btn.disabled = true;
                        btn.innerHTML = `
                            <div class="premium-balls-spinner spinner-xs" style="margin:0;">
                                <div class="spinner-ball ball-1"></div>
                                <div class="spinner-ball ball-2"></div>
                                <div class="spinner-ball ball-3"></div>
                                <div class="spinner-ball ball-4"></div>
                                <div class="spinner-ball ball-5"></div>
                                <div class="spinner-ball ball-6"></div>
                            </div>
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

        // Support physical keyboard in PIN mode
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

        // Submit loading state on standard form
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = `
                <div class="premium-balls-spinner spinner-xs" style="margin:0;">
                    <div class="spinner-ball ball-1"></div>
                    <div class="spinner-ball ball-2"></div>
                    <div class="spinner-ball ball-3"></div>
                    <div class="spinner-ball ball-4"></div>
                    <div class="spinner-ball ball-5"></div>
                    <div class="spinner-ball ball-6"></div>
                </div>
                <span>Memverifikasi Akun...</span>
            `;
        });
    </script>
</body>
</html>
