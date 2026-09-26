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
        $stmt = $db->query("SELECT id, username, name, role, is_active, created_at, CASE WHEN pin IS NOT NULL AND pin != '' THEN 1 ELSE 0 END AS has_pin FROM users ORDER BY id ASC");
    } else {
        $stmt = $db->query("SELECT id, username, name, role, is_active, created_at, CASE WHEN pin IS NOT NULL AND pin != '' THEN 1 ELSE 0 END AS has_pin FROM users WHERE role != 'superadmin' ORDER BY id ASC");
    }
    $users = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $users]);
    exit;
}

if ($action === 'get_profile') {
    $stmt = $db->prepare("SELECT id, username, name, role, is_active, created_at FROM users WHERE id = ?");
    $stmt->execute([$currentUser['id']]);
    $u = $stmt->fetch();
    if (!$u) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan.']);
        exit;
    }
    echo json_encode(['success' => true, 'user' => $u]);
    exit;
}

if ($action === 'update_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nama lengkap dan Username wajib diisi.']);
        exit;
    }

    // Periksa duplikasi username untuk pengguna lain
    $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check->execute([$username, $currentUser['id']]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Username '{$username}' sudah digunakan oleh akun lain."]);
        exit;
    }

    // Ambil user saat ini dari DB
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$currentUser['id']]);
    $dbUser = $stmt->fetch();

    if (!$dbUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Akun tidak ditemukan di database.']);
        exit;
    }

    $passwordChanged = false;
    // Jika ada kolom password diisi, lakukan proses validasi dan ganti password
    if (!empty($newPassword) || !empty($currentPassword) || !empty($confirmPassword)) {
        if (empty($currentPassword)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Silakan masukkan Password Saat Ini untuk verifikasi perubahan password.']);
            exit;
        }

        if (!password_verify($currentPassword, $dbUser['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password saat ini yang Anda masukkan salah.']);
            exit;
        }

        if (strlen($newPassword) < 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password baru minimal harus 5 karakter.']);
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Konfirmasi password baru tidak cocok.']);
            exit;
        }

        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        $up = $db->prepare("UPDATE users SET name = ?, username = ?, password = ? WHERE id = ?");
        $up->execute([$name, $username, $hashed, $currentUser['id']]);
        $passwordChanged = true;
    } else {
        $up = $db->prepare("UPDATE users SET name = ?, username = ? WHERE id = ?");
        $up->execute([$name, $username, $currentUser['id']]);
    }

    // Sinkronkan session pengguna saat ini
    $_SESSION['name'] = $name;
    $_SESSION['username'] = $username;

    echo json_encode([
        'success' => true,
        'message' => $passwordChanged ? 'Profil dan password Anda berhasil diperbarui!' : 'Profil berhasil diperbarui!',
        'user' => [
            'id' => $currentUser['id'],
            'name' => $name,
            'username' => $username,
            'role' => $dbUser['role'],
            'created_at' => $dbUser['created_at']
        ]
    ]);
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

// ── Update User (Admin / Superadmin) ──────────────────────────────────
if ($action === 'update_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId      = intval($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $username    = trim($_POST['username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $pin         = trim($_POST['pin'] ?? '');

    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID pengguna tidak valid.']);
        exit;
    }

    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nama lengkap wajib diisi.']);
        exit;
    }

    // Ambil data target user
    $stmt = $db->prepare("SELECT id, username, name, role, is_active FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if (!$target) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan.']);
        exit;
    }

    // Non-superadmin tidak boleh mengubah akun superadmin
    if ($target['role'] === 'superadmin' && !isSuperAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hanya Superadmin yang dapat mengubah akun Superadmin.']);
        exit;
    }

    $updates = ['name = ?'];
    $params  = [$name];

    // Cek username jika diubah
    if (!empty($username) && $username !== $target['username']) {
        $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$username, $userId]);
        if ($check->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Username '{$username}' sudah digunakan oleh akun lain."]);
            exit;
        }
        $updates[] = 'username = ?';
        $params[]  = $username;
    }

    // Update password jika diisi
    if (!empty($newPassword)) {
        if (strlen($newPassword) < 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password baru minimal 5 karakter.']);
            exit;
        }
        $updates[] = 'password = ?';
        $params[]  = password_hash($newPassword, PASSWORD_BCRYPT);
    }

    // Update PIN jika diisi (khusus operator)
    if (!empty($pin)) {
        if (!preg_match('/^\d{4,6}$/', $pin)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'PIN harus berupa 4–6 digit angka.']);
            exit;
        }
        $updates[] = 'pin = ?';
        $params[]  = password_hash($pin, PASSWORD_BCRYPT);
    }

    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $up = $db->prepare($sql);
    $up->execute($params);

    echo json_encode([
        'success' => true,
        'message' => "Data pengguna '{$name}' berhasil diperbarui."
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

// ── Set PIN for operator (Admin/Superadmin only) ─────────────────────
if ($action === 'set_pin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = intval($_POST['id'] ?? 0);
    $pin    = trim($_POST['pin'] ?? '');

    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID pengguna tidak valid.']);
        exit;
    }

    // Only digits, 4–6 length
    if (!preg_match('/^\d{4,6}$/', $pin)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'PIN harus berupa 4–6 digit angka.']);
        exit;
    }

    // Fetch target user
    $stmt = $db->prepare("SELECT id, name, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if (!$target) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan.']);
        exit;
    }

    // Only allow setting PIN for operators
    if ($target['role'] !== 'operator') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'PIN hanya dapat diatur untuk role Operator.']);
        exit;
    }

    // Cannot set PIN on yourself if you are an operator (admin/superadmin can set for others)
    $hashed = password_hash($pin, PASSWORD_BCRYPT);
    $up = $db->prepare("UPDATE users SET pin = ? WHERE id = ?");
    $up->execute([$hashed, $userId]);

    echo json_encode([
        'success' => true,
        'message' => "PIN untuk {$target['name']} berhasil diatur."
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
