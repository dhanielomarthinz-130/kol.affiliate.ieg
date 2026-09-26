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

// ==========================================
// RESILIENT JSON RESPONSE PARSER
// ==========================================
async function parseResponseJson(res) {
    const text = await res.text();
    if (!text || !text.trim()) {
        throw new Error(`Server tidak merespon (HTTP ${res.status}). Kemungkinan sesi berakhir atau timeout.`);
    }
    try {
        return JSON.parse(text);
    } catch (e) {
        // Hapus tag HTML jika server mengirim halaman error HTML
        const cleanSnippet = text.replace(/<[^>]*>?/gm, ' ').replace(/\s+/g, ' ').trim().slice(0, 160);
        throw new Error(`Respon server bukan format JSON (HTTP ${res.status}): ${cleanSnippet || 'Format tidak valid'}`);
    }
}

// ==========================================
// PREMIUM 6-BALLS SPINNER HELPERS
// ==========================================
function getPremiumSpinnerHtml(text = 'Memuat data...', size = '') {
    const sizeClass = size ? ` ${size}` : '';
    return `
        <div class="spinner-container">
            <div class="premium-balls-spinner${sizeClass}">
                <div class="spinner-ball ball-1"></div>
                <div class="spinner-ball ball-2"></div>
                <div class="spinner-ball ball-3"></div>
                <div class="spinner-ball ball-4"></div>
                <div class="spinner-ball ball-5"></div>
                <div class="spinner-ball ball-6"></div>
            </div>
            ${text ? `<div class="spinner-loading-text">${text}</div>` : ''}
        </div>
    `;
}

function getTableLoadingHtml(colspan = 8, text = 'Memuat data...') {
    return `<tr><td colspan="${colspan}" style="text-align:center; padding: 2.5rem 1rem;">${getPremiumSpinnerHtml(text)}</td></tr>`;
}

function showGlobalLoading(text = 'Memproses data, harap tunggu...') {
    let overlay = document.getElementById('globalLoadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'globalLoadingOverlay';
        overlay.className = 'global-loading-overlay';
        overlay.innerHTML = `
            <div class="premium-balls-spinner">
                <div class="spinner-ball ball-1"></div>
                <div class="spinner-ball ball-2"></div>
                <div class="spinner-ball ball-3"></div>
                <div class="spinner-ball ball-4"></div>
                <div class="spinner-ball ball-5"></div>
                <div class="spinner-ball ball-6"></div>
            </div>
            <div id="globalLoadingText" style="font-size: 0.94rem; font-weight: 700; color: #0f172a; text-align: center; max-width: 320px; line-height: 1.45;">${escapeHtml(text)}</div>
        `;
        document.body.appendChild(overlay);
    } else {
        const textEl = document.getElementById('globalLoadingText');
        if (textEl) textEl.textContent = text;
    }
    overlay.style.display = 'flex';
    requestAnimationFrame(() => overlay.classList.add('active'));
}

function hideGlobalLoading() {
    const overlay = document.getElementById('globalLoadingOverlay');
    if (overlay) {
        overlay.classList.remove('active');
        setTimeout(() => {
            if (!overlay.classList.contains('active')) overlay.style.display = 'none';
        }, 250);
    }
}

// ==========================================
// BROWSER URL STATE SYNCHRONIZATION
// ==========================================
function syncUrlWithState(replace = true) {
    try {
        const params = new URLSearchParams();
        const pageName = _currentView || 'packings';
        params.set('page', pageName);

        if (pageName === 'packings') {
            const filters = getActiveFilterParams();
            if (filters.search) params.set('search', filters.search);
            if (filters.operator_id && filters.operator_id !== '0') params.set('operator', filters.operator_id);
            if (filters.date_from) params.set('date_from', filters.date_from);
            if (filters.date_to) params.set('date_to', filters.date_to);
            if (typeof currentPage !== 'undefined' && currentPage > 1) params.set('p', currentPage);
        }

        const newUrl = window.location.pathname + '?' + params.toString();
        if (replace) {
            window.history.replaceState({ view: pageName }, '', newUrl);
        } else {
            window.history.pushState({ view: pageName }, '', newUrl);
        }
    } catch (err) {
        console.warn('URL sync error:', err);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initFlatpickr();

    // Baca parameter dari URL browser
    const urlParams = new URLSearchParams(window.location.search);
    const pageFromUrl = urlParams.get('page') || (window.location.hash ? window.location.hash.replace('#', '') : 'packings');
    
    // Pulihkan filter dari URL jika ada, atau default ke tanggal hari ini
    const localToday = getLocalTodayDate();
    const searchFromUrl = urlParams.get('search');
    const opFromUrl = urlParams.get('operator');
    const dfFromUrl = urlParams.get('date_from');
    const dtFromUrl = urlParams.get('date_to');
    const pFromUrl = parseInt(urlParams.get('p') || '1', 10);
    if (!isNaN(pFromUrl) && pFromUrl > 1) currentPage = pFromUrl;

    if (searchFromUrl) {
        const sEl = document.getElementById('searchResi');
        if (sEl) sEl.value = searchFromUrl;
    }

    if (urlParams.has('date_from')) {
        const dfVal = urlParams.get('date_from');
        if (dfVal && _fpFrom) {
            _fpFrom.setDate(dfVal, false);
        } else if (_fpFrom) {
            _fpFrom.clear();
        }
    } else if (_fpFrom) {
        _fpFrom.setDate(localToday, false);
    }

    if (urlParams.has('date_to')) {
        const dtVal = urlParams.get('date_to');
        if (dtVal && _fpTo) {
            _fpTo.setDate(dtVal, false);
        } else if (_fpTo) {
            _fpTo.clear();
        }
    } else if (_fpTo) {
        _fpTo.setDate(localToday, false);
    }

    loadStats();
    loadOperatorsFilter().then(() => {
        if (opFromUrl) {
            const opEl = document.getElementById('filterOperator');
            if (opEl) opEl.value = opFromUrl;
        }
    });

    setupEventListeners();
    updateGoogleSyncBadge();
    checkGlobalMaintenanceStatus();
    startAutoRefresh();

    // Buka view sesuai URL parameter
    switchAdminView(pageFromUrl || 'packings', false);

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const p = params.get('page') || 'packings';
        switchAdminView(p, false);
    });
});

// ==========================================
// VIEW SWITCHING (TABS)
// ==========================================
function switchAdminView(view, updateUrl = true) {
    _currentView = view;

    const navPackings = document.getElementById('navPackings');
    const navUsers = document.getElementById('navUsers');
    const navMaint = document.getElementById('navMaintenance');

    const viewPackings = document.getElementById('viewPackings');
    const viewUsers = document.getElementById('viewUsers');
    const viewMaint = document.getElementById('viewMaintenance');

    const titleEl = document.getElementById('mainPageTitle');

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
        loadUsersTable();
    } else if (view === 'maintenance') {
        if (navMaint) navMaint.classList.add('active');
        if (viewMaint) viewMaint.style.display = 'block';
        if (titleEl) titleEl.innerHTML = `<span class="material-symbols-outlined" style="font-size:26px; color:#f59e0b;">build_circle</span><span>Maintenance &amp; Diagnostik Sistem</span>`;
        loadMaintenanceInfo();
        loadDbTablesList();
    } else {
        // default: packings
        if (navPackings) navPackings.classList.add('active');
        if (viewPackings) viewPackings.style.display = 'block';
        if (titleEl) titleEl.innerHTML = `<span class="material-symbols-outlined" style="font-size:26px; color:#2563eb;">table_view</span><span>Semua Data Hasil Packaging</span>`;
        loadPackings(currentPage);
    }

    syncUrlWithState(updateUrl ? false : true);
}

