<?php
// maintenance_notice.php - Pemberitahuan Pemeliharaan Sistem
http_response_code(503);
header('Retry-After: 3600');
require_once __DIR__ . '/config/auth.php';

$currentUser = getCurrentUser();
// Jika maintenance sudah dimatikan atau user ini adalah Daniel (Superadmin), redirect ke halaman yang sesuai
if (!isMaintenanceActive() || canBypassMaintenance($currentUser)) {
    if (isLoggedIn()) {
        header('Location: ' . (in_array($currentUser['role'] ?? '', ['admin', 'superadmin'], true) ? 'admin' : 'packing'));
    } else {
        header('Location: login');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance — IEG KOL</title>
    <link rel="icon" type="image/svg+xml" href="assets/image/favicon.svg">
    <link rel="icon" type="image/png" href="assets/image/favicon.png">
    <link rel="shortcut icon" href="favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <style>
        :root {
            --bg-base: #0a0d14;
            --card-bg: rgba(17, 24, 39, 0.82);
            --card-border: rgba(245, 158, 11, 0.25);
            --text-primary: #f8fafc;
            --text-muted: #94a3b8;
            --amber-accent: #f59e0b;
            --amber-glow: rgba(245, 158, 11, 0.25);
            --font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-sans);
            background: radial-gradient(circle at 50% 25%, #2d1804 0%, #111422 55%, var(--bg-base) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow-x: hidden;
        }

        .ambient-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            pointer-events: none;
            z-index: 0;
            opacity: 0.45;
        }
        .ambient-blob-1 {
            width: 420px;
            height: 420px;
            background: rgba(245, 158, 11, 0.2);
            top: 10%;
            left: 50%;
            transform: translateX(-50%);
        }
        .ambient-blob-2 {
            width: 320px;
            height: 320px;
            background: rgba(99, 102, 241, 0.12);
            bottom: 10%;
            right: 15%;
        }

        .maint-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 560px;
            background: var(--card-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            padding: 3rem 2.25rem;
            text-align: center;
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.7), 0 0 50px var(--amber-glow);
            animation: fadeInCard 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes fadeInCard {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.97);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .icon-wrapper {
            width: 84px;
            height: 84px;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.05));
            border: 1px solid rgba(245, 158, 11, 0.35);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 25px -5px rgba(245, 158, 11, 0.25);
            animation: pulseIcon 3s ease-in-out infinite;
        }

        @keyframes pulseIcon {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 10px 25px -5px rgba(245, 158, 11, 0.25);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 15px 35px -5px rgba(245, 158, 11, 0.45);
            }
        }

        .maint-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fbbf24;
            border-radius: 9999px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 1.25rem;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #f59e0b;
            box-shadow: 0 0 8px #f59e0b;
        }

        .maint-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.75rem;
            letter-spacing: -0.02em;
        }

        .maint-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 2rem;
            padding: 0 0.5rem;
        }

        .info-box {
            background: rgba(15, 23, 42, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 1.2rem;
            text-align: left;
            margin-bottom: 2rem;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            font-size: 0.85rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #cbd5e1;
        }

        .info-label {
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .info-value {
            font-weight: 600;
            color: #ffffff;
            font-family: var(--font-mono);
        }

        .btn-group {
            display: flex;
            gap: 0.85rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.85rem 1.6rem;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 14px;
            text-decoration: none;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
            color: #ffffff;
            box-shadow: 0 8px 20px -4px rgba(245, 158, 11, 0.4);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -4px rgba(245, 158, 11, 0.55);
            background: linear-gradient(135deg, #b45309 0%, #d97706 100%);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.12);
            color: #cbd5e1;
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.22);
            color: #ffffff;
            transform: translateY(-2px);
        }

        .brand-footer {
            margin-top: 2.25rem;
            font-size: 0.78rem;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }
    </style>
</head>
<body>
    <div class="ambient-blob ambient-blob-1"></div>
    <div class="ambient-blob ambient-blob-2"></div>

    <div class="maint-card">
        <div class="icon-wrapper">
            <span class="material-symbols-outlined" style="font-size: 42px; color: #fbbf24;">construction</span>
        </div>

        <br>
        <div class="maint-badge">
            <span class="status-dot"></span>
            Maintenance Mode Aktif
        </div>

        <h1 class="maint-title">Sedang Dalam Pemeliharaan</h1>
        
        <p class="maint-desc">
            Sistem saat ini sedang dilakukan peningkatan, pemeliharaan rutin, atau audit database. Demi keamanan data, akses pengguna sementara dibatasi.
        </p>

        <div class="info-box">
            <div class="info-row">
                <span class="info-label">
                    <span class="material-symbols-outlined" style="font-size: 16px;">verified_user</span>
                    Akses Diizinkan:
                </span>
                <span class="info-value" style="color: #67e8f9;">Superadmin (Daniel)</span>
            </div>
            <div class="info-row">
                <span class="info-label">
                    <span class="material-symbols-outlined" style="font-size: 16px;">schedule</span>
                    Status Operasional:
                </span>
                <span class="info-value" style="color: #fbbf24;">Offline untuk Umum</span>
            </div>
        </div>

        <div class="btn-group">
            <button onclick="window.location.reload()" class="btn btn-primary" type="button">
                <span class="material-symbols-outlined" style="font-size: 18px;">refresh</span>
                <span>Cek Ulang Status</span>
            </button>
            <a href="login" class="btn btn-secondary">
                <span class="material-symbols-outlined" style="font-size: 18px;">login</span>
                <span>Halaman Login</span>
            </a>
        </div>

        <div class="brand-footer">
            <span class="material-symbols-outlined" style="font-size: 14px;">inventory_2</span>
            <span>KOL Affiliate Video Packing Station</span>
        </div>
    </div>
</body>
</html>
