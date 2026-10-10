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

$loginMode = (($_POST['login_mode'] ?? '') === 'pin') ? 'pin' : 'standard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim((string)($_POST['password'] ?? ''));

    if ($username === '' || $password === '') {
        $error = ($loginMode === 'pin') ? 'Pilih nama operator dan masukkan PIN 4 digit!' : 'Username dan password wajib diisi!';
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
            $error = ($loginMode === 'pin')
                ? 'PIN salah, atau PIN belum diatur untuk operator ini. Minta admin mengatur PIN di menu Kelola User.'
                : 'Username atau password salah!';
        }
    }
}

// Resilient base path resolution (supports domain root, subdirectories like /kol.ieg/, ngrok tunnels, etc.)
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$assetBase = ($scriptDir !== '') ? ($scriptDir . '/') : '/';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <base href="<?= htmlspecialchars($assetBase) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — IEG Packaging KOL Affiliate</title>
    <meta name="description" content="Sistem Packaging dan Perekaman CCTV Paket KOL Affiliate - Inovasi Eka Gemilang">
    <link rel="icon" type="image/svg+xml" href="<?= $assetBase ?>assets/image/favicon.svg">
    <link rel="icon" type="image/png" href="<?= $assetBase ?>assets/image/favicon.png">
    <link rel="shortcut icon" href="<?= $assetBase ?>favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="<?= $assetBase ?>assets/css/style.css?v=<?= file_exists(__DIR__ . '/assets/css/style.css') ? filemtime(__DIR__ . '/assets/css/style.css') : 1 ?>">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --font-main: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-code: 'JetBrains Mono', monospace;
            --primary-blue: #2563eb;
            --primary-indigo: #4f46e5;
            --primary-dark: #1d4ed8;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --text-dim: #94a3b8;
            --border-light: #e2e8f0;
        }

        body.login-page {
            font-family: var(--font-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #ffffff;
            position: relative;
            overflow-x: hidden;
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 38%, #3b82f6 72%, #4f46e5 100%);
        }

        /* Ambient subtle radial glow highlights */
        .ambient-glow {
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            filter: blur(100px);
            z-index: 0;
        }

        .ambient-glow-1 {
            width: 550px;
            height: 550px;
            background: rgba(96, 165, 250, 0.28);
            top: -120px;
            left: -100px;
        }

        .ambient-glow-2 {
            width: 500px;
            height: 500px;
            background: rgba(129, 140, 248, 0.25);
            bottom: -100px;
            right: -80px;
        }

        .ambient-glow-3 {
            width: 400px;
            height: 400px;
            background: rgba(37, 99, 235, 0.2);
            bottom: 20%;
            left: 30%;
        }

        /* Main Container Layout */
        .login-layout-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 1360px;
            margin: 0 auto;
            padding: 3rem 4rem 2rem;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 4.5rem;
        }

        /* Left Hero Branding Section */
        .login-hero-section {
            flex: 1.15;
            max-width: 650px;
            animation: fadeInHero 0.7s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes fadeInHero {
            from {
                opacity: 0;
                transform: translateX(-24px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .hero-title {
            font-size: clamp(2.5rem, 4.2vw, 3.6rem);
            font-weight: 800;
            line-height: 1.14;
            letter-spacing: -0.035em;
            color: #ffffff;
            margin-bottom: 1.35rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .hero-desc {
            font-size: 1.05rem;
            line-height: 1.65;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 400;
            margin-bottom: 2.5rem;
            max-width: 560px;
        }

        /* 2x2 Feature Pills Grid */
        .hero-feature-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.15rem;
            max-width: 540px;
        }

        .feature-pill-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 13px 20px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 14px;
            box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.08);
            transition: all 0.24s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
        }

        .feature-pill-card:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.18);
            border-color: rgba(255, 255, 255, 0.38);
            box-shadow: 0 12px 28px -4px rgba(15, 23, 42, 0.16);
        }

        .feature-pill-icon-box {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            flex-shrink: 0;
            box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.4);
        }

        .feature-pill-icon-box .material-symbols-outlined {
            font-size: 20px;
        }

        .feature-pill-text {
            font-size: 0.92rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.01em;
            white-space: nowrap;
        }

        /* Right Column: Clean White Login Card */
        .login-card-wrapper {
            flex: 0 0 460px;
            width: 100%;
            max-width: 460px;
            animation: fadeInCard 0.7s cubic-bezier(0.16, 1, 0.3, 1) 0.1s both;
        }

        @keyframes fadeInCard {
            from {
                opacity: 0;
                transform: translateY(24px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .login-white-card {
            background: #ffffff;
            border-radius: 28px;
            padding: 2.75rem 2.5rem 2.5rem;
            box-shadow:
                0 30px 65px -15px rgba(15, 23, 42, 0.24),
                0 0 0 1px rgba(255, 255, 255, 0.95);
            position: relative;
            color: var(--text-dark);
        }

        /* Top Logo */
        .card-logo-container {
            text-align: center;
            margin-bottom: 1.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 0.5rem;
        }

        .card-brand-logo {
            width: 100%;
            max-width: 280px;
            height: auto;
            max-height: 95px;
            object-fit: contain;
            display: block;
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.04));
        }

        .card-heading-title {
            text-align: center;
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.025em;
            margin-bottom: 5px;
        }

        .card-heading-desc {
            text-align: center;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        /* Segmented Method Switcher */
        .method-segmented-tabs {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 4px;
            display: flex;
            gap: 6px;
            margin-bottom: 1.6rem;
        }

        .method-tab-btn {
            flex: 1 1 0;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border: none;
            background: transparent;
            border-radius: 10px;
            cursor: pointer;
            color: #64748b;
            font-family: var(--font-main);
            font-size: 0.86rem;
            font-weight: 600;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
        }

        .method-tab-btn:focus {
            outline: none;
        }

        .method-tab-btn .tab-symbol {
            font-size: 19px;
            color: inherit;
        }

        .method-badge {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            letter-spacing: 0.02em;
            transition: all 0.2s ease;
        }

        .badge-inactive {
            background: #e2e8f0;
            color: #64748b;
        }

        .method-tab-btn.active {
            background: #ffffff;
            color: #1e40af;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.08), 0 1px 3px rgba(15, 23, 42, 0.04);
        }

        .method-tab-btn.active .tab-symbol {
            color: #2563eb;
        }

        .method-tab-btn.active .method-badge {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #dbeafe;
        }

        /* Notched / Floating Outlined Input Fields */
        .input-notch-group {
            position: relative;
            margin-bottom: 1.35rem;
        }

        .input-notch-label {
            position: absolute;
            top: -9px;
            left: 14px;
            background: #ffffff;
            padding: 0 6px;
            font-size: 0.73rem;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 0.01em;
            z-index: 2;
            transition: color 0.2s ease;
        }

        .input-notch-group:focus-within .input-notch-label {
            color: #2563eb;
        }

        .input-notch-box {
            position: relative;
            display: flex;
            align-items: center;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            height: 50px;
            transition: all 0.2s ease;
        }

        .input-notch-group:focus-within .input-notch-box {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .input-notch-icon {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            font-size: 20px;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .input-notch-group:focus-within .input-notch-icon {
            color: #2563eb;
        }

        .input-notch-field {
            width: 100%;
            height: 100%;
            border: none;
            outline: none;
            background: transparent;
            padding: 0 14px 0 44px;
            font-size: 0.92rem;
            font-weight: 600;
            font-family: var(--font-main);
            color: var(--text-dark);
        }

        .input-notch-field::placeholder {
            color: #94a3b8;
            font-weight: 500;
        }

        .input-pwd-toggle {
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

        .input-pwd-toggle:hover {
            color: var(--text-dark);
            background: #f1f5f9;
        }

        /* Submit Button (Gradient Purple-Blue) */
        .btn-submit-action {
            width: 100%;
            height: 50px;
            margin-top: 1.35rem;
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 0.94rem;
            font-weight: 700;
            font-family: var(--font-main);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: -0.01em;
            box-shadow: 0 10px 24px -4px rgba(79, 70, 229, 0.42);
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-submit-action:hover {
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
            transform: translateY(-2px);
            box-shadow: 0 14px 28px -5px rgba(79, 70, 229, 0.52);
        }

        .btn-submit-action:active {
            transform: translateY(0);
        }

        .btn-submit-action:disabled {
            opacity: 0.85;
            cursor: not-allowed;
            transform: none;
        }

        /* PIN Mode Elements */
        .pin-dots-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin: 14px auto 18px;
            padding: 6px 0;
        }

        .pin-dot-item {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid #cbd5e1;
            background: #f8fafc;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .pin-dot-item.active {
            background: #4f46e5;
            border-color: #4f46e5;
            box-shadow: 0 0 14px rgba(79, 70, 229, 0.55);
            transform: scale(1.22);
        }

        .pin-keyboard-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 0.5rem;
        }

        .pin-key-btn {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            color: var(--text-dark);
            font-size: 1.25rem;
            font-weight: 700;
            font-family: var(--font-main);
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

        .pin-key-btn:focus {
            outline: none;
        }

        .pin-key-btn:hover {
            background: #eef2ff;
            border-color: #c7d2fe;
            color: #4338ca;
            transform: translateY(-1.5px);
            box-shadow: 0 6px 14px rgba(79, 70, 229, 0.14);
        }

        .pin-key-btn:active {
            transform: translateY(1px) scale(0.97);
            background: #e0e7ff;
        }

        .pin-key-btn.key-blank {
            background: transparent;
            border-color: transparent;
            box-shadow: none;
            cursor: default;
            pointer-events: none;
        }

        /* Alerts & Notices */
        .login-error-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.85rem 1rem;
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

        /* Page Footer */
        .login-page-footer {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 1360px;
            margin: 0 auto;
            padding: 1.5rem 4rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.82rem;
            font-weight: 500;
        }

        .footer-left {
            letter-spacing: 0.01em;
        }

        .footer-right {
            letter-spacing: 0.01em;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1080px) {
            .login-layout-container {
                padding: 2.5rem 2rem 1.5rem;
                gap: 2.5rem;
            }
            .hero-title {
                font-size: 2.5rem;
            }
            .login-page-footer {
                padding: 1.25rem 2rem 1.5rem;
            }
        }

        @media (max-width: 900px) {
            body.login-page {
                justify-content: flex-start;
            }
            .login-layout-container {
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 2rem 1.25rem 1rem;
                gap: 2.25rem;
            }
            .login-hero-section {
                text-align: center;
                max-width: 500px;
            }
            .hero-desc {
                margin: 0 auto 2rem;
            }
            .hero-feature-grid {
                margin: 0 auto;
            }
            .login-card-wrapper {
                max-width: 440px;
            }
            .login-page-footer {
                flex-direction: column;
                gap: 8px;
                text-align: center;
                padding: 1rem 1.25rem 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .hero-feature-grid {
                grid-template-columns: 1fr;
            }
            .login-white-card {
                padding: 2rem 1.5rem 1.75rem;
                border-radius: 22px;
            }
            .feature-pill-card {
                padding: 10px 14px;
            }
        }
        /* Inline Resilient Loading Spinner */
        .premium-balls-spinner {
            position: relative;
            width: 44px;
            height: 44px;
            margin: 0 auto;
            animation: spinRotate 2.4s cubic-bezier(0.45, 0.05, 0.55, 0.95) infinite;
        }
        .premium-balls-spinner.spinner-xs {
            transform: scale(0.5);
            margin: 0;
        }
        .spinner-ball {
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            margin-top: -5px;
            margin-left: -5px;
        }
        .spinner-ball.ball-1 { background: #60a5fa; animation: bOrb1 2s ease-in-out infinite; }
        .spinner-ball.ball-2 { background: #f87171; animation: bOrb2 2s ease-in-out infinite; }
        .spinner-ball.ball-3 { background: #fbbf24; animation: bOrb3 2s ease-in-out infinite; }
        .spinner-ball.ball-4 { background: #34d399; animation: bOrb4 2s ease-in-out infinite; }
        .spinner-ball.ball-5 { background: #a78bfa; animation: bOrb5 2s ease-in-out infinite; }
        .spinner-ball.ball-6 { background: #38bdf8; animation: bOrb6 2s ease-in-out infinite; }
        @keyframes bOrb1 { 0%, 100% { transform: rotate(0deg) translate(18px) rotate(0deg); } 50% { transform: rotate(0deg) translate(11px) rotate(0deg); } }
        @keyframes bOrb2 { 0%, 100% { transform: rotate(60deg) translate(18px) rotate(-60deg); } 50% { transform: rotate(60deg) translate(11px) rotate(-60deg); } }
        @keyframes bOrb3 { 0%, 100% { transform: rotate(120deg) translate(18px) rotate(-120deg); } 50% { transform: rotate(120deg) translate(11px) rotate(-120deg); } }
        @keyframes bOrb4 { 0%, 100% { transform: rotate(180deg) translate(18px) rotate(-180deg); } 50% { transform: rotate(180deg) translate(11px) rotate(-180deg); } }
        @keyframes bOrb5 { 0%, 100% { transform: rotate(240deg) translate(18px) rotate(-240deg); } 50% { transform: rotate(240deg) translate(11px) rotate(-240deg); } }
        @keyframes bOrb6 { 0%, 100% { transform: rotate(300deg) translate(18px) rotate(-300deg); } 50% { transform: rotate(300deg) translate(11px) rotate(-300deg); } }
        @keyframes spinRotate { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body class="login-page">

    <!-- Ambient Glowing Background Accents -->
    <div class="ambient-glow ambient-glow-1"></div>
    <div class="ambient-glow ambient-glow-2"></div>
    <div class="ambient-glow ambient-glow-3"></div>

    <!-- Main Content Container -->
    <main class="login-layout-container">

        <!-- Left Hero Section (Packaging Paket KOL Affiliate) -->
        <section class="login-hero-section">
            <h1 class="hero-title">
                Selamat Datang<br>
                di IEG Packaging
            </h1>
            <p class="hero-desc">
                Sistem perekaman video packaging paket KOL Affiliate, integrasi scan barcode resi otomatis, kompresi video MP4 hemat, serta audit riwayat packing secara cepat dan akurat.
            </p>

            <!-- 2x2 Feature Cards -->
            <div class="hero-feature-grid">
                <!-- Feature 1 -->
                <div class="feature-pill-card">
                    <div class="feature-pill-icon-box">
                        <span class="material-symbols-outlined">qr_code_scanner</span>
                    </div>
                    <span class="feature-pill-text">Scan Barcode Resi</span>
                </div>

                <!-- Feature 2 -->
                <div class="feature-pill-card">
                    <div class="feature-pill-icon-box">
                        <span class="material-symbols-outlined">inventory_2</span>
                    </div>
                    <span class="feature-pill-text">Proses Packing Paket</span>
                </div>

                <!-- Feature 3 -->
                <div class="feature-pill-card">
                    <div class="feature-pill-icon-box">
                        <span class="material-symbols-outlined">videocam</span>
                    </div>
                    <span class="feature-pill-text">Perekaman Video CCTV</span>
                </div>

                <!-- Feature 4 -->
                <div class="feature-pill-card">
                    <div class="feature-pill-icon-box">
                        <span class="material-symbols-outlined">video_library</span>
                    </div>
                    <span class="feature-pill-text">Audit &amp; Riwayat Video</span>
                </div>
            </div>
        </section>

        <!-- Right Login Card -->
        <section class="login-card-wrapper">
            <div class="login-white-card">

                <!-- Logo Inovasi Eka Gemilang -->
                <div class="card-logo-container">
                    <img
                        src="<?= $assetBase ?>assets/image/logo_text-BademdvM.jpg?v=<?= file_exists(__DIR__ . '/assets/image/logo_text-BademdvM.jpg') ? filemtime(__DIR__ . '/assets/image/logo_text-BademdvM.jpg') : time() ?>"
                        onerror="if(this.src.indexOf('logo-IEG.png')===-1){this.src='<?= $assetBase ?>assets/image/logo-IEG.png';}"
                        alt="Inovasi Eka Gemilang"
                        class="card-brand-logo"
                    >
                </div>

                <!-- Card Heading -->
                <h2 class="card-heading-title">Masuk ke Akun Anda</h2>
                <p class="card-heading-desc">Pilih metode masuk Admin (Password) atau Operator (PIN)</p>

                <!-- Maintenance Alert Banner -->
                <?php if ($isMaintenance): ?>
                    <div style="background:#fef2f2; border:1px solid #fca5a5; border-radius:12px; padding:12px 14px; margin-bottom:1.35rem; display:flex; align-items:center; gap:10px; color:#991b1b; font-size:0.82rem; font-weight:600; box-shadow:0 4px 12px rgba(239,68,68,0.1);">
                        <span class="material-symbols-outlined" style="font-size:22px; color:#dc2626; flex-shrink:0;">warning</span>
                        <span>Sistem dalam Mode Pemeliharaan. Hanya Superadmin <strong>Daniel</strong> yang dapat login.</span>
                    </div>
                <?php endif; ?>

                <!-- Error Notification -->
                <?php if (!empty($error)): ?>
                    <div class="login-error-alert" role="alert">
                        <span class="material-symbols-outlined" style="font-size:20px; color:#dc2626; flex-shrink:0;">error</span>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Segmented Tabs Switcher -->
                <div class="method-segmented-tabs" role="tablist">
                    <button type="button" class="method-tab-btn active" id="tabAdminBtn" role="tab" aria-selected="true" onclick="switchLoginMode('standard')">
                        <span class="material-symbols-outlined tab-symbol">person</span>
                        <span>Admin</span>
                        <span class="method-badge" id="adminBadge">Password</span>
                    </button>
                    <button type="button" class="method-tab-btn" id="tabOperatorBtn" role="tab" aria-selected="false" onclick="switchLoginMode('pin')">
                        <span class="material-symbols-outlined tab-symbol">badge</span>
                        <span>Operator</span>
                        <span class="method-badge badge-inactive" id="operatorBadge">PIN</span>
                    </button>
                </div>

                <!-- Mode 1: Admin Form (Standard Password) -->
                <form method="POST" action="login" id="loginForm" novalidate>
                    <input type="hidden" name="login_mode" value="standard">

                    <!-- Username Field with floating notch label -->
                    <div class="input-notch-group">
                        <label class="input-notch-label" for="inputUsername">Username</label>
                        <div class="input-notch-box">
                            <span class="material-symbols-outlined input-notch-icon">person</span>
                            <input
                                type="text"
                                name="username"
                                id="inputUsername"
                                class="input-notch-field"
                                placeholder="ADMIN"
                                required
                                autofocus
                                autocomplete="username"
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            >
                        </div>
                    </div>

                    <!-- Password Field with floating notch label -->
                    <div class="input-notch-group">
                        <label class="input-notch-label" for="inputPassword">Password</label>
                        <div class="input-notch-box">
                            <span class="material-symbols-outlined input-notch-icon">lock</span>
                            <input
                                type="password"
                                name="password"
                                id="inputPassword"
                                class="input-notch-field"
                                placeholder="••••••••"
                                required
                                autocomplete="current-password"
                                style="padding-right: 2.85rem;"
                            >
                            <button type="button" class="input-pwd-toggle" id="togglePwdBtn" onclick="togglePwdVisibility()" tabindex="-1" title="Tampilkan / Sembunyikan Password">
                                <span class="material-symbols-outlined" id="eyeIcon" style="font-size:19px;">visibility</span>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit-action" id="submitAdminBtn">
                        <span>Masuk sebagai Admin</span>
                        <span class="material-symbols-outlined" style="font-size:20px;">login</span>
                    </button>
                </form>

                <!-- Mode 2: Operator PIN Form (Touchpad / Numpad) -->
                <form method="POST" action="login" id="pinForm" style="display:none;" novalidate>
                    <input type="hidden" name="login_mode" value="pin">
                    <input type="hidden" name="password" id="pinSecretInput" value="">

                    <!-- Operator Select Field -->
                    <div class="input-notch-group">
                        <label class="input-notch-label" for="pinUsernameSelect">Pilih Operator</label>
                        <div class="input-notch-box">
                            <span class="material-symbols-outlined input-notch-icon">badge</span>
                            <?php if (!empty($operators)): ?>
                                <select name="username" id="pinUsernameSelect" class="input-notch-field" style="appearance:none; -webkit-appearance:none; cursor:pointer; padding-right:2.5rem; font-weight:600;">
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
                                    class="input-notch-field"
                                    placeholder="Username operator"
                                    value="operator"
                                    required
                                >
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 4-Digit PIN Indicator Dots -->
                    <div style="text-align:center; margin-top:1.15rem;">
                        <div style="font-size:0.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.06em;">
                            Masukkan PIN 4-Digit
                        </div>
                        <div class="pin-dots-bar">
                            <div class="pin-dot-item" id="dot0"></div>
                            <div class="pin-dot-item" id="dot1"></div>
                            <div class="pin-dot-item" id="dot2"></div>
                            <div class="pin-dot-item" id="dot3"></div>
                        </div>
                    </div>

                    <!-- Touch Numeric Keypad -->
                    <div class="pin-keyboard-grid">
                        <?php foreach ([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                            <button type="button" class="pin-key-btn<?= ($k === '') ? ' key-blank' : '' ?>" onclick="pressPinKey('<?= $k ?>')">
                                <?= ($k === '⌫') ? '<span class="material-symbols-outlined" style="font-size:22px; color:#ef4444;">backspace</span>' : $k ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="btn-submit-action" id="pinSubmitBtn" style="margin-top:1.25rem;">
                        <span>Masuk sebagai Operator</span>
                        <span class="material-symbols-outlined" style="font-size:20px;">login</span>
                    </button>
                </form>

            </div>
        </section>

    </main>

    <!-- Page Footer -->
    <footer class="login-page-footer">
        <div class="footer-left">Powered By Dhanielo-Marthinz</div>
        <div class="footer-right">Versi 2.4 &bull; All Rights Reserved</div>
    </footer>

    <script>
        let currentMode = 'standard';
        let currentPin = '';
        const PIN_LEN = 4;

        function switchLoginMode(mode) {
            currentMode = mode;
            const tabAdmin = document.getElementById('tabAdminBtn');
            const tabOperator = document.getElementById('tabOperatorBtn');
            const adminBadge = document.getElementById('adminBadge');
            const opBadge = document.getElementById('operatorBadge');
            const formStd = document.getElementById('loginForm');
            const formPin = document.getElementById('pinForm');

            if (mode === 'pin') {
                tabAdmin.classList.remove('active');
                tabAdmin.setAttribute('aria-selected', 'false');
                adminBadge.classList.add('badge-inactive');

                tabOperator.classList.add('active');
                tabOperator.setAttribute('aria-selected', 'true');
                opBadge.classList.remove('badge-inactive');

                formStd.style.display = 'none';
                formPin.style.display = 'block';
                currentPin = '';
                updatePinDisplay();
            } else {
                tabOperator.classList.remove('active');
                tabOperator.setAttribute('aria-selected', 'false');
                opBadge.classList.add('badge-inactive');

                tabAdmin.classList.add('active');
                tabAdmin.setAttribute('aria-selected', 'true');
                adminBadge.classList.remove('badge-inactive');

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

            // Auto submit jika 4 digit PIN sudah terisi lengkap
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
                        dot.classList.add('active');
                    } else {
                        dot.classList.remove('active');
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

        function togglePwdVisibility() {
            const inp = document.getElementById('inputPassword');
            const ico = document.getElementById('eyeIcon');
            const isHidden = inp.type === 'password';
            inp.type = isHidden ? 'text' : 'password';
            ico.textContent = isHidden ? 'visibility_off' : 'visibility';
        }

        // Kembali ke mode PIN jika percobaan login PIN sebelumnya error
        <?php if ($loginMode === 'pin'): ?>
        switchLoginMode('pin');
        <?php endif; ?>

        // Submit loading indicator pada standard form
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const u = document.getElementById('inputUsername');
            const p = document.getElementById('inputPassword');
            if (!u.value.trim() || !p.value) {
                e.preventDefault();
                (!u.value.trim() ? u : p).focus();
                return;
            }
            const btn = document.getElementById('submitAdminBtn');
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
