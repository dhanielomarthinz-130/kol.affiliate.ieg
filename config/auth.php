<?php
// config/auth.php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/maintenance.php';

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

function checkMaintenanceAccess() {
    if (isMaintenanceActive()) {
        $currentUser = getCurrentUser();
        if (!canBypassMaintenance($currentUser)) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $isApi = (strpos($uri, '/api/') !== false) ||
                     (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                     (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

            if ($isApi) {
                http_response_code(503);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'maintenance' => true,
                    'message' => 'Sistem sedang dalam mode pemeliharaan (Maintenance). Akses dibatasi khusus Superadmin (Daniel).'
                ]);
                exit;
            } else {
                header('Location: maintenance_notice');
                exit;
            }
        }
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login');
        exit;
    }
    checkMaintenanceAccess();
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

function loginUser($username, $password, &$maintenanceError = null) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?) AND (is_active = 1 OR is_active IS NULL) LIMIT 1");
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch();

    if ($user) {
        $validPassword = password_verify($password, $user['password']);
        $validPin = (!empty($user['pin']) && password_verify($password, $user['pin']));

        if ($validPassword || $validPin) {
            // Jika maintenance aktif, hanya Superadmin Daniel yang diizinkan masuk
            if (isMaintenanceActive()) {
                $isSuperAdmin = (strtolower($user['role'] ?? '') === 'superadmin');
                $isDaniel = (strtolower(trim($user['username'] ?? '')) === 'daniel');

                if (!$isSuperAdmin || !$isDaniel) {
                    $maintenanceError = 'Sistem sedang dalam mode Pemeliharaan (Maintenance). Hanya Superadmin dengan akun Daniel yang dapat masuk saat ini.';
                    return false;
                }
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
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
