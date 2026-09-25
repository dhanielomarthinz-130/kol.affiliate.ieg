// assets/js/admin.js

let currentPage = 1;
const pageLimit = 15;
let _autoRefreshTimer = null;
let _currentView = 'packings'; // 'packings' | 'users' | 'maintenance'

let currentSortBy = 'created_at';
let currentSortDir = 'DESC';

let _fpFrom = null;
let _fpTo = null;

let _isBatchSyncRunning = false;
let _batchSyncCancelled = false;

document.addEventListener('DOMContentLoaded', () => {
    initFlatpickr();
    loadStats();
    loadOperatorsFilter();
    loadPackings(1);
    setupEventListeners();
    updateGoogleSyncBadge();
    startAutoRefresh();

    // Check hash for direct tab view
    if (window.location.hash === '#users') {
        switchAdminView('users');
    } else if (window.location.hash === '#maintenance') {
        switchAdminView('maintenance');
    }
});

// ==========================================
// VIEW SWITCHING (TABS)
// ==========================================
function switchAdminView(view) {
    _currentView = view;
    window.location.hash = (view === 'packings') ? '' : view;

    const navPackings = document.getElementById('navPackings');
    const navUsers = document.getElementById('navUsers');
    const navMaint = document.getElementById('navMaintenance');

    const viewPackings = document.getElementById('viewPackings');
    const viewUsers = document.getElementById('viewUsers');
    const viewMaint = document.getElementById('viewMaintenance');

    const titleEl = document.getElementById('mainPageTitle');
    const subEl = document.getElementById('mainPageSub');

    // Reset navigation
    if (navPackings) navPackings.classList.remove('active');
    if (navUsers) navUsers.classList.remove('active');
    if (navMaint) navMaint.classList.remove('active');

    // Hide all views
    if (viewPackings) viewPackings.style.display = 'none';
    if (viewUsers) viewUsers.style.display = 'none';
    if (viewMaint) viewMaint.style.display = 'none';

    if (view === 'users') {
        if (navUsers) navUsers.classList.add('active');
        if (viewUsers) viewUsers.style.display = 'block';
        if (titleEl) titleEl.innerHTML = `<span class="material-symbols-outlined" style="font-size:26px; color:#2563eb;">manage_accounts</span><span>Kelola Operator &amp; Pengguna</span>`;
        if (subEl) subEl.textContent = 'Daftar semua operator packing dan akun administrator sistem beserta status aktif/nonaktif.';
        loadUsersTable();
    } else if (view === 'maintenance') {
        if (navMaint) navMaint.classList.add('active');
        if (viewMaint) viewMaint.style.display = 'block';
        if (titleEl) titleEl.innerHTML = `<span class="material-symbols-outlined" style="font-size:26px; color:#f59e0b;">build_circle</span><span>Maintenance &amp; Diagnostik Sistem</span>`;
        if (subEl) subEl.textContent = 'Area khusus Superadmin untuk memantau server, mengoptimasi database, dan membersihkan file sampah.';
        loadMaintenanceInfo();
    } else {
        // default: packings
        if (navPackings) navPackings.classList.add('active');
        if (viewPackings) viewPackings.style.display = 'block';
        if (titleEl) titleEl.innerHTML = `<span class="material-symbols-outlined" style="font-size:26px; color:#2563eb;">table_view</span><span>Semua Data Hasil Packaging</span>`;
        if (subEl) subEl.textContent = 'Pusat pemantauan rekaman video, verifikasi nomor resi, dan audit durasi kerja operator packing.';
        loadPackings(currentPage);
    }
}

// ==========================================
// FLATPICKR & DATE PRESETS
// ==========================================
function initFlatpickr() {
    const today = new Date().toISOString().split('T')[0];

    const fpConfig = {
        locale: (typeof flatpickr !== 'undefined' && flatpickr.l10ns?.id) ? flatpickr.l10ns.id : 'default',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        allowInput: true,
        onChange: () => {
            clearPresetActive();
            loadPackings(1);
            updateGoogleSyncBadge();
        }
    };

    const fromEl = document.getElementById('filterDateFrom');
    const toEl = document.getElementById('filterDateTo');

    if (fromEl && typeof flatpickr !== 'undefined') {
        _fpFrom = flatpickr(fromEl, Object.assign({}, fpConfig, { defaultDate: fromEl.value || today }));
    }
    if (toEl && typeof flatpickr !== 'undefined') {
        _fpTo = flatpickr(toEl, Object.assign({}, fpConfig, { defaultDate: toEl.value || today }));
    }
}

function clearPresetActive() {
    document.querySelectorAll('.preset-date-btn').forEach(b => b.classList.remove('active'));
}

function applyDatePreset(preset, btn) {
    clearPresetActive();
    if (btn) btn.classList.add('active');

    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    const formatYmd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

    let fromStr = '';
    let toStr = '';

    if (preset === 'today') {
        fromStr = formatYmd(now);
        toStr = formatYmd(now);
    } else if (preset === 'yesterday') {
        const y = new Date(now);
        y.setDate(y.getDate() - 1);
        fromStr = formatYmd(y);
        toStr = formatYmd(y);
    } else if (preset === 'last7') {
        const d7 = new Date(now);
        d7.setDate(d7.getDate() - 6);
        fromStr = formatYmd(d7);
        toStr = formatYmd(now);
    } else if (preset === 'thismonth') {
        const mStart = new Date(now.getFullYear(), now.getMonth(), 1);
        fromStr = formatYmd(mStart);
        toStr = formatYmd(now);
    } else if (preset === 'all') {
        fromStr = '';
        toStr = '';
    }

    if (_fpFrom) _fpFrom.setDate(fromStr, false);
    if (_fpTo) _fpTo.setDate(toStr, false);

    const fromInput = document.getElementById('filterDateFrom');
    const toInput = document.getElementById('filterDateTo');
    if (fromInput) fromInput.value = fromStr;
    if (toInput) toInput.value = toStr;

    loadPackings(1);
    updateGoogleSyncBadge();
}

