<?php
// index.php
require_once __DIR__ . '/config/auth.php';

if (!isLoggedIn()) {
    header('Location: login');
    exit;
}

$user = getCurrentUser();
if ($user['role'] === 'admin') {
    header('Location: admin');
} else {
    header('Location: packing');
}
exit;
