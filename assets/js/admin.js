// assets/js/admin.js

let currentPage = 1;
const pageLimit = 15;

document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    loadOperatorsFilter();
    loadPackings(1);
    setupEventListeners();
});

function setupEventListeners() {
    // Search input with debounce
    let searchTimeout;
    const searchInput = document.getElementById('searchResi');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => loadPackings(1), 300);
        });
    }

    // Filter changes
    const filterOperator = document.getElementById('filterOperator');
    if (filterOperator) filterOperator.addEventListener('change', () => loadPackings(1));

    const filterDateFrom = document.getElementById('filterDateFrom');
    if (filterDateFrom) filterDateFrom.addEventListener('change', () => loadPackings(1));

    const filterDateTo = document.getElementById('filterDateTo');
    if (filterDateTo) filterDateTo.addEventListener('change', () => loadPackings(1));

    // Reset filters
    const resetBtn = document.getElementById('btnResetFilter');
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (filterOperator) filterOperator.value = '0';
            if (filterDateFrom) filterDateFrom.value = '';
            if (filterDateTo) filterDateTo.value = '';
            loadPackings(1);
        });
    }

    // Speed buttons
    document.querySelectorAll('.speed-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.speed-btn').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            const speed = parseFloat(e.target.getAttribute('data-speed'));
            const player = document.getElementById('adminVideoPlayer');
            if (player) player.playbackRate = speed;
        });
    });

    // Add user form
    const userForm = document.getElementById('addUserForm');
    if (userForm) {
        userForm.addEventListener('submit', async (e) => {
            e.preventDefault();
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
                    loadOperatorsFilter();
                    loadUsersList();
                } else {
                    showAdminToast(data.message, 'error');
                }
            } catch (err) {
                showAdminToast('Gagal menambahkan user.', 'error');
            }
        });
    }
}

