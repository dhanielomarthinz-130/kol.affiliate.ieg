<?php
// 404.php - Halaman Error 404 Tidak Ditemukan
http_response_code(404);
require_once __DIR__ . '/config/auth.php';

$isLoggedIn = isLoggedIn();
$currentUser = getCurrentUser();
$redirectTarget = 'login';
$redirectLabel = 'Kembali ke Login';
if ($isLoggedIn) {
    $role = $currentUser['role'] ?? 'operator';
    $redirectTarget = in_array($role, ['admin', 'superadmin'], true) ? 'admin' : 'packing';
    $redirectLabel = 'Kembali ke Dashboard';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — IEG KOL</title>
    <link rel="icon" type="image/svg+xml" href="assets/image/favicon.svg">
    <link rel="icon" type="image/png" href="assets/image/favicon.png">
    <link rel="shortcut icon" href="favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --font: 'Plus Jakarta Sans', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --blue-500: #3b82f6;
            --blue-600: #2563eb;
            --blue-700: #1d4ed8;
            --text-primary: #0f172a;
            --text-secondary: #334155;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --card-bg: #ffffff;
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
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(-20px); }
        }

        .error-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 500px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 2.8rem 2.25rem 2.25rem;
            text-align: center;
            box-shadow:
                0 20px 45px -12px rgba(15, 23, 42, 0.08),
                0 2px 8px rgba(15, 23, 42, 0.04);
            animation: cardIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(16px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .error-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            background: rgba(37, 99, 235, 0.08);
            border: 1px solid rgba(37, 99, 235, 0.2);
            color: #2563eb;
            border-radius: 9999px;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 1.25rem;
        }

        .error-code {
            font-family: var(--font-mono);
            font-size: 5.5rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.6rem;
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 50%, #60a5fa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -2px;
        }

        .error-title {
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.65rem;
            letter-spacing: -0.02em;
        }

        .error-desc {
            font-size: 0.86rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 1.5rem;
            padding: 0 0.5rem;
        }

        .error-path {
            display: inline-block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 0.4rem 0.85rem;
            border-radius: 9px;
            font-family: var(--font-mono);
            font-size: 0.78rem;
            color: #475569;
            margin-bottom: 1.75rem;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.78rem 1.4rem;
            font-size: 0.88rem;
            font-weight: 700;
            font-family: var(--font);
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            box-shadow: 0 8px 18px -3px rgba(37, 99, 235, 0.35);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            transform: translateY(-2px);
            box-shadow: 0 12px 22px -4px rgba(37, 99, 235, 0.45);
        }

        .btn-secondary {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            color: #334155;
        }
        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
            transform: translateY(-2px);
        }

        .brand-footer {
            margin-top: 2rem;
            font-size: 0.75rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-badge">
            <span class="material-symbols-outlined" style="font-size: 15px;">help</span>
            Error 404 Not Found
        </div>

        <div class="error-code">404</div>
        <h1 class="error-title">Halaman Tidak Ditemukan</h1>
        
        <p class="error-desc">
            Mohon maaf, tautan atau halaman yang Anda tuju tidak tersedia, telah dipindahkan, atau alamat URL yang dimasukkan salah.
        </p>

        <?php if (!empty($_SERVER['REQUEST_URI'])): ?>
            <div class="error-path" title="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                <span>URL: <?= htmlspecialchars($_SERVER['REQUEST_URI']) ?></span>
            </div>
        <?php endif; ?>

        <div class="btn-group">
            <a href="<?= htmlspecialchars($redirectTarget) ?>" class="btn btn-primary">
                <span class="material-symbols-outlined" style="font-size: 18px;">home</span>
                <span><?= htmlspecialchars($redirectLabel) ?></span>
            </a>
            <button onclick="window.history.back()" class="btn btn-secondary" type="button">
                <span class="material-symbols-outlined" style="font-size: 18px;">arrow_back</span>
                <span>Kembali</span>
            </button>
        </div>

        <div class="brand-footer">
            <span class="material-symbols-outlined" style="font-size: 14px; color: #2563eb;">inventory_2</span>
            <span>KOL Packing Station</span>
        </div>
    </div>
</body>
</html>
