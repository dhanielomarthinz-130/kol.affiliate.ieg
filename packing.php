<?php
// packing.php
require_once __DIR__ . '/config/auth.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();
$todayDate = date('Y-m-d');

// Total packings today
$stmtToday = $db->prepare("SELECT COUNT(*) as cnt FROM packings WHERE DATE(created_at) = ?");
$stmtToday->execute([$todayDate]);
$initialTodayCount = intval($stmtToday->fetch()['cnt'] ?? 0);

// Pre-fetch recent packings for instant zero-delay rendering
$stmtRecent = $db->query("SELECT * FROM packings ORDER BY id DESC LIMIT 10");
$recentPackings = $stmtRecent ? $stmtRecent->fetchAll() : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stasiun Kerja Packing - KOL Affiliate</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="operator-body">

    <div class="operator-screen">
        
        <!-- ==========================================
             TOP HEADER BAR (NO SIDEBAR - FULL WIDTH)
             ========================================== -->
        <header class="operator-header" id="scannerBanner">
            
            <!-- Left: Brand Identity -->
            <div class="brand-area">
                <div class="brand-badge">
                    <span class="material-symbols-outlined" style="font-size: 22px;">inventory_2</span>
                </div>
                <div>
                    <div class="brand-title">KOL PACKING</div>
                    <div class="brand-sub">Auto Video Recorder & Audit</div>
                </div>
            </div>

            <!-- Center: Barcode Scanner Hero Field -->
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

                <button type="button" id="manualStopBtn" class="btn btn-success" style="display: none; padding: 0.55rem 1rem;">
                    <span class="material-symbols-outlined" style="font-size: 17px;">stop_circle</span>
                    <span>Selesai Packing</span>
                </button>

                <button type="button" id="cancelRecordBtn" class="btn btn-danger" style="display: none; padding: 0.55rem 0.9rem;">
                    <span class="material-symbols-outlined" style="font-size: 17px;">cancel</span>
                    <span>Batal</span>
                </button>

                <span class="autofocus-chip">
                    <span class="material-symbols-outlined" style="font-size: 14px; color: #2563eb;">center_focus_strong</span>
                    <span>Auto-Focus</span>
                </span>
            </div>

            <!-- Right: Operator Profile & Actions -->
            <div class="operator-header-right">
                <?php if ($user['role'] === 'admin'): ?>
                <a href="admin" class="header-admin-btn" title="Buka Portal Admin">
                    <span class="material-symbols-outlined" style="font-size: 16px;">dashboard</span>
                    <span>Portal Admin</span>
                </a>
                <?php endif; ?>

                <div class="user-pill">
                    <div class="user-pill-avatar">
                        <span class="material-symbols-outlined" style="font-size: 16px;">person</span>
                    </div>
                    <div>
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

        <!-- ==========================================
             MAIN WORKSTATION GRID (2 COLUMNS)
             ========================================== -->
        <main class="operator-main-grid">
            
            <!-- LEFT COLUMN: Live Camera Studio -->
            <div class="card camera-card">
                <div class="card-header">
                    <div class="card-title">
                        <span class="material-symbols-outlined">videocam</span>
                        <span>Feed Kamera Meja Packing</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-outlined" style="color: #64748b; font-size: 18px;">photo_camera</span>
                        <select id="cameraSelect" class="select-input" style="max-width: 260px;">
                            <option value="">Memuat perangkat kamera...</option>
                        </select>

                        <select id="qualitySelect" class="select-input" title="Pilih Mode Penyimpanan" style="font-weight: 600; color: #2563eb; background: #eff6ff; border-color: #bfdbfe; max-width: 190px;">
                            <option value="saver" selected>⚡ Mode Hemat (~400 KB)</option>
                            <option value="hd">🎥 Mode HD (~1.4 MB)</option>
                        </select>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Cinema-Grade Matte Camera Viewport -->
                    <div class="camera-container">
                        <video id="cameraFeed" class="camera-video" autoplay playsinline muted></video>
                        
                        <!-- Top HUD Overlay -->
                        <div class="camera-overlay-top">
                            <div class="status-indicator status-standby" id="statusBadge">
                                <span class="rec-dot"></span>
                                <span id="statusBadgeText">STANDBY (SIAP SCAN)</span>
                            </div>
                            <div class="timer-box" id="timerDisplay">
                                <span class="material-symbols-outlined" style="font-size: 16px; color: #94a3b8;">timer</span>
                                <span>00:00:00</span>
                            </div>
                        </div>

                        <!-- Bottom HUD Overlay -->
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

                    <!-- Footnote Metadata Bar -->
                    <div class="camera-footnote">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="material-symbols-outlined" style="font-size: 15px; color: #10b981;">volume_up</span>
                            <span>Audio Cue: <b style="color: #0f172a;">Aktif</b></span>
                            <span style="color: #cbd5e1;">•</span>
                            <span class="material-symbols-outlined" style="font-size: 15px; color: #2563eb;">high_quality</span>
                            <span>Format: <b style="color: #0f172a;">MP4 H.264 Auto-Compress</b></span>
                        </div>
                        <div>
                            Tekan <kbd style="background: #f1f5f9; padding: 1px 5px; border-radius: 4px; border: 1px solid #cbd5e1; font-family: 'JetBrains Mono', monospace; font-size: 0.72rem;">Enter</kbd> otomatis via scanner fisik.
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Riwayat & Total Resi yang di-scan -->
            <div class="card history-card">
                <div class="card-header">
                    <div class="card-title">
                        <span class="material-symbols-outlined">receipt_long</span>
                        <span>Riwayat & Aktivitas Packing</span>
                    </div>
                    <button class="btn btn-outline btn-sm btn-icon" onclick="window.station.loadRecentHistory()" title="Segarkan Data">
                        <span class="material-symbols-outlined" style="font-size: 17px;">sync</span>
                    </button>
                </div>

                <div class="card-body" style="gap: 10px;">
                    
                    <!-- HERO STAT CARD: Total Resi Ter-scan Hari Ini -->
                    <div class="today-counter-card">
                        <div class="today-counter-icon">
                            <span class="material-symbols-outlined" style="font-size: 24px;">inventory_2</span>
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

                    <!-- Header List -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 2px 2px 0;">
                        <span style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em;">Daftar Terakhir Di-Scan</span>
                        <span style="font-size: 0.7rem; color: #94a3b8;">10 Paket Terkini</span>
                    </div>

                    <!-- Scrollable History Feed (Pre-rendered from Server) -->
                    <div class="history-list" id="recentHistoryList">
                        <?php if (empty($recentPackings)): ?>
                            <div style="text-align: center; color: #94a3b8; padding: 2.5rem 1rem; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                                <div style="width: 44px; height: 44px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                                    <span class="material-symbols-outlined" style="font-size: 24px;">qr_code_scanner</span>
                                </div>
                                <div style="font-size: 0.86rem; font-weight: 600; color: #64748b;">Belum ada paket yang direkam</div>
                                <div style="font-size: 0.76rem; color: #94a3b8; max-width: 220px; line-height: 1.3;">Arahkan scanner ke resi pertama untuk mulai merekam otomatis.</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentPackings as $item): ?>
                                <?php 
                                    $videoUrl = 'uploads/videos/' . htmlspecialchars($item['video_filename']);
                                    $durMin = floor($item['duration_seconds'] / 60);
                                    $durSec = $item['duration_seconds'] % 60;
                                    $formattedDur = sprintf('%02d:%02d', $durMin, $durSec);
                                    $formattedDate = date('d/m/Y H:i', strtotime($item['created_at']));
                                ?>
                                <div class="history-item">
                                    <div>
                                        <div class="history-resi"><?= htmlspecialchars($item['resi_no']) ?></div>
                                        <div class="history-sub" style="display: flex; align-items: center; gap: 4px;">
                                            <span><?= $formattedDate ?></span>
                                            <span>•</span>
                                            <span class="material-symbols-outlined" style="font-size: 13px; color: #94a3b8;">timer</span>
                                            <span><?= $formattedDur ?></span>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span class="history-badge">MP4</span>
                                        <button class="btn btn-outline btn-sm btn-icon" onclick="window.previewVideo('<?= $videoUrl ?>', '<?= htmlspecialchars($item['resi_no']) ?>', <?= $item['id'] ?>)" title="Putar Video">
                                            <span class="material-symbols-outlined" style="font-size: 17px; color: #2563eb;">play_arrow</span>
                                        </button>
                                        <a href="download.php?id=<?= $item['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Download MP4" download>
                                            <span class="material-symbols-outlined" style="font-size: 16px; color: #64748b;">download</span>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- ==========================================
         MODAL QUICK VIDEO PREVIEW WITH CONTROLS
         ========================================== -->
    <div class="modal-overlay" id="videoModal">
        <div class="modal-content">
            <div class="modal-header">
                <div style="font-weight: 700; font-size: 1.05rem; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined" style="color: #2563eb;">movie</span>
                    <span>Video Rekaman:</span>
                    <span id="modalResiTitle" style="color: #2563eb; font-family: 'JetBrains Mono', monospace;">-</span>
                </div>
                <button class="btn btn-outline btn-sm btn-icon" onclick="closeVideoModal()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="modal-body">
                <video id="modalVideoPlayer" class="modal-video-player" controls></video>

                <!-- Speed playback controls -->
                <div class="playback-controls">
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 0.78rem; color: #64748b;">
                        <span class="material-symbols-outlined" style="font-size: 16px;">speed</span>
                        <span>Kecepatan Audit:</span>
                    </div>
                    <div class="speed-buttons">
                        <button type="button" class="speed-btn active" onclick="setModalSpeed(1, this)">1x</button>
                        <button type="button" class="speed-btn" onclick="setModalSpeed(1.25, this)">1.25x</button>
                        <button type="button" class="speed-btn" onclick="setModalSpeed(1.5, this)">1.5x</button>
                        <button type="button" class="speed-btn" onclick="setModalSpeed(2, this)">2x</button>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <a id="modalPackingDownloadBtn" href="#" class="btn btn-primary btn-sm" download style="display: none;">
                        <span class="material-symbols-outlined" style="font-size: 16px;">download</span>
                        <span>Download MP4</span>
                    </a>
                    <button class="btn btn-outline btn-sm" onclick="closeVideoModal()" style="margin-left: auto;">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/packing.js?v=<?= filemtime(__DIR__ . '/assets/js/packing.js') ?>"></script>
    <script>
        function setModalSpeed(speed, btn) {
            const player = document.getElementById('modalVideoPlayer');
            if (player) player.playbackRate = speed;
            document.querySelectorAll('.speed-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
        }
    </script>
</body>
</html>
