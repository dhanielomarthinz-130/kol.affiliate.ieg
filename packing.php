<?php
// packing.php
require_once __DIR__ . '/config/auth.php';
requireLogin();

$user = getCurrentUser();
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
<body class="workstation-layout">

    <div class="app-layout workstation-layout">
        
        <!-- Clean Modern Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="packing" class="sidebar-brand">
                    <div class="sidebar-brand-icon">
                        <span class="material-symbols-outlined">inventory_2</span>
                    </div>
                    <div>
                        <div class="sidebar-brand-title">KOL PACKING</div>
                        <div class="sidebar-brand-sub">Auto Video Recorder</div>
                    </div>
                </a>
            </div>

            <div class="sidebar-content">
                <div>
                    <div class="sidebar-section-title">Menu Operator</div>
                    <nav class="sidebar-nav">
                        <a href="packing" class="nav-link active">
                            <span class="material-symbols-outlined">videocam</span>
                            <span>Layar Packing</span>
                            <span class="nav-badge" style="background:#ecfdf5; color:#059669;">LIVE</span>
                        </a>

                        <a href="#recentHistoryList" class="nav-link">
                            <span class="material-symbols-outlined">receipt_long</span>
                            <span>Riwayat Hari Ini</span>
                        </a>
                    </nav>
                </div>

                <?php if ($user['role'] === 'admin'): ?>
                <div>
                    <div class="sidebar-section-title">Akses Khusus</div>
                    <nav class="sidebar-nav">
                        <a href="admin" class="nav-link">
                            <span class="material-symbols-outlined">dashboard</span>
                            <span>Portal Admin</span>
                        </a>
                    </nav>
                </div>
                <?php endif; ?>

                <div style="margin-top: auto;">
                    <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 10px; padding: 10px 12px; font-size: 0.75rem; color: var(--text-muted);">
                        <div style="display: flex; align-items: center; gap: 5px; color: #2563eb; font-weight: 700; margin-bottom: 2px;">
                            <span class="material-symbols-outlined" style="font-size: 15px;">compress</span>
                            <span>Auto-Compression MP4</span>
                        </div>
                        Resolusi HD 720p dikompresi otomatis hemat 80% ruang penyimpanan.
                    </div>
                </div>
            </div>

            <!-- Sidebar User Profile & Logout -->
            <div class="sidebar-footer">
                <div class="user-profile-widget">
                    <div class="user-avatar-circle">
                        <span class="material-symbols-outlined" style="font-size: 20px;">person</span>
                    </div>
                    <div class="user-profile-details">
                        <div class="user-profile-name"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="user-profile-role <?= $user['role'] === 'admin' ? 'role-admin' : 'role-operator' ?>">
                            <span class="material-symbols-outlined" style="font-size: 12px;">
                                <?= $user['role'] === 'admin' ? 'shield_person' : 'badge' ?>
                            </span>
                            <?= strtoupper($user['role']) ?>
                        </div>
                    </div>
                </div>

                <a href="logout" class="btn-sidebar-logout">
                    <span class="material-symbols-outlined" style="font-size: 16px;">logout</span>
                    <span>Keluar Sistem</span>
                </a>
            </div>
        </aside>

        <!-- Main Auto-Resize Workstation Viewport -->
        <main class="workstation-viewport">
            
            <!-- Compact, Prominent Top Control Bar (Never Cuts Off) -->
            <div class="workstation-top-bar" id="scannerBanner">
                <div style="display: flex; align-items: center; gap: 12px; min-width: 220px;">
                    <div style="width: 38px; height: 38px; border-radius: 8px; background: #eff6ff; display: flex; align-items: center; justify-content: center; color: #2563eb;">
                        <span class="material-symbols-outlined" style="font-size: 24px;">barcode_scanner</span>
                    </div>
                    <div>
                        <div style="font-size: 0.95rem; font-weight: 800; color: #0f172a; line-height: 1.2;">
                            Stasiun Packing
                        </div>
                        <div style="font-size: 0.72rem; color: #64748b;" id="scannerPromptText">
                            Scan No Resi untuk Mulai Rekam:
                        </div>
                    </div>
                </div>

                <!-- Central Scanner Input Field -->
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

                    <button type="button" id="manualStopBtn" class="btn btn-success" style="display: none; padding: 0.65rem 1.2rem; font-size: 0.88rem; white-space: nowrap;">
                        <span class="material-symbols-outlined">stop_circle</span>
                        <span>Selesai Packing</span>
                    </button>

                    <button type="button" id="cancelRecordBtn" class="btn btn-danger" style="display: none; padding: 0.65rem 1rem; font-size: 0.88rem; white-space: nowrap;">
                        <span class="material-symbols-outlined">cancel</span>
                        <span>Batal</span>
                    </button>
                </div>

                <!-- Scanner Status Badge -->
                <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                    <span style="font-size: 0.75rem; background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; padding: 4px 10px; border-radius: 6px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                        <span class="material-symbols-outlined" style="font-size: 14px; color: #2563eb;">center_focus_strong</span>
                        Auto-Focus Aktif
                    </span>
                </div>
            </div>

            <!-- Auto-Fit Grid: Automatically fills the rest of 100vh without scrolling! -->
            <div class="workstation-grid">
                
                <!-- Left Side: Camera Live Feed (Auto-Scales with object-fit: contain) -->
                <div class="card" style="height: 100%;">
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

                <!-- Right Side: Recent Packings Feed -->
                <div class="card" style="height: 100%;">
                    <div class="card-header">
                        <div class="card-title">
                            <span class="material-symbols-outlined">receipt_long</span>
                            <span>Riwayat Hari Ini</span>
                        </div>
                        <button class="btn btn-outline btn-sm" onclick="window.station.loadRecentHistory()" title="Segarkan Data">
                            <span class="material-symbols-outlined">sync</span>
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="history-list" id="recentHistoryList">
                            <div style="text-align: center; color: #94a3b8; padding: 2rem 0; font-size: 0.85rem;">
                                Memuat riwayat...
                            </div>
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
