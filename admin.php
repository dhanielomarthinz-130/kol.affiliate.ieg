<?php
// admin.php
require_once __DIR__ . '/config/auth.php';
requireRole('admin');
require_once __DIR__ . '/config/google_sync.php';

$user = getCurrentUser();
$isSuperAdmin = isSuperAdmin();
$todayDate = date('Y-m-d');
$googleSyncCfg = getGoogleSyncConfig();

// Preload stats agar langsung tampil data riil tanpa jeda atau menampilkan 0
try {
    $dbInit = getDB();
    $todayStr = date('Y-m-d');
    $stmtT = $dbInit->prepare("SELECT COUNT(*) as count, COALESCE(AVG(duration_seconds), 0) as avg_duration FROM packings WHERE DATE(created_at) = ?");
    $stmtT->execute([$todayStr]);
    $tStats = $stmtT->fetch();
    $initTodayCount = intval($tStats['count'] ?? 0);
    $initTodayAvg = round($tStats['avg_duration'] ?? 0);

    $stmtTot = $dbInit->prepare("SELECT COUNT(*) as total_count, COALESCE(SUM(video_filesize), 0) as total_storage FROM packings WHERE DATE(created_at) = ?");
    $stmtTot->execute([$todayStr]);
    $totStats = $stmtTot->fetch();
    $initTotalCount = intval($totStats['total_count'] ?? 0);
    $initRawBytes = floatval($totStats['total_storage'] ?? 0);
    if ($initRawBytes >= 1073741824) {
        $initStorage = round($initRawBytes / 1073741824, 2) . ' GB';
    } elseif ($initRawBytes >= 1048576) {
        $initStorage = round($initRawBytes / 1048576, 2) . ' MB';
    } elseif ($initRawBytes >= 1024) {
        $initStorage = round($initRawBytes / 1024, 1) . ' KB';
    } else {
        $initStorage = $initRawBytes . ' B';
    }

    $stmtUInfo = $dbInit->prepare("SELECT created_at FROM users WHERE id = ?");
    $stmtUInfo->execute([$user['id'] ?? 0]);
    $uRow = $stmtUInfo->fetch();
    $userCreatedAt = !empty($uRow['created_at']) ? date('d M Y, H:i', strtotime($uRow['created_at'])) : '-';
} catch (Exception $e) {
    $initTodayCount = 0;
    $initTodayAvg = 0;
    $initTotalCount = 0;
    $initStorage = '0 MB';
    $userCreatedAt = '-';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - KOL Packaging Audit</title>
    <link rel="icon" type="image/svg+xml" href="assets/image/favicon.svg">
    <link rel="icon" type="image/png" href="assets/image/favicon.png">
    <link rel="shortcut icon" href="favicon.ico">
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
                            <span>Data Packing</span>
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
                            <span class="material-symbols-outlined" style="color: #60a5fa;">manage_accounts</span>
                            <span>Kelola User</span>
                        </a>

                        <?php if ($isSuperAdmin): ?>
                        <a href="javascript:void(0)" class="nav-link" id="navMaintenance" onclick="switchAdminView('maintenance')">
                            <span class="material-symbols-outlined" style="color: #f59e0b;">build_circle</span>
                            <span>Maintenance</span>
                            <span class="nav-badge badge-maint-sidebar-normal" id="sidebarMaintBadge">SUPER</span>
                        </a>

                        <button type="button" class="nav-link" onclick="openGoogleSyncModal()">
                            <span class="material-symbols-outlined" style="color: #10b981;">tune</span>
                            <span>Google Sync</span>
                        </button>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>

            <!-- Sidebar User Profile & Logout -->
            <div class="sidebar-footer">
                <div class="user-profile-widget" onclick="openProfileModal()" role="button" tabindex="0" title="Klik untuk detail & pengaturan profil">
                    <div class="user-avatar-circle" style="<?= $isSuperAdmin ? 'background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color:#92400e;' : 'background: linear-gradient(135deg, rgba(99,102,241,0.2) 0%, rgba(129,140,248,0.35) 100%); color:#818cf8;' ?>">
                        <span class="material-symbols-outlined" style="font-size: 20px;"><?= $isSuperAdmin ? 'verified_user' : 'shield_person' ?></span>
                    </div>
                    <div class="user-profile-details">
                        <div class="user-profile-name" id="sidebarUserName"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="user-profile-sub">
                            <span class="user-profile-role <?= $isSuperAdmin ? 'badge-role-superadmin' : 'role-admin' ?>">
                                <?= strtoupper($user['role']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="user-profile-settings-btn" title="Pengaturan Akun">
                        <span class="material-symbols-outlined" style="font-size: 17px;">tune</span>
                    </div>
                </div>

                <a href="logout" class="btn-sidebar-logout" title="Keluar dari sistem">
                    <span class="material-symbols-outlined" style="font-size: 16px;">logout</span>
                    <span>Keluar</span>
                </a>
            </div>
        </aside>

        <!-- Main Workspace -->
        <main class="main-viewport">
            
            <!-- Page Top Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h1 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; letter-spacing: -0.02em;" id="mainPageTitle">
                        <span class="material-symbols-outlined" style="font-size: 26px; color: var(--primary);">table_view</span>
                        <span>Semua Data Hasil Packaging</span>
                    </h1>
                </div>

                <div style="display: flex; align-items: center; gap: 10px;">
                    <!-- Maintenance Status Indicator Pill -->
                    <div id="topbarMaintChip" class="topbar-status-chip normal" onclick="switchAdminView('maintenance')" title="Klik untuk membuka status & diagnosa sistem">
                        <span class="dot" id="topbarMaintDot"></span>
                        <span id="topbarMaintLabel">Sistem Normal</span>
                    </div>
                </div>
            </div>

            <!-- Global Maintenance Active Alert Banner (Visible across all tabs when maintenance is on) -->
            <div id="globalMaintenanceAlertBanner" style="display: none; background: #fff5f5; border: 1.5px solid #ef4444; border-radius: 12px; padding: 12px 18px; margin-bottom: 1.25rem; box-shadow: 0 4px 14px -2px rgba(239,68,68,0.15); align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span class="material-symbols-outlined" style="font-size: 28px; color: #dc2626; flex-shrink: 0; animation: maintPulseDot 1.2s infinite;">warning</span>
                    <div>
                        <div style="font-weight: 800; color: #991b1b; font-size: 0.92rem; display: flex; align-items: center; gap: 8px;">
                            <span>MODE PEMELIHARAAN (MAINTENANCE) SEDANG AKTIF!</span>
                            <span style="background: #dc2626; color: #fff; font-size: 0.65rem; padding: 2px 8px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.5px;">Sistem Terkunci</span>
                        </div>
                        <div style="font-size: 0.8rem; color: #b91c1c; margin-top: 2px;">
                            Sistem terkunci untuk Operator &amp; Admin biasa. Hanya Superadmin <strong>Daniel</strong> yang dapat login dan beraktivitas.
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <button type="button" onclick="switchAdminView('maintenance')" class="btn btn-sm btn-outline" style="border-color: #fca5a5; color: #991b1b; background: #fff; font-weight: 600;">
                        Kelola
                    </button>
                    <?php if ($isSuperAdmin): ?>
                    <button type="button" onclick="toggleMaintenanceMode()" class="btn btn-sm" style="background: #16a34a; color: #fff; border: none; font-weight: 700; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(22,163,74,0.35);">
                        <span class="material-symbols-outlined" style="font-size: 16px;">power_settings_new</span>
                        <span>Matikan Maintenance Sekarang</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ==========================================
                 VIEW 1: DATA PACKAGING (MAIN DASHBOARD)
                 ========================================== -->
            <div id="viewPackings">
                <!-- Summary KPI Cards -->
                <div class="stats-grid" style="margin-bottom: 1.25rem;">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <span class="material-symbols-outlined">inventory_2</span>
                        </div>
                        <div>
                            <div class="stat-label">Paket Dipacking Hari Ini</div>
                            <div class="stat-val" id="statTodayCount"><?= $initTodayCount ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon success">
                            <span class="material-symbols-outlined">timer</span>
                        </div>
                        <div>
                            <div class="stat-label">Rata-rata Durasi Packing</div>
                            <div class="stat-val" id="statTodayAvg"><?= $initTodayAvg ?>s</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <span class="material-symbols-outlined">monitoring</span>
                        </div>
                        <div>
                            <div class="stat-label">Total Paket (Filter)</div>
                            <div class="stat-val" id="statTotalCount"><?= $initTotalCount ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <span class="material-symbols-outlined">hard_drive</span>
                        </div>
                        <div>
                            <div class="stat-label">Total Penyimpanan MP4</div>
                            <div class="stat-val" id="statStorage"><?= $initStorage ?></div>
                        </div>
                    </div>
                </div>

                <!-- Modern Filter Card -->
                <div class="card" style="margin-bottom: 1.25rem;">
                    <div class="card-body filter-card-body">
                        <div class="filter-main-grid">
                            <div class="filter-field field-search">
                                <label class="form-label">
                                    <span class="material-symbols-outlined" style="font-size: 15px; color:#2563eb; vertical-align:-2px;">search</span>
                                    <span>Cari No Resi / Invoice</span>
                                </label>
                                <input type="text" id="searchResi" class="form-control" placeholder="Ketik No Resi..." autocomplete="off">
                            </div>

                            <div class="filter-field">
                                <label class="form-label">
                                    <span class="material-symbols-outlined" style="font-size: 15px; color:#2563eb; vertical-align:-2px;">badge</span>
                                    <span>Operator</span>
                                </label>
                                <select id="filterOperator" class="form-control" style="cursor: pointer;">
                                    <option value="0">Semua Operator</option>
                                </select>
                            </div>

                            <div class="filter-field">
                                <label class="form-label">
                                    <span class="material-symbols-outlined" style="font-size: 15px; color:#2563eb; vertical-align:-2px;">calendar_today</span>
                                    <span>Tanggal Dari (From Date)</span>
                                </label>
                                <input type="text" id="filterDateFrom" class="form-control flatpickr-input" placeholder="Pilih Tanggal Mulai" value="<?= $todayDate ?>">
                            </div>

                            <div class="filter-field">
                                <label class="form-label">
                                    <span class="material-symbols-outlined" style="font-size: 15px; color:#2563eb; vertical-align:-2px;">event_available</span>
                                    <span>Sampai Tanggal (To Date)</span>
                                </label>
                                <input type="text" id="filterDateTo" class="form-control flatpickr-input" placeholder="Pilih Tanggal Akhir" value="<?= $todayDate ?>">
                            </div>

                            <div class="filter-actions-group">
                                <button type="button" id="btnResetFilter" class="btn btn-outline filter-action-btn" title="Reset semua filter">
                                    <span class="material-symbols-outlined" style="font-size: 17px;">restart_alt</span>
                                    <span>Reset</span>
                                </button>

                                <button type="button" onclick="startBatchSync()" class="btn filter-action-btn btn-sync-action" id="btnSyncGoogle" title="Sinkronkan data sesuai filter ke Google Drive & Sheets">
                                    <svg width="16" height="16" viewBox="0 0 87.3 78" style="vertical-align: middle; flex-shrink:0;">
                                      <path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8h-27.5c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
                                      <path d="m43.65 25-13.75-23.8c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44a9.06 9.06 0 0 0 -1.2 4.5h27.5z" fill="#00ac47"/>
                                      <path d="m73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5h-27.502l5.852 11.5z" fill="#ea4335"/>
                                      <path d="m43.65 25 13.75-23.8c-1.35-.8-2.9-1.2-4.5-1.2h-18.5c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/>
                                      <path d="m59.8 53h-32.3l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/>
                                      <path d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3l-13.75 23.8 16.15 28h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/>
                                    </svg>
                                    <span>Sync</span>
                                    <span class="badge" id="pendingSyncBadgeFilter" style="background:#f59e0b; color:#fff; display:none; padding:1px 6px; font-size:0.68rem; border-radius:10px; margin-left:2px;">0</span>
                                </button>

                                <button type="button" onclick="exportToExcel()" class="btn filter-action-btn btn-excel-action" title="Export data saat ini ke Excel">
                                    <svg width="16" height="16" viewBox="0 0 24 24" style="vertical-align: middle; flex-shrink:0;">
                                      <path fill="#ffffff" d="M21.17 3.25H7.83A1.58 1.58 0 0 0 6.25 4.83v2.92h5.5a1.58 1.58 0 0 1 1.58 1.58v5.34a1.58 1.58 0 0 1-1.58 1.58h-5.5v2.92a1.58 1.58 0 0 0 1.58 1.58h13.34a1.58 1.58 0 0 0 1.58-1.58V4.83a1.58 1.58 0 0 0-1.58-1.58z"/>
                                      <path fill="#ffffff" opacity="0.9" d="M12.5 7.25H2.5A1.5 1.5 0 0 0 1 8.75v6.5A1.5 1.5 0 0 0 2.5 16.75h10a1.5 1.5 0 0 0 1.5-1.5v-6.5a1.5 1.5 0 0 0-1.5-1.5z"/>
                                      <path fill="#107c41" d="M4.6 9.2l1.6 2.8-1.6 2.8h1.4l.9-1.8.9 1.8h1.4l-1.6-2.8 1.6-2.8H9.4L8.4 11l-.9-1.8H6z"/>
                                    </svg>
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
                                        <th style="width: 200px; text-align: right;">Action</th>
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

                <!-- ── Header Bar ── -->
                <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border: 1px solid rgba(255,255,255,0.07); border-radius: 16px; padding: 1.4rem 1.6rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <div style="font-size: 1.15rem; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px; margin-bottom: 3px;">
                            <span class="material-symbols-outlined" style="color: #f59e0b; font-size: 24px;">build_circle</span>
                            Pusat Maintenance &amp; Diagnostik Sistem
                        </div>
                        <div style="font-size: 0.8rem; color: #64748b;">Area khusus Superadmin — diagnostik server, database, mode pemeliharaan &amp; data manager.</div>
                    </div>
                    <button onclick="loadMaintenanceInfo()" class="btn btn-sm" style="background:rgba(255,255,255,0.08); color:#cbd5e1; border:1px solid rgba(255,255,255,0.12); display:flex; align-items:center; gap:6px;">
                        <span class="material-symbols-outlined" style="font-size:16px;">refresh</span>
                        <span>Segarkan</span>
                    </button>
                </div>

                <!-- ── KPI Cards ── -->
                <div class="stats-grid" style="margin-bottom: 1.25rem;">
                    <div class="stat-card">
                        <div class="stat-icon warning"><span class="material-symbols-outlined">storage</span></div>
                        <div>
                            <div class="stat-val" id="maintFreeDisk">-</div>
                            <div class="stat-label">Disk Bebas</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon primary"><span class="material-symbols-outlined">video_file</span></div>
                        <div>
                            <div class="stat-val" id="maintVideoSize">-</div>
                            <div class="stat-label">Folder Videos</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon success"><span class="material-symbols-outlined">cloud_done</span></div>
                        <div>
                            <div class="stat-val" id="maintSyncRatio">-</div>
                            <div class="stat-label">Synced ke Drive</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple"><span class="material-symbols-outlined">database</span></div>
                        <div>
                            <div class="stat-val" id="maintDbSize">-</div>
                            <div class="stat-label">Ukuran Database</div>
                        </div>
                    </div>
                </div>

                <!-- ── MAINTENANCE MODE CONTROL ── -->
                <div class="card maint-card-normal" id="maintModeCard" style="margin-bottom: 1.25rem; transition: all 0.3s ease;">
                    <div class="card-header" id="maintModeCardHeader" style="background: linear-gradient(90deg, rgba(245,158,11,0.08), transparent); border-bottom: 1px solid rgba(226,232,240,0.8);">
                        <div class="card-title" id="maintModeCardTitle" style="display:flex; align-items:center; gap:8px;">
                            <span class="material-symbols-outlined" id="maintModeCardIcon" style="color:#10b981;">verified</span>
                            <span id="maintModeCardTitleText" style="font-weight:700;">Status Operasional &amp; Mode Pemeliharaan</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1.25rem; flex-wrap:wrap;">
                            <div style="flex:1; min-width:260px;">
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                                    <span style="font-weight:700; font-size:0.88rem; color:#0f172a;">Status Saat Ini:</span>
                                    <span id="maintModeBadge" style="display:inline-flex; align-items:center; gap:6px; padding:4px 14px; border-radius:9999px; font-size:0.8rem; font-weight:800; background:rgba(16,185,129,0.12); color:#059669; border:1px solid rgba(16,185,129,0.3);">
                                        <span id="maintModeDot" style="width:8px; height:8px; border-radius:50%; background:#10b981; display:inline-block;"></span>
                                        <span id="maintModeLabel">Sistem Normal (Aktif)</span>
                                    </span>
                                </div>
                                <p id="maintModeDesc" style="font-size:0.82rem; color:#64748b; line-height:1.55; max-width:520px; margin:0;">
                                    Sistem beroperasi secara normal. Seluruh operator dan admin dapat login dan menggunakan fitur packaging.
                                </p>
                            </div>
                            <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px; flex-shrink:0;">
                                <button id="btnToggleMaintenance" onclick="toggleMaintenanceMode()" class="btn btn-sm" style="min-width:210px; display:flex; align-items:center; justify-content:center; gap:7px; padding:0.65rem 1.15rem; font-weight:700; font-size:0.82rem; border-radius:10px; background:linear-gradient(135deg,#d97706,#f59e0b); color:#fff; border:none; box-shadow:0 4px 14px -4px rgba(245,158,11,0.5);">
                                    <span class="material-symbols-outlined" id="maintToggleIcon" style="font-size:18px;">power_settings_new</span>
                                    <span id="maintToggleLabel">Aktifkan Maintenance</span>
                                </button>
                                <span style="font-size:0.72rem; color:#94a3b8;" id="maintLastChanged">Memuat status...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── DAFTAR TABEL DATABASE SISTEM ── -->
                <div class="card" style="margin-bottom: 1.25rem;">
                    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <div class="card-title">
                            <span class="material-symbols-outlined" style="color:#2563eb;">storage</span>
                            <span>Tabel Database Sistem (SQLite)</span>
                        </div>
                        <button onclick="loadDbTablesList()" class="btn btn-sm btn-outline" style="display:inline-flex; align-items:center; gap:6px; font-size:0.8rem;">
                            <span class="material-symbols-outlined" style="font-size:16px;">refresh</span>
                            <span>Segarkan Tabel</span>
                        </button>
                    </div>
                    <div class="card-body" style="padding:0;">
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse; font-size:0.84rem;" id="dbTablesMasterTable">
                                <thead>
                                    <tr style="background:#f8fafc; border-bottom:1.5px solid #e2e8f0; color:#475569; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.04em;">
                                        <th style="padding:12px 18px; text-align:left;">Nama Tabel</th>
                                        <th style="padding:12px 18px; text-align:left;">Deskripsi / Fungsi</th>
                                        <th style="padding:12px 18px; text-align:center;">Jumlah Data</th>
                                        <th style="padding:12px 18px; text-align:right;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="dbTablesMasterBody">
                                    <tr>
                                        <td colspan="4" style="padding:24px; text-align:center; color:#94a3b8; font-size:0.84rem;">
                                            <span class="material-symbols-outlined" style="vertical-align:middle; font-size:18px;">sync</span> Memuat daftar tabel...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ── ACTIONS GRID ── -->
                <div class="card" style="margin-bottom:1.25rem;">
                    <div class="card-header">
                        <div class="card-title">
                            <span class="material-symbols-outlined" style="color:#f59e0b;">handyman</span>
                            <span>Tindakan Pemeliharaan</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px,1fr)); gap:14px;">

                            <!-- Optimize DB -->
                            <div style="border:1px solid var(--border-color); border-radius:12px; padding:16px; background:#f8fafc; display:flex; flex-direction:column; gap:10px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span class="material-symbols-outlined" style="color:#2563eb; font-size:22px;">speed</span>
                                    <div>
                                        <div style="font-weight:700; font-size:0.88rem; color:#0f172a;">Optimasi Database</div>
                                        <div style="font-size:0.76rem; color:#64748b;">VACUUM &amp; ANALYZE SQLite</div>
                                    </div>
                                </div>
                                <button onclick="runOptimizeDB()" class="btn btn-primary btn-sm" id="btnOptimizeDB" style="width:100%;">
                                    <span class="material-symbols-outlined" style="font-size:15px;">database</span>
                                    <span>Jalankan Optimasi</span>
                                </button>
                            </div>

                            <!-- Clean Temp -->
                            <div style="border:1px solid var(--border-color); border-radius:12px; padding:16px; background:#f8fafc; display:flex; flex-direction:column; gap:10px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span class="material-symbols-outlined" style="color:#dc2626; font-size:22px;">delete_sweep</span>
                                    <div>
                                        <div style="font-weight:700; font-size:0.88rem; color:#0f172a;">Bersihkan File Sampah</div>
                                        <div style="font-size:0.76rem; color:#64748b;">Hapus file 0-byte &amp; .tmp</div>
                                    </div>
                                </div>
                                <button onclick="runCleanTempFiles()" class="btn btn-danger btn-sm" id="btnCleanTemp" style="width:100%;">
                                    <span class="material-symbols-outlined" style="font-size:15px;">cleaning_services</span>
                                    <span>Bersihkan Sekarang</span>
                                </button>
                            </div>

                        </div>

                        <!-- Server Specs -->
                        <div style="margin-top:1.25rem; background:#fff; border:1px solid var(--border-color); border-radius:10px; padding:14px;">
                            <div style="font-weight:700; font-size:0.82rem; color:#475569; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                                <span class="material-symbols-outlined" style="font-size:17px;">info</span>
                                Spesifikasi Lingkungan Server
                            </div>
                            <div id="maintSysSpecs" style="font-family:'JetBrains Mono',monospace; font-size:0.74rem; color:#334155; line-height:1.75;">
                                Memuat...
                            </div>
                        </div>
                    </div>
                </
        </main>
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
                <div id="adminVideoLoading" style="display:none; flex-direction:column; align-items:center; justify-content:center; gap:14px; min-height:220px; color:#64748b;">
                    <div class="premium-balls-spinner">
                        <div class="spinner-ball ball-1"></div>
                        <div class="spinner-ball ball-2"></div>
                        <div class="spinner-ball ball-3"></div>
                        <div class="spinner-ball ball-4"></div>
                        <div class="spinner-ball ball-5"></div>
                        <div class="spinner-ball ball-6"></div>
                    </div>
                    <div style="font-size:0.88rem; font-weight:600; color:#475569;">Memuat rekaman video, harap tunggu...</div>
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
         MODAL: KELOLA & HAPUS DATA TABEL DATABASE
         ========================================== -->
    <div class="modal-overlay" id="dbDataManageModal">
        <div class="modal-content" style="max-width: 960px; width: 95%; max-height: 88vh; display:flex; flex-direction:column; border-radius:18px; overflow:hidden;">
            <div class="modal-header" style="flex-shrink:0; background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:1.1rem 1.4rem;">
                <div>
                    <div style="font-weight: 800; font-size: 1.05rem; color: #0f172a; display: flex; align-items: center; gap: 8px;" id="dbModalTitle">
                        <span class="material-symbols-outlined" style="color: #2563eb;" id="dbModalIcon">table_chart</span>
                        <span id="dbModalTableName">Kelola Data Tabel</span>
                    </div>
                    <div style="font-size: 0.78rem; color: #64748b; margin-top: 3px;" id="dbModalSubtitle">
                        Daftar baris data — Anda dapat menghapus data satu per satu.
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <button class="btn btn-outline btn-sm" onclick="reloadCurrentDbModalTable()" title="Muat Ulang Data">
                        <span class="material-symbols-outlined" style="font-size:16px;">refresh</span>
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="closeDbDataManageModal()" title="Tutup">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            </div>

            <div class="modal-body" style="padding: 0; overflow-y: auto; flex: 1;">
                <!-- Filter / Search in modal -->
                <div style="padding: 10px 18px; background: #fff; border-bottom: 1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                    <div style="font-size: 0.8rem; color: #475569; font-weight: 600;" id="dbModalRowCount">
                        Memuat data...
                    </div>
                    <input type="text" id="dbModalSearchInput" placeholder="Cari di data ini..." oninput="filterDbModalRows(this.value)" style="padding: 6px 12px; font-size: 0.8rem; border: 1.5px solid #cbd5e1; border-radius: 8px; width: 220px; outline:none;">
                </div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;" id="dbModalTableEl">
                        <thead id="dbModalTableHead" style="position: sticky; top: 0; z-index: 2; background: #f8fafc; border-bottom: 2px solid #e2e8f0;"></thead>
                        <tbody id="dbModalTableBody"></tbody>
                    </table>
                </div>

                <div id="dbModalEmpty" style="display:none; padding:3rem 1.5rem; text-align:center; color:#94a3b8;">
                    <span class="material-symbols-outlined" style="font-size:40px; color:#cbd5e1; display:block; margin-bottom:8px;">inbox</span>
                    <span>Tidak ada data di tabel ini.</span>
                </div>
            </div>

            <div class="modal-footer" style="padding: 12px 18px; border-top: 1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc; flex-shrink:0;">
                <div style="font-size:0.75rem; color:#64748b; display:flex; align-items:center; gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:16px; color:#f59e0b;">info</span>
                    <span>Klik tombol <strong style="color:#dc2626;">Hapus</strong> merah di baris terkait untuk menghapus data tersebut.</span>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeDbDataManageModal()">Tutup</button>
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
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <div style="font-weight: 700; font-size: 1.08rem; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined" style="color: #0284c7;">cloud_upload</span>
                    <span>Sinkronisasi Google Drive &amp; Sheets</span>
                </div>
                <button class="btn btn-outline btn-sm" onclick="closeBatchSyncModal()" id="btnCloseBatchSync">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="modal-body">
                <!-- Filter Info Banner -->
                <div id="batchSyncFilterNotice" style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:8px 12px; font-size:0.75rem; color:#1d4ed8; margin-bottom:14px; display:flex; align-items:center; gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:16px;">filter_alt</span>
                    <span id="batchSyncFilterNoticeText">Menyinkronkan data sesuai filter aktif...</span>
                </div>

                <!-- Premium Spinner & Progress Container -->
                <div id="syncSpinnerContainer" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 28px 20px 22px; margin-bottom: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative;">
                    <!-- The 6 Colorful Orbiting Balls -->
                    <div id="syncSpinnerAnimation" class="premium-balls-spinner">
                        <div class="spinner-ball ball-1"></div>
                        <div class="spinner-ball ball-2"></div>
                        <div class="spinner-ball ball-3"></div>
                        <div class="spinner-ball ball-4"></div>
                        <div class="spinner-ball ball-5"></div>
                        <div class="spinner-ball ball-6"></div>
                    </div>

                    <!-- Success / Finish Icon -->
                    <div id="syncSuccessIcon" style="display: none; width: 60px; height: 60px; border-radius: 50%; background: #dcfce7; color: #16a34a; align-items: center; justify-content: center; margin: 4px auto 10px; box-shadow: 0 4px 14px rgba(22,163,74,0.25);">
                        <span class="material-symbols-outlined" style="font-size: 36px;">check_circle</span>
                    </div>

                    <div style="margin-top: 14px; text-align: center; width: 100%;">
                        <div id="batchSyncStatusText" style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-bottom: 6px;">
                            Menyiapkan antrean paket...
                        </div>
                        <div id="batchSyncDetailText" style="font-size: 0.78rem; color: #64748b; margin-bottom: 10px; min-height: 18px;">
                            Memeriksa data...
                        </div>

                        <!-- Modern Rounded Progress Bar -->
                        <div style="width: 100%; max-width: 360px; height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin: 0 auto 10px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.06);">
                            <div id="batchSyncProgressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #2563eb, #0284c7, #10b981); border-radius: 999px; transition: width 0.35s ease;"></div>
                        </div>

                        <div style="display: inline-flex; align-items: center; gap: 6px;">
                            <span class="badge" style="background:#e0f2fe; color:#0369a1; font-family:'JetBrains Mono', monospace; font-size:0.78rem; font-weight:700; padding:3px 10px; border-radius:999px;" id="batchSyncProgressText">0%</span>
                        </div>
                    </div>
                </div>

                <!-- Feedback Alert Notice (Success / Warning / Error) -->
                <div id="batchSyncNotice" style="display: none; border-radius: 8px; padding: 10px 14px; font-size: 0.8rem; font-weight: 500; margin-top: 10px;"></div>

                <!-- Hidden log container for JS backwards compatibility -->
                <div id="batchSyncLogs" style="display: none;"></div>

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

    <!-- ==========================================
         MODAL 5: DETAIL PROFIL & EDIT AKUN
         ========================================== -->
    <div class="modal-overlay" id="profileModal">
        <div class="modal-content" style="max-width: 530px;">
            <div class="modal-header">
                <div style="font-weight: 700; font-size: 1.08rem; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined" style="color: #6366f1;">account_circle</span>
                    <span>Detail &amp; Pengaturan Profil</span>
                </div>
                <button class="btn btn-outline btn-sm" onclick="closeProfileModal()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="modal-body">
                <!-- User Identity Card -->
                <div class="profile-hero-card">
                    <div class="profile-hero-avatar <?= $isSuperAdmin ? 'avatar-superadmin' : 'avatar-admin' ?>">
                        <span class="material-symbols-outlined" style="font-size: 32px;"><?= $isSuperAdmin ? 'verified_user' : 'shield_person' ?></span>
                    </div>
                    <div class="profile-hero-info">
                        <div class="profile-hero-name" id="modalHeroName"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="profile-hero-meta">
                            <span class="profile-badge-role <?= $isSuperAdmin ? 'badge-role-superadmin' : 'role-admin' ?>">
                                <?= strtoupper($user['role']) ?>
                            </span>
                            <span class="profile-username-tag" id="modalHeroUsername">@<?= htmlspecialchars($user['username']) ?></span>
                        </div>
                        <div class="profile-chips-row">
                            <span class="profile-chip"><span class="chip-dot-active"></span> Aktif</span>
                            <span class="profile-chip">ID: #<?= htmlspecialchars($user['id'] ?? '1') ?></span>
                            <span class="profile-chip" id="modalHeroCreated">Terdaftar: <?= htmlspecialchars($userCreatedAt ?? '-') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Alert Feedback Box -->
                <div id="profileAlert" style="display: none; margin-bottom: 14px; border-radius: 8px; padding: 10px 14px; font-size: 0.82rem; font-weight: 500;"></div>

                <form id="profileEditForm" onsubmit="handleUpdateProfile(event)">
                    <div style="font-size: 0.76rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                        <span class="material-symbols-outlined" style="font-size: 16px; color: #6366f1;">badge</span>
                        <span>Informasi Akun</span>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label class="form-label" style="font-weight: 600; font-size: 0.82rem;">Nama Lengkap</label>
                        <div class="input-with-icon">
                            <span class="material-symbols-outlined input-icon">person</span>
                            <input type="text" id="profileInputName" name="name" class="form-control" style="padding-left: 36px;" value="<?= htmlspecialchars($user['name']) ?>" required autocomplete="name">
                        </div>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label class="form-label" style="font-weight: 600; font-size: 0.82rem;">Username Login</label>
                        <div class="input-with-icon">
                            <span class="material-symbols-outlined input-icon">alternate_email</span>
                            <input type="text" id="profileInputUsername" name="username" class="form-control" style="padding-left: 36px;" value="<?= htmlspecialchars($user['username']) ?>" required autocomplete="username">
                        </div>
                    </div>

                    <div style="border-top: 1px solid #e2e8f0; padding-top: 14px; margin-top: 14px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <div style="font-size: 0.76rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px;">
                                <span class="material-symbols-outlined" style="font-size: 16px; color: #6366f1;">lock_reset</span>
                                <span>Ganti Password</span>
                            </div>
                            <span style="font-size: 0.72rem; color: #94a3b8; font-style: italic;">Kosongkan jika tidak diubah</span>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label class="form-label" style="font-weight: 600; font-size: 0.82rem;">Password Saat Ini</label>
                            <div class="input-with-icon">
                                <span class="material-symbols-outlined input-icon">lock</span>
                                <input type="password" id="profileInputCurrentPassword" name="current_password" class="form-control" style="padding-left: 36px; padding-right: 36px;" placeholder="Masukkan password saat ini untuk verifikasi" autocomplete="current-password">
                                <button type="button" class="btn-toggle-pwd" onclick="togglePasswordVisibility('profileInputCurrentPassword', this)" tabindex="-1" title="Lihat/Sembunyikan password">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">visibility</span>
                                </button>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 6px;">
                            <div>
                                <label class="form-label" style="font-weight: 600; font-size: 0.82rem;">Password Baru</label>
                                <div class="input-with-icon">
                                    <span class="material-symbols-outlined input-icon">key</span>
                                    <input type="password" id="profileInputNewPassword" name="new_password" class="form-control" style="padding-left: 36px; padding-right: 36px;" placeholder="Min. 5 karakter" autocomplete="new-password">
                                    <button type="button" class="btn-toggle-pwd" onclick="togglePasswordVisibility('profileInputNewPassword', this)" tabindex="-1" title="Lihat/Sembunyikan password">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">visibility</span>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="form-label" style="font-weight: 600; font-size: 0.82rem;">Konfirmasi Password Baru</label>
                                <div class="input-with-icon">
                                    <span class="material-symbols-outlined input-icon">check</span>
                                    <input type="password" id="profileInputConfirmPassword" name="confirm_password" class="form-control" style="padding-left: 36px; padding-right: 36px;" placeholder="Ulangi password baru" autocomplete="new-password">
                                    <button type="button" class="btn-toggle-pwd" onclick="togglePasswordVisibility('profileInputConfirmPassword', this)" tabindex="-1" title="Lihat/Sembunyikan password">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">visibility</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                        <button type="button" class="btn btn-outline" onclick="closeProfileModal()">Batal</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveProfile">
                            <span class="material-symbols-outlined" style="font-size: 18px;">save</span>
                            <span>Simpan Perubahan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==========================================
         MODAL: EDIT PENGGUNA (NAMA, PASSWORD / PIN)
         ========================================== -->
    <div class="modal-overlay" id="editUserModal">
        <div class="modal-content" style="max-width:440px; padding:0; border-radius:20px; overflow:hidden;">
            <!-- Header -->
            <div style="background:linear-gradient(135deg,#1e1b4b 0%,#312e81 100%); padding:1.25rem 1.4rem; position:relative;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="width:38px; height:38px; border-radius:10px; background:rgba(99,102,241,0.25); border:1px solid rgba(165,180,252,0.3); display:flex; align-items:center; justify-content:center; color:#a5b4fc;">
                        <span class="material-symbols-outlined" style="font-size:22px;">manage_accounts</span>
                    </div>
                    <div>
                        <div style="font-size:1.05rem; font-weight:800; color:#fff; letter-spacing:-0.01em;">Edit Pengguna</div>
                        <div id="editUserSubtitle" style="font-size:0.75rem; color:#a5b4fc; display:flex; align-items:center; gap:6px; margin-top:2px;"></div>
                    </div>
                </div>
                <button type="button" onclick="closeEditUserModal()" style="position:absolute; top:14px; right:14px; background:rgba(255,255,255,0.12); border:none; border-radius:50%; width:30px; height:30px; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#fff;">
                    <span class="material-symbols-outlined" style="font-size:17px;">close</span>
                </button>
            </div>

            <!-- Body -->
            <div style="padding:1.4rem 1.4rem 1.2rem; background:#fff; max-height:calc(90vh - 100px); overflow-y:auto;">
                <div id="editUserAlert" style="display:none; margin-bottom:14px; border-radius:8px; padding:10px 14px; font-size:0.82rem; font-weight:500;"></div>

                <form id="editUserForm" onsubmit="handleSaveEditUser(event)">
                    <input type="hidden" id="editUserId" value="">
                    <input type="hidden" id="editUserRole" value="">

                    <!-- Section: Profil Pengguna -->
                    <div style="margin-bottom:12px;">
                        <label class="form-label" style="font-weight:600; font-size:0.82rem; margin-bottom:5px; display:block;">Nama Lengkap <span style="color:#ef4444;">*</span></label>
                        <div class="input-with-icon">
                            <span class="material-symbols-outlined input-icon">person</span>
                            <input type="text" id="editUserName" name="name" class="form-control" style="padding-left:36px;" required placeholder="Nama lengkap pengguna">
                        </div>
                    </div>

                    <div style="margin-bottom:14px;">
                        <label class="form-label" style="font-weight:600; font-size:0.82rem; margin-bottom:5px; display:block;">Username Login</label>
                        <div class="input-with-icon">
                            <span class="material-symbols-outlined input-icon">alternate_email</span>
                            <input type="text" id="editUserUsername" name="username" class="form-control" style="padding-left:36px;" required placeholder="Username">
                        </div>
                    </div>

                    <!-- Section: Operator PIN (Mobile Numpad Style) -->
                    <div id="editUserPinSection" style="display:none; border-top:1px solid #e2e8f0; padding-top:14px; margin-top:14px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <div style="font-size:0.76rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:6px;">
                                <span class="material-symbols-outlined" style="font-size:16px; color:#6366f1;">pin</span>
                                <span>Ganti PIN Operator (4 Digit)</span>
                            </div>
                            <span id="editUserPinStatusBadge" style="font-size:0.72rem; color:#64748b;"></span>
                        </div>

                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:10px 12px; margin-bottom:10px; font-size:0.75rem; color:#64748b; line-height:1.4;">
                            Kosongkan jika tidak ingin mengubah PIN. Ketuk angka di bawah jika ingin mengatur <strong>4 digit PIN baru</strong>.
                        </div>

                        <!-- Dark Numpad Box -->
                        <div style="background:#1e1b4b; border-radius:18px; padding:1.2rem 1.2rem 1rem;">
                            <!-- Dots indicator -->
                            <div style="display:flex; justify-content:center; align-items:center; gap:12px; margin-bottom:6px;">
                                <div class="pin-dot" id="editPinDot0"></div>
                                <div class="pin-dot" id="editPinDot1"></div>
                                <div class="pin-dot" id="editPinDot2"></div>
                                <div class="pin-dot" id="editPinDot3"></div>
                            </div>
                            <div id="editPinErrorMsg" style="text-align:center; font-size:0.72rem; color:#f87171; min-height:18px; margin-bottom:6px;"></div>

                            <!-- Mobile Numpad Grid -->
                            <div class="pin-numpad" style="gap:8px;">
                                <?php foreach ([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                                <button type="button" class="pin-key edit-pin-key<?= ($k === '') ? ' pin-key-empty' : '' ?>" data-key="<?= $k ?>" style="height:48px; font-size:1.2rem; border-radius:12px;"><?= $k ?></button>
                                <?php endforeach; ?>
                            </div>

                            <!-- Reset / Clear PIN Entry button -->
                            <div style="text-align:center; margin-top:8px;">
                                <button type="button" onclick="_clearEditPinInput()" style="background:transparent; border:none; color:#a5b4fc; font-size:0.74rem; cursor:pointer; text-decoration:underline;">
                                    Batal Ubah PIN (Reset)
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Admin / Superadmin Password Change -->
                    <div id="editUserPasswordSection" style="display:none; border-top:1px solid #e2e8f0; padding-top:14px; margin-top:14px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <div style="font-size:0.76rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:6px;">
                                <span class="material-symbols-outlined" style="font-size:16px; color:#6366f1;">lock_reset</span>
                                <span>Ganti Password</span>
                            </div>
                            <span style="font-size:0.72rem; color:#94a3b8; font-style:italic;">Kosongkan jika tidak diubah</span>
                        </div>

                        <div style="margin-bottom:12px;">
                            <label class="form-label" style="font-weight:600; font-size:0.82rem; margin-bottom:5px; display:block;">Password Baru</label>
                            <div class="input-with-icon">
                                <span class="material-symbols-outlined input-icon">key</span>
                                <input type="password" id="editUserPassword" class="form-control" style="padding-left:36px; padding-right:36px;" placeholder="Min. 5 karakter (kosongkan jika tetap)" autocomplete="new-password">
                                <button type="button" class="btn-toggle-pwd" onclick="togglePasswordVisibility('editUserPassword', this)" tabindex="-1" title="Lihat/Sembunyikan password">
                                    <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="form-label" style="font-weight:600; font-size:0.82rem; margin-bottom:5px; display:block;">Konfirmasi Password Baru</label>
                            <div class="input-with-icon">
                                <span class="material-symbols-outlined input-icon">check</span>
                                <input type="password" id="editUserConfirmPassword" class="form-control" style="padding-left:36px; padding-right:36px;" placeholder="Ulangi password baru" autocomplete="new-password">
                                <button type="button" class="btn-toggle-pwd" onclick="togglePasswordVisibility('editUserConfirmPassword', this)" tabindex="-1" title="Lihat/Sembunyikan password">
                                    <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action Buttons -->
                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:20px; border-top:1px solid #e2e8f0; padding-top:14px;">
                        <button type="button" class="btn btn-outline" onclick="closeEditUserModal()">Batal</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveEditUser">
                            <span class="material-symbols-outlined" style="font-size:18px;">save</span>
                            <span>Simpan Perubahan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==========================================
         MODAL PIN: SET PIN OPERATOR (MOBILE NUMPAD)
         ========================================== -->
    <div class="modal-overlay" id="setPinModal">
        <div class="modal-content" style="max-width:380px; padding:0; border-radius:24px; overflow:hidden;">

            <!-- Header -->
            <div style="background:linear-gradient(135deg,#3730a3 0%,#6366f1 100%); padding:1.4rem 1.5rem 1.2rem; position:relative;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
                    <span class="material-symbols-outlined" style="color:#a5b4fc; font-size:26px;">pin</span>
                    <div>
                        <div style="font-size:1rem; font-weight:800; color:#fff; letter-spacing:-0.01em;">Atur PIN Operator</div>
                        <div id="pinModalSubtitle" style="font-size:0.75rem; color:#a5b4fc; margin-top:1px;">Masukkan PIN 4 digit</div>
                    </div>
                </div>
                <button onclick="closeSetPinModal()" style="position:absolute; top:14px; right:14px; background:rgba(255,255,255,0.12); border:none; border-radius:50%; width:30px; height:30px; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#fff;">
                    <span class="material-symbols-outlined" style="font-size:17px;">close</span>
                </button>
            </div>

            <!-- PIN Display -->
            <div style="background:#1e1b4b; padding:1.5rem 1.5rem 0.8rem;">
                <div style="display:flex; justify-content:center; gap:12px; margin-bottom:6px;">
                    <div class="pin-dot" id="pinDot0"></div>
                    <div class="pin-dot" id="pinDot1"></div>
                    <div class="pin-dot" id="pinDot2"></div>
                    <div class="pin-dot" id="pinDot3"></div>
                </div>
                <div id="pinErrorMsg" style="text-align:center; font-size:0.75rem; color:#f87171; min-height:18px; margin-top:4px;"></div>
            </div>

            <!-- Numpad -->
            <div style="background:#1e1b4b; padding:0.4rem 1.5rem 1.5rem;">
                <div class="pin-numpad">
                    <?php foreach ([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                    <button class="pin-key<?= ($k === '') ? ' pin-key-empty' : '' ?>" data-key="<?= $k ?>"><?= $k ?></button>
                    <?php endforeach; ?>
                </div>

                <!-- Save button -->
                <button id="btnSavePinSubmit" onclick="submitSetPin()" class="btn btn-primary" style="width:100%; margin-top:14px; background:linear-gradient(135deg,#4f46e5,#6366f1); border:none; padding:0.75rem; font-size:0.9rem; font-weight:700; border-radius:12px; letter-spacing:0.02em;">
                    <span class="material-symbols-outlined" style="font-size:17px;">lock_reset</span>
                    <span>Simpan PIN</span>
                </button>
                <button onclick="closeSetPinModal()" style="width:100%; margin-top:8px; background:transparent; border:none; color:#6366f1; font-size:0.82rem; cursor:pointer; padding:0.4rem;">Batal</button>
            </div>
        </div>
    </div>

    <!-- PIN Modal Styles -->
    <style>
    .pin-dot {
        width: 16px; height: 16px;
        border-radius: 50%;
        background: rgba(255,255,255,0.15);
        border: 2px solid rgba(165,180,252,0.4);
        transition: background 0.15s, transform 0.15s;
    }
    .pin-dot.filled {
        background: #a5b4fc;
        border-color: #a5b4fc;
        transform: scale(1.15);
        box-shadow: 0 0 10px rgba(165,180,252,0.6);
    }
    .pin-dot.error {
        background: #f87171;
        border-color: #ef4444;
        animation: pin-shake 0.4s ease;
    }
    @keyframes pin-shake {
        0%,100%{transform:translateX(0)}
        20%{transform:translateX(-5px)}
        40%{transform:translateX(5px)}
        60%{transform:translateX(-4px)}
        80%{transform:translateX(4px)}
    }
    .pin-numpad {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }
    .pin-key {
        height: 60px;
        border-radius: 14px;
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.08);
        color: #e2e8f0;
        font-size: 1.35rem;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.12s, transform 0.1s;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: inherit;
        user-select: none;
    }
    .pin-key:hover {
        background: rgba(99,102,241,0.3);
        border-color: rgba(99,102,241,0.5);
        color: #fff;
    }
    .pin-key:active {
        transform: scale(0.93);
        background: rgba(99,102,241,0.5);
    }
    .pin-key-empty {
        background: transparent !important;
        border: none !important;
        cursor: default !important;
        pointer-events: none;
    }
    .pin-key[data-key="⌫"] {
        color: #f87171;
        font-size: 1.1rem;
    }
    </style>

    <!-- External Libs: Flatpickr JS & Indonesian Locale -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
    <script src="assets/js/admin.js?v=<?= filemtime(__DIR__ . '/assets/js/admin.js') ?>"></script>
</body>
</html>