function getActiveFilterParams() {
    const search = document.getElementById('searchResi')?.value.trim() || '';
    const operatorId = document.getElementById('filterOperator')?.value || '0';
    const dateFrom = document.getElementById('filterDateFrom')?.value || '';
    const dateTo = document.getElementById('filterDateTo')?.value || '';

    return { search, operator_id: operatorId, date_from: dateFrom, date_to: dateTo };
}

// ==========================================
// EVENT LISTENERS
// ==========================================
function setupEventListeners() {
    // Search input with debounce
    let searchTimeout;
    const searchInput = document.getElementById('searchResi');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadPackings(1);
                updateGoogleSyncBadge();
            }, 300);
        });
    }

    // Filter changes
    const filterOperator = document.getElementById('filterOperator');
    if (filterOperator) {
        filterOperator.addEventListener('change', () => {
            loadPackings(1);
            updateGoogleSyncBadge();
        });
    }

    // Reset filters
    const resetBtn = document.getElementById('btnResetFilter');
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (filterOperator) filterOperator.value = '0';
            applyDatePreset('today', document.getElementById('btnPresetToday'));
        });
    }

    // Playback Speed buttons in video modal
    document.querySelectorAll('.speed-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.speed-btn').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            const speed = parseFloat(e.target.getAttribute('data-speed'));
            const player = document.getElementById('adminVideoPlayer');
            if (player) player.playbackRate = speed;
        });
    });

    // Add user form submit
    const userForm = document.getElementById('addUserForm');
    if (userForm) {
        userForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('btnSubmitAddUser');
            if (submitBtn) submitBtn.disabled = true;

            const formData = new FormData(userForm);
            try {
                const res = await fetch('api/users.php?action=create', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showAdminToast(data.message, 'success');
                    userForm.reset();
                    closeAddUserModal();
                    loadUsersTable();
                    loadOperatorsFilter();
                } else {
                    showAdminToast(data.message, 'error');
                }
            } catch (err) {
                showAdminToast('Gagal menambahkan pengguna.', 'error');
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    }

    // Google sync form submit
    const syncForm = document.getElementById('googleSyncForm');
    if (syncForm) {
        syncForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnSaveGoogleSync');
            if (btn) btn.disabled = true;
            try {
                const folderInput = document.getElementById('cfgFolderId');
                if (folderInput && folderInput.value) {
                    const match = folderInput.value.match(/\/folders\/([a-zA-Z0-9_-]+)/);
                    if (match && match[1]) {
                        folderInput.value = match[1];
                    }
                }
                const formData = new FormData(syncForm);
                formData.set('auto_sync', document.getElementById('cfgAutoSync')?.checked ? '1' : '0');
                const res = await fetch('api/sync_google.php?action=save_config', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showAdminToast(data.message, 'success');
                    closeGoogleSyncModal();
                    updateGoogleSyncBadge();
                } else {
                    showAdminToast(data.message, 'error');
                }
            } catch (err) {
                showAdminToast('Gagal menyimpan konfigurasi.', 'error');
            } finally {
                if (btn) btn.disabled = false;
            }
        });
    }
}

// ==========================================
// STATS & PACKINGS DATA TABLE
// ==========================================
async function loadStats(silent = false) {
    try {
        const res = await fetch('api/stats.php?_t=' + Date.now(), { cache: 'no-store' });
        const data = await res.json();
        if (data.success) {
            const animateVal = (id, newVal) => {
                const el = document.getElementById(id);
                if (!el) return;
                if (el.textContent !== String(newVal)) {
                    el.classList.add('stat-bump');
                    setTimeout(() => el.classList.remove('stat-bump'), 500);
                }
                el.textContent = newVal;
            };

            animateVal('statTodayCount', data.stats.today_packings || 0);
            animateVal('statTodayAvg', (data.stats.today_avg_duration || 0) + 's');
            animateVal('statTotalCount', data.stats.total_packings || 0);
            animateVal('statStorage', data.stats.total_storage || '0 MB');

            const refreshTimeEl = document.getElementById('lastRefreshTime');
            if (refreshTimeEl) {
                const now = new Date();
                refreshTimeEl.textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
            }
        }
    } catch (e) {
        console.warn('Failed loading stats', e);
    }
}

function startAutoRefresh() {
    _autoRefreshTimer = setInterval(() => {
        loadStats();
        updateGoogleSyncBadge();
    }, 15000);
}

async function loadOperatorsFilter() {
    try {
        const res = await fetch('api/users.php?action=list');
        const data = await res.json();
        if (data.success) {
            const select = document.getElementById('filterOperator');
            if (select) {
                const current = select.value;
                select.innerHTML = '<option value="0">Semua Operator</option>';
                data.data.forEach(user => {
                    const opt = document.createElement('option');
                    opt.value = user.id;
                    opt.textContent = `${user.name} (${user.role})`;
                    select.appendChild(opt);
                });
                select.value = current || '0';
            }
        }
    } catch (e) {
        console.warn('Failed loading operators', e);
    }
}

function handleSortTable(col) {
    if (currentSortBy === col) {
        currentSortDir = (currentSortDir === 'ASC') ? 'DESC' : 'ASC';
    } else {
        currentSortBy = col;
        currentSortDir = (col === 'resi_no' || col === 'operator_name') ? 'ASC' : 'DESC';
    }
    updateSortIcons();
    loadPackings(1);
}

