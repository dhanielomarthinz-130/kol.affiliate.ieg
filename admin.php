<?php
// admin.php
require_once __DIR__ . '/config/auth.php';
requireRole('admin');

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Semua Data Hasil Packaging</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="app-layout">
        
        <!-- Premium Vertical Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="admin.php" class="sidebar-brand">
                    <div class="sidebar-brand-icon" style="background: linear-gradient(135deg, #7c3aed 0%, #3b82f6 100%);">
                        <span class="material-symbols-outlined" style="font-size: 26px;">shield_person</span>
                    </div>
                    <div>
                        <div class="sidebar-brand-title">KOL AUDIT</div>
                        <div class="sidebar-brand-sub" style="color: #c084fc;">Admin Portal</div>
                    </div>
                </a>
            </div>

            <div class="sidebar-content">
                <div>
                    <div class="sidebar-section-title">Audit & Analitik</div>
                    <nav class="sidebar-nav">
                        <a href="admin.php" class="nav-link active">
                            <span class="material-symbols-outlined">analytics</span>
                            <span>Data Packaging</span>
                            <span class="nav-badge" style="background: rgba(124, 58, 237, 0.25); color: #c084fc;">ALL</span>
                        </a>

                        <a href="packing.php" class="nav-link">
                            <span class="material-symbols-outlined">videocam</span>
                            <span>Layar Packing</span>
                        </a>
                    </nav>
                </div>

                <div>
                    <div class="sidebar-section-title">Manajemen & Ekspor</div>
                    <nav class="sidebar-nav">
                        <button type="button" class="nav-link" onclick="openUserModal()">
                            <span class="material-symbols-outlined">manage_accounts</span>
                            <span>Kelola Operator</span>
                        </button>

                        <button type="button" class="nav-link" onclick="exportToCSV()">
                            <span class="material-symbols-outlined">file_download</span>
                            <span>Ekspor CSV / Excel</span>
                        </button>
                    </nav>
                </div>

                <!-- Server Info Card in Sidebar -->
                <div style="margin-top: auto;">
                    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 12px; padding: 12px; font-size: 0.78rem; color: var(--text-muted);">
                        <div style="display: flex; align-items: center; gap: 6px; color: #34d399; font-weight: 700; margin-bottom: 4px;">
                            <span class="material-symbols-outlined" style="font-size: 16px;">storage</span>
                            <span>Status Penyimpanan</span>
                        </div>
                        Auto-transcoding MP4 H.264 aktif dengan kompresi hemat ruang.
                    </div>
                </div>
            </div>

            <!-- Sidebar User Profile & Logout -->
            <div class="sidebar-footer">
                <div class="user-profile-widget">
                    <div class="user-avatar-circle" style="border-color: rgba(124, 58, 237, 0.4); color: #c084fc;">
                        <span class="material-symbols-outlined">shield_person</span>
                    </div>
                    <div class="user-profile-details">
                        <div class="user-profile-name"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="user-profile-role role-admin">
                            <span class="material-symbols-outlined" style="font-size: 13px;">verified</span>
                            <span>ADMINISTRATOR</span>
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
                        <span class="material-symbols-outlined" style="font-size: 32px; color: #a78bfa;">table_view</span>
                        <span>Semua Data Hasil Packaging</span>
                    </h1>
                    <p class="page-subtitle">Pusat pemantauan rekaman video, verifikasi nomor resi, dan audit durasi kerja operator packing.</p>
                </div>

                <div style="display: flex; align-items: center; gap: 10px;">
                    <a href="packing.php" class="btn btn-outline btn-sm">
                        <span class="material-symbols-outlined">videocam</span>
                        <span>Buka Layar Packing</span>
                    </a>

                    <button onclick="exportToCSV()" class="btn btn-success btn-sm">
                        <span class="material-symbols-outlined">download</span>
                        <span>Download Rekap CSV</span>
                    </button>
                </div>
            </div>

            <!-- Summary KPI Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                        <span class="material-symbols-outlined">inventory_2</span>
                    </div>
                    <div>
                        <div class="stat-val" id="statTodayCount">0</div>
                        <div class="stat-label">Paket Dipacking Hari Ini</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
                        <span class="material-symbols-outlined">timer</span>
                    </div>
                    <div>
                        <div class="stat-val" id="statTodayAvg">0s</div>
                        <div class="stat-label">Rata-rata Durasi Packing</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(139, 92, 246, 0.15); color: #c084fc;">
                        <span class="material-symbols-outlined">monitoring</span>
                    </div>
                    <div>
                        <div class="stat-val" id="statTotalCount">0</div>
                        <div class="stat-label">Total Semua Paket Tersimpan</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
                        <span class="material-symbols-outlined">hard_drive</span>
                    </div>
                    <div>
                        <div class="stat-val" id="statStorage">0 MB</div>
                        <div class="stat-label">Total Penyimpanan MP4</div>
                    </div>
                </div>
            </div>

            <!-- Filter & Search Card -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 120px 110px; gap: 12px; align-items: end;">
                        <div>
                            <label class="form-label">
                                <span class="material-symbols-outlined" style="font-size: 15px; vertical-align: -2px;">search</span>
                                <span>Cari No Resi / Invoice</span>
                            </label>
                            <input type="text" id="searchResi" class="form-control" placeholder="Ketik No Resi..." autocomplete="off">
                        </div>

                        <div>
                            <label class="form-label">
                                <span class="material-symbols-outlined" style="font-size: 15px; vertical-align: -2px;">badge</span>
                                <span>Operator</span>
                            </label>
                            <select id="filterOperator" class="form-control" style="cursor: pointer;">
                                <option value="0">Semua Operator</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">
                                <span class="material-symbols-outlined" style="font-size: 15px; vertical-align: -2px;">event</span>
                                <span>Tanggal Mulai</span>
                            </label>
                            <input type="date" id="filterDateFrom" class="form-control">
                        </div>

                        <div>
                            <label class="form-label">
                                <span class="material-symbols-outlined" style="font-size: 15px; vertical-align: -2px;">event_available</span>
                                <span>Tanggal Akhir</span>
                            </label>
                            <input type="date" id="filterDateTo" class="form-control">
                        </div>

                        <div>
                            <button id="btnResetFilter" class="btn btn-outline" style="width: 100%; height: 44px;">
                                <span class="material-symbols-outlined">restart_alt</span>
                                <span>Reset</span>
                            </button>
                        </div>

                        <div>
                            <button onclick="exportToCSV()" class="btn btn-success" style="width: 100%; height: 44px;" title="Export data ke CSV">
                                <span class="material-symbols-outlined">file_download</span>
                                <span>CSV</span>
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
                    <div style="font-size: 0.85rem; color: #94a3b8;" id="recordCountInfo">
                        Memuat data...
                    </div>
                </div>

                <div class="card-body" style="padding: 0;">
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th>No Resi / Invoice</th>
                                    <th>Nama Operator</th>
                                    <th>Durasi Packing</th>
                                    <th>Tanggal & Jam WIB</th>
                                    <th>Ukuran File</th>
                                    <th style="width: 230px;">Aksi & Audit</th>
                                </tr>
                            </thead>
                            <tbody id="packingsTableBody">
                                <tr>
                                    <td colspan="7" style="text-align:center; padding: 2rem; color: #94a3b8;">
                                        Memuat data...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination controls -->
                    <div style="padding: 1.1rem 1.4rem; display: flex; justify-content: flex-end; align-items: center; border-top: 1px solid var(--border-color);" id="tablePagination">
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Modal Video Player with Speed Control -->
    <div class="modal-overlay" id="adminVideoModal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div style="font-weight: 700; font-size: 1.15rem; color: #ffffff; display: flex; align-items: center; gap: 8px;" id="videoResiTitle">
                        <span class="material-symbols-outlined" style="color: #60a5fa;">movie</span>
                        <span>HASIL PACKING VIDEO</span>
                    </div>
                    <div style="font-size: 0.82rem; color: #94a3b8; margin-top: 3px;" id="videoMetaInfo">
                        Detail
                    </div>
                </div>
                <button class="btn btn-outline btn-sm" onclick="closeAdminVideoModal()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="modal-body">
                <video id="adminVideoPlayer" class="modal-video-player" controls></video>

                <!-- Audit Tools: Playback Speed & Download -->
                <div class="playback-controls">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 0.82rem; color: #94a3b8; font-weight: 600; display: flex; align-items: center; gap: 4px;">
                            <span class="material-symbols-outlined" style="font-size: 18px; color: #60a5fa;">speed</span>
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

    <!-- Modal Kelola Operator -->
    <div class="modal-overlay" id="userModal">
        <div class="modal-content" style="max-width: 650px;">
            <div class="modal-header">
                <div style="font-weight: 700; font-size: 1.15rem; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined" style="color: #60a5fa;">manage_accounts</span>
                    <span>Manajemen Akun Operator & Admin</span>
                </div>
                <button class="btn btn-outline btn-sm" onclick="closeUserModal()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="modal-body">
                <!-- Add User Form -->
                <form id="addUserForm" style="background: rgba(255,255,255,0.02); padding: 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; border: 1px solid var(--border-color);">
                    <div style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.75rem; color: #60a5fa; display: flex; align-items: center; gap: 6px;">
                        <span class="material-symbols-outlined" style="font-size: 20px;">person_add</span>
                        <span>Tambah Operator Baru</span>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                        <div>
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="name" class="form-control" placeholder="Contoh: Budi Santoso" required>
                        </div>
                        <div>
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="Contoh: budi" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                        <div>
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Password akun" required>
                        </div>
                        <div>
                            <label class="form-label">Role Akses</label>
                            <select name="role" class="form-control">
                                <option value="operator">Operator (Layar Kerja Packing)</option>
                                <option value="admin">Administrator (Semua Akses)</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <span class="material-symbols-outlined">save</span>
                        <span>Simpan Akun Baru</span>
                    </button>
                </form>

                <!-- Users List -->
                <div style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined" style="font-size: 18px; color: #94a3b8;">group</span>
                    <span>Daftar Pengguna Saat Ini</span>
                </div>
                <div class="table-wrapper">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                        <thead>
                            <tr style="background: rgba(0,0,0,0.3); color: #94a3b8;">
                                <th style="padding:10px 14px;">Nama</th>
                                <th style="padding:10px 14px;">Username</th>
                                <th style="padding:10px 14px;">Role</th>
                                <th style="padding:10px 14px; text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="usersListTable">
                            <tr><td colspan="4" style="text-align:center; padding:1rem; color:#64748b;">Memuat pengguna...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
</body>
</html>
