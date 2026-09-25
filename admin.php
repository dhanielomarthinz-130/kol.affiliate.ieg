<?php
// admin.php
require_once __DIR__ . '/config/auth.php';
requireRole('admin');
require_once __DIR__ . '/config/google_sync.php';

$user = getCurrentUser();
$isSuperAdmin = isSuperAdmin();
$todayDate = date('Y-m-d');
$googleSyncCfg = getGoogleSyncConfig();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - KOL Packaging Audit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <!-- Premium Flatpickr Datepicker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>

    <div class="app-layout">
        
        <!-- Clean Modern Light Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="admin" class="sidebar-brand">
                    <img src="assets/image/logo-IEG.png" alt="IEG Logo" class="sidebar-brand-logo" style="height: 38px; width: auto; object-fit: contain; filter: drop-shadow(0 2px 5px rgba(0,0,0,0.3));">
                    <div>
                        <div class="sidebar-brand-title">IEG PACKING</div>
                        <div class="sidebar-brand-sub"><?= $isSuperAdmin ? 'Superadmin Portal' : 'Admin Portal' ?></div>
                    </div>
                </a>
            </div>

            <div class="sidebar-content">
                <div>
                    <div class="sidebar-section-title">Audit & Analitik</div>
                    <nav class="sidebar-nav">
                        <a href="javascript:void(0)" class="nav-link active" id="navPackings" onclick="switchAdminView('packings')">
                            <span class="material-symbols-outlined">analytics</span>
                            <span>Data Packaging</span>
                            <span class="nav-badge" style="background: var(--primary-subtle); color: var(--primary);">ALL</span>
                        </a>

                        <a href="packing" class="nav-link">
                            <span class="material-symbols-outlined">videocam</span>
                            <span>Layar Packing</span>
                        </a>
                    </nav>
                </div>

                <div>
                    <div class="sidebar-section-title">Manajemen & Sistem</div>
                    <nav class="sidebar-nav">
                        <a href="javascript:void(0)" class="nav-link" id="navUsers" onclick="switchAdminView('users')">
                            <span class="material-symbols-outlined">manage_accounts</span>
                            <span>Kelola Pengguna</span>
                        </a>

                        <?php if ($isSuperAdmin): ?>
                        <a href="javascript:void(0)" class="nav-link" id="navMaintenance" onclick="switchAdminView('maintenance')">
                            <span class="material-symbols-outlined" style="color: #f59e0b;">build_circle</span>
                            <span>Maintenance Sistem</span>
                            <span class="nav-badge" style="background: #fef3c7; color: #b45309; font-weight: 800;">SUPER</span>
                        </a>
                        <?php endif; ?>

                        <button type="button" class="nav-link" onclick="openGoogleSyncModal()">
                            <span class="material-symbols-outlined" style="color: #10b981;">cloud_sync</span>
                            <span>Setting Google Sync</span>
                        </button>

                        <button type="button" class="nav-link" onclick="exportToExcel()">
                            <span class="material-symbols-outlined" style="color: #107c41;">description</span>
                            <span>Export Data Excel</span>
                        </button>
                    </nav>
                </div>

                <div style="margin-top: auto;">
                    <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 12px; font-size: 0.74rem; color: var(--text-muted);">
                        <div style="display: flex; align-items: center; gap: 5px; color: var(--success); font-weight: 700; margin-bottom: 2px;">
                            <span class="material-symbols-outlined" style="font-size: 15px;">storage</span>
                            <span>Status Server</span>
                        </div>
                        Kompresi MP4 H.264 &amp; Google Drive Sync aktif.
                    </div>
                </div>
            </div>

            <!-- Sidebar User Profile & Logout -->
            <div class="sidebar-footer">
                <div class="user-profile-widget">
                    <div class="user-avatar-circle" style="<?= $isSuperAdmin ? 'background:#fef3c7; color:#b45309;' : '' ?>">
                        <span class="material-symbols-outlined"><?= $isSuperAdmin ? 'verified_user' : 'shield_person' ?></span>
                    </div>
                    <div class="user-profile-details">
                        <div class="user-profile-name"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="user-profile-role <?= $isSuperAdmin ? 'badge-role-superadmin' : 'role-admin' ?>" style="display:inline-block; margin-top:2px;">
                            <?= strtoupper($user['role']) ?>
                        </div>
                    </div>
                </div>

                <a href="logout" class="btn-sidebar-logout" title="Keluar dari sistem">
                    <span class="material-symbols-outlined" style="font-size: 15px;">logout</span>
                    <span>Keluar</span>
                </a>
            </div>
        </aside>

        <!-- Main Workspace -->
        <main class="main-viewport">
            
            <!-- Page Top Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <h1 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; letter-spacing: -0.02em;" id="mainPageTitle">
                        <span class="material-symbols-outlined" style="font-size: 26px; color: var(--primary);">table_view</span>
                        <span>Semua Data Hasil Packaging</span>
                    </h1>
                    <p style="font-size: 0.8rem; color: #64748b; margin-top: 2px;" id="mainPageSub">
                        Pusat pemantauan rekaman video, verifikasi nomor resi, dan audit durasi kerja operator packing.
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <!-- Tombol Sync dengan Google Drive Icon -->
                    <button onclick="startBatchSync()" class="btn btn-primary btn-sm" id="btnSyncGoogle" style="background:#0284c7; border-color:#0284c7; display:inline-flex; align-items:center; gap:6px;" title="Sinkronkan data dan rekaman video sesuai filter ke Google Drive & Sheets">
                        <svg width="18" height="18" viewBox="0 0 87.3 78" style="vertical-align: middle;">
                          <path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8h-27.5c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
                          <path d="m43.65 25-13.75-23.8c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44a9.06 9.06 0 0 0 -1.2 4.5h27.5z" fill="#00ac47"/>
                          <path d="m73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5h-27.502l5.852 11.5z" fill="#ea4335"/>
                          <path d="m43.65 25 13.75-23.8c-1.35-.8-2.9-1.2-4.5-1.2h-18.5c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/>
                          <path d="m59.8 53h-32.3l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/>
                          <path d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3l-13.75 23.8 16.15 28h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/>
                        </svg>
                        <span>Sync ke Google Sheet</span>
                        <span class="badge" id="pendingSyncBadge" style="background:#f59e0b; color:#fff; display:none; padding:1px 7px; font-size:0.7rem; border-radius:10px; margin-left:2px;">0</span>
                    </button>

                    <!-- Tombol Export Excel dengan Icon Excel -->
                    <button onclick="exportToExcel()" class="btn btn-sm" style="background:#107c41; color:#fff; border:1px solid #0e6c38; display:inline-flex; align-items:center; gap:6px;" title="Download Data ke Excel (.xls)">
                        <svg width="17" height="17" viewBox="0 0 24 24" style="vertical-align: middle;">
                          <path fill="#ffffff" d="M21.17 3.25H7.83A1.58 1.58 0 0 0 6.25 4.83v2.92h5.5a1.58 1.58 0 0 1 1.58 1.58v5.34a1.58 1.58 0 0 1-1.58 1.58h-5.5v2.92a1.58 1.58 0 0 0 1.58 1.58h13.34a1.58 1.58 0 0 0 1.58-1.58V4.83a1.58 1.58 0 0 0-1.58-1.58z"/>
                          <path fill="#ffffff" opacity="0.9" d="M12.5 7.25H2.5A1.5 1.5 0 0 0 1 8.75v6.5A1.5 1.5 0 0 0 2.5 16.75h10a1.5 1.5 0 0 0 1.5-1.5v-6.5a1.5 1.5 0 0 0-1.5-1.5z"/>
                          <path fill="#107c41" d="M4.6 9.2l1.6 2.8-1.6 2.8h1.4l.9-1.8.9 1.8h1.4l-1.6-2.8 1.6-2.8H9.4L8.4 11l-.9-1.8H6z"/>
                        </svg>
                        <span>Export Excel</span>
                    </button>

                    <button onclick="openGoogleSyncModal()" class="btn btn-outline btn-sm" title="Pengaturan Google Drive & Sheet">
                        <span class="material-symbols-outlined" style="color:#10b981;">settings</span>
                        <span>Setting Google</span>
                    </button>

                    <a href="packing.php" class="btn btn-outline btn-sm">
                        <span class="material-symbols-outlined">videocam</span>
                        <span>Layar Packing</span>
                    </a>
                </div>
            </div>

            <!-- ==========================================
                 VIEW 1: DATA PACKAGING (MAIN DASHBOARD)
                 ========================================== -->
            <div id="viewPackings">
                <!-- Summary KPI Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <span class="material-symbols-outlined">inventory_2</span>
                        </div>
                        <div>
                            <div class="stat-val" id="statTodayCount">0</div>
                            <div class="stat-label">Paket Dipacking Hari Ini</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon success">
                            <span class="material-symbols-outlined">timer</span>
                        </div>
                        <div>
                            <div class="stat-val" id="statTodayAvg">0s</div>
                            <div class="stat-label">Rata-rata Durasi Packing</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <span class="material-symbols-outlined">monitoring</span>
                        </div>
                        <div>
                            <div class="stat-val" id="statTotalCount">0</div>
                            <div class="stat-label">Total Semua Paket Tersimpan</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <span class="material-symbols-outlined">hard_drive</span>
                        </div>
                        <div>
                            <div class="stat-val" id="statStorage">0 MB</div>
                            <div class="stat-label">Total Penyimpanan MP4</div>
                        </div>
                    </div>
                </div>

                <!-- Realtime Refresh Indicator -->
                <div style="display:flex; align-items:center; gap:8px; margin-bottom: 1.25rem; font-size:0.76rem; color:#64748b;">
                    <span class="realtime-dot"></span>
                    <span>Data diperbarui otomatis setiap 15 detik &nbsp;•&nbsp; Terakhir diperbarui: <b id="lastRefreshTime" style="color:#0f172a;">-</b></span>
                    <button onclick="loadStats()" style="background:none; border:none; cursor:pointer; color:#2563eb; font-size:0.76rem; font-weight:600; padding:0; display:flex; align-items:center; gap:3px;" title="Refresh sekarang">
                        <span class="material-symbols-outlined" style="font-size:14px;">refresh</span>
                        Refresh Sekarang
                    </button>
                </div>

                <!-- Modern Filter Card with Flatpickr & Quick Presets -->
                <div class="card" style="margin-bottom: 1.25rem;">
                    <div class="card-body">
                        <!-- Quick Preset Pills -->
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                            <div style="font-size:0.78rem; font-weight:700; color:#475569; display:flex; align-items:center; gap:4px;">
                                <span class="material-symbols-outlined" style="font-size:16px; color:#2563eb;">tune</span>
                                <span>Filter Cepat Periode Tanggal:</span>
                            </div>
                            <div class="date-presets" style="display:flex; gap:6px; flex-wrap:wrap;">
                                <button type="button" class="preset-date-btn active" id="btnPresetToday" onclick="applyDatePreset('today', this)">Hari Ini</button>
                                <button type="button" class="preset-date-btn" onclick="applyDatePreset('yesterday', this)">Kemarin</button>
                                <button type="button" class="preset-date-btn" onclick="applyDatePreset('last7', this)">7 Hari Terakhir</button>
                                <button type="button" class="preset-date-btn" onclick="applyDatePreset('thismonth', this)">Bulan Ini</button>
                                <button type="button" class="preset-date-btn" onclick="applyDatePreset('all', this)">Semua Tanggal</button>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)) 100px 140px; gap: 10px; align-items: end;">
                            <div>
                                <label class="form-label">
                                    <span class="material-symbols-outlined" style="font-size: 14px; vertical-align: -2px;">search</span>
                                    <span>Cari No Resi / Invoice</span>
                                </label>
                                <input type="text" id="searchResi" class="form-control" placeholder="Ketik No Resi..." autocomplete="off">
                            </div>

                            <div>
                                <label class="form-label">
                                    <span class="material-symbols-outlined" style="font-size: 14px; vertical-align: -2px;">badge</span>
                                    <span>Operator</span>
                                </label>
                                <select id="filterOperator" class="form-control" style="cursor: pointer;">
                                    <option value="0">Semua Operator</option>
                                </select>
                            </div>

                            <div>
                                <label class="form-label">
                                    <span class="material-symbols-outlined" style="font-size: 14px; vertical-align: -2px;">event</span>
                                    <span>Tanggal Dari (From Date)</span>
                                </label>
                                <input type="text" id="filterDateFrom" class="form-control flatpickr-input" placeholder="Pilih Tanggal Mulai" value="<?= $todayDate ?>">
                            </div>

                            <div>
                                <label class="form-label">
                                    <span class="material-symbols-outlined" style="font-size: 14px; vertical-align: -2px;">event_available</span>
                                    <span>Sampai Tanggal (To Date)</span>
                                </label>
                                <input type="text" id="filterDateTo" class="form-control flatpickr-input" placeholder="Pilih Tanggal Akhir" value="<?= $todayDate ?>">
                            </div>

                            <div>
                                <button id="btnResetFilter" class="btn btn-outline" style="width: 100%; height: 38px;">
                                    <span class="material-symbols-outlined">restart_alt</span>
                                    <span>Reset</span>
                                </button>
                            </div>

                            <div>
                                <button onclick="exportToExcel()" class="btn btn-sm" style="width: 100%; height: 38px; background:#107c41; color:#fff; border:1px solid #0e6c38; display:inline-flex; align-items:center; justify-content:center; gap:6px;" title="Export data saat ini ke Excel">
                                    <span class="material-symbols-outlined" style="font-size:17px;">file_download</span>
                                    <span>Export Excel</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Data Hasil Packaging -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <span class="material-symbols-outlined">video_library</span>
                            <span>Daftar Video Hasil Packing</span>
                        </div>
                        <div style="font-size: 0.82rem; color: #64748b;" id="recordCountInfo">
                            Memuat data...
                        </div>
                    </div>

                    <div class="card-body" style="padding: 0;">
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">No</th>
                                        <th class="th-sortable" onclick="handleSortTable('resi_no')" title="Urutkan No Resi">
                                            <div class="th-sort-inner"><span>No Resi / Invoice</span><span class="material-symbols-outlined sort-icon" id="sortIcon_resi_no">unfold_more</span></div>
                                        </th>
                                        <th class="th-sortable" onclick="handleSortTable('operator_name')" title="Urutkan Operator">
                                            <div class="th-sort-inner"><span>Nama Operator</span><span class="material-symbols-outlined sort-icon" id="sortIcon_operator_name">unfold_more</span></div>
                                        </th>
                                        <th class="th-sortable" onclick="handleSortTable('duration_seconds')" title="Urutkan Durasi">
                                            <div class="th-sort-inner"><span>Durasi Packing</span><span class="material-symbols-outlined sort-icon" id="sortIcon_duration_seconds">unfold_more</span></div>
                                        </th>
                                        <th class="th-sortable" onclick="handleSortTable('created_at')" title="Urutkan Tanggal">
                                            <div class="th-sort-inner"><span>Tanggal &amp; Jam WIB</span><span class="material-symbols-outlined sort-icon" id="sortIcon_created_at">arrow_downward</span></div>
                                        </th>
                                        <th class="th-sortable" onclick="handleSortTable('gdrive_url')" title="Urutkan Status Drive">
                                            <div class="th-sort-inner"><span>Google Drive &amp; Sheet</span><span class="material-symbols-outlined sort-icon" id="sortIcon_gdrive_url">unfold_more</span></div>
                                        </th>
                                        <th class="th-sortable" onclick="handleSortTable('video_filesize')" title="Urutkan Ukuran File">
                                            <div class="th-sort-inner"><span>Ukuran File</span><span class="material-symbols-outlined sort-icon" id="sortIcon_video_filesize">unfold_more</span></div>
                                        </th>
                                        <th style="width: 200px; text-align: right;">Aksi &amp; Audit</th>
                                    </tr>
                                </thead>
                                <tbody id="packingsTableBody">
                                    <tr>
                                        <td colspan="8" style="text-align:center; padding: 2rem; color: #94a3b8;">
                                            Memuat data...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination controls -->
                        <div style="padding: 0.85rem 1.25rem; display: flex; justify-content: flex-end; align-items: center; border-top: 1px solid var(--border-color);" id="tablePagination">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==========================================
                 VIEW 2: KELOLA PENGGUNA (DEDICATED DATA TABLE)
                 ========================================== -->
            <div id="viewUsers" style="display: none;">
                <div class="card" style="margin-bottom: 1.25rem;">
                    <div class="card-header" style="padding: 1.1rem 1.5rem;">
                        <div>
                            <div class="card-title" style="font-size: 1.15rem;">
                                <span class="material-symbols-outlined" style="color: #2563eb;">manage_accounts</span>
                                <span>Manajemen Operator & Pengguna Sistem</span>
                            </div>
                            <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;">
                                Kelola akun operator packing dan administrator sistem. Anda dapat mengaktifkan atau menonaktifkan (inactive) akun kapan saja.
                            </div>
                        </div>

                        <button onclick="openAddUserModal()" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:6px;">
                            <span class="material-symbols-outlined">person_add</span>
                            <span>Tambah Pengguna Baru</span>
                        </button>
                    </div>

                    <div class="card-body" style="padding: 0;">
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">No</th>
                                        <th>Nama Lengkap</th>
                                        <th>Username</th>
                                        <th>Role Akses</th>
                                        <th>Status Akun</th>
                                        <th>Terdaftar</th>
                                        <th style="width: 190px; text-align: right;">Aksi Status</th>
                                    </tr>
                                </thead>
                                <tbody id="usersTableBody">
                                    <tr>
                                        <td colspan="7" style="text-align:center; padding: 2rem; color: #94a3b8;">
                                            Memuat daftar operator...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($isSuperAdmin): ?>
            <!-- ==========================================
                 VIEW 3: MAINTENANCE SISTEM (SUPERADMIN ONLY)
                 ========================================== -->
            <div id="viewMaintenance" style="display: none;">
                <!-- Superadmin Diagnostics Header -->
                <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff; padding: 1.5rem; border-radius: 12px; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 1.2rem; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                            <span class="material-symbols-outlined" style="color: #f59e0b; font-size: 26px;">build_circle</span>
                            <span>Pusat Maintenance & Diagnostik Sistem</span>
                        </div>
                        <div style="font-size: 0.82rem; color: #94a3b8; margin-top: 4px;">
                            Area khusus Superadmin untuk memantau performa server, optimasi database SQLite/MySQL, dan pembersihan file sampah.
                        </div>
                    </div>

                    <button onclick="loadMaintenanceInfo()" class="btn btn-outline btn-sm" style="color:#fff; border-color:#475569;" title="Segarkan Informasi Diagnostik">
                        <span class="material-symbols-outlined">refresh</span>
                        <span>Segarkan Diagnostik</span>
                    </button>
                </div>

                <!-- Server KPI & Storage Cards -->
                <div class="stats-grid" style="margin-bottom: 1.25rem;">
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <span class="material-symbols-outlined">storage</span>
                        </div>
                        <div>
                            <div class="stat-val" id="maintFreeDisk">-</div>
                            <div class="stat-label">Ruang Harddisk Bebas</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <span class="material-symbols-outlined">video_file</span>
                        </div>
                        <div>
                            <div class="stat-val" id="maintVideoSize">-</div>
                            <div class="stat-label">Total Folder uploads/videos</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon success">
                            <span class="material-symbols-outlined">cloud_done</span>
                        </div>
                        <div>
                            <div class="stat-val" id="maintSyncRatio">-</div>
                            <div class="stat-label">Rasio Sync ke Google Drive</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <span class="material-symbols-outlined">database</span>
                        </div>
                        <div>
                            <div class="stat-val" id="maintDbSize">-</div>
                            <div class="stat-label">Ukuran Database</div>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Action Card -->
                <div class="card" style="margin-bottom: 1.25rem;">
                    <div class="card-header">
                        <div class="card-title">
                            <span class="material-symbols-outlined" style="color: #f59e0b;">handyman</span>
                            <span>Tindakan Pemeliharaan (Maintenance Actions)</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
                            
                            <!-- Card Action 1: Optimize Database -->
                            <div style="border: 1px solid var(--border-color); border-radius: 10px; padding: 16px; background: #f8fafc; display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <div style="font-weight: 700; font-size: 0.95rem; color: #0f172a; display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
                                        <span class="material-symbols-outlined" style="color: #2563eb;">database</span>
                                        <span>Optimalkan Database (VACUUM &amp; ANALYZE)</span>
                                    </div>
                                    <p style="font-size: 0.8rem; color: #64748b; line-height: 1.4;">
                                        Merapikan file database SQLite/MySQL, mengindeks ulang, memadatkan tabel, dan meningkatkan kecepatan query data packing.
                                    </p>
                                </div>
                                <div style="margin-top: 14px;">
                                    <button onclick="runOptimizeDB()" class="btn btn-primary btn-sm" id="btnOptimizeDB" style="width: 100%;">
                                        <span class="material-symbols-outlined">speed</span>
                                        <span>Jalankan Optimasi Database</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Card Action 2: Clean Temp & Corrupt Files -->
                            <div style="border: 1px solid var(--border-color); border-radius: 10px; padding: 16px; background: #f8fafc; display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <div style="font-weight: 700; font-size: 0.95rem; color: #0f172a; display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
                                        <span class="material-symbols-outlined" style="color: #dc2626;">delete_sweep</span>
                                        <span>Bersihkan File 0-Byte / Corrupt (.tmp)</span>
                                    </div>
                                    <p style="font-size: 0.8rem; color: #64748b; line-height: 1.4;">
                                        Menghapus file video rekaman yang gagal tersimpan (ukuran 0 byte) atau file temporary yang tertinggal di folder server.
                                    </p>
                                </div>
                                <div style="margin-top: 14px;">
                                    <button onclick="runCleanTempFiles()" class="btn btn-danger btn-sm" id="btnCleanTemp" style="width: 100%;">
                                        <span class="material-symbols-outlined">cleaning_services</span>
                                        <span>Bersihkan File Sampah Sekarang</span>
                                    </button>
                                </div>
                            </div>

                        </div>

                        <!-- System Environment Details -->
                        <div style="margin-top: 1.5rem; background: #ffffff; border: 1px solid var(--border-color); border-radius: 10px; padding: 14px;">
                            <div style="font-weight: 700; font-size: 0.85rem; color: #475569; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                                <span class="material-symbols-outlined" style="font-size: 18px; color: #64748b;">info</span>
                                <span>Informasi Lingkungan Server (Server Specs)</span>
                            </div>
                            <div id="maintSysSpecs" style="font-family: 'JetBrains Mono', monospace; font-size: 0.76rem; color: #334155; line-height: 1.7;">
                                Memuat spesifikasi sistem...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- ==========================================
         MODAL 1: VIDEO PLAYER WITH SPEED CONTROL
         ========================================== -->
    <div class="modal-overlay" id="adminVideoModal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div style="font-weight: 700; font-size: 1.1rem; color: #0f172a; display: flex; align-items: center; gap: 8px;" id="videoResiTitle">
                        <span class="material-symbols-outlined" style="color: #2563eb;">movie</span>
                        <span>HASIL PACKING VIDEO</span>
                    </div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;" id="videoMetaInfo">
                        Detail
                    </div>
                </div>
                <button class="btn btn-outline btn-sm" onclick="closeAdminVideoModal()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="modal-body">
                <div id="adminVideoLoading" style="display:none; flex-direction:column; align-items:center; justify-content:center; gap:12px; min-height:220px; color:#64748b;">
                    <div class="video-spinner"></div>
                    <div style="font-size:0.85rem; font-weight:600;">Memuat video, harap tunggu...</div>
                </div>

                <video id="adminVideoPlayer" class="modal-video-player" controls preload="auto"></video>

                <!-- Audit Tools: Playback Speed & Download -->
                <div class="playback-controls">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 0.8rem; color: #64748b; font-weight: 600; display: flex; align-items: center; gap: 4px;">
                            <span class="material-symbols-outlined" style="font-size: 16px; color: #2563eb;">speed</span>
                            <span>Kecepatan Audit:</span>
                        </span>
                        <div class="speed-buttons">
                            <button class="speed-btn" data-speed="0.75">0.75x</button>
                            <button class="speed-btn active" data-speed="1">1x</button>
                            <button class="speed-btn" data-speed="1.25">1.25x</button>
                            <button class="speed-btn" data-speed="1.5">1.5x</button>
                            <button class="speed-btn" data-speed="2">2x</button>
                        </div>
                    </div>

                    <div style="display: flex; gap: 8px;">
                        <a id="modalDownloadLink" href="#" download class="btn btn-primary btn-sm">
                            <span class="material-symbols-outlined">download</span>
                            <span>Download MP4</span>
                        </a>
                        <button class="btn btn-outline btn-sm" onclick="closeAdminVideoModal()">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================
         MODAL 2: TAMBAH PENGGUNA BARU (MODAL CEPAT)
         ========================================== -->
    <div class="modal-overlay" id="addUserModal">
        <div class="modal-content" style="max-width: 520px;">
            <div class="modal-header">
                <div style="font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined" style="color: #2563eb;">person_add</span>
                    <span>Tambah Pengguna Baru</span>
                </div>
                <button class="btn btn-outline btn-sm" onclick="closeAddUserModal()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="modal-body">
                <form id="addUserForm">
                    <div style="margin-bottom: 12px;">
                        <label class="form-label" style="font-weight: 600;">Nama Lengkap</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Budi Santoso" required autocomplete="off">
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label class="form-label" style="font-weight: 600;">Username Login</label>
                        <input type="text" name="username" class="form-control" placeholder="Contoh: budi" required autocomplete="off">
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label class="form-label" style="font-weight: 600;">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Masukkan password akun" required autocomplete="new-password">
                    </div>
                    <div style="margin-bottom: 16px;">
                        <label class="form-label" style="font-weight: 600;">Role Akses</label>
                        <select name="role" class="form-control" style="cursor: pointer;">
                            <option value="operator">Operator (Hanya Layar Kerja Packing)</option>
                            <option value="admin">Administrator (Audit &amp; Ekspor)</option>
                            <?php if ($isSuperAdmin): ?>
                            <option value="superadmin">Superadmin (Akses Penuh + Maintenance)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                        <button type="button" class="btn btn-outline" onclick="closeAddUserModal()">Batal</button>
                        <button type="submit" class="btn btn-primary" id="btnSubmitAddUser">
                            <span class="material-symbols-outlined">save</span>
                            <span>Simpan Akun</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==========================================
         MODAL 3: PENGATURAN GOOGLE DRIVE & SHEETS
         ========================================== -->
    <div class="modal-overlay" id="googleSyncModal">
        <div class="modal-content" style="max-width: 680px;">
            <div class="modal-header">
                <div style="font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined" style="color: #10b981;">cloud_sync</span>
                    <span>Pengaturan Google Drive & Google Sheet</span>
                </div>
                <button class="btn btn-outline btn-sm" onclick="closeGoogleSyncModal()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="modal-body">
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px 14px; margin-bottom: 1.2rem; font-size: 0.82rem; color: #166534; display: flex; align-items: flex-start; gap: 10px;">
                    <span class="material-symbols-outlined" style="font-size: 22px; color: #16a34a; flex-shrink: 0; margin-top: 1px;">verified</span>
                    <div>
                        <div style="font-weight: 700; margin-bottom: 2px;">Sinkronisasi Google Drive &amp; Sheets</div>
                        <div>Setiap kali paket disinkronkan, file video MP4 akan diupload ke Google Drive dan link videonya otomatis dicatat ke baris Google Sheet.</div>
                    </div>
                </div>

                <form id="googleSyncForm">
                    <div style="margin-bottom: 12px;">
                        <label class="form-label" style="font-weight: 600;">
                            <span>URL Web App Google Apps Script (Webhook)</span>
                            <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="url" id="cfgGasUrl" name="gas_webapp_url" class="form-control" value="<?= htmlspecialchars($googleSyncCfg['gas_webapp_url'] ?? '') ?>" placeholder="https://script.google.com/macros/s/AKfycb.../exec" required autocomplete="off">
                        <div style="font-size: 0.73rem; color: #64748b; margin-top: 4px;">
                            Pastikan pengaturan <b>Who has access</b> di Google Apps Script diset ke <b>Anyone (Siapa saja)</b>.
                        </div>
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label class="form-label" style="font-weight: 600;">
                            <span>ID Folder Google Drive (Opsional)</span>
                        </label>
                        <input type="text" id="cfgFolderId" name="folder_id" class="form-control" value="<?= htmlspecialchars($googleSyncCfg['folder_id'] ?? '') ?>" placeholder="Contoh: 1ArUc5cSTO-decvyF0auMmTFuF3v_fon7 atau link folder" autocomplete="off">
                        <div style="font-size: 0.73rem; color: #64748b; margin-top: 4px;">
                            Bisa paste link folder lengkap (<code>https://drive.google.com/drive/folders/...</code>) atau langsung ID foldernya saja.
                        </div>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; margin-bottom: 14px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none;">
                            <input type="checkbox" id="cfgAutoSync" name="auto_sync" <?= !empty($googleSyncCfg['auto_sync']) ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer; accent-color: #2563eb;">
                            <div>
                                <div style="font-weight: 700; font-size: 0.88rem; color: #0f172a;">Auto-Sync Otomatis Setiap Scan Selesai</div>
                                <div style="font-size: 0.75rem; color: #64748b;">Kirim otomatis data &amp; video ke Google Drive/Sheet di background setelah operator scan paket.</div>
                            </div>
                        </label>
                    </div>

                    <div id="testConnAlert" style="display: none; padding: 10px 12px; border-radius: 8px; font-size: 0.8rem; margin-bottom: 12px;"></div>

                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" class="btn btn-outline" onclick="testGoogleConnection()" id="btnTestConn">
                            <span class="material-symbols-outlined" style="font-size: 17px;">network_check</span>
                            <span>Uji Koneksi</span>
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnSaveGoogleSync">
                            <span class="material-symbols-outlined" style="font-size: 17px;">save</span>
                            <span>Simpan Pengaturan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==========================================
         MODAL 4: BATCH SYNC PROGRESS (FILTER AWARE)
         ========================================== -->
    <div class="modal-overlay" id="batchSyncModal">
        <div class="modal-content" style="max-width: 580px;">
            <div class="modal-header">
                <div style="font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined" style="color: #0284c7;">cloud_upload</span>
                    <span>Sinkronisasi ke Google Drive &amp; Sheets</span>
                </div>
                <button class="btn btn-outline btn-sm" onclick="closeBatchSyncModal()" id="btnCloseBatchSync">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="modal-body">
                <div style="margin-bottom: 1rem;">
                    <!-- Filter Info Banner -->
                    <div id="batchSyncFilterNotice" style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:8px 12px; font-size:0.75rem; color:#1d4ed8; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                        <span class="material-symbols-outlined" style="font-size:16px;">filter_alt</span>
                        <span id="batchSyncFilterNoticeText">Menyinkronkan data sesuai filter aktif...</span>
                    </div>

                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">
                        <span id="batchSyncStatusText">Menyiapkan antrean paket...</span>
                        <span id="batchSyncProgressText" style="color: #0284c7; font-family: 'JetBrains Mono', monospace;">0%</span>
                    </div>
                    <!-- Progress bar -->
                    <div style="width: 100%; height: 12px; background: #e2e8f0; border-radius: 999px; overflow: hidden;">
                        <div id="batchSyncProgressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #0284c7, #10b981); transition: width 0.3s ease;"></div>
                    </div>
                </div>

                <div style="font-size: 0.78rem; font-weight: 700; color: #64748b; margin-bottom: 6px; text-transform: uppercase;">
                    Log Proses:
                </div>
                <div id="batchSyncLogs" style="background: #0f172a; color: #f8fafc; border-radius: 8px; padding: 12px; font-family: 'JetBrains Mono', monospace; font-size: 0.74rem; max-height: 200px; overflow-y: auto; line-height: 1.5;">
                    <div style="color: #94a3b8;">Menunggu proses...</div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 1.25rem;">
                    <button type="button" class="btn btn-danger btn-sm" id="btnCancelBatchSync" onclick="stopBatchSync()" style="display: none;">
                        <span class="material-symbols-outlined" style="font-size: 16px;">stop</span>
                        <span>Hentikan</span>
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnDoneBatchSync" onclick="closeBatchSyncModal()" style="display: none;">
                        <span class="material-symbols-outlined" style="font-size: 16px;">check_circle</span>
                        <span>Selesai &amp; Tutup</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- External Libs: Flatpickr JS & Indonesian Locale -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
    <script src="assets/js/admin.js?v=<?= filemtime(__DIR__ . '/assets/js/admin.js') ?>"></script>
</body>
</html>
