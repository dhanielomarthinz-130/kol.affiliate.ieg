<?php
// index.php
require_once __DIR__ . '/config/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
if ($user['role'] === 'admin') {
    header('Location: admin.php');
} else {
    header('Location: packing.php');
}
exit;
