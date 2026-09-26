<?php
// api/db_manager.php — Database Table Viewer & Delete (Superadmin only)
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');
require_once __DIR__ . '/../config/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak: hanya Superadmin.']);
    exit;
}

$db     = getDB();
$action = $_REQUEST['action'] ?? 'list_tables';

// ── Whitelist of allowed tables ──
$ALLOWED_TABLES = ['packings', 'users', 'sqlite_sequence'];

// ── List all tables ──────────────────────────────────────────
if ($action === 'list_tables') {
    $meta = [
        'packings' => [
            'label'      => 'packings',
            'desc'       => 'Data rekaman video sesi packing, nomor resi, durasi, dan file Google Drive.',
            'icon'       => 'inventory_2',
            'badge'      => 'Data Utama',
            'can_clear'  => true,
            'can_delete' => true,
        ],
        'users' => [
            'label'      => 'users',
            'desc'       => 'Data akun operator dan superadmin yang memiliki hak akses login.',
            'icon'       => 'group',
            'badge'      => 'Master User',
            'can_clear'  => false,
            'can_delete' => true,
        ],
        'sqlite_sequence' => [
            'label'      => 'sqlite_sequence',
            'desc'       => 'Tabel sistem internal SQLite untuk counter auto-increment primary key ID.',
            'icon'       => 'tag',
            'badge'      => 'Sistem Internal',
            'can_clear'  => false,
            'can_delete' => false,
        ]
    ];

    $tables = [];
    foreach ($ALLOWED_TABLES as $t) {
        $cnt = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $tables[] = [
            'name'       => $t,
            'count'      => (int)$cnt,
            'label'      => $meta[$t]['label'] ?? $t,
            'desc'       => $meta[$t]['desc'] ?? 'Tabel database sistem',
            'icon'       => $meta[$t]['icon'] ?? 'table_chart',
            'badge'      => $meta[$t]['badge'] ?? 'Tabel',
            'can_clear'  => $meta[$t]['can_clear'] ?? false,
            'can_delete' => $meta[$t]['can_delete'] ?? true,
        ];
    }
    echo json_encode(['success' => true, 'tables' => $tables]);
    exit;
}

// ── Get table data ────────────────────────────────────────────
if ($action === 'get_table') {
    $table = $_GET['table'] ?? '';
    if (!in_array($table, $ALLOWED_TABLES, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tabel tidak diizinkan.']);
        exit;
    }

    // Get columns
    $colsRaw = $db->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC);
    $columns = array_column($colsRaw, 'name');

    // Get all rows (max 1000)
    $rows = $db->query("SELECT * FROM `$table` ORDER BY rowid DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
    $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();

    echo json_encode([
        'success'  => true,
        'table'    => $table,
        'columns'  => $columns,
        'rows'     => $rows,
        'total'    => (int)$count
    ]);
    exit;
}

// ── Delete single row ─────────────────────────────────────────
if ($action === 'delete_row' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $table = $_POST['table'] ?? '';
    $id    = $_POST['id'] ?? null;

    if (!in_array($table, $ALLOWED_TABLES, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tabel tidak diizinkan.']);
        exit;
    }
    if ($id === null || $id === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID baris tidak diberikan.']);
        exit;
    }

    // Protect: don't allow deleting the current user
    if ($table === 'users') {
        $me = getCurrentUser();
        if ((int)$id === (int)($me['id'] ?? 0)) {
            echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus akun yang sedang login!']);
            exit;
        }
    }

    try {
        $stmt = $db->prepare("DELETE FROM `$table` WHERE id = ?");
        $stmt->execute([(int)$id]);
        $affected = $stmt->rowCount();
        echo json_encode(['success' => true, 'affected' => $affected, 'message' => "Baris ID $id berhasil dihapus dari tabel $table."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()]);
    }
    exit;
}

// ── Delete ALL rows from table ────────────────────────────────
if ($action === 'delete_all_rows' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $table = $_POST['table'] ?? '';

    if (!in_array($table, $ALLOWED_TABLES, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tabel tidak diizinkan.']);
        exit;
    }

    // Protect users table — don't allow wiping all users
    if ($table === 'users') {
        echo json_encode(['success' => false, 'message' => 'Tabel "users" tidak boleh dikosongkan sepenuhnya demi keamanan!']);
        exit;
    }

    try {
        $db->exec("DELETE FROM `$table`");
        // Reset auto-increment
        $db->exec("DELETE FROM sqlite_sequence WHERE name = '$table'");
        echo json_encode(['success' => true, 'message' => "Semua data di tabel '$table' berhasil dihapus."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal hapus semua: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