function updateSortIcons() {
    const allCols = ['resi_no', 'operator_name', 'duration_seconds', 'created_at', 'gdrive_url', 'video_filesize'];
    allCols.forEach(col => {
        const iconEl = document.getElementById('sortIcon_' + col);
        if (!iconEl) return;
        if (currentSortBy === col) {
            iconEl.textContent = (currentSortDir === 'ASC') ? 'arrow_upward' : 'arrow_downward';
            iconEl.style.opacity = '1';
        } else {
            iconEl.textContent = 'unfold_more';
            iconEl.style.opacity = '0.5';
        }
    });
}

async function loadPackings(page = 1) {
    if (_currentView !== 'packings') return;
    currentPage = page;
    const tableBody = document.getElementById('packingsTableBody');
    if (!tableBody) return;

    tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:2rem; color:#94a3b8;">Memuat data packaging...</td></tr>`;

    const filters = getActiveFilterParams();
    const params = new URLSearchParams(Object.assign({
        page: page,
        limit: pageLimit,
        sort_by: currentSortBy,
        sort_dir: currentSortDir
    }, filters));

    try {
        const res = await fetch('api/get_packings.php?' + params.toString());
        const result = await res.json();

        if (result.success) {
            renderTable(result.data, result.total, page, result.total_pages);
        } else {
            tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:2rem; color:#ef4444;">Gagal: ${result.message}</td></tr>`;
        }
    } catch (err) {
        tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:2rem; color:#ef4444;">Kesalahan jaringan saat memuat data.</td></tr>`;
    }
}

