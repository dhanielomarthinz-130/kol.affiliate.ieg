<?php
// config/auth.php
require_once __DIR__ . '/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'name' => $_SESSION['name'] ?? '',
        'role' => $_SESSION['role'] ?? ''
    ];
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login');
        exit;
    }
}

function isSuperAdmin() {
    return isLoggedIn() && (($_SESSION['role'] ?? '') === 'superadmin');
}

function isAdminOrSuperAdmin() {
    return isLoggedIn() && in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true);
}

function requireRole($role) {
    requireLogin();
    $currentRole = $_SESSION['role'] ?? '';

    // superadmin memiliki akses penuh ke semua halaman
    if ($currentRole === 'superadmin') {
        return;
    }

    // admin memiliki akses ke halaman role admin
    if ($role === 'admin' && $currentRole === 'admin') {
        return;
    }

    if ($currentRole !== $role) {
        if ($currentRole === 'operator') {
            header('Location: packing');
            exit;
        } else {
            header('Location: admin');
            exit;
        }
    }
}

function loginUser($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?) AND (is_active = 1 OR is_active IS NULL) LIMIT 1");
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        return true;
    }
    return false;
}

function logoutUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
