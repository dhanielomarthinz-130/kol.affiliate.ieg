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
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses khusus Admin.']);
    exit;
}

$db = getDB();
$action = $_REQUEST['action'] ?? 'list';

if ($action === 'list') {
    $stmt = $db->query("SELECT id, username, name, role, is_active, created_at FROM users ORDER BY id ASC");
    $users = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $users]);
    exit;
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['admin', 'operator']) ? $_POST['role'] : 'operator';

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
    $stmt = $db->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $hashed, $name, $role]);

    echo json_encode(['success' => true, 'message' => "User {$name} ({$username}) berhasil ditambahkan."]);
    exit;
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = intval($_POST['id'] ?? 0);
    if ($userId === $currentUser['id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus akun Anda sendiri saat ini.']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    echo json_encode(['success' => true, 'message' => 'User berhasil dihapus.']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
