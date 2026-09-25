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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="operator-body">

    <div class="operator-screen">
        
        <!-- Header Atas: Brand + Scanner Barcode Input + Info Operator & Tombol Logout (Tanpa Sidebar) -->
        <header class="operator-header" id="scannerBanner">
            <div class="brand-area">
                <div class="brand-badge">
                    <span class="material-symbols-outlined">inventory_2</span>
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
                        <span class="material-symbols-outlined">qr_code_scanner</span>
                    </div>
                    <input type="text" 
                           id="resiInput" 
                           class="scanner-input" 
                           placeholder="Arahkan Barcode Scanner atau Ketik No Resi disini..." 
                           autocomplete="off" 
                           autofocus>
                </div>

                <button type="button" id="manualStopBtn" class="btn btn-success" style="display: none; padding: 0.6rem 1.1rem; white-space: nowrap;">
                    <span class="material-symbols-outlined">stop_circle</span>
                    <span>Selesai Packing</span>
                </button>

                <button type="button" id="cancelRecordBtn" class="btn btn-danger" style="display: none; padding: 0.6rem 1rem; white-space: nowrap;">
                    <span class="material-symbols-outlined">cancel</span>
                    <span>Batal</span>
                </button>

                <span class="autofocus-chip">
                    <span class="material-symbols-outlined" style="font-size: 14px; color: #2563eb;">center_focus_strong</span>
                    <span>Auto-Focus Aktif</span>
                </span>
            </div>

            <!-- Operator Profile & Action Controls -->
            <div class="operator-header-right">
                <?php if ($user['role'] === 'admin'): ?>
                <a href="admin" class="btn btn-outline btn-sm header-admin-btn" title="Buka Portal Admin">
                    <span class="material-symbols-outlined" style="font-size: 16px; color: #7c3aed;">dashboard</span>
                    <span>Portal Admin</span>
                </a>
                <?php endif; ?>

                <div class="user-pill">
                    <div class="user-pill-avatar">
                        <span class="material-symbols-outlined" style="font-size: 18px;">person</span>
                    </div>
                    <div class="user-pill-text">
                        <div class="user-pill-name"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="user-pill-role <?= $user['role'] === 'admin' ? 'role-admin' : 'role-operator' ?>">
                            <?= strtoupper($user['role']) ?>
                        </div>
                    </div>
                </div>

                <a href="logout" class="btn btn-outline btn-sm btn-header-logout" title="Keluar dari sistem">
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
                        <span class="material-symbols-outlined">videocam</span>
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
                        <span class="material-symbols-outlined">receipt_long</span>
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
                            <span class="material-symbols-outlined">inventory_2</span>
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

    <script src="assets/js/packing.js"></script>
</body>
</html>
