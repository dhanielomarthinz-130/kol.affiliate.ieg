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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="app-layout">
        
        <!-- Premium Vertical Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="packing.php" class="sidebar-brand">
                    <div class="sidebar-brand-icon">
                        <span class="material-symbols-outlined" style="font-size: 26px;">inventory_2</span>
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
                        <a href="packing.php" class="nav-link active">
                            <span class="material-symbols-outlined">videocam</span>
                            <span>Layar Packing</span>
                            <span class="nav-badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399;">LIVE</span>
                        </a>

                        <a href="#recentHistoryList" class="nav-link">
                            <span class="material-symbols-outlined">history</span>
                            <span>Riwayat Hari Ini</span>
                        </a>
                    </nav>
                </div>

                <?php if ($user['role'] === 'admin'): ?>
                <div>
                    <div class="sidebar-section-title">Akses Khusus</div>
                    <nav class="sidebar-nav">
                        <a href="admin.php" class="nav-link">
                            <span class="material-symbols-outlined">dashboard</span>
                            <span>Portal Admin</span>
                        </a>
                    </nav>
                </div>
                <?php endif; ?>

                <div style="margin-top: auto;">
                    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 12px; padding: 12px; font-size: 0.78rem; color: var(--text-muted);">
                        <div style="display: flex; align-items: center; gap: 6px; color: #60a5fa; font-weight: 700; margin-bottom: 4px;">
                            <span class="material-symbols-outlined" style="font-size: 16px;">compress</span>
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
                        <span class="material-symbols-outlined">person</span>
                    </div>
                    <div class="user-profile-details">
                        <div class="user-profile-name"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="user-profile-role <?= $user['role'] === 'admin' ? 'role-admin' : 'role-operator' ?>">
                            <span class="material-symbols-outlined" style="font-size: 13px;">
                                <?= $user['role'] === 'admin' ? 'shield_person' : 'badge' ?>
                            </span>
                            <?= strtoupper($user['role']) ?>
                        </div>
                    </div>
                </div>

                <a href="logout.php" class="btn-sidebar-logout">
                    <span class="material-symbols-outlined">logout</span>
                    <span>Keluar Sistem</span>
                </a>
            </div>
        </aside>

        <!-- Main Workspace -->
        <main class="main-viewport">
            
            <!-- Page Top Bar -->
            <div class="page-top-bar">
                <div class="page-title-box">
                    <h1 class="page-title">
                        <span class="material-symbols-outlined" style="font-size: 32px; color: #60a5fa;">barcode_scanner</span>
                        <span>Stasiun Perekaman Packaging</span>
                    </h1>
                    <p class="page-subtitle">Perekaman otomatis saat scan nomor resi invoice, dan simpan saat scan ulang resi yang sama.</p>
                </div>

                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.04); border: 1px solid var(--border-color); padding: 6px 14px; border-radius: 30px; font-size: 0.85rem;">
                        <span class="material-symbols-outlined" style="color: #10b981; font-size: 18px;">sensors</span>
                        <span style="font-weight: 600; color: #e2e8f0;">Scanner Siap</span>
                    </div>
                </div>
            </div>

            <!-- Barcode Scanner Input Banner -->
            <div class="scanner-banner" id="scannerBanner">
                <div class="scanner-title">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-outlined" style="font-size: 20px;">qr_code_scanner</span>
                        <span id="scannerPromptText">Scan No Resi / Barcode Invoice untuk Mulai:</span>
                    </div>
                    <span id="scannerHintBadge" style="font-size: 0.75rem; background: rgba(255,255,255,0.08); padding: 3px 10px; border-radius: 6px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                        <span class="material-symbols-outlined" style="font-size: 14px; color: #60a5fa;">center_focus_strong</span>
                        Auto-Focus Aktif
                    </span>
                </div>

                <div class="scanner-input-wrapper">
                    <div class="scanner-icon">
                        <span class="material-symbols-outlined">barcode_scanner</span>
                    </div>
                    <input type="text" 
                           id="resiInput" 
                           class="scanner-input" 
                           placeholder="Arahkan Barcode Scanner atau Ketik No Resi disini..." 
                           autocomplete="off" 
                           autofocus>
                    
                    <button type="button" id="manualStopBtn" class="btn btn-success" style="display: none; padding: 0 1.5rem; font-size: 0.95rem;">
                        <span class="material-symbols-outlined">stop_circle</span>
                        <span>Selesai Packing</span>
                    </button>

                    <button type="button" id="cancelRecordBtn" class="btn btn-danger" style="display: none; padding: 0 1.25rem;">
                        <span class="material-symbols-outlined">cancel</span>
                        <span>Batal</span>
                    </button>
                </div>

                <div class="scanner-hint">
                    <span class="material-symbols-outlined" style="font-size: 18px; color: #fbbf24;">lightbulb</span> 
                    <span id="workflowTip">
                        <b>Cara Kerja Cepat:</b> Scan Resi sekali untuk <b>Mulai Rekam</b>. Tempel resi pada paket, lalu scan kembali nomor resi yang sama untuk <b>Menyimpan Video MP4</b>.
                    </span>
                </div>
            </div>

            <!-- Packing Workstation Grid -->
            <div class="packing-grid">
                
                <!-- Left Side: Camera Live Feed -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <span class="material-symbols-outlined">videocam</span>
                            <span>Live Feed Kamera Packing</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="material-symbols-outlined" style="color: #94a3b8; font-size: 18px;">photo_camera</span>
                            <select id="cameraSelect" class="select-input">
                                <option value="">Memuat perangkat kamera...</option>
                            </select>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Live Camera Viewport -->
                        <div class="camera-container">
                            <video id="cameraFeed" class="camera-video" autoplay playsinline muted></video>
                            
                            <!-- Top Overlay -->
                            <div class="camera-overlay-top">
                                <div class="status-indicator status-standby" id="statusBadge">
                                    <span class="rec-dot" style="background:#10b981;"></span> STANDBY (SIAP SCAN)
                                </div>
                                <div class="timer-box" id="timerDisplay">
                                    <span class="material-symbols-outlined" style="font-size: 18px; color: #94a3b8;">timer</span>
                                    <span>00:00:00</span>
                                </div>
                            </div>

                            <!-- Bottom Overlay -->
                            <div class="camera-overlay-bottom">
                                <div class="overlay-meta">
                                    <div id="overlayResi" class="meta-resi" style="display: none;"></div>
                                    <div style="font-size: 0.75rem; color: #cbd5e1; display: flex; align-items: center; gap: 4px;">
                                        <span class="material-symbols-outlined" style="font-size: 14px;">badge</span>
                                        <span>Operator: <b><?= htmlspecialchars($user['name']) ?></b></span>
                                    </div>
                                </div>
                                <div class="overlay-meta" style="text-align: right;">
                                    <div id="overlayClock" style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: #60a5fa; font-weight: 700;">
                                        00:00:00 WIB
                                    </div>
                                    <div style="font-size: 0.75rem; color: #94a3b8;">
                                        <?= date('d M Y') ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Technical Footnote -->
                        <div class="camera-controls">
                            <div style="display:flex; align-items:center; gap:8px; font-size:0.82rem; color:#94a3b8;">
                                <span class="material-symbols-outlined" style="font-size: 16px; color: #10b981;">volume_up</span>
                                <span>Audio Cue: <b>Aktif</b></span>
                                <span>•</span>
                                <span class="material-symbols-outlined" style="font-size: 16px; color: #3b82f6;">high_quality</span>
                                <span>Format: <b>MP4 H.264 (Compressed)</b></span>
                            </div>
                            <div style="font-size: 0.82rem; color: #64748b;">
                                Tekan <kbd style="background:#1e293b; padding:2px 6px; border-radius:4px; border:1px solid #334155; font-family:'JetBrains Mono';">Enter</kbd> otomatis via scanner fisik.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Recent Packings Feed -->
                <div class="card">
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
                            <div style="text-align: center; color: #64748b; padding: 2rem 0;">
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
                <div style="font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined" style="color: #60a5fa;">movie</span>
                    <span>Hasil Rekaman Video:</span>
                    <span id="modalResiTitle" style="color: #60a5fa; font-family: 'JetBrains Mono', monospace;">-</span>
                </div>
                <button class="btn btn-outline btn-sm" onclick="closeVideoModal()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="modal-body">
                <video id="modalVideoPlayer" class="modal-video-player" controls></video>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                    <a id="modalPackingDownloadBtn" href="#" class="btn btn-primary btn-sm" download style="display: none;">
                        <span class="material-symbols-outlined">download</span>
                        <span>Download Video MP4</span>
                    </a>
                    <button class="btn btn-outline" onclick="closeVideoModal()">Tutup Jendela</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/packing.js"></script>
</body>
</html>