// ==========================================
// FLATPICKR & DATE PRESETS
// ==========================================
function getLocalTodayDate() {
    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

function initFlatpickr() {
    const localToday = getLocalTodayDate();

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
            syncUrlWithState(true);
        }
    };

    const fromEl = document.getElementById('filterDateFrom');
    const toEl = document.getElementById('filterDateTo');

    if (fromEl && typeof flatpickr !== 'undefined') {
        const fromOpts = Object.assign({}, fpConfig);
        fromOpts.defaultDate = fromEl.value || localToday;
        _fpFrom = flatpickr(fromEl, fromOpts);
    }
    if (toEl && typeof flatpickr !== 'undefined') {
        const toOpts = Object.assign({}, fpConfig);
        toOpts.defaultDate = toEl.value || localToday;
        _fpTo = flatpickr(toEl, toOpts);
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
    syncUrlWithState(true);
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
                syncUrlWithState(true);
            }, 300);
        });
    }

    // Filter changes
    const filterOperator = document.getElementById('filterOperator');
    if (filterOperator) {
        filterOperator.addEventListener('change', () => {
            loadPackings(1);
            updateGoogleSyncBadge();
            syncUrlWithState(true);
        });
    }

    // Reset filters (kembali ke default: hari ini)
    const resetBtn = document.getElementById('btnResetFilter');
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            const localToday = getLocalTodayDate();
            if (searchInput) searchInput.value = '';
            if (filterOperator) filterOperator.value = '0';
            if (_fpFrom) _fpFrom.setDate(localToday, false);
            if (_fpTo) _fpTo.setDate(localToday, false);
            const fromInput = document.getElementById('filterDateFrom');
            const toInput = document.getElementById('filterDateTo');
            if (fromInput) fromInput.value = localToday;
            if (toInput) toInput.value = localToday;

            loadPackings(1);
            updateGoogleSyncBadge();
            syncUrlWithState(true);
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
                const data = await parseResponseJson(res);
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
                const data = await parseResponseJson(res);
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
        const filters = getActiveFilterParams();
        const params = new URLSearchParams(Object.assign({ _t: Date.now() }, filters));
        const res = await fetch('api/stats.php?' + params.toString(), { cache: 'no-store' });
        const data = await parseResponseJson(res);
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

            const stats = data.stats || data;
            const todayCount = stats.today_packings ?? stats.today_count ?? 0;
            const todayAvg = stats.today_avg_duration ?? 0;
            const totalCount = stats.total_packings ?? stats.total_count ?? 0;
            const totalStorage = stats.total_storage ?? (stats.total_storage_mb ? stats.total_storage_mb + ' MB' : '0 MB');

            animateVal('statTodayCount', todayCount);
            animateVal('statTodayAvg', (todayAvg > 60 ? Math.floor(todayAvg / 60) + 'm ' + (todayAvg % 60) + 's' : todayAvg + 's'));
            animateVal('statTotalCount', totalCount);
            animateVal('statStorage', totalStorage);

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
        const data = await parseResponseJson(res);
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

    tableBody.innerHTML = getTableLoadingHtml(8, 'Memuat data packaging...');

    const filters = getActiveFilterParams();
    const params = new URLSearchParams(Object.assign({
        page: page,
        limit: pageLimit,
        sort_by: currentSortBy,
        sort_dir: currentSortDir
    }, filters));

    try {
        const res = await fetch('api/get_packings.php?' + params.toString());
        const result = await parseResponseJson(res);

        if (result.success) {
            renderTable(result.data, result.total, page, result.total_pages);
            if (result.stats) {
                const animateVal = (id, newVal) => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    if (el.textContent !== String(newVal)) {
                        el.classList.add('stat-bump');
                        setTimeout(() => el.classList.remove('stat-bump'), 500);
                    }
                    el.textContent = newVal;
                };

                animateVal('statTodayCount', result.stats.today_packings ?? 0);
                animateVal('statTodayAvg', result.stats.formatted_avg ?? (result.stats.avg_duration + 's'));
                animateVal('statTotalCount', result.stats.total ?? 0);
                animateVal('statStorage', result.stats.storage ?? '0 MB');
            }
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
                    <a href="${escapeHtml(row.gdrive_url)}" target="_blank" class="btn btn-sm" style="display:inline-flex; align-items:center; gap:5px; color:#15803d; border:1px solid #86efac; background:#f0fdf4; padding:3px 9px; text-decoration:none; font-weight:600; font-size:0.78rem; border-radius:6px;" title="Buka rekaman di Google Drive (Sudah Sync)">
                        <svg width="14" height="14" viewBox="0 0 87.3 78" style="vertical-align: middle;">
                          <path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8h-27.5c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
                          <path d="m43.65 25-13.75-23.8c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44a9.06 9.06 0 0 0 -1.2 4.5h27.5z" fill="#00ac47"/>
                          <path d="m73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5h-27.502l5.852 11.5z" fill="#ea4335"/>
                          <path d="m43.65 25 13.75-23.8c-1.35-.8-2.9-1.2-4.5-1.2h-18.5c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/>
                          <path d="m59.8 53h-32.3l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/>
                          <path d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3l-13.75 23.8 16.15 28h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/>
                        </svg>
                        <span class="material-symbols-outlined" style="font-size:14px; color:#16a34a;">check_circle</span>
                        <span>Done Sync</span>
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

    tbody.innerHTML = getTableLoadingHtml(7, 'Memuat data operator &amp; pengguna...');

    try {
        const res = await fetch('api/users.php?action=list');
        const data = await parseResponseJson(res);

        if (data.success && data.data) {
            if (data.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:2rem; color:#64748b;">Belum ada pengguna.</td></tr>`;
                return;
            }

            tbody.innerHTML = data.data.map((u, idx) => {
                const isActive = (parseInt(u.is_active) === 1);
                const hasPin   = (parseInt(u.has_pin) === 1);
                const roleClass = (u.role === 'superadmin') ? 'badge-role-superadmin' : (u.role === 'admin' ? 'badge-role-admin' : 'badge-role-operator');
                const roleLabel = (u.role === 'superadmin') ? 'SUPERADMIN' : (u.role === 'admin' ? 'ADMIN' : 'OPERATOR');
                const createdStr = u.created_at ? new Date(u.created_at).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' }) : '-';
                const pinBadge = (u.role === 'operator') ? (hasPin
                    ? `<span title="PIN sudah diatur" style="display:inline-flex;align-items:center;gap:3px;font-size:0.68rem;font-weight:700;padding:2px 7px;border-radius:20px;background:rgba(99,102,241,0.12);color:#6366f1;border:1px solid rgba(99,102,241,0.25);margin-left:5px;"><span class="material-symbols-outlined" style="font-size:11px;">pin</span>PIN ✓</span>`
                    : `<span title="PIN belum diatur" style="display:inline-flex;align-items:center;gap:3px;font-size:0.68rem;font-weight:700;padding:2px 7px;border-radius:20px;background:rgba(148,163,184,0.12);color:#94a3b8;border:1px solid rgba(148,163,184,0.2);margin-left:5px;"><span class="material-symbols-outlined" style="font-size:11px;">pin</span>No PIN</span>`
                ) : '';

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
                            <span class="${roleClass}">${roleLabel}</span>${pinBadge}
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
                                <button class="btn btn-sm" onclick="openEditUserModal(${u.id}, '${escapeHtml(u.name)}', '${escapeHtml(u.username)}', '${u.role}', ${hasPin ? 1 : 0})" title="Edit Pengguna (Nama, Password / PIN)" style="font-size:0.75rem; padding:4px 9px; display:inline-flex; align-items:center; gap:4px; color:#0369a1; background:#e0f2fe; border:1px solid #bae6fd; border-radius:8px; font-weight:600; cursor:pointer;">
                                    <span class="material-symbols-outlined" style="font-size:14px;">edit</span>
                                    <span>Edit</span>
                                </button>
                                <button class="btn btn-sm ${isActive ? 'btn-outline' : 'btn-success'}" onclick="toggleUserStatus(${u.id}, '${escapeHtml(u.name)}', ${isActive ? 1 : 0})" title="${isActive ? 'Klik untuk Menonaktifkan akun' : 'Klik untuk Mengaktifkan akun kembali'}" style="font-size:0.75rem; padding:4px 9px;">
                                    <span class="material-symbols-outlined" style="font-size:15px;">${isActive ? 'power_settings_new' : 'check'}</span>
                                    <span>${isActive ? 'Inactive' : 'Aktifkan'}</span>
                                </button>
                                ${u.role === 'operator' ? `<button class="btn btn-sm" onclick="openSetPinModal(${u.id}, '${escapeHtml(u.name)}')" title="Atur PIN Login Operator" style="font-size:0.75rem; padding:4px 9px; background:linear-gradient(135deg,#6366f1,#818cf8); color:#fff; border:none; border-radius:8px; display:inline-flex; align-items:center; gap:4px;"><span class="material-symbols-outlined" style="font-size:14px;">pin</span><span>PIN</span></button>` : ''}
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
        const data = await parseResponseJson(res);

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
        const data = await parseResponseJson(res);
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
// DATABASE MANAGER (SUPERADMIN)
// ==========================================

// ==========================================
// DATABASE MANAGER — TABEL SISTEM & HAPUS PER DATA
// ==========================================
let _dbModalActiveTable = '';
let _dbModalCurrentRows = [];
let _dbModalColumns = [];

async function loadDbTablesList() {
    const tbody = document.getElementById('dbTablesMasterBody');
    if (!tbody) return;

    tbody.innerHTML = getTableLoadingHtml(4, 'Mengambil daftar tabel database (SQLite)...');

    try {
        const res  = await fetch('api/db_manager.php?action=list_tables&_t=' + Date.now());
        const data = await parseResponseJson(res);

        if (!data.success || !Array.isArray(data.tables)) {
            tbody.innerHTML = `<tr><td colspan="4" style="padding:16px; text-align:center; color:#dc2626; font-size:0.82rem;">Gagal memuat tabel: ${escapeHtml(data.message || 'Error')}</td></tr>`;
            return;
        }

        let html = '';
        data.tables.forEach((t, i) => {
            const bg = i % 2 === 0 ? '#ffffff' : '#fafbfc';
            const countBadge = t.count > 0 
                ? `<span style="display:inline-flex; align-items:center; padding:3px 10px; border-radius:9999px; font-size:0.75rem; font-weight:700; background:rgba(37,99,235,0.1); color:#2563eb; border:1px solid rgba(37,99,235,0.2);">${t.count} Baris Data</span>`
                : `<span style="display:inline-flex; align-items:center; padding:3px 10px; border-radius:9999px; font-size:0.75rem; font-weight:600; background:#f1f5f9; color:#94a3b8;">Kosong (0)</span>`;

            html += `
                <tr style="background:${bg}; border-bottom:1px solid #f1f5f9; transition:background 0.15s ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='${bg}'">
                    <td style="padding:14px 18px; vertical-align:middle;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:36px; height:36px; border-radius:10px; background:#eff6ff; color:#2563eb; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <span class="material-symbols-outlined" style="font-size:20px;">${escapeHtml(t.icon || 'table_chart')}</span>
                            </div>
                            <div>
                                <div style="font-weight:700; font-size:0.88rem; color:#0f172a; display:flex; align-items:center; gap:6px;">
                                    <code>${escapeHtml(t.name)}</code>
                                    <span style="font-size:0.68rem; font-weight:600; padding:1px 6px; border-radius:4px; background:#f1f5f9; color:#475569;">${escapeHtml(t.badge || 'Tabel')}</span>
                                </div>
                                <div style="font-size:0.74rem; color:#64748b; margin-top:2px;">${escapeHtml(t.label || t.name)}</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:14px 18px; vertical-align:middle; color:#475569; font-size:0.8rem; max-width:320px; line-height:1.45;">
                        ${escapeHtml(t.desc)}
                    </td>
                    <td style="padding:14px 18px; text-align:center; vertical-align:middle; white-space:nowrap;">
                        ${countBadge}
                    </td>
                    <td style="padding:14px 18px; text-align:right; vertical-align:middle; white-space:nowrap;">
                        <div style="display:inline-flex; align-items:center; gap:8px;">
                            <button onclick="openDbDataManageModal('${escapeHtml(t.name)}', '${escapeHtml(t.label || t.name)}', '${escapeHtml(t.icon || 'table_chart')}')" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:5px; padding:0.42rem 0.85rem; font-size:0.78rem;">
                                <span class="material-symbols-outlined" style="font-size:15px;">manage_search</span>
                                <span>Kelola &amp; Hapus Data</span>
                            </button>
                            ${t.can_clear && t.count > 0 ? `
                                <button onclick="deleteAllTableRows('${escapeHtml(t.name)}')" class="btn btn-danger btn-sm" style="display:inline-flex; align-items:center; gap:4px; padding:0.42rem 0.75rem; font-size:0.78rem;" title="Kosongkan semua baris tabel ini">
                                    <span class="material-symbols-outlined" style="font-size:15px;">delete_sweep</span>
                                    <span>Kosongkan</span>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="4" style="padding:16px; text-align:center; color:#dc2626; font-size:0.82rem;">Error: ${escapeHtml(e.message)}</td></tr>`;
    }
}

// Open modal to view and delete rows
function openDbDataManageModal(tableName, tableLabel, tableIcon) {
    _dbModalActiveTable = tableName;
    const modal = document.getElementById('dbDataManageModal');
    const titleEl = document.getElementById('dbModalTableName');
    const iconEl = document.getElementById('dbModalIcon');
    const searchEl = document.getElementById('dbModalSearchInput');

    if (titleEl) titleEl.textContent = `Tabel: ${tableName} (${tableLabel || tableName})`;
    if (iconEl) iconEl.textContent = tableIcon || 'table_chart';
    if (searchEl) searchEl.value = '';

    if (modal) modal.classList.add('active');
    loadDbModalTable(tableName);
}

function closeDbDataManageModal() {
    const modal = document.getElementById('dbDataManageModal');
    if (modal) modal.classList.remove('active');
    _dbModalActiveTable = '';
    // Refresh table list count
    loadDbTablesList();
}

function reloadCurrentDbModalTable() {
    if (_dbModalActiveTable) {
        loadDbModalTable(_dbModalActiveTable);
    }
}

async function loadDbModalTable(tableName) {
    const headEl = document.getElementById('dbModalTableHead');
    const bodyEl = document.getElementById('dbModalTableBody');
    const emptyEl = document.getElementById('dbModalEmpty');
    const countEl = document.getElementById('dbModalRowCount');

    if (!headEl || !bodyEl) return;

    if (countEl) countEl.innerHTML = '<span style="color:#64748b;">Mengambil data...</span>';
    if (emptyEl) emptyEl.style.display = 'none';
    headEl.innerHTML = '';
    bodyEl.innerHTML = getTableLoadingHtml(1, 'Mengambil data isi tabel database...');

    try {
        const res  = await fetch(`api/db_manager.php?action=get_table&table=${encodeURIComponent(tableName)}&_t=` + Date.now());
        const data = await parseResponseJson(res);

        if (!data.success) {
            headEl.innerHTML = '';
            if (countEl) countEl.innerHTML = `<span style="color:#dc2626;">Gagal memuat: ${escapeHtml(data.message || 'Error')}</span>`;
            return;
        }

        const { columns, rows, total } = data;
        _dbModalColumns = columns || [];
        _dbModalCurrentRows = rows || [];

        if (countEl) {
            countEl.innerHTML = `Total: <strong>${total}</strong> baris data di tabel <code style="background:#eff6ff; color:#2563eb; padding:2px 6px; border-radius:4px;">${escapeHtml(tableName)}</code>`;
        }

        if (!rows || rows.length === 0) {
            headEl.innerHTML = '';
            bodyEl.innerHTML = '';
            if (emptyEl) emptyEl.style.display = 'block';
            return;
        }

        renderDbModalRows(_dbModalColumns, _dbModalCurrentRows, tableName);

    } catch (e) {
        if (countEl) countEl.innerHTML = `<span style="color:#dc2626;">Error: ${escapeHtml(e.message)}</span>`;
    }
}

function renderDbModalRows(columns, rows, tableName) {
    const headEl = document.getElementById('dbModalTableHead');
    const bodyEl = document.getElementById('dbModalTableBody');
    const emptyEl = document.getElementById('dbModalEmpty');

    if (!headEl || !bodyEl) return;

    if (!rows || rows.length === 0) {
        headEl.innerHTML = '';
        bodyEl.innerHTML = '';
        if (emptyEl) emptyEl.style.display = 'block';
        return;
    }
    if (emptyEl) emptyEl.style.display = 'none';

    // Build specialized clean columns based on table
    const colStyle = 'padding:10px 14px; text-align:left; font-size:0.74rem; font-weight:700; color:#475569; background:#f8fafc; border-bottom:2px solid #e2e8f0; white-space:nowrap; text-transform:uppercase; letter-spacing:.03em;';
    const cellStyle = 'padding:10px 14px; border-bottom:1px solid #f1f5f9; font-size:0.8rem; color:#334155; vertical-align:middle;';

    let hRow = '<tr>';
    if (tableName === 'packings') {
        hRow += `<th style="${colStyle} width:60px;">ID</th>`;
        hRow += `<th style="${colStyle}">No. Resi</th>`;
        hRow += `<th style="${colStyle}">Operator</th>`;
        hRow += `<th style="${colStyle}">Waktu Rekam</th>`;
        hRow += `<th style="${colStyle}">Durasi</th>`;
        hRow += `<th style="${colStyle}">Ukuran Video</th>`;
        hRow += `<th style="${colStyle}">Google Drive</th>`;
        hRow += `<th style="${colStyle} text-align:center; width:90px;">Aksi</th>`;
    } else if (tableName === 'users') {
        hRow += `<th style="${colStyle} width:60px;">ID</th>`;
        hRow += `<th style="${colStyle}">Username</th>`;
        hRow += `<th style="${colStyle}">Nama Pengguna</th>`;
        hRow += `<th style="${colStyle}">Role</th>`;
        hRow += `<th style="${colStyle}">Status</th>`;
        hRow += `<th style="${colStyle}">Dibuat</th>`;
        hRow += `<th style="${colStyle} text-align:center; width:90px;">Aksi</th>`;
    } else {
        // Fallback for system tables
        columns.forEach(c => { hRow += `<th style="${colStyle}">${escapeHtml(c)}</th>`; });
        hRow += `<th style="${colStyle} text-align:center; width:90px;">Aksi</th>`;
    }
    hRow += '</tr>';
    headEl.innerHTML = hRow;

    // Build body
    let bHtml = '';
    rows.forEach((r, idx) => {
        const rowId = r.id ?? r.rowid ?? idx;
        const bg = idx % 2 === 0 ? '#ffffff' : '#fafbfc';

        bHtml += `<tr style="background:${bg}; transition:background 0.15s ease;" onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='${bg}'">`;

        if (tableName === 'packings') {
            const sizeFormatted = r.video_filesize ? formatBytes(r.video_filesize) : '—';
            const durFormatted = r.duration_seconds ? `${r.duration_seconds} dtk` : '—';
            const driveBadge = r.gdrive_url 
                ? `<a href="${escapeHtml(r.gdrive_url)}" target="_blank" style="display:inline-flex; align-items:center; gap:4px; color:#2563eb; font-weight:600; text-decoration:none;"><span class="material-symbols-outlined" style="font-size:15px;">open_in_new</span> Link Drive</a>` 
                : `<span style="color:#94a3b8; font-size:0.75rem;">Belum Sync</span>`;

            bHtml += `<td style="${cellStyle} font-family:monospace; font-weight:600; color:#64748b;">#${escapeHtml(String(rowId))}</td>`;
            bHtml += `<td style="${cellStyle} font-weight:700; color:#0f172a;"><span style="background:#f1f5f9; padding:2px 8px; border-radius:5px; border:1px solid #e2e8f0;">${escapeHtml(r.resi_no || '—')}</span></td>`;
            bHtml += `<td style="${cellStyle} font-weight:600;">${escapeHtml(r.operator_name || '—')}</td>`;
            bHtml += `<td style="${cellStyle} font-size:0.76rem; color:#475569; white-space:nowrap;">${escapeHtml(r.start_time || r.created_at || '—')}</td>`;
            bHtml += `<td style="${cellStyle} font-size:0.76rem; color:#475569;">${durFormatted}</td>`;
            bHtml += `<td style="${cellStyle} font-size:0.76rem; color:#475569;">${sizeFormatted}</td>`;
            bHtml += `<td style="${cellStyle}">${driveBadge}</td>`;
            bHtml += `<td style="${cellStyle} text-align:center;">
                <button onclick="deleteDbModalRow('packings', ${rowId}, '${escapeHtml(r.resi_no || '#' + rowId)}')" class="btn btn-danger btn-sm" style="display:inline-flex; align-items:center; gap:4px; padding:3px 9px; font-size:0.74rem;" title="Hapus data ini">
                    <span class="material-symbols-outlined" style="font-size:14px;">delete</span>
                    <span>Hapus</span>
                </button>
            </td>`;
        } else if (tableName === 'users') {
            const roleBadge = r.role === 'superadmin' 
                ? '<span style="background:#fef3c7; color:#b45309; padding:2px 7px; border-radius:4px; font-weight:700; font-size:0.72rem;">Superadmin</span>'
                : (r.role === 'admin' 
                    ? '<span style="background:#e0e7ff; color:#3730a3; padding:2px 7px; border-radius:4px; font-weight:700; font-size:0.72rem;">Admin</span>' 
                    : '<span style="background:#f1f5f9; color:#475569; padding:2px 7px; border-radius:4px; font-weight:700; font-size:0.72rem;">Operator</span>');
            const statusBadge = (r.is_active == 1 || r.is_active === null)
                ? '<span style="color:#059669; font-weight:600; font-size:0.74rem;">● Aktif</span>'
                : '<span style="color:#dc2626; font-weight:600; font-size:0.74rem;">● Nonaktif</span>';

            bHtml += `<td style="${cellStyle} font-family:monospace; color:#64748b;">#${escapeHtml(String(rowId))}</td>`;
            bHtml += `<td style="${cellStyle} font-weight:700; color:#0f172a;">${escapeHtml(r.username || '—')}</td>`;
            bHtml += `<td style="${cellStyle}">${escapeHtml(r.name || '—')}</td>`;
            bHtml += `<td style="${cellStyle}">${roleBadge}</td>`;
            bHtml += `<td style="${cellStyle}">${statusBadge}</td>`;
            bHtml += `<td style="${cellStyle} font-size:0.74rem; color:#64748b;">${escapeHtml(r.created_at || '—')}</td>`;
            bHtml += `<td style="${cellStyle} text-align:center;">
                <button onclick="deleteDbModalRow('users', ${rowId}, '${escapeHtml(r.username || '#' + rowId)}')" class="btn btn-danger btn-sm" style="display:inline-flex; align-items:center; gap:4px; padding:3px 9px; font-size:0.74rem;" title="Hapus pengguna ini">
                    <span class="material-symbols-outlined" style="font-size:14px;">delete</span>
                    <span>Hapus</span>
                </button>
            </td>`;
        } else {
            // generic fallback
            columns.forEach(c => {
                let val = r[c] ?? '';
                if (typeof val === 'string' && val.length > 50) val = val.substring(0,50) + '…';
                bHtml += `<td style="${cellStyle} max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(String(val))}</td>`;
            });
            const canDelete = tableName !== 'sqlite_sequence';
            if (canDelete) {
                bHtml += `<td style="${cellStyle} text-align:center;">
                    <button onclick="deleteDbModalRow('${tableName}', ${rowId}, '#${rowId}')" class="btn btn-danger btn-sm" style="display:inline-flex; align-items:center; gap:4px; padding:3px 9px; font-size:0.74rem;">
                        <span class="material-symbols-outlined" style="font-size:14px;">delete</span>
                        <span>Hapus</span>
                    </button>
                </td>`;
            } else {
                bHtml += `<td style="${cellStyle} text-align:center; color:#94a3b8;">—</td>`;
            }
        }

        bHtml += '</tr>';
    });

    bodyEl.innerHTML = bHtml;
}

function filterDbModalRows(query) {
    if (!_dbModalCurrentRows || _dbModalCurrentRows.length === 0) return;
    const q = (query || '').toLowerCase().trim();

    if (!q) {
        renderDbModalRows(_dbModalColumns, _dbModalCurrentRows, _dbModalActiveTable);
        return;
    }

    const filtered = _dbModalCurrentRows.filter(row => {
        return Object.values(row).some(v => String(v || '').toLowerCase().includes(q));
    });

    renderDbModalRows(_dbModalColumns, filtered, _dbModalActiveTable);
}

async function deleteDbModalRow(tableName, rowId, identifier) {
    const label = identifier ? `"${identifier}" (ID: ${rowId})` : `ID ${rowId}`;
    if (!confirm(`⚠️ Yakin ingin menghapus data ${label} dari tabel "${tableName}"?\n\nTindakan ini permanen dan tidak dapat dibatalkan!`)) return;

    try {
        const fd = new FormData();
        fd.append('table', tableName);
        fd.append('id', rowId);

        const res  = await fetch('api/db_manager.php?action=delete_row', { method: 'POST', body: fd });
        const data = await parseResponseJson(res);

        if (data.success) {
            showAdminToast(data.message || 'Data berhasil dihapus.', 'success');
            // Refresh modal table
            loadDbModalTable(tableName);
            // Refresh master table list count
            loadDbTablesList();
        } else {
            showAdminToast(data.message || 'Gagal menghapus data.', 'error');
        }
    } catch (e) {
        showAdminToast('Gagal menghapus: ' + e.message, 'error');
    }
}

async function deleteAllTableRows(tableName) {
    if (!confirm(`🔴 HAPUS SEMUA data di tabel "${tableName}"?\n\nSemua baris data akan dihapus permanen dari database!`)) return;
    if (!confirm(`⚠️ Konfirmasi Terakhir: Anda benar-benar yakin ingin MENGOSONGKAN tabel "${tableName}"?`)) return;

    try {
        const fd = new FormData();
        fd.append('table', tableName);
        const res  = await fetch('api/db_manager.php?action=delete_all_rows', { method: 'POST', body: fd });
        const data = await parseResponseJson(res);
        if (data.success) {
            showAdminToast(data.message || `Semua data di tabel "${tableName}" berhasil dikosongkan.`, 'success');
            loadDbTablesList();
            if (_dbModalActiveTable === tableName) {
                loadDbModalTable(tableName);
            }
        } else {
            showAdminToast(data.message || 'Gagal mengosongkan tabel.', 'error');
        }
    } catch (e) {
        showAdminToast('Gagal: ' + e.message, 'error');
    }
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
        const data = await parseResponseJson(res);

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

            // ----- Render Maintenance Mode status -----
            _renderMaintenanceModeUI(data.maintenance_mode);

        } else {
            showAdminToast(data.message, 'error');
        }
    } catch (e) {
        if (specsEl) specsEl.textContent = 'Gagal memuat diagnostik: ' + e.message;
        checkGlobalMaintenanceStatus();
    }
}

// Global Maintenance Status Checker (Runs everywhere in admin)
async function checkGlobalMaintenanceStatus() {
    try {
        const res = await fetch('api/maintenance.php?action=get_status&_t=' + Date.now());
        const data = await parseResponseJson(res);
        if (data.success && data.config) {
            _renderMaintenanceModeUI(data.config);
        }
    } catch (err) {
        console.warn('Could not check global maintenance status:', err);
    }
}

function _renderMaintenanceModeUI(cfg) {
    if (!cfg) return;
    const isActive = !!cfg.is_maintenance;

    // 1. Sticky Global Alert Banner (Visible across ALL tabs when active)
    const globalBanner = document.getElementById('globalMaintenanceAlertBanner');
    if (globalBanner) {
        globalBanner.style.display = isActive ? 'flex' : 'none';
    }

    // 2. Topbar Status Chip
    const topChip = document.getElementById('topbarMaintChip');
    const topLabel = document.getElementById('topbarMaintLabel');
    if (topChip) {
        topChip.className = isActive ? 'topbar-status-chip active' : 'topbar-status-chip normal';
    }
    if (topLabel) {
        topLabel.textContent = isActive ? '⚠️ MAINTENANCE AKTIF' : 'Sistem Normal';
    }

    // 3. Sidebar Nav Badge Indicator
    const sidebarBadge = document.getElementById('sidebarMaintBadge');
    if (sidebarBadge) {
        if (isActive) {
            sidebarBadge.className = 'nav-badge badge-maint-sidebar-active';
            sidebarBadge.textContent = '● AKTIF';
        } else {
            sidebarBadge.className = 'nav-badge badge-maint-sidebar-normal';
            sidebarBadge.textContent = 'SUPER';
        }
    }

    // 4. Maintenance View Card Elements
    const card      = document.getElementById('maintModeCard');
    const cardIcon  = document.getElementById('maintModeCardIcon');
    const cardTitle = document.getElementById('maintModeCardTitleText');
    const badge     = document.getElementById('maintModeBadge');
    const dot       = document.getElementById('maintModeDot');
    const label     = document.getElementById('maintModeLabel');
    const desc      = document.getElementById('maintModeDesc');
    const btn       = document.getElementById('btnToggleMaintenance');
    const btnIcon   = document.getElementById('maintToggleIcon');
    const btnLabel  = document.getElementById('maintToggleLabel');
    const lastEl    = document.getElementById('maintLastChanged');

    if (card) {
        card.className = isActive ? 'card maint-card-active' : 'card maint-card-normal';
    }
    if (cardIcon) {
        cardIcon.textContent = isActive ? 'warning' : 'verified';
        cardIcon.style.color = isActive ? '#dc2626' : '#10b981';
    }
    if (cardTitle) {
        cardTitle.textContent = isActive 
            ? 'Status: MODE PEMELIHARAAN AKTIF (SISTEM DIKUNCI)' 
            : 'Status: Sistem Normal (Operasional Penuh)';
        cardTitle.style.color = isActive ? '#991b1b' : '#0f172a';
    }

    if (badge) {
        if (isActive) {
            badge.style.background = '#fef2f2';
            badge.style.color = '#dc2626';
            badge.style.borderColor = '#fca5a5';
        } else {
            badge.style.background = 'rgba(16,185,129,0.12)';
            badge.style.color = '#059669';
            badge.style.borderColor = 'rgba(16,185,129,0.3)';
        }
    }
    if (dot) {
        dot.style.background = isActive ? '#dc2626' : '#10b981';
        dot.style.animation = isActive ? 'maintPulseDot 1.2s infinite' : 'none';
    }
    if (label) {
        label.textContent = isActive ? '🔴 Mode Pemeliharaan Aktif' : '🟢 Sistem Normal (Aktif)';
    }

    if (desc) {
        if (isActive) {
            desc.innerHTML = '<strong style="color:#dc2626;">PERHATIAN:</strong> Mode maintenance sedang <b>AKTIF</b>. Sistem terkunci untuk Operator &amp; Admin biasa. Hanya Superadmin <strong>Daniel</strong> yang dapat login.';
        } else {
            desc.innerHTML = 'Sistem beroperasi normal. Seluruh operator dan admin dapat login dan menggunakan fitur packing secara penuh.';
        }
    }

    if (btn) {
        if (isActive) {
            btn.style.background = 'linear-gradient(135deg,#15803d,#16a34a)';
            btn.style.color = '#fff';
            btn.style.boxShadow = '0 4px 14px -4px rgba(22,163,74,0.5)';
        } else {
            btn.style.background = 'linear-gradient(135deg,#d97706,#f59e0b)';
            btn.style.color = '#fff';
            btn.style.boxShadow = '0 4px 14px -4px rgba(245,158,11,0.5)';
        }
    }
    if (btnIcon) btnIcon.textContent = isActive ? 'check_circle' : 'power_settings_new';
    if (btnLabel) btnLabel.textContent = isActive ? 'Matikan Maintenance (Kembali Normal)' : 'Aktifkan Mode Maintenance';

    if (lastEl && cfg.updated_at) {
        const ts = new Date(cfg.updated_at).toLocaleString('id-ID');
        lastEl.textContent = isActive ? `Diaktifkan oleh ${cfg.updated_by || 'Daniel'} pada ${ts}` : `Terakhir dinonaktifkan: ${ts}`;
    } else if (lastEl) {
        lastEl.textContent = isActive ? `Diaktifkan oleh ${cfg.updated_by || 'Daniel'}` : 'Status: Normal';
    }
}

async function toggleMaintenanceMode() {
    const btn = document.getElementById('btnToggleMaintenance');
    const labelEl = document.getElementById('maintModeLabel');
    const topLabel = document.getElementById('topbarMaintLabel');
    const isCurrentlyActive = (labelEl && (labelEl.textContent.includes('Pemeliharaan Aktif') || labelEl.textContent.includes('Maintenance Aktif'))) ||
                              (topLabel && topLabel.textContent.includes('AKTIF'));

    const confirmMsg = isCurrentlyActive
        ? '⚠️ NONAKTIFKAN MODE PEMELIHARAAN?\n\nSistem akan kembali dibuka secara normal untuk seluruh Operator dan Admin.'
        : '🔒 AKTIFKAN MODE PEMELIHARAAN?\n\nSistem akan DIKUNCI. Hanya Superadmin (Daniel) yang dapat login. Semua Operator dan pengguna lain akan dialihkan ke layar pemeliharaan.';

    if (!confirm(confirmMsg)) return;

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="material-symbols-outlined spin" style="font-size:16px;">sync</span> <span>Memproses...</span>`;
    }

    try {
        const res = await fetch('api/maintenance.php?action=toggle_maintenance', { method: 'POST' });
        const data = await parseResponseJson(res);
        if (data.success) {
            showAdminToast(data.message, data.is_maintenance ? 'warning' : 'success');
            await checkGlobalMaintenanceStatus();
            if (_currentView === 'maintenance') {
                await loadMaintenanceInfo();
            }
        } else {
            showAdminToast(data.message || 'Gagal mengubah status maintenance.', 'error');
        }
    } catch (e) {
        showAdminToast('Kesalahan jaringan: ' + e.message, 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function runOptimizeDB() {
    const btn = document.getElementById('btnOptimizeDB');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="material-symbols-outlined spin" style="font-size:16px;">sync</span> <span>Mengoptimalkan...</span>`;
    }
    showGlobalLoading('Mengoptimalkan database SQLite (VACUUM & ANALYZE)...');

    try {
        const res = await fetch('api/maintenance.php?action=optimize_db', { method: 'POST' });
        const data = await parseResponseJson(res);
        if (data.success) {
            showAdminToast(data.message, 'success');
            loadMaintenanceInfo();
        } else {
            showAdminToast(data.message, 'error');
        }
    } catch (e) {
        showAdminToast('Kesalahan jaringan saat optimasi DB: ' + e.message, 'error');
    } finally {
        hideGlobalLoading();
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
    showGlobalLoading('Membersihkan file sampah dan rekaman video 0-byte...');

    try {
        const res = await fetch('api/maintenance.php?action=clean_temp', { method: 'POST' });
        const data = await parseResponseJson(res);
        if (data.success) {
            showAdminToast(data.message, 'success');
            loadMaintenanceInfo();
        } else {
            showAdminToast(data.message, 'error');
        }
    } catch (e) {
        showAdminToast('Kesalahan pembersihan: ' + e.message, 'error');
    } finally {
        hideGlobalLoading();
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
        const data = await parseResponseJson(res);
        if (data.success) {
            const badges = [
                document.getElementById('pendingSyncBadge'),
                document.getElementById('pendingSyncBadgeFilter')
            ];
            const count = (typeof data.filter_pending_count !== 'undefined') ? data.filter_pending_count : data.pending_count;
            badges.forEach(b => {
                if (b) {
                    if (count > 0) {
                        b.textContent = count;
                        b.style.display = 'inline-block';
                        b.title = `${count} paket sesuai filter aktif belum di-sync ke Google`;
                    } else {
                        b.style.display = 'none';
                    }
                }
            });
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
        const data = await parseResponseJson(res);
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
        const data = await parseResponseJson(res);

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
        const data = await parseResponseJson(res);

        if (data.success && data.drive_url) {
            showAdminToast('Berhasil disinkronkan ke Google Drive & Sheet!', 'success');
            btn.outerHTML = `
                <a href="${escapeHtml(data.drive_url)}" target="_blank" class="btn btn-sm" style="display:inline-flex; align-items:center; gap:5px; color:#15803d; border:1px solid #86efac; background:#f0fdf4; padding:3px 9px; text-decoration:none; font-weight:600; font-size:0.78rem; border-radius:6px;" title="Buka rekaman di Google Drive (Sudah Sync)">
                    <svg width="14" height="14" viewBox="0 0 87.3 78" style="vertical-align: middle;">
                      <path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8h-27.5c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
                      <path d="m43.65 25-13.75-23.8c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44a9.06 9.06 0 0 0 -1.2 4.5h27.5z" fill="#00ac47"/>
                      <path d="m73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5h-27.502l5.852 11.5z" fill="#ea4335"/>
                      <path d="m43.65 25 13.75-23.8c-1.35-.8-2.9-1.2-4.5-1.2h-18.5c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/>
                      <path d="m59.8 53h-32.3l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/>
                      <path d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3l-13.75 23.8 16.15 28h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/>
                    </svg>
                    <span class="material-symbols-outlined" style="font-size:14px; color:#16a34a;">check_circle</span>
                    <span>Done Sync</span>
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
    const detailText = document.getElementById('batchSyncDetailText');
    const progressText = document.getElementById('batchSyncProgressText');
    const progressBar = document.getElementById('batchSyncProgressBar');
    const noticeBox = document.getElementById('batchSyncNotice');
    const filterNoticeText = document.getElementById('batchSyncFilterNoticeText');
    const btnCancel = document.getElementById('btnCancelBatchSync');
    const btnDone = document.getElementById('btnDoneBatchSync');
    const btnClose = document.getElementById('btnCloseBatchSync');

    if (btnDone) btnDone.style.display = 'none';
    if (btnCancel) btnCancel.style.display = 'inline-flex';
    if (btnClose) btnClose.style.display = 'inline-flex';
    if (noticeBox) {
        noticeBox.style.display = 'none';
        noticeBox.textContent = '';
    }

    const spinnerAnim = document.getElementById('syncSpinnerAnimation');
    const successIcon = document.getElementById('syncSuccessIcon');
    if (spinnerAnim) spinnerAnim.style.display = 'block';
    if (successIcon) successIcon.style.display = 'none';

    if (statusText) statusText.textContent = 'Memeriksa antrean paket...';
    if (detailText) detailText.textContent = 'Menyiapkan sinkronisasi...';
    if (progressText) progressText.textContent = '0%';
    if (progressBar) progressBar.style.width = '0%';

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
        filterNoticeText.textContent = `Target Sinkronisasi: ${filterDesc}`;
    }

    try {
        const queryParams = new URLSearchParams(Object.assign({ action: 'get_config' }, activeFilters));
        const cfgRes = await fetch('api/sync_google.php?' + queryParams.toString());
        const cfgData = await parseResponseJson(cfgRes);

        if (!cfgData.success || !cfgData.config?.gas_webapp_url) {
            if (spinnerAnim) spinnerAnim.style.display = 'none';
            if (successIcon) {
                successIcon.style.display = 'inline-flex';
                successIcon.style.background = '#fef2f2';
                successIcon.style.color = '#dc2626';
                successIcon.innerHTML = '<span class="material-symbols-outlined" style="font-size: 36px;">warning</span>';
            }
            if (statusText) statusText.textContent = 'URL Google Apps Script Belum Diatur';
            if (detailText) detailText.textContent = 'Harap atur URL Web App di menu Google Sync terlebih dahulu.';
            if (btnCancel) btnCancel.style.display = 'none';
            if (btnDone) btnDone.style.display = 'inline-flex';
            _isBatchSyncRunning = false;
            return;
        }

        const totalToSync = cfgData.filter_pending_count || 0;
        if (totalToSync === 0) {
            if (spinnerAnim) spinnerAnim.style.display = 'none';
            if (successIcon) {
                successIcon.style.display = 'inline-flex';
                successIcon.style.background = '#dcfce7';
                successIcon.style.color = '#16a34a';
                successIcon.innerHTML = '<span class="material-symbols-outlined" style="font-size: 36px;">check_circle</span>';
            }
            if (statusText) statusText.textContent = 'Semua Data Sudah Tersinkronkan!';
            if (detailText) detailText.textContent = 'Tidak ada antrean paket yang belum tersinkronkan pada filter ini.';
            if (progressText) progressText.textContent = '100%';
            if (progressBar) progressBar.style.width = '100%';
            if (btnCancel) btnCancel.style.display = 'none';
            if (btnDone) btnDone.style.display = 'inline-flex';
            _isBatchSyncRunning = false;
            return;
        }

        if (statusText) statusText.textContent = `Menyinkronkan ${totalToSync} paket ke Google Drive...`;
        if (detailText) detailText.textContent = 'Memulai proses upload...';

        let processedCount = 0;
        let successCount = 0;
        let failedCount = 0;
        let lastFailedMessage = '';
        const failedIds = [];

        while (_isBatchSyncRunning && !_batchSyncCancelled) {
            const formData = new FormData();
            formData.append('batch_size', '1');
            formData.append('search', activeFilters.search);
            formData.append('operator_id', activeFilters.operator_id);
            formData.append('date_from', activeFilters.date_from);
            formData.append('date_to', activeFilters.date_to);
            if (failedIds.length > 0) {
                formData.append('exclude_ids', failedIds.join(','));
            }

            const syncRes = await fetch('api/sync_google.php?action=sync_pending', {
                method: 'POST',
                body: formData
            });
            const syncData = await parseResponseJson(syncRes);

            if (!syncData.success) {
                failedCount++;
                lastFailedMessage = syncData.message || 'Koneksi ke server gagal.';
                break;
            }

            if (!syncData.synced_items || syncData.synced_items.length === 0) {
                break;
            }

            for (const item of syncData.synced_items) {
                processedCount++;
                if (item.success) {
                    successCount++;
                } else {
                    failedCount++;
                    lastFailedMessage = item.message || 'Gagal sinkron ke Google.';
                    failedIds.push(item.id);
                }

                const percent = Math.min(100, Math.round((processedCount / totalToSync) * 100));
                if (progressText) progressText.textContent = `${percent}% (${processedCount}/${totalToSync})`;
                if (progressBar) progressBar.style.width = percent + '%';
                if (statusText) statusText.textContent = `Mengunggah (${processedCount}/${totalToSync})...`;
                if (detailText) detailText.textContent = `Resi: ${item.resi_no}`;
            }

            if (syncData.remaining_count === 0) {
                break;
            }
        }

        if (spinnerAnim) spinnerAnim.style.display = 'none';

        if (_batchSyncCancelled) {
            if (successIcon) {
                successIcon.style.display = 'inline-flex';
                successIcon.style.background = '#fef3c7';
                successIcon.style.color = '#d97706';
                successIcon.innerHTML = '<span class="material-symbols-outlined" style="font-size: 36px;">pause_circle</span>';
            }
            if (statusText) statusText.textContent = 'Sinkronisasi Dihentikan';
            if (detailText) detailText.textContent = `Dihentikan pada progres ${processedCount} dari ${totalToSync} paket.`;
        } else {
            if (failedCount === 0) {
                if (successIcon) {
                    successIcon.style.display = 'inline-flex';
                    successIcon.style.background = '#dcfce7';
                    successIcon.style.color = '#16a34a';
                    successIcon.innerHTML = '<span class="material-symbols-outlined" style="font-size: 36px;">check_circle</span>';
                }
                if (statusText) statusText.textContent = 'Sinkronisasi Selesai!';
                if (detailText) detailText.textContent = `Semua ${processedCount} paket berhasil diunggah ke Google Sheet & Drive.`;
                if (progressText) progressText.textContent = '100%';
                if (progressBar) progressBar.style.width = '100%';
            } else if (successCount > 0) {
                if (successIcon) {
                    successIcon.style.display = 'inline-flex';
                    successIcon.style.background = '#fef3c7';
                    successIcon.style.color = '#d97706';
                    successIcon.innerHTML = '<span class="material-symbols-outlined" style="font-size: 36px;">warning</span>';
                }
                if (statusText) statusText.textContent = 'Sinkronisasi Selesai Sebagian';
                if (detailText) detailText.textContent = `${successCount} berhasil, ${failedCount} gagal.`;
                if (noticeBox) {
                    noticeBox.style.display = 'block';
                    noticeBox.style.background = '#fffbeb';
                    noticeBox.style.color = '#b45309';
                    noticeBox.style.border = '1px solid #fde68a';
                    noticeBox.innerHTML = `<b>Catatan Kendala:</b> ${escapeHtml(lastFailedMessage)}`;
                }
            } else {
                if (successIcon) {
                    successIcon.style.display = 'inline-flex';
                    successIcon.style.background = '#fef2f2';
                    successIcon.style.color = '#dc2626';
                    successIcon.innerHTML = '<span class="material-symbols-outlined" style="font-size: 36px;">error</span>';
                }
                if (statusText) statusText.textContent = 'Gagal Menyinkronkan Paket';
                if (detailText) detailText.textContent = 'Terjadi kendala pada respon Google Apps Script.';
                if (noticeBox) {
                    noticeBox.style.display = 'block';
                    noticeBox.style.background = '#fef2f2';
                    noticeBox.style.color = '#b91c1c';
                    noticeBox.style.border = '1px solid #fecaca';
                    noticeBox.innerHTML = `<b>Penyebab:</b> ${escapeHtml(lastFailedMessage)}`;
                }
            }
        }

    } catch (err) {
        if (spinnerAnim) spinnerAnim.style.display = 'none';
        if (successIcon) {
            successIcon.style.display = 'inline-flex';
            successIcon.style.background = '#fef2f2';
            successIcon.style.color = '#dc2626';
            successIcon.innerHTML = '<span class="material-symbols-outlined" style="font-size: 36px;">error</span>';
        }
        if (statusText) statusText.textContent = 'Terjadi Kesalahan Koneksi';
        if (detailText) detailText.textContent = err.message || 'Silakan coba lagi.';
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
        const result = await parseResponseJson(res);
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

// ==========================================
// USER PROFILE MODAL & ACCOUNT SETTINGS
// ==========================================
async function openProfileModal() {
    const modal = document.getElementById('profileModal');
    if (!modal) return;

    modal.classList.add('active');

    // Reset alert box
    const alertBox = document.getElementById('profileAlert');
    if (alertBox) {
        alertBox.style.display = 'none';
        alertBox.textContent = '';
    }

    // Reset password inputs
    const pwdCur = document.getElementById('profileInputCurrentPassword');
    const pwdNew = document.getElementById('profileInputNewPassword');
    const pwdConf = document.getElementById('profileInputConfirmPassword');
    if (pwdCur) pwdCur.value = '';
    if (pwdNew) pwdNew.value = '';
    if (pwdConf) pwdConf.value = '';

    // Fetch fresh profile data
    try {
        const res = await fetch('api/users.php?action=get_profile&_t=' + Date.now());
        const data = await parseResponseJson(res);
        if (data.success && data.user) {
            const u = data.user;
            const inputName = document.getElementById('profileInputName');
            const inputUsername = document.getElementById('profileInputUsername');
            const heroName = document.getElementById('modalHeroName');
            const heroUsername = document.getElementById('modalHeroUsername');
            const heroCreated = document.getElementById('modalHeroCreated');

            if (inputName) inputName.value = u.name || '';
            if (inputUsername) inputUsername.value = u.username || '';
            if (heroName) heroName.textContent = u.name || '';
            if (heroUsername) heroUsername.textContent = '@' + (u.username || '');
            if (heroCreated && u.created_at) {
                const d = new Date(u.created_at);
                if (!isNaN(d.getTime())) {
                    const formatted = d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
                    heroCreated.textContent = 'Terdaftar: ' + formatted;
                }
            }
        }
    } catch (e) {
        console.warn('Gagal memuat detail profil terkini:', e);
    }
}

function closeProfileModal() {
    const modal = document.getElementById('profileModal');
    if (modal) modal.classList.remove('active');

    const alertBox = document.getElementById('profileAlert');
    if (alertBox) {
        alertBox.style.display = 'none';
        alertBox.textContent = '';
    }

    const pwdCur = document.getElementById('profileInputCurrentPassword');
    const pwdNew = document.getElementById('profileInputNewPassword');
    const pwdConf = document.getElementById('profileInputConfirmPassword');
    if (pwdCur) pwdCur.value = '';
    if (pwdNew) pwdNew.value = '';
    if (pwdConf) pwdConf.value = '';
}

function togglePasswordVisibility(inputId, btnEl) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';

    const icon = btnEl ? btnEl.querySelector('.material-symbols-outlined') : null;
    if (icon) {
        icon.textContent = isPassword ? 'visibility_off' : 'visibility';
    }
}

async function handleUpdateProfile(event) {
    if (event) event.preventDefault();

    const form = document.getElementById('profileEditForm');
    const submitBtn = document.getElementById('btnSaveProfile');
    const alertBox = document.getElementById('profileAlert');

    const nameInput = document.getElementById('profileInputName');
    const usernameInput = document.getElementById('profileInputUsername');
    const currentPwdInput = document.getElementById('profileInputCurrentPassword');
    const newPwdInput = document.getElementById('profileInputNewPassword');
    const confirmPwdInput = document.getElementById('profileInputConfirmPassword');

    const name = nameInput ? nameInput.value.trim() : '';
    const username = usernameInput ? usernameInput.value.trim() : '';
    const currentPassword = currentPwdInput ? currentPwdInput.value : '';
    const newPassword = newPwdInput ? newPwdInput.value : '';
    const confirmPassword = confirmPwdInput ? confirmPwdInput.value : '';

    function showAlertMessage(msg, type) {
        if (!alertBox) return;
        alertBox.style.display = 'block';
        if (type === 'error') {
            alertBox.style.background = '#fef2f2';
            alertBox.style.border = '1px solid #fecaca';
            alertBox.style.color = '#b91c1c';
        } else {
            alertBox.style.background = '#f0fdf4';
            alertBox.style.border = '1px solid #bbf7d0';
            alertBox.style.color = '#15803d';
        }
        alertBox.textContent = msg;
    }

    if (!name || !username) {
        showAlertMessage('Nama lengkap dan Username wajib diisi.', 'error');
        return;
    }

    // Validasi ganti password jika salah satu diisi
    if (newPassword || currentPassword || confirmPassword) {
        if (!currentPassword) {
            showAlertMessage('Masukkan Password Saat Ini untuk mengonfirmasi penggantian password.', 'error');
            if (currentPwdInput) currentPwdInput.focus();
            return;
        }
        if (newPassword.length < 5) {
            showAlertMessage('Password baru minimal harus 5 karakter.', 'error');
            if (newPwdInput) newPwdInput.focus();
            return;
        }
        if (newPassword !== confirmPassword) {
            showAlertMessage('Konfirmasi password baru tidak cocok dengan password baru.', 'error');
            if (confirmPwdInput) confirmPwdInput.focus();
            return;
        }
    }

    if (alertBox) alertBox.style.display = 'none';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<span class="material-symbols-outlined spin" style="font-size: 18px;">sync</span> <span>Menyimpan...</span>`;
    }

    try {
        const formData = new FormData();
        formData.append('name', name);
        formData.append('username', username);
        if (currentPassword) formData.append('current_password', currentPassword);
        if (newPassword) formData.append('new_password', newPassword);
        if (confirmPassword) formData.append('confirm_password', confirmPassword);

        const res = await fetch('api/users.php?action=update_profile', {
            method: 'POST',
            body: formData
        });

        const data = await parseResponseJson(res);

        if (data.success) {
            showAdminToast(data.message, 'success');
            showAlertMessage(data.message, 'success');

            // Perbarui tampilan sidebar dan hero modal secara dinamis
            const sidebarName = document.getElementById('sidebarUserName');
            const sidebarHandle = document.querySelector('.user-profile-handle');
            const heroName = document.getElementById('modalHeroName');
            const heroUsername = document.getElementById('modalHeroUsername');

            if (sidebarName) sidebarName.textContent = name;
            if (sidebarHandle) sidebarHandle.textContent = '@' + username;
            if (heroName) heroName.textContent = name;
            if (heroUsername) heroUsername.textContent = '@' + username;

            // Kosongkan kolom password setelah berhasil
            if (currentPwdInput) currentPwdInput.value = '';
            if (newPwdInput) newPwdInput.value = '';
            if (confirmPwdInput) confirmPwdInput.value = '';

            // Tutup modal setelah jeda singkat agar user melihat feedback
            setTimeout(() => {
                closeProfileModal();
            }, 1200);
        } else {
            showAdminToast(data.message, 'error');
            showAlertMessage(data.message || 'Gagal memperbarui profil.', 'error');
        }
    } catch (err) {
        console.error('Error saat update profile:', err);
        showAdminToast('Terjadi kesalahan saat menyimpan profil.', 'error');
        showAlertMessage('Terjadi kesalahan jaringan. Silakan coba lagi.', 'error');
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = `<span class="material-symbols-outlined" style="font-size: 18px;">save</span> <span>Simpan Perubahan</span>`;
        }
    }
}

// Backdrop modal click listener
document.addEventListener('click', (e) => {
    const profileModal = document.getElementById('profileModal');
    if (profileModal && e.target === profileModal) {
        closeProfileModal();
    }
});

// Escape key listener for profile modal
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const profileModal = document.getElementById('profileModal');
        if (profileModal && profileModal.classList.contains('active')) {
            closeProfileModal();
        }
        const pinModal = document.getElementById('setPinModal');
        if (pinModal && pinModal.classList.contains('active')) {
            closeSetPinModal();
        }
    }
});

// ==========================================
// PIN MODAL — SET PIN FOR OPERATOR
// ==========================================

let _pinUserId   = null;
let _pinUserName = '';
let _pinValue    = '';
const PIN_LENGTH = 4;

function openSetPinModal(userId, userName) {
    _pinUserId   = userId;
    _pinUserName = userName;
    _pinValue    = '';

    const subtitle = document.getElementById('pinModalSubtitle');
    if (subtitle) subtitle.textContent = `Atur PIN untuk: ${userName}`;

    _renderPinDots();
    _clearPinError();

    const modal = document.getElementById('setPinModal');
    if (modal) modal.classList.add('active');
}

function closeSetPinModal() {
    _pinValue = '';
    _renderPinDots();
    _clearPinError();
    const modal = document.getElementById('setPinModal');
    if (modal) modal.classList.remove('active');
}

function _renderPinDots() {
    for (let i = 0; i < PIN_LENGTH; i++) {
        const dot = document.getElementById('pinDot' + i);
        if (!dot) continue;
        dot.classList.remove('filled', 'error');
        if (i < _pinValue.length) dot.classList.add('filled');
    }
}

function _clearPinError() {
    const errEl = document.getElementById('pinErrorMsg');
    if (errEl) errEl.textContent = '';
    for (let i = 0; i < PIN_LENGTH; i++) {
        const dot = document.getElementById('pinDot' + i);
        if (dot) dot.classList.remove('error');
    }
}

function _showPinError(msg) {
    const errEl = document.getElementById('pinErrorMsg');
    if (errEl) errEl.textContent = msg;
    for (let i = 0; i < PIN_LENGTH; i++) {
        const dot = document.getElementById('pinDot' + i);
        if (dot) { dot.classList.add('error'); dot.classList.remove('filled'); }
    }
    setTimeout(() => {
        _pinValue = '';
        _clearPinError();
        _renderPinDots();
    }, 900);
}

// Wire up numpad buttons for Set PIN modal
document.addEventListener('DOMContentLoaded', () => {
    // Set PIN Modal numpad keys
    document.querySelectorAll('#setPinModal .pin-key').forEach(btn => {
        btn.addEventListener('click', () => {
            const key = btn.getAttribute('data-key');
            if (key === '⌫') {
                _pinValue = _pinValue.slice(0, -1);
            } else if (key !== '' && _pinValue.length < PIN_LENGTH) {
                _pinValue += key;
            }
            _clearPinError();
            _renderPinDots();
        });
    });

    // Edit User Modal PIN numpad keys
    document.querySelectorAll('#editUserModal .edit-pin-key').forEach(btn => {
        btn.addEventListener('click', () => {
            const key = btn.getAttribute('data-key');
            if (key === '⌫') {
                _editPinValue = _editPinValue.slice(0, -1);
            } else if (key !== '' && _editPinValue.length < EDIT_PIN_LENGTH) {
                _editPinValue += key;
            }
            const err = document.getElementById('editPinErrorMsg');
            if (err) err.textContent = '';
            _renderEditPinDots();
        });
    });
});

async function submitSetPin() {
    if (_pinValue.length < PIN_LENGTH) {
        _showPinError(`PIN harus ${PIN_LENGTH} digit!`);
        return;
    }

    const btn = document.getElementById('btnSavePinSubmit');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:17px;animation:spin 0.8s linear infinite;">progress_activity</span><span>Menyimpan...</span>'; }

    try {
        const fd = new FormData();
        fd.append('id', _pinUserId);
        fd.append('pin', _pinValue);

        const res  = await fetch('api/users.php?action=set_pin', { method: 'POST', body: fd });
        const data = await parseResponseJson(res);

        if (data.success) {
            showAdminToast(data.message, 'success');
            closeSetPinModal();
            loadUsersTable();
        } else {
            _showPinError(data.message || 'Gagal menyimpan PIN.');
        }
    } catch (e) {
        _showPinError('Kesalahan jaringan.');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:17px;">lock_reset</span><span>Simpan PIN</span>'; }
    }
}

// ==========================================
// EDIT USER MODAL (NAMA, PASSWORD / PIN)
// ==========================================

let _editUserId = null;
let _editUserRole = '';
let _editPinValue = '';
const EDIT_PIN_LENGTH = 4;

function openEditUserModal(id, name, username, role, hasPin) {
    _editUserId = id;
    _editUserRole = role;
    _editPinValue = '';

    const idInput = document.getElementById('editUserId');
    const roleInput = document.getElementById('editUserRole');
    const nameInput = document.getElementById('editUserName');
    const userInput = document.getElementById('editUserUsername');

    if (idInput) idInput.value = id;
    if (roleInput) roleInput.value = role;
    if (nameInput) nameInput.value = name;
    if (userInput) userInput.value = username;

    // Reset alert
    const alertEl = document.getElementById('editUserAlert');
    if (alertEl) { alertEl.style.display = 'none'; alertEl.className = ''; alertEl.textContent = ''; }

    // Subtitle
    const sub = document.getElementById('editUserSubtitle');
    const roleBadgeHtml = (role === 'superadmin') ? '<span class="badge-role-superadmin" style="font-size:0.68rem; padding:1px 6px;">SUPERADMIN</span>' :
                          (role === 'admin' ? '<span class="badge-role-admin" style="font-size:0.68rem; padding:1px 6px;">ADMIN</span>' :
                          '<span class="badge-role-operator" style="font-size:0.68rem; padding:1px 6px;">OPERATOR</span>');
    if (sub) sub.innerHTML = `@${escapeHtml(username)} &bull; ${roleBadgeHtml}`;

    const pinSec = document.getElementById('editUserPinSection');
    const pwdSec = document.getElementById('editUserPasswordSection');

    if (role === 'operator') {
        if (pinSec) pinSec.style.display = 'block';
        if (pwdSec) pwdSec.style.display = 'none';

        const pinBadge = document.getElementById('editUserPinStatusBadge');
        if (pinBadge) {
            pinBadge.innerHTML = (hasPin === 1)
                ? '<span style="color:#6366f1; font-weight:700; background:rgba(99,102,241,0.1); padding:2px 8px; border-radius:10px;">PIN Aktif ✓</span>'
                : '<span style="color:#94a3b8; font-weight:600; background:rgba(148,163,184,0.1); padding:2px 8px; border-radius:10px;">Belum Ada PIN</span>';
        }
        _clearEditPinInput();
    } else {
        if (pinSec) pinSec.style.display = 'none';
        if (pwdSec) pwdSec.style.display = 'block';

        const p1 = document.getElementById('editUserPassword');
        const p2 = document.getElementById('editUserConfirmPassword');
        if (p1) p1.value = '';
        if (p2) p2.value = '';
    }

    const modal = document.getElementById('editUserModal');
    if (modal) modal.classList.add('active');
}

function closeEditUserModal() {
    _editUserId = null;
    _editUserRole = '';
    _editPinValue = '';
    _clearEditPinInput();
    const modal = document.getElementById('editUserModal');
    if (modal) modal.classList.remove('active');
}

function _clearEditPinInput() {
    _editPinValue = '';
    _renderEditPinDots();
    const err = document.getElementById('editPinErrorMsg');
    if (err) err.textContent = '';
}

function _renderEditPinDots() {
    for (let i = 0; i < EDIT_PIN_LENGTH; i++) {
        const dot = document.getElementById('editPinDot' + i);
        if (!dot) continue;
        dot.classList.remove('filled', 'error');
        if (i < _editPinValue.length) dot.classList.add('filled');
    }
}

function _showEditPinError(msg) {
    const err = document.getElementById('editPinErrorMsg');
    if (err) err.textContent = msg;
    for (let i = 0; i < EDIT_PIN_LENGTH; i++) {
        const dot = document.getElementById('editPinDot' + i);
        if (dot) { dot.classList.add('error'); dot.classList.remove('filled'); }
    }
    setTimeout(() => {
        _editPinValue = '';
        _renderEditPinDots();
        if (err) err.textContent = '';
    }, 900);
}

async function handleSaveEditUser(e) {
    e.preventDefault();

    const id = document.getElementById('editUserId').value;
    const name = document.getElementById('editUserName').value.trim();
    const username = document.getElementById('editUserUsername').value.trim();
    const role = _editUserRole;

    if (!name) {
        showEditUserAlert('Nama lengkap wajib diisi.', 'error');
        return;
    }

    const fd = new FormData();
    fd.append('id', id);
    fd.append('name', name);
    fd.append('username', username);

    if (role === 'operator') {
        if (_editPinValue.length > 0) {
            if (_editPinValue.length < EDIT_PIN_LENGTH) {
                _showEditPinError(`PIN harus ${EDIT_PIN_LENGTH} digit angka!`);
                return;
            }
            fd.append('pin', _editPinValue);
        }
    } else {
        const newPwd = document.getElementById('editUserPassword').value;
        const confirmPwd = document.getElementById('editUserConfirmPassword').value;

        if (newPwd || confirmPwd) {
            if (newPwd.length < 5) {
                showEditUserAlert('Password baru minimal 5 karakter.', 'error');
                return;
            }
            if (newPwd !== confirmPwd) {
                showEditUserAlert('Konfirmasi password baru tidak cocok.', 'error');
                return;
            }
            fd.append('new_password', newPwd);
        }
    }

    const btn = document.getElementById('btnSaveEditUser');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px; animation:spin 0.8s linear infinite;">progress_activity</span><span>Menyimpan...</span>';
    }

    try {
        const res = await fetch('api/users.php?action=update_user', {
            method: 'POST',
            body: fd
        });
        const data = await parseResponseJson(res);

        if (data.success) {
            showAdminToast(data.message || 'Data pengguna berhasil diperbarui.', 'success');
            closeEditUserModal();
            loadUsersTable();
        } else {
            showEditUserAlert(data.message || 'Gagal memperbarui pengguna.', 'error');
        }
    } catch (err) {
        showEditUserAlert('Terjadi kesalahan jaringan.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;">save</span><span>Simpan Perubahan</span>';
        }
    }
}

function showEditUserAlert(msg, type) {
    const alertEl = document.getElementById('editUserAlert');
    if (!alertEl) return;
    alertEl.style.display = 'block';
    alertEl.textContent = msg;
    if (type === 'error') {
        alertEl.style.background = '#fef2f2';
        alertEl.style.color = '#dc2626';
        alertEl.style.border = '1px solid #fecaca';
    } else {
        alertEl.style.background = '#f0fdf4';
        alertEl.style.color = '#16a34a';
        alertEl.style.border = '1px solid #bbf7d0';
    }
}