function renderTable(rows, total, page, totalPages) {
    const tableBody = document.getElementById('packingsTableBody');
    const paginationEl = document.getElementById('tablePagination');
    const countInfo = document.getElementById('recordCountInfo');

    if (countInfo) {
        countInfo.textContent = `Menampilkan ${rows.length} dari total ${total} hasil packaging`;
    }

    if (!rows || rows.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:2.5rem; color:#64748b;">Tidak ada data hasil packingan yang cocok dengan filter.</td></tr>`;
        if (paginationEl) paginationEl.innerHTML = '';
        return;
    }

    const startIdx = (page - 1) * pageLimit + 1;

    tableBody.innerHTML = rows.map((row, idx) => `
        <tr>
            <td style="color:#64748b; font-size:0.8rem; font-weight:500;">${startIdx + idx}</td>
            <td>
                <span class="resi-badge">${escapeHtml(row.resi_no)}</span>
            </td>
            <td>
                <div style="font-weight:600; color:#0f172a;">${escapeHtml(row.operator_name)}</div>
            </td>
            <td style="font-family:'JetBrains Mono', monospace; font-size:0.85rem; color:#334155;">
                <span style="display:inline-flex; align-items:center; gap:4px;">
                    <span class="material-symbols-outlined" style="font-size:15px; color:#2563eb;">timer</span>
                    <span>${row.formatted_duration}</span>
                </span>
            </td>
            <td style="font-size:0.82rem; color:#475569;">
                ${row.formatted_date}
            </td>
            <td>
                ${row.is_synced && row.gdrive_url ? `
                    <a href="${escapeHtml(row.gdrive_url)}" target="_blank" class="btn btn-outline btn-sm" style="display:inline-flex; align-items:center; gap:5px; color:#16a34a; border-color:#bbf7d0; background:#f0fdf4; padding:3px 8px; text-decoration:none;" title="Buka rekaman di Google Drive">
                        <svg width="15" height="15" viewBox="0 0 87.3 78" style="vertical-align: middle;">
                          <path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8h-27.5c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
                          <path d="m43.65 25-13.75-23.8c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44a9.06 9.06 0 0 0 -1.2 4.5h27.5z" fill="#00ac47"/>
                          <path d="m73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5h-27.502l5.852 11.5z" fill="#ea4335"/>
                          <path d="m43.65 25 13.75-23.8c-1.35-.8-2.9-1.2-4.5-1.2h-18.5c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/>
                          <path d="m59.8 53h-32.3l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/>
                          <path d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3l-13.75 23.8 16.15 28h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/>
                        </svg>
                        <span>Drive</span>
                    </a>
                ` : `
                    <button class="btn btn-outline btn-sm" onclick="syncSinglePacking(${row.id}, this)" style="display:inline-flex; align-items:center; gap:5px; color:#64748b; padding:3px 8px;" title="Upload ke Google Drive & Sheets sekarang">
                        <svg width="15" height="15" viewBox="0 0 87.3 78" style="vertical-align: middle;">
                          <path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8h-27.5c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
                          <path d="m43.65 25-13.75-23.8c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44a9.06 9.06 0 0 0 -1.2 4.5h27.5z" fill="#00ac47"/>
                          <path d="m73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5h-27.502l5.852 11.5z" fill="#ea4335"/>
                          <path d="m43.65 25 13.75-23.8c-1.35-.8-2.9-1.2-4.5-1.2h-18.5c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/>
                          <path d="m59.8 53h-32.3l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/>
                          <path d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3l-13.75 23.8 16.15 28h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/>
                        </svg>
                        <span>Sync</span>
                    </button>
                `}
            </td>
            <td style="font-size:0.82rem; color:#64748b; font-family:'JetBrains Mono', monospace;">
                ${row.formatted_size}
            </td>
            <td>
                <div style="display:flex; gap:6px;">
                    <button class="btn btn-primary btn-sm" onclick="openAdminVideoModal('${row.video_url}', '${escapeHtml(row.resi_no)}', '${escapeHtml(row.operator_name)}', '${row.formatted_duration}', '${row.formatted_date}', ${row.id})">
                        <span class="material-symbols-outlined" style="font-size:16px;">play_circle</span>
                        <span>Putar</span>
                    </button>
                    <a href="download.php?id=${row.id}" class="btn btn-outline btn-sm" title="Download Video (MP4)" download>
                        <span class="material-symbols-outlined" style="font-size:16px;">download</span>
                        <span>MP4</span>
                    </a>
                    <button class="btn btn-danger btn-sm" onclick="deletePackingRecord(${row.id}, '${escapeHtml(row.resi_no)}')" title="Hapus Data">
                        <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    // Pagination
    if (paginationEl) {
        let pagHtml = '';
        if (totalPages > 1) {
            pagHtml += `<button class="btn btn-outline btn-sm" ${page <= 1 ? 'disabled' : ''} onclick="loadPackings(${page - 1})"><span class="material-symbols-outlined" style="font-size:16px;">chevron_left</span> Sebelumnya</button>`;
            pagHtml += `<span style="font-size:0.82rem; color:#64748b; font-weight:600; align-self:center; margin: 0 10px;">Hal ${page} dari ${totalPages}</span>`;
            pagHtml += `<button class="btn btn-outline btn-sm" ${page >= totalPages ? 'disabled' : ''} onclick="loadPackings(${page + 1})">Berikutnya <span class="material-symbols-outlined" style="font-size:16px;">chevron_right</span></button>`;
        }
        paginationEl.innerHTML = pagHtml;
    }
}

// ==========================================
// EXPORT TO EXCEL
// ==========================================
function exportToExcel() {
    const filters = getActiveFilterParams();
    const params = new URLSearchParams(filters);
    window.location.href = 'export.php?' + params.toString();
}

function exportToCSV() {
    exportToExcel();
}

// ==========================================
// DEDICATED USER MANAGEMENT DATA TABLE
// ==========================================
async function loadUsersTable() {
    const tbody = document.getElementById('usersTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:2rem; color:#94a3b8;">Memuat data operator &amp; pengguna...</td></tr>`;

    try {
        const res = await fetch('api/users.php?action=list');
        const data = await res.json();

        if (data.success && data.data) {
            if (data.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:2rem; color:#64748b;">Belum ada pengguna.</td></tr>`;
                return;
            }

            tbody.innerHTML = data.data.map((u, idx) => {
                const isActive = (parseInt(u.is_active) === 1);
                const roleClass = (u.role === 'superadmin') ? 'badge-role-superadmin' : (u.role === 'admin' ? 'badge-role-admin' : 'badge-role-operator');
                const roleLabel = (u.role === 'superadmin') ? 'SUPERADMIN' : (u.role === 'admin' ? 'ADMIN' : 'OPERATOR');
                const createdStr = u.created_at ? new Date(u.created_at).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' }) : '-';

                return `
                    <tr>
                        <td style="color:#64748b; font-size:0.8rem; font-weight:500;">${idx + 1}</td>
                        <td>
                            <div style="font-weight:700; color:#0f172a; font-size:0.9rem;">${escapeHtml(u.name)}</div>
                        </td>
                        <td>
                            <span style="font-family:'JetBrains Mono', monospace; font-size:0.85rem; color:#2563eb; font-weight:600;">@${escapeHtml(u.username)}</span>
                        </td>
                        <td>
                            <span class="${roleClass}">${roleLabel}</span>
                        </td>
                        <td>
                            ${isActive ? `
                                <span class="badge-active">
                                    <span class="material-symbols-outlined" style="font-size:14px;">check_circle</span>
                                    <span>Aktif</span>
                                </span>
                            ` : `
                                <span class="badge-inactive">
                                    <span class="material-symbols-outlined" style="font-size:14px;">block</span>
                                    <span>Nonaktif</span>
                                </span>
                            `}
                        </td>
                        <td style="font-size:0.8rem; color:#64748b;">
                            ${createdStr}
                        </td>
                        <td style="text-align:right;">
                            <div style="display:flex; justify-content:flex-end; gap:6px;">
                                <button class="btn btn-sm ${isActive ? 'btn-outline' : 'btn-success'}" onclick="toggleUserStatus(${u.id}, '${escapeHtml(u.name)}', ${isActive ? 1 : 0})" title="${isActive ? 'Klik untuk Menonaktifkan akun' : 'Klik untuk Mengaktifkan akun kembali'}" style="font-size:0.75rem; padding:4px 9px;">
                                    <span class="material-symbols-outlined" style="font-size:15px;">${isActive ? 'power_settings_new' : 'check'}</span>
                                    <span>${isActive ? 'Inactive' : 'Aktifkan'}</span>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteUserRecord(${u.id}, '${escapeHtml(u.username)}')" title="Hapus Akun Pengguna" style="padding:4px 8px;">
                                    <span class="material-symbols-outlined" style="font-size:15px;">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:2rem; color:#ef4444;">Gagal memuat pengguna: ${data.message}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:2rem; color:#ef4444;">Kesalahan jaringan saat memuat data pengguna.</td></tr>`;
    }
}

async function toggleUserStatus(userId, userName, currentStatus) {
    const actionName = (currentStatus === 1) ? 'menonaktifkan (inactive)' : 'mengaktifkan kembali';
    if (!confirm(`Apakah Anda yakin ingin ${actionName} akun "${userName}"?`)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('id', userId);

        const res = await fetch('api/users.php?action=toggle_status', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            showAdminToast(data.message, 'success');
            loadUsersTable();
            loadOperatorsFilter();
        } else {
            showAdminToast(data.message, 'error');
        }
    } catch (e) {
        showAdminToast('Gagal mengubah status: ' + e.message, 'error');
    }
}

async function deleteUserRecord(userId, username) {
    if (!confirm(`Hapus permanen akun "${username}"?`)) return;

    try {
        const formData = new FormData();
        formData.append('id', userId);

        const res = await fetch('api/users.php?action=delete', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showAdminToast(data.message, 'success');
            loadUsersTable();
            loadOperatorsFilter();
        } else {
            showAdminToast(data.message, 'error');
        }
    } catch (e) {
        showAdminToast('Gagal menghapus pengguna.', 'error');
    }
}

function openAddUserModal() {
    const modal = document.getElementById('addUserModal');
    if (modal) modal.classList.add('active');
}

function closeAddUserModal() {
    const modal = document.getElementById('addUserModal');
    if (modal) modal.classList.remove('active');
}

// ==========================================
// MAINTENANCE SYSTEM (SUPERADMIN)
// ==========================================
async function loadMaintenanceInfo() {
    const freeEl = document.getElementById('maintFreeDisk');
    const vidEl = document.getElementById('maintVideoSize');
    const ratioEl = document.getElementById('maintSyncRatio');
    const dbEl = document.getElementById('maintDbSize');
    const specsEl = document.getElementById('maintSysSpecs');

    if (specsEl) specsEl.innerHTML = 'Mengambil status diagnostik dari server...';

    try {
        const res = await fetch('api/maintenance.php?action=get_info&_t=' + Date.now());
        const data = await res.json();

        if (data.success) {
            if (freeEl) freeEl.textContent = data.storage.disk_free_formatted;
            if (vidEl) vidEl.textContent = `${data.storage.video_size_formatted} (${data.storage.video_count} file)`;
            if (ratioEl) ratioEl.textContent = `${data.sync_stats.synced_packings} / ${data.sync_stats.total_packings} Synced`;
            if (dbEl) dbEl.textContent = data.storage.db_size_formatted;

            if (specsEl) {
                specsEl.innerHTML = `
                    <div><b>PHP Version:</b> ${data.system.php_version} (${data.system.os})</div>
                    <div><b>Web Server:</b> ${escapeHtml(data.system.server_software)}</div>
                    <div><b>PHP Memory Limit:</b> ${data.system.memory_limit} | <b>Max Upload:</b> ${data.system.upload_max_filesize} | <b>Max Post:</b> ${data.system.post_max_size}</div>
                    <div><b>Harddisk Server:</b> Terpakai ${data.storage.disk_used_percent}% (Bebas: ${data.storage.disk_free_formatted} dari total ${data.storage.disk_total_formatted})</div>
                    <div><b>File Sampah (0-Byte):</b> ${data.storage.zero_byte_count} file corrupt/aborted</div>
                    <div><b>Converter FFmpeg:</b> ${data.ffmpeg.installed ? '<span style="color:#10b981; font-weight:bold;">Tersedia &amp; Aktif (' + escapeHtml(data.ffmpeg.path) + ')</span>' : '<span style="color:#ef4444;">Tidak Ditemukan</span>'}</div>
                `;
            }
        } else {
            showAdminToast(data.message, 'error');
        }
    } catch (e) {
        if (specsEl) specsEl.textContent = 'Gagal memuat diagnostik: ' + e.message;
    }
}

async function runOptimizeDB() {
    const btn = document.getElementById('btnOptimizeDB');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="material-symbols-outlined spin" style="font-size:16px;">sync</span> <span>Mengoptimalkan...</span>`;
    }

    try {
        const res = await fetch('api/maintenance.php?action=optimize_db', { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            showAdminToast(data.message, 'success');
            loadMaintenanceInfo();
        } else {
            showAdminToast(data.message, 'error');
        }
    } catch (e) {
        showAdminToast('Kesalahan jaringan saat optimasi DB: ' + e.message, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `<span class="material-symbols-outlined">speed</span> <span>Jalankan Optimasi Database</span>`;
        }
    }
}

async function runCleanTempFiles() {
    if (!confirm('Hapus semua file video 0-byte (rekaman corrupt) dan file temporary di uploads/videos?')) return;

    const btn = document.getElementById('btnCleanTemp');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="material-symbols-outlined spin" style="font-size:16px;">sync</span> <span>Membersihkan...</span>`;
    }

    try {
        const res = await fetch('api/maintenance.php?action=clean_temp', { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            showAdminToast(data.message, 'success');
            loadMaintenanceInfo();
        } else {
            showAdminToast(data.message, 'error');
        }
    } catch (e) {
        showAdminToast('Kesalahan pembersihan: ' + e.message, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `<span class="material-symbols-outlined">cleaning_services</span> <span>Bersihkan File Sampah Sekarang</span>`;
        }
    }
}

// ==========================================
// GOOGLE DRIVE & GOOGLE SHEET SYNC MODULE
// ==========================================
async function updateGoogleSyncBadge() {
    try {
        const filters = getActiveFilterParams();
        const params = new URLSearchParams(Object.assign({ action: 'get_config' }, filters));

        const res = await fetch('api/sync_google.php?' + params.toString());
        const data = await res.json();
        if (data.success) {
            const badge = document.getElementById('pendingSyncBadge');
            if (badge) {
                // Gunakan filter_pending_count agar badge mencerminkan filter yang aktif!
                const count = (typeof data.filter_pending_count !== 'undefined') ? data.filter_pending_count : data.pending_count;
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'inline-block';
                    badge.title = `${count} paket sesuai filter aktif belum di-sync ke Google`;
                } else {
                    badge.style.display = 'none';
                }
            }
        }
    } catch (e) {
        console.warn('Failed to update sync badge', e);
    }
}

async function openGoogleSyncModal() {
    const modal = document.getElementById('googleSyncModal');
    if (modal) modal.classList.add('active');
    await loadGoogleSyncConfig();
}

function closeGoogleSyncModal() {
    const modal = document.getElementById('googleSyncModal');
    if (modal) modal.classList.remove('active');
    const alertBox = document.getElementById('testConnAlert');
    if (alertBox) alertBox.style.display = 'none';
}

async function loadGoogleSyncConfig() {
    try {
        const res = await fetch('api/sync_google.php?action=get_config');
        const data = await res.json();
        if (data.success && data.config) {
            const urlInput = document.getElementById('cfgGasUrl');
            const folderInput = document.getElementById('cfgFolderId');
            const autoSyncCheck = document.getElementById('cfgAutoSync');
            
            if (urlInput) urlInput.value = data.config.gas_webapp_url || '';
            if (folderInput) folderInput.value = data.config.folder_id || '';
            if (autoSyncCheck) autoSyncCheck.checked = Boolean(data.config.auto_sync);
        }
    } catch (e) {
        console.warn('Failed loading sync config', e);
    }
}

async function testGoogleConnection() {
    const btn = document.getElementById('btnTestConn');
    const alertBox = document.getElementById('testConnAlert');
    const url = document.getElementById('cfgGasUrl')?.value.trim();

    if (!url) {
        if (alertBox) {
            alertBox.style.display = 'block';
            alertBox.style.background = '#fef2f2';
            alertBox.style.color = '#b91c1c';
            alertBox.style.border = '1px solid #fecaca';
            alertBox.textContent = 'Silakan isi URL Web App Google Apps Script terlebih dahulu.';
        }
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="material-symbols-outlined spin" style="font-size: 17px;">sync</span> <span>Menguji...</span>`;
    }

    if (alertBox) {
        alertBox.style.display = 'block';
        alertBox.style.background = '#f0f9ff';
        alertBox.style.color = '#0369a1';
        alertBox.style.border = '1px solid #bae6fd';
        alertBox.textContent = 'Menghubungkan ke Webhook Google Apps Script...';
    }

    try {
        const formData = new FormData();
        formData.append('gas_webapp_url', url);

        const res = await fetch('api/sync_google.php?action=test_connection', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (alertBox) {
            if (data.success) {
                alertBox.style.background = '#f0fdf4';
                alertBox.style.color = '#15803d';
                alertBox.style.border = '1px solid #bbf7d0';
                alertBox.innerHTML = `<b>Sukses:</b> ${escapeHtml(data.message)}`;
            } else {
                alertBox.style.background = '#fef2f2';
                alertBox.style.color = '#b91c1c';
                alertBox.style.border = '1px solid #fecaca';
                alertBox.innerHTML = `<b>Perhatian:</b> ${escapeHtml(data.message)}`;
            }
        }
    } catch (err) {
        if (alertBox) {
            alertBox.style.background = '#fef2f2';
            alertBox.style.color = '#b91c1c';
            alertBox.style.border = '1px solid #fecaca';
            alertBox.textContent = 'Gagal menghubungi server lokal: ' + err.message;
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `<span class="material-symbols-outlined" style="font-size: 17px;">network_check</span> <span>Uji Koneksi</span>`;
        }
    }
}

async function syncSinglePacking(id, btn) {
    if (!id) return;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="material-symbols-outlined spin" style="font-size:15px; color:#0284c7;">sync</span> <span>Syncing...</span>`;

    try {
        const formData = new FormData();
        formData.append('id', id);

        const res = await fetch('api/sync_google.php?action=sync_single', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success && data.drive_url) {
            showAdminToast('Berhasil disinkronkan ke Google Drive & Sheet!', 'success');
            btn.outerHTML = `
                <a href="${escapeHtml(data.drive_url)}" target="_blank" class="btn btn-outline btn-sm" style="display:inline-flex; align-items:center; gap:5px; color:#16a34a; border-color:#bbf7d0; background:#f0fdf4; padding:3px 8px; text-decoration:none;" title="Buka rekaman di Google Drive">
                    <svg width="15" height="15" viewBox="0 0 87.3 78" style="vertical-align: middle;">
                      <path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8h-27.5c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
                      <path d="m43.65 25-13.75-23.8c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44a9.06 9.06 0 0 0 -1.2 4.5h27.5z" fill="#00ac47"/>
                      <path d="m73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5h-27.502l5.852 11.5z" fill="#ea4335"/>
                      <path d="m43.65 25 13.75-23.8c-1.35-.8-2.9-1.2-4.5-1.2h-18.5c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/>
                      <path d="m59.8 53h-32.3l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/>
                      <path d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3l-13.75 23.8 16.15 28h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/>
                    </svg>
                    <span>Drive</span>
                </a>
            `;
            updateGoogleSyncBadge();
        } else {
            showAdminToast(data.message || 'Gagal sinkron.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    } catch (e) {
        showAdminToast('Kesalahan jaringan: ' + e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

// BATCH SYNC DENGAN MENGIKUTI FILTER YANG AKTIF!
async function startBatchSync() {
    const modal = document.getElementById('batchSyncModal');
    if (modal) modal.classList.add('active');

    const statusText = document.getElementById('batchSyncStatusText');
    const progressText = document.getElementById('batchSyncProgressText');
    const progressBar = document.getElementById('batchSyncProgressBar');
    const logBox = document.getElementById('batchSyncLogs');
    const filterNoticeText = document.getElementById('batchSyncFilterNoticeText');
    const btnCancel = document.getElementById('btnCancelBatchSync');
    const btnDone = document.getElementById('btnDoneBatchSync');
    const btnClose = document.getElementById('btnCloseBatchSync');

    if (btnDone) btnDone.style.display = 'none';
    if (btnCancel) btnCancel.style.display = 'inline-flex';
    if (btnClose) btnClose.style.display = 'inline-flex';

    if (statusText) statusText.textContent = 'Memeriksa antrean paket sesuai filter aktif...';
    if (progressText) progressText.textContent = '0%';
    if (progressBar) progressBar.style.width = '0%';
    if (logBox) logBox.innerHTML = '<div style="color:#94a3b8;">Menyiapkan proses sinkronisasi...</div>';

    _isBatchSyncRunning = true;
    _batchSyncCancelled = false;

    // Ambil parameter filter aktif
    const activeFilters = getActiveFilterParams();
    let filterSummary = [];
    if (activeFilters.date_from && activeFilters.date_to) {
        filterSummary.push(`Tanggal: ${activeFilters.date_from} s/d ${activeFilters.date_to}`);
    } else if (activeFilters.date_from) {
        filterSummary.push(`Dari: ${activeFilters.date_from}`);
    } else if (activeFilters.date_to) {
        filterSummary.push(`Sampai: ${activeFilters.date_to}`);
    }
    if (activeFilters.operator_id > 0) {
        const opSelect = document.getElementById('filterOperator');
        const opText = opSelect?.options[opSelect.selectedIndex]?.text || '';
        filterSummary.push(`Operator: ${opText}`);
    }
    if (activeFilters.search) {
        filterSummary.push(`Cari: "${activeFilters.search}"`);
    }

    const filterDesc = filterSummary.length > 0 ? filterSummary.join(' | ') : 'Semua Periode';
    if (filterNoticeText) {
        filterNoticeText.textContent = `Target Sinkronisasi [Filter Aktif]: ${filterDesc}`;
    }

    try {
        const queryParams = new URLSearchParams(Object.assign({ action: 'get_config' }, activeFilters));
        const cfgRes = await fetch('api/sync_google.php?' + queryParams.toString());
        const cfgData = await cfgRes.json();

        if (!cfgData.success || !cfgData.config?.gas_webapp_url) {
            if (statusText) statusText.textContent = 'URL Google Apps Script belum diatur!';
            if (logBox) logBox.innerHTML += '<div style="color:#ef4444; margin-top:4px;">[PERHATIAN] Harap masukkan URL Web App Google Apps Script terlebih dahulu di menu Setting Google.</div>';
            if (btnCancel) btnCancel.style.display = 'none';
            if (btnDone) btnDone.style.display = 'inline-flex';
            _isBatchSyncRunning = false;
            return;
        }

        const totalToSync = cfgData.filter_pending_count || 0;
        if (totalToSync === 0) {
            if (statusText) statusText.textContent = 'Semua data sesuai filter sudah tersinkronkan!';
            if (progressText) progressText.textContent = '100%';
            if (progressBar) progressBar.style.width = '100%';
            if (logBox) logBox.innerHTML += `<div style="color:#10b981; margin-top:4px;">[SELESAI] Tidak ada paket tertunda untuk filter: <b>${escapeHtml(filterDesc)}</b>. Semua rekaman sudah ada di Google Drive &amp; Sheets!</div>`;
            if (btnCancel) btnCancel.style.display = 'none';
            if (btnDone) btnDone.style.display = 'inline-flex';
            _isBatchSyncRunning = false;
            return;
        }

        if (statusText) statusText.textContent = `Menemukan ${totalToSync} paket sesuai filter. Mengunggah ke Drive...`;
        if (logBox) logBox.innerHTML += `<div style="margin-top:4px;">Ditemukan total <b>${totalToSync}</b> paket untuk disinkronkan (${escapeHtml(filterDesc)}).</div>`;

        let processedCount = 0;

        while (_isBatchSyncRunning && !_batchSyncCancelled) {
            const formData = new FormData();
            formData.append('batch_size', '1');
            formData.append('search', activeFilters.search);
            formData.append('operator_id', activeFilters.operator_id);
            formData.append('date_from', activeFilters.date_from);
            formData.append('date_to', activeFilters.date_to);

            const syncRes = await fetch('api/sync_google.php?action=sync_pending', {
                method: 'POST',
                body: formData
            });
            const syncData = await syncRes.json();

            if (!syncData.success) {
                if (logBox) logBox.innerHTML += `<div style="color:#ef4444; margin-top:4px;">[GAGAL] ${escapeHtml(syncData.message || 'Error')}</div>`;
                break;
            }

            if (!syncData.synced_items || syncData.synced_items.length === 0) {
                break;
            }

            syncData.synced_items.forEach(item => {
                processedCount++;
                const isSuccess = item.success;
                const color = isSuccess ? '#34d399' : '#f87171';
                const icon = isSuccess ? '✓' : '✗';
                if (logBox) {
                    logBox.innerHTML += `<div style="color:${color}; margin-top:3px;">[${processedCount}/${totalToSync}] ${icon} Resi: <b>${escapeHtml(item.resi_no)}</b> - ${escapeHtml(item.message)}</div>`;
                    logBox.scrollTop = logBox.scrollHeight;
                }
            });

            const percent = Math.min(100, Math.round((processedCount / totalToSync) * 100));
            if (progressText) progressText.textContent = percent + '%';
            if (progressBar) progressBar.style.width = percent + '%';
            if (statusText) statusText.textContent = `Mengunggah... (${processedCount}/${totalToSync} selesai)`;

            if (syncData.remaining_count === 0) {
                break;
            }
        }

        if (_batchSyncCancelled) {
            if (statusText) statusText.textContent = 'Sinkronisasi dihentikan oleh pengguna.';
            if (logBox) logBox.innerHTML += '<div style="color:#fbbf24; margin-top:4px;">[INFO] Proses sinkronisasi dihentikan.</div>';
        } else {
            if (statusText) statusText.textContent = 'Proses sinkronisasi filter selesai!';
            if (progressText) progressText.textContent = '100%';
            if (progressBar) progressBar.style.width = '100%';
            if (logBox) logBox.innerHTML += `<div style="color:#10b981; font-weight:bold; margin-top:4px;">[SUKSES] Semua antrean sesuai filter (${escapeHtml(filterDesc)}) telah selesai disinkronkan ke Google Sheet &amp; Drive!</div>`;
        }

    } catch (err) {
        if (statusText) statusText.textContent = 'Terjadi kesalahan sistem saat sinkronisasi.';
        if (logBox) logBox.innerHTML += `<div style="color:#ef4444; margin-top:4px;">[ERROR KONEKSI] ${escapeHtml(err.message)}</div>`;
    } finally {
        _isBatchSyncRunning = false;
        if (btnCancel) btnCancel.style.display = 'none';
        if (btnDone) btnDone.style.display = 'inline-flex';
        updateGoogleSyncBadge();
        loadPackings(currentPage);
    }
}

function stopBatchSync() {
    _batchSyncCancelled = true;
    _isBatchSyncRunning = false;
    const btnCancel = document.getElementById('btnCancelBatchSync');
    if (btnCancel) {
        btnCancel.disabled = true;
        btnCancel.textContent = 'Menghentikan...';
    }
}

function closeBatchSyncModal() {
    const modal = document.getElementById('batchSyncModal');
    if (modal) modal.classList.remove('active');
    updateGoogleSyncBadge();
    loadPackings(currentPage);
}

// ==========================================
// VIDEO MODAL & DELETE RECORDS
// ==========================================
function openAdminVideoModal(videoUrl, resiNo, operatorName, duration, formattedDate, recordId) {
    const modal = document.getElementById('adminVideoModal');
    const player = document.getElementById('adminVideoPlayer');
    const resiTitle = document.getElementById('videoResiTitle');
    const metaInfo = document.getElementById('videoMetaInfo');
    const downloadLink = document.getElementById('modalDownloadLink');
    const loadingSpinner = document.getElementById('adminVideoLoading');

    if (resiTitle) {
        resiTitle.innerHTML = `<span class="material-symbols-outlined" style="color: #2563eb;">movie</span> <span>RESI: ${escapeHtml(resiNo)}</span>`;
    }
    if (metaInfo) {
        metaInfo.innerHTML = `Operator: <b>${escapeHtml(operatorName)}</b> &nbsp;•&nbsp; Durasi: <b>${duration}</b> &nbsp;•&nbsp; ${formattedDate} WIB`;
    }
    if (downloadLink) {
        downloadLink.href = 'download.php?id=' + recordId;
        downloadLink.download = `${resiNo}.mp4`;
    }

    if (player) {
        player.pause();
        player.src = '';
        player.playbackRate = 1;
        document.querySelectorAll('.speed-btn').forEach(b => b.classList.remove('active'));
        const btn1x = document.querySelector('.speed-btn[data-speed="1"]');
        if (btn1x) btn1x.classList.add('active');

        if (loadingSpinner) loadingSpinner.style.display = 'flex';
        player.style.display = 'none';

        player.src = videoUrl;
        player.oncanplay = () => {
            if (loadingSpinner) loadingSpinner.style.display = 'none';
            player.style.display = 'block';
            player.play().catch(() => {});
        };
        player.onerror = () => {
            if (loadingSpinner) loadingSpinner.style.display = 'none';
            player.style.display = 'block';
        };
    }

    if (modal) modal.classList.add('active');
}

function closeAdminVideoModal() {
    const modal = document.getElementById('adminVideoModal');
    const player = document.getElementById('adminVideoPlayer');
    if (player) {
        player.pause();
        player.src = '';
    }
    if (modal) modal.classList.remove('active');
}

async function deletePackingRecord(id, resiNo) {
    if (!confirm(`Apakah Anda yakin ingin menghapus data packing dan rekaman resi "${resiNo}"?`)) return;

    try {
        const formData = new FormData();
        formData.append('id', id);

        const res = await fetch('api/delete_packing.php', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            showAdminToast(result.message, 'success');
            loadPackings(currentPage);
            loadStats();
            updateGoogleSyncBadge();
        } else {
            showAdminToast(result.message, 'error');
        }
    } catch (e) {
        showAdminToast('Gagal menghapus data.', 'error');
    }
}

// ==========================================
// TOAST NOTIFICATIONS & HELPERS
// ==========================================
function showAdminToast(msg, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    let iconName = 'info';
    let iconColor = '#3b82f6';
    if (type === 'success') { iconName = 'check_circle'; iconColor = '#10b981'; }
    if (type === 'error') { iconName = 'error'; iconColor = '#f43f5e'; }
    if (type === 'warning') { iconName = 'warning'; iconColor = '#f59e0b'; }

    toast.innerHTML = `<span class="material-symbols-outlined" style="color: ${iconColor}; font-size: 22px;">${iconName}</span> <span>${escapeHtml(msg)}</span>`;
    container.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function escapeHtml(str) {
    return String(str || '').replace(/[&<>'"]/g, 
        tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
    );
}
