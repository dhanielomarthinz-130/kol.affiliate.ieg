<?php
// api/stats.php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../config/auth.php';

date_default_timezone_set('Asia/Jakarta');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isAdminOrSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses admin atau superadmin diperlukan.']);
    exit;
}

$db = getDB();
$today = date('Y-m-d');

// Today packings
$stmtToday = $db->prepare("SELECT COUNT(*) as count, COALESCE(AVG(duration_seconds), 0) as avg_duration FROM packings WHERE DATE(created_at) = ?");
$stmtToday->execute([$today]);
$todayStats = $stmtToday->fetch();

// Filter-based conditions
$whereClauses = [];
$params = [];

$operatorId = intval($_GET['operator_id'] ?? 0);
$search     = trim($_GET['search'] ?? '');
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');

if ($operatorId > 0) {
    $whereClauses[] = "user_id = ?";
    $params[] = $operatorId;
}
if (!empty($search)) {
    $whereClauses[] = "(resi_no LIKE ? OR operator_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if (!empty($dateFrom)) {
    $whereClauses[] = "DATE(created_at) >= ?";
    $params[] = $dateFrom;
}
if (!empty($dateTo)) {
    $whereClauses[] = "DATE(created_at) <= ?";
    $params[] = $dateTo;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Total packings & storage based on filter (or all if no filter)
$stmtTotal = $db->prepare("SELECT COUNT(*) as total_count, COALESCE(AVG(duration_seconds), 0) as total_avg_duration, COALESCE(SUM(video_filesize), 0) as total_storage FROM packings {$whereSql}");
$stmtTotal->execute($params);
$totalStats = $stmtTotal->fetch();

// Operator ranking today
$stmtOperators = $db->prepare("SELECT operator_name, COUNT(*) as pack_count, ROUND(AVG(duration_seconds), 1) as avg_duration 
    FROM packings 
    WHERE DATE(created_at) = ? 
    GROUP BY operator_name 
    ORDER BY pack_count DESC LIMIT 5");
$stmtOperators->execute([$today]);
$topOperators = $stmtOperators->fetchAll();

// Last 7 days trend (database-agnostic)
$sevenDaysAgo = date('Y-m-d 00:00:00', strtotime('-7 days'));
$stmtTrend = $db->prepare("SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM packings 
    WHERE created_at >= ? 
    GROUP BY DATE(created_at) 
    ORDER BY date ASC");
$stmtTrend->execute([$sevenDaysAgo]);
$trend = $stmtTrend->fetchAll();

// Format storage
$rawBytes = floatval($totalStats['total_storage'] ?? 0);
if ($rawBytes >= 1073741824) {
    $storageFormatted = round($rawBytes / 1073741824, 2) . ' GB';
} elseif ($rawBytes >= 1048576) {
    $storageFormatted = round($rawBytes / 1048576, 2) . ' MB';
} elseif ($rawBytes >= 1024) {
    $storageFormatted = round($rawBytes / 1024, 1) . ' KB';
} else {
    $storageFormatted = $rawBytes . ' B';
}

$statsData = [
    'today_packings'     => intval($todayStats['count'] ?? 0),
    'today_count'        => intval($todayStats['count'] ?? 0),
    'today_avg_duration' => round($todayStats['avg_duration'] ?? 0),
    'total_packings'     => intval($totalStats['total_count'] ?? 0),
    'total_count'        => intval($totalStats['total_count'] ?? 0),
    'total_avg_duration' => round($totalStats['total_avg_duration'] ?? 0),
    'total_storage'      => $storageFormatted,
    'total_storage_mb'   => round($rawBytes / 1048576, 2),
    'top_operators'      => $topOperators ?: [],
    'trend'              => $trend ?: []
];

echo json_encode(array_merge([
    'success' => true,
    'stats'   => $statsData
], $statsData));

