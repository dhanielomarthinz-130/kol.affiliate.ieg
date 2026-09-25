<?php
// packing.php
require_once __DIR__ . '/config/auth.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();
$todayDate = date('Y-m-d');
$stmtToday = $db->prepare("SELECT COUNT(*) as cnt FROM packings WHERE DATE(created_at) = ?");
$stmtToday->execute([$todayDate]);
$initialTodayCount = intval($stmtToday->fetch()['cnt'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stasiun Kerja Packing - KOL Affiliate</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">

    <style>
        /* ========================================================
           CRITICAL WORKSTATION LAYOUT (IMMUNE TO BROWSER CACHE)
           ======================================================== */
        html, body.operator-body {
            height: 100vh !important;
            overflow: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
            background-color: #f1f5f9 !important;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            box-sizing: border-box !important;
        }

        .operator-screen {
            height: 100vh !important;
            display: flex !important;
            flex-direction: column !important;
            padding: 10px 14px !important;
            gap: 10px !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
        }

        /* Top Header: Single Row Flex Bar */
        .operator-header {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            padding: 8px 16px !important;
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 14px !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
            flex-shrink: 0 !important;
            height: 58px !important;
            box-sizing: border-box !important;
            transition: all 0.25s ease !important;
        }

        .operator-header.recording-mode {
            border-color: #f43f5e !important;
            background: #fff5f7 !important;
            box-shadow: 0 0 15px rgba(244, 63, 94, 0.2) !important;
        }

        .brand-area {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            flex-shrink: 0 !important;
        }

        .brand-badge {
            width: 36px !important;
            height: 36px !important;
            border-radius: 9px !important;
            background: #eff6ff !important;
            border: 1px solid #bfdbfe !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: #2563eb !important;
        }

        .brand-title {
            font-size: 0.95rem !important;
            font-weight: 800 !important;
            color: #0f172a !important;
            line-height: 1.1 !important;
            letter-spacing: -0.01em !important;
        }

        .brand-sub {
            font-size: 0.68rem !important;
            color: #64748b !important;
            font-weight: 500 !important;
        }

        /* Central Scanner Input Box */
        .scanner-box {
            flex: 1 !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            max-width: 680px !important;
        }

        .scanner-field-wrapper {
            position: relative !important;
            flex: 1 !important;
        }

        .scanner-icon {
            position: absolute !important;
            left: 12px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            color: #64748b !important;
            display: flex !important;
            align-items: center !important;
            pointer-events: none !important;
        }

        .scanner-input {
            width: 100% !important;
            box-sizing: border-box !important;
            background: #f8fafc !important;
            border: 2px solid #cbd5e1 !important;
            border-radius: 8px !important;
            padding: 7px 12px 7px 38px !important;
            font-size: 1.05rem !important;
            font-family: 'JetBrains Mono', monospace !important;
            font-weight: 700 !important;
            color: #0f172a !important;
            outline: none !important;
            transition: all 0.2s ease !important;
        }

        .scanner-input:focus {
            border-color: #2563eb !important;
            background: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
        }

        .recording-mode .scanner-input:focus {
            border-color: #e11d48 !important;
            box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.15) !important;
        }

        .autofocus-chip {
            font-size: 0.72rem !important;
            background: #f1f5f9 !important;
            border: 1px solid #cbd5e1 !important;
            color: #475569 !important;
            padding: 5px 9px !important;
            border-radius: 6px !important;
            font-weight: 600 !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
            white-space: nowrap !important;
            flex-shrink: 0 !important;
        }

        /* Operator Right Actions */
        .operator-header-right {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            flex-shrink: 0 !important;
        }

        .user-pill {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: #f8fafc !important;
            border: 1px solid #e2e8f0 !important;
            padding: 4px 10px !important;
            border-radius: 20px !important;
        }

        .user-pill-avatar {
            width: 24px !important;
            height: 24px !important;
            border-radius: 50% !important;
            background: #e2e8f0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: #2563eb !important;
        }

        .user-pill-name {
            font-size: 0.8rem !important;
            font-weight: 700 !important;
            color: #0f172a !important;
            line-height: 1.1 !important;
        }

        .user-pill-role {
            font-size: 0.6rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            padding: 1px 6px !important;
            border-radius: 4px !important;
            line-height: 1 !important;
        }
        .role-operator { background: #dcfce7 !important; color: #15803d !important; }
        .role-admin { background: #f3e8ff !important; color: #7e22ce !important; }

        .btn-header-logout {
            border-color: #fecdd3 !important;
            color: #e11d48 !important;
            background: #ffffff !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
            padding: 5px 10px !important;
            border-radius: 6px !important;
            font-size: 0.8rem !important;
            font-weight: 600 !important;
            border: 1px solid #fecdd3 !important;
        }
        .btn-header-logout:hover {
            background: #fff1f2 !important;
            border-color: #fda4af !important;
        }

        .header-admin-btn {
            border: 1px solid #d8b4fe !important;
            color: #7c3aed !important;
            background: #faf5ff !important;
            font-weight: 600 !important;
            font-size: 0.8rem !important;
            padding: 5px 10px !important;
            border-radius: 6px !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
        }
        .header-admin-btn:hover {
            background: #f3e8ff !important;
        }

        /* 2-Column Workstation Grid */
        .operator-main-grid {
            flex: 1 !important;
            min-height: 0 !important;
            display: grid !important;
            grid-template-columns: 1fr 390px !important;
            gap: 10px !important;
            align-items: stretch !important;
            box-sizing: border-box !important;
        }

        @media (max-width: 1100px) {
            .operator-main-grid {
                grid-template-columns: 1fr 320px !important;
            }
        }

        .card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
            height: 100% !important;
            box-sizing: border-box !important;
        }

        .card-header {
            padding: 8px 14px !important;
            border-bottom: 1px solid #e2e8f0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            background: #ffffff !important;
            flex-shrink: 0 !important;
        }

        .card-body {
            padding: 10px !important;
            flex: 1 !important;
            min-height: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            box-sizing: border-box !important;
        }

        /* Today Counter Card Widget */
        .today-counter-card {
            background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%) !important;
            border: 1px solid #bfdbfe !important;
            border-radius: 8px !important;
            padding: 10px 12px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 10px !important;
            flex-shrink: 0 !important;
        }

        .today-counter-icon {
            width: 42px !important;
            height: 42px !important;
            border-radius: 9px !important;
            background: #2563eb !important;
            color: #ffffff !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            flex-shrink: 0 !important;
            box-shadow: 0 3px 8px rgba(37, 99, 235, 0.25) !important;
        }

        .today-counter-content {
            flex: 1 !important;
        }

        .today-counter-label {
            font-size: 0.65rem !important;
            font-weight: 700 !important;
            color: #1e40af !important;
            text-transform: uppercase !important;
            letter-spacing: 0.04em !important;
            margin-bottom: 2px !important;
        }

        .today-counter-val-row {
            display: flex !important;
            align-items: baseline !important;
            gap: 5px !important;
        }

        .today-counter-number {
            font-family: 'JetBrains Mono', monospace !important;
            font-size: 1.6rem !important;
            font-weight: 800 !important;
            color: #0f172a !important;
            line-height: 1 !important;
        }

        .today-counter-unit {
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            color: #64748b !important;
        }

        .today-counter-badge {
            display: flex !important;
            align-items: center !important;
            gap: 5px !important;
            font-size: 0.62rem !important;
            font-weight: 700 !important;
            color: #059669 !important;
            background: #ecfdf5 !important;
            border: 1px solid #a7f3d0 !important;
            padding: 2px 7px !important;
            border-radius: 10px !important;
        }

        .badge-dot {
            width: 6px !important;
            height: 6px !important;
            border-radius: 50% !important;
            background: #10b981 !important;
            display: inline-block !important;
            animation: pulseGlow 1.5s infinite !important;
        }

        @keyframes pulseGlow {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }

        /* History List scroll */
        .history-list {
            flex: 1 !important;
            min-height: 0 !important;
            overflow-y: auto !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 8px !important;
            margin-top: 8px !important;
            padding-right: 4px !important;
        }
    </style>
</head>
<body class="operator-body">

    <div class="operator-screen">
        
        <!-- Header Atas: Brand + Scanner Barcode Input + Info Operator & Tombol Logout (Tanpa Sidebar) -->
        <header class="operator-header" id="scannerBanner">
            <div class="brand-area">
                <div class="brand-badge">
                    <span class="material-symbols-outlined" style="font-size: 22px;">inventory_2</span>
                </div>
                <div>
                    <div class="brand-title">KOL PACKING</div>
                    <div class="brand-sub">Auto Video Recorder</div>
                </div>
            </div>

            <!-- Central Scanner Field -->
            <div class="scanner-box">
                <div class="scanner-field-wrapper">
                    <div class="scanner-icon">
                        <span class="material-symbols-outlined" style="font-size: 20px;">qr_code_scanner</span>
                    </div>
                    <input type="text" 
                           id="resiInput" 
                           class="scanner-input" 
                           placeholder="Arahkan Barcode Scanner atau Ketik No Resi disini..." 
                           autocomplete="off" 
                           autofocus>
                </div>

                <button type="button" id="manualStopBtn" class="btn btn-success" style="display: none; padding: 0.55rem 1rem; white-space: nowrap;">
                    <span class="material-symbols-outlined" style="font-size: 16px;">stop_circle</span>
                    <span>Selesai Packing</span>
                </button>

                <button type="button" id="cancelRecordBtn" class="btn btn-danger" style="display: none; padding: 0.55rem 0.9rem; white-space: nowrap;">
                    <span class="material-symbols-outlined" style="font-size: 16px;">cancel</span>
                    <span>Batal</span>
                </button>

                <span class="autofocus-chip">
                    <span class="material-symbols-outlined" style="font-size: 14px; color: #2563eb;">center_focus_strong</span>
                    <span>Auto-Focus</span>
                </span>
            </div>

            <!-- Operator Profile & Action Controls -->
            <div class="operator-header-right">
                <?php if ($user['role'] === 'admin'): ?>
                <a href="admin" class="header-admin-btn" title="Buka Portal Admin">
                    <span class="material-symbols-outlined" style="font-size: 16px; color: #7c3aed;">dashboard</span>
                    <span>Portal Admin</span>
                </a>
                <?php endif; ?>

                <div class="user-pill">
                    <div class="user-pill-avatar">
                        <span class="material-symbols-outlined" style="font-size: 16px;">person</span>
                    </div>
                    <div class="user-pill-text">
                        <div class="user-pill-name"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="user-pill-role <?= $user['role'] === 'admin' ? 'role-admin' : 'role-operator' ?>">
                            <?= strtoupper($user['role']) ?>
                        </div>
                    </div>
                </div>

                <a href="logout" class="btn-header-logout" title="Keluar dari sistem">
                    <span class="material-symbols-outlined" style="font-size: 16px;">logout</span>
                    <span>Keluar</span>
                </a>
            </div>
        </header>

        <!-- Main Layout: 2 Kolom (Card Camera di kiri, Card Riwayat & Total Resi di kanan) -->
        <main class="operator-main-grid">
            
            <!-- Left Side: Camera Live Feed (Auto-Fit 100vh) -->
            <div class="card camera-card">
                <div class="card-header">
                    <div class="card-title">
                        <span class="material-symbols-outlined" style="color: #2563eb;">videocam</span>
                        <span>Live Feed Kamera Packing</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-outlined" style="color: #64748b; font-size: 18px;">photo_camera</span>
                        <select id="cameraSelect" class="select-input">
                            <option value="">Memuat perangkat kamera...</option>
                        </select>

                        <select id="qualitySelect" class="select-input" title="Pilih Mode Penyimpanan" style="font-weight: 600; color: #2563eb; background: #eff6ff; border-color: #bfdbfe;">
                            <option value="saver" selected>⚡ Mode Hemat (~400 KB)</option>
                            <option value="hd">🎥 Mode HD (~1.4 MB)</option>
                        </select>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Auto-Resizing Camera Viewport (Never Cut Off) -->
                    <div class="camera-container">
                        <video id="cameraFeed" class="camera-video" autoplay playsinline muted></video>
                        
                        <!-- Top Overlay -->
                        <div class="camera-overlay-top">
                            <div class="status-indicator status-standby" id="statusBadge">
                                <span class="rec-dot" style="background:#ffffff;"></span> STANDBY (SIAP SCAN)
                            </div>
                            <div class="timer-box" id="timerDisplay">
                                <span class="material-symbols-outlined" style="font-size: 16px; color: #cbd5e1;">timer</span>
                                <span>00:00:00</span>
                            </div>
                        </div>

                        <!-- Bottom Overlay -->
                        <div class="camera-overlay-bottom">
                            <div class="overlay-meta">
                                <div id="overlayResi" class="meta-resi" style="display: none;"></div>
                                <div style="font-size: 0.72rem; color: #cbd5e1; display: flex; align-items: center; gap: 4px;">
                                    <span class="material-symbols-outlined" style="font-size: 13px;">badge</span>
                                    <span>Operator: <b><?= htmlspecialchars($user['name']) ?></b></span>
                                </div>
                            </div>
                            <div class="overlay-meta" style="text-align: right;">
                                <div id="overlayClock" style="font-family: 'JetBrains Mono', monospace; font-size: 0.82rem; color: #60a5fa; font-weight: 700;">
                                    00:00:00 WIB
                                </div>
                                <div style="font-size: 0.7rem; color: #94a3b8;">
                                    <?= date('d M Y') ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Technical Footnote -->
                    <div class="camera-footnote">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span class="material-symbols-outlined" style="font-size: 15px; color: #059669;">volume_up</span>
                            <span>Audio Cue: <b>Aktif</b></span>
                            <span>•</span>
                            <span class="material-symbols-outlined" style="font-size: 15px; color: #2563eb;">high_quality</span>
                            <span>Format: <b>MP4 H.264 Auto-Compress</b></span>
                        </div>
                        <div>
                            Tekan <kbd style="background:#f1f5f9; padding:1px 5px; border-radius:4px; border:1px solid #cbd5e1; font-family:'JetBrains Mono';">Enter</kbd> otomatis via scanner fisik.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Card Riwayat & Total Resi yang di scan -->
            <div class="card history-card">
                <div class="card-header">
                    <div class="card-title">
                        <span class="material-symbols-outlined" style="color: #2563eb;">receipt_long</span>
                        <span>Riwayat Hasil Packing</span>
                    </div>
                    <button class="btn btn-outline btn-sm" onclick="window.station.loadRecentHistory()" title="Segarkan Data">
                        <span class="material-symbols-outlined" style="font-size: 16px;">sync</span>
                    </button>
                </div>

                <div class="card-body" style="gap: 10px;">
                    <!-- Stat Card: Total Resi Ter-scan Hari Ini -->
                    <div class="today-counter-card">
                        <div class="today-counter-icon">
                            <span class="material-symbols-outlined" style="font-size: 22px;">inventory_2</span>
                        </div>
                        <div class="today-counter-content">
                            <div class="today-counter-label">TOTAL RESI TER-SCAN HARI INI</div>
                            <div class="today-counter-val-row">
                                <span id="todayTotalCount" class="today-counter-number"><?= $initialTodayCount ?></span>
                                <span class="today-counter-unit">Resi Paket</span>
                            </div>
                        </div>
                        <div class="today-counter-badge">
                            <span class="badge-dot"></span>
                            <span>REALTIME</span>
                        </div>
                    </div>

                    <!-- Header Riwayat -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 2px 2px 0;">
                        <span style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em;">Daftar Terakhir Di-Scan</span>
                        <span style="font-size: 0.7rem; color: #94a3b8;">10 Paket Terkini</span>
                    </div>

                    <!-- Scrollable History List -->
                    <div class="history-list" id="recentHistoryList">
                        <div style="text-align: center; color: #94a3b8; padding: 2rem 0; font-size: 0.85rem;">
                            Memuat riwayat...
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Modal Quick Video Preview -->
    <div class="modal-overlay" id="videoModal">
        <div class="modal-content">
            <div class="modal-header">
                <div style="font-weight: 700; font-size: 1.05rem; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined" style="color: #2563eb;">movie</span>
                    <span>Hasil Rekaman Video:</span>
                    <span id="modalResiTitle" style="color: #2563eb; font-family: 'JetBrains Mono', monospace;">-</span>
                </div>
                <button class="btn btn-outline btn-sm" onclick="closeVideoModal()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="modal-body">
                <video id="modalVideoPlayer" class="modal-video-player" controls></video>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                    <a id="modalPackingDownloadBtn" href="#" class="btn btn-primary btn-sm" download style="display: none;">
                        <span class="material-symbols-outlined">download</span>
                        <span>Download Video MP4</span>
                    </a>
                    <button class="btn btn-outline btn-sm" onclick="closeVideoModal()">Tutup Jendela</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/packing.js?v=<?= time() ?>"></script>
</body>
</html>