async function loadStats() {
    try {
        const res = await fetch('api/stats.php');
        const data = await res.json();
        if (data.success) {
            document.getElementById('statTodayCount').textContent = data.today_count;
            document.getElementById('statTodayAvg').textContent = data.today_avg_duration + 's';
            document.getElementById('statTotalCount').textContent = data.total_count;
            document.getElementById('statStorage').textContent = data.total_storage_mb + ' MB';

            // Top operators
            const list = document.getElementById('topOperatorsList');
            if (list) {
                if (data.top_operators && data.top_operators.length > 0) {
                    list.innerHTML = data.top_operators.map((op, idx) => `
                        <div style="display:flex; justify-content:space-between; align-items:center; padding: 6px 0; border-bottom:1px solid #1f2937;">
                            <div>
                                <span style="font-weight:700; color:#60a5fa;">#${idx+1}</span>
                                <span style="margin-left:6px; font-weight:600;">${escapeHtml(op.operator_name)}</span>
                            </div>
                            <div>
                                <span class="badge" style="background:#065f46; color:#a7f3d0; padding:2px 8px; border-radius:6px; font-size:0.75rem;">
                                    ${op.pack_count} Paket
                                </span>
                            </div>
                        </div>
                    `).join('');
                } else {
                    list.innerHTML = '<div style="color:#64748b; font-size:0.85rem;">Belum ada aktivitas packing hari ini.</div>';
                }
            }
        }
    } catch (e) {
        console.warn('Failed loading stats', e);
    }
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

async function loadPackings(page = 1) {
    currentPage = page;
    const tableBody = document.getElementById('packingsTableBody');
    if (!tableBody) return;

    tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:2rem; color:#94a3b8;">Memuat data packaging...</td></tr>`;

    const search = document.getElementById('searchResi')?.value || '';
    const operatorId = document.getElementById('filterOperator')?.value || '0';
    const dateFrom = document.getElementById('filterDateFrom')?.value || '';
    const dateTo = document.getElementById('filterDateTo')?.value || '';

    const params = new URLSearchParams({
        page: page,
        limit: pageLimit,
        search: search,
        operator_id: operatorId,
        date_from: dateFrom,
        date_to: dateTo
    });

    try {
        const res = await fetch('api/get_packings.php?' + params.toString());
        const result = await res.json();

        if (result.success) {
            renderTable(result.data, result.total, page, result.total_pages);
        } else {
            tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:2rem; color:#ef4444;">Gagal: ${result.message}</td></tr>`;
        }
    } catch (err) {
        tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:2rem; color:#ef4444;">Kesalahan jaringan saat memuat data.</td></tr>`;
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
        tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:2.5rem; color:#64748b;">Tidak ada data hasil packingan yang cocok.</td></tr>`;
        if (paginationEl) paginationEl.innerHTML = '';
        return;
    }

    const startIdx = (page - 1) * pageLimit + 1;

    tableBody.innerHTML = rows.map((row, idx) => `
        <tr>
            <td style="color:#64748b; font-size:0.8rem;">${startIdx + idx}</td>
            <td>
                <span class="resi-badge">${escapeHtml(row.resi_no)}</span>
            </td>
            <td>
                <div style="font-weight:600; color:#f1f5f9;">${escapeHtml(row.operator_name)}</div>
            </td>
            <td style="font-family:'JetBrains Mono', monospace; font-size:0.85rem;">
                <span style="display:inline-flex; align-items:center; gap:4px;">
                    <span class="material-symbols-outlined" style="font-size:16px; color:#94a3b8;">timer</span>
                    <span>${row.formatted_duration}</span>
                </span>
            </td>
            <td style="font-size:0.85rem; color:#94a3b8;">
                ${row.formatted_date}
            </td>
            <td style="font-size:0.85rem; color:#94a3b8;">
                ${row.formatted_size}
            </td>
            <td>
                <div style="display:flex; gap:6px;">
                    <button class="btn btn-primary btn-sm" onclick="openAdminVideoModal('${row.video_url}', '${escapeHtml(row.resi_no)}', '${escapeHtml(row.operator_name)}', '${row.formatted_duration}', '${row.formatted_date}', ${row.id})">
                        <span class="material-symbols-outlined">play_circle</span>
                        <span>Putar</span>
                    </button>
                    <a href="download.php?id=${row.id}" class="btn btn-outline btn-sm" title="Download Video (MP4)" download>
                        <span class="material-symbols-outlined">download</span>
                        <span>MP4</span>
                    </a>
                    <button class="btn btn-danger btn-sm" onclick="deletePackingRecord(${row.id}, '${escapeHtml(row.resi_no)}')" title="Hapus Data">
                        <span class="material-symbols-outlined">delete</span>
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
            pagHtml += `<span style="font-size:0.85rem; color:#94a3b8; align-self:center; margin: 0 8px;">Hal ${page} dari ${totalPages}</span>`;
            pagHtml += `<button class="btn btn-outline btn-sm" ${page >= totalPages ? 'disabled' : ''} onclick="loadPackings(${page + 1})">Berikutnya <span class="material-symbols-outlined" style="font-size:16px;">chevron_right</span></button>`;
        }
        paginationEl.innerHTML = pagHtml;
    }
}

function openAdminVideoModal(videoUrl, resi, operator, duration, date, id) {
    const modal = document.getElementById('adminVideoModal');
    const player = document.getElementById('adminVideoPlayer');
    const title = document.getElementById('videoResiTitle');
    const meta = document.getElementById('videoMetaInfo');
    const dlBtn = document.getElementById('modalDownloadLink');

    if (modal && player) {
        player.src = videoUrl;
        player.playbackRate = 1.0;
        document.querySelectorAll('.speed-btn').forEach(b => b.classList.remove('active'));
        const normalBtn = document.querySelector('.speed-btn[data-speed="1"]');
        if (normalBtn) normalBtn.classList.add('active');

        if (title) title.textContent = 'HASIL PACKING: ' + resi;
        if (meta) meta.innerHTML = `Operator: <b>${operator}</b> | Durasi: <b>${duration}</b> | Waktu: <b>${date}</b>`;
        if (dlBtn) {
            dlBtn.href = id ? ('download.php?id=' + id) : videoUrl;
            dlBtn.innerHTML = '💾 Download Video MP4';
        }

        modal.classList.add('active');
        player.play();
    }
}

function closeAdminVideoModal() {
    const modal = document.getElementById('adminVideoModal');
    const player = document.getElementById('adminVideoPlayer');
    if (modal && player) {
        player.pause();
        player.src = '';
        modal.classList.remove('active');
    }
}

async function deletePackingRecord(id, resi) {
    if (!confirm(`Apakah Anda yakin ingin menghapus data packing dan rekaman video resi ${resi}? Tindakan ini permanen.`)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('id', id);

        const res = await fetch('api/delete_packing.php', {
            method: 'POST',
            body: formData
        });

        const data = await res.json();
        if (data.success) {
            showAdminToast(data.message, 'success');
            loadPackings(currentPage);
            loadStats();
        } else {
            showAdminToast(data.message, 'error');
        }
    } catch (e) {
        showAdminToast('Gagal menghapus rekaman.', 'error');
    }
}

// User Management Modal functions
function openUserModal() {
    const modal = document.getElementById('userModal');
    if (modal) {
        modal.classList.add('active');
        loadUsersList();
    }
}

function closeUserModal() {
    const modal = document.getElementById('userModal');
    if (modal) modal.classList.remove('active');
}

async function loadUsersList() {
    const container = document.getElementById('usersListTable');
    if (!container) return;

    try {
        const res = await fetch('api/users.php?action=list');
        const data = await res.json();
        if (data.success) {
            container.innerHTML = data.data.map(u => `
                <tr style="border-bottom:1px solid #1f2937;">
                    <td style="padding:10px 12px; font-weight:600;">${escapeHtml(u.name)}</td>
                    <td style="padding:10px 12px; font-family:'JetBrains Mono', monospace; color:#94a3b8;">${escapeHtml(u.username)}</td>
                    <td style="padding:10px 12px;">
                        <span class="user-role-tag ${u.role === 'admin' ? 'role-admin' : 'role-operator'}">${u.role}</span>
                    </td>
                    <td style="padding:10px 12px; text-align:right;">
                        <button class="btn btn-danger btn-sm" onclick="deleteUser(${u.id}, '${escapeHtml(u.name)}')">Hapus</button>
                    </td>
                </tr>
            `).join('');
        }
    } catch (e) {
        console.warn('Could not load users list', e);
    }
}

async function deleteUser(id, name) {
    if (!confirm(`Hapus akun operator ${name}?`)) return;

    const fd = new FormData();
    fd.append('id', id);

    try {
        const res = await fetch('api/users.php?action=delete', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            showAdminToast(data.message, 'success');
            loadUsersList();
            loadOperatorsFilter();
        } else {
            showAdminToast(data.message, 'error');
        }
    } catch (e) {
        showAdminToast('Gagal menghapus user.', 'error');
    }
}

function exportToCSV() {
    const search = document.getElementById('searchResi')?.value || '';
    const operatorId = document.getElementById('filterOperator')?.value || '0';
    const dateFrom = document.getElementById('filterDateFrom')?.value || '';
    const dateTo = document.getElementById('filterDateTo')?.value || '';

    const params = new URLSearchParams({
        search: search,
        operator_id: operatorId,
        date_from: dateFrom,
        date_to: dateTo,
        export: 'csv'
    });

    window.location.href = 'export.php?' + params.toString();
}

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
