<?php
// api/stats.php
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
    echo json_encode(['success' => false, 'message' => 'Akses admin diperlukan']);
    exit;
}

$db = getDB();
$today = date('Y-m-d');

// Today packings
$stmtToday = $db->prepare("SELECT COUNT(*) as count, COALESCE(AVG(duration_seconds), 0) as avg_duration FROM packings WHERE DATE(created_at) = ?");
$stmtToday->execute([$today]);
$todayStats = $stmtToday->fetch();

// Total packings
$stmtTotal = $db->query("SELECT COUNT(*) as total_count, COALESCE(AVG(duration_seconds), 0) as total_avg_duration, COALESCE(SUM(video_filesize), 0) as total_storage FROM packings");
$totalStats = $stmtTotal->fetch();

// Operator ranking today
$stmtOperators = $db->prepare("SELECT operator_name, COUNT(*) as pack_count, ROUND(AVG(duration_seconds), 1) as avg_duration 
    FROM packings 
    WHERE DATE(created_at) = ? 
    GROUP BY operator_name 
    ORDER BY pack_count DESC LIMIT 5");
$stmtOperators->execute([$today]);
$topOperators = $stmtOperators->fetchAll();

// Last 7 days trend
$stmtTrend = $db->query("SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM packings 
    WHERE created_at >= DATE('now', '-7 days') 
    GROUP BY DATE(created_at) 
    ORDER BY date ASC");
$trend = $stmtTrend->fetchAll();

echo json_encode([
    'success' => true,
    'today_count' => intval($todayStats['count']),
    'today_avg_duration' => round($todayStats['avg_duration']),
    'total_count' => intval($totalStats['total_count']),
    'total_avg_duration' => round($totalStats['total_avg_duration']),
    'total_storage_mb' => round($totalStats['total_storage'] / (1024 * 1024), 2),
    'top_operators' => $topOperators,
    'trend' => $trend
]);
