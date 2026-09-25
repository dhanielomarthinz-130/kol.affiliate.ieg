<?php
// api/users.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();
if (!isAdminOrSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses khusus Admin / Superadmin.']);
    exit;
}

$db = getDB();
$action = $_REQUEST['action'] ?? 'list';

if ($action === 'list') {
    if (isSuperAdmin()) {
        $stmt = $db->query("SELECT id, username, name, role, is_active, created_at FROM users ORDER BY id ASC");
    } else {
        $stmt = $db->query("SELECT id, username, name, role, is_active, created_at FROM users WHERE role != 'superadmin' ORDER BY id ASC");
    }
    $users = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $users]);
    exit;
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $requestedRole = trim($_POST['role'] ?? 'operator');

    $allowedRoles = ['operator', 'admin'];
    if (isSuperAdmin()) {
        $allowedRoles[] = 'superadmin';
    }

    $role = in_array($requestedRole, $allowedRoles, true) ? $requestedRole : 'operator';

    if (empty($username) || empty($password) || empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Semua kolom wajib diisi.']);
        exit;
    }

    // Check duplicate
    $check = $db->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username sudah digunakan, silakan pilih yang lain.']);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (username, password, name, role, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$username, $hashed, $name, $role]);

    echo json_encode(['success' => true, 'message' => "Pengguna {$name} ({$username}) berhasil ditambahkan."]);
    exit;
}

if ($action === 'toggle_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = intval($_POST['id'] ?? 0);

    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID pengguna tidak valid.']);
        exit;
    }

    if ($userId === intval($currentUser['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.']);
        exit;
    }

    $stmt = $db->prepare("SELECT id, username, name, role, is_active FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan.']);
        exit;
    }

    // Hanya superadmin yang boleh menonaktifkan admin lain atau superadmin
    if ($targetUser['role'] === 'superadmin' && !isSuperAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hanya Superadmin yang dapat mengubah status akun Superadmin.']);
        exit;
    }

    $newStatus = ($targetUser['is_active'] == 1) ? 0 : 1;
    $upStmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $upStmt->execute([$newStatus, $userId]);

    $statusText = ($newStatus === 1) ? 'diaktifkan' : 'dinonaktifkan (inactive)';
    echo json_encode([
        'success' => true,
        'message' => "Akun {$targetUser['name']} berhasil {$statusText}.",
        'is_active' => $newStatus
    ]);
    exit;
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = intval($_POST['id'] ?? 0);
    if ($userId === intval($currentUser['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus akun Anda sendiri.']);
        exit;
    }

    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if ($u && $u['role'] === 'superadmin' && !isSuperAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hanya Superadmin yang dapat menghapus sesama Superadmin.']);
        exit;
    }

    $del = $db->prepare("DELETE FROM users WHERE id = ?");
    $del->execute([$userId]);
    echo json_encode(['success' => true, 'message' => 'Pengguna berhasil dihapus.']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
