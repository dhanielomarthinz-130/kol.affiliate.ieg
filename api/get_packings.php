<?php
// api/get_packings.php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../config/auth.php';
date_default_timezone_set('Asia/Jakarta');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();
$db = getDB();

$search = trim($_GET['search'] ?? '');
$operatorId = intval($_GET['operator_id'] ?? 0);
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

// If operator, they can see today's packings or their own packings
$whereClauses = [];
$params = [];

// Filter by operator only if explicitly specified
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

// Aggregates based on active filter
$aggStmt = $db->prepare("SELECT 
    COUNT(*) as total_filter, 
    COALESCE(AVG(duration_seconds), 0) as avg_duration, 
    COALESCE(SUM(video_filesize), 0) as total_storage 
    FROM packings {$whereSql}");
$aggStmt->execute($params);
$agg = $aggStmt->fetch();

$totalRows = intval($agg['total_filter'] ?? 0);
$avgDur = round($agg['avg_duration'] ?? 0);
$storageBytes = floatval($agg['total_storage'] ?? 0);

if ($storageBytes >= 1073741824) {
    $storageFormatted = round($storageBytes / 1073741824, 2) . ' GB';
} elseif ($storageBytes >= 1048576) {
    $storageFormatted = round($storageBytes / 1048576, 2) . ' MB';
} elseif ($storageBytes >= 1024) {
    $storageFormatted = round($storageBytes / 1024, 1) . ' KB';
} else {
    $storageFormatted = $storageBytes . ' B';
}

$formattedAvg = ($avgDur > 60) ? (floor($avgDur / 60) . 'm ' . ($avgDur % 60) . 's') : ($avgDur . 's');

// Sorting column and direction
$sortBy = trim($_GET['sort_by'] ?? 'id');
$sortDir = (strtoupper(trim($_GET['sort_dir'] ?? 'DESC')) === 'ASC') ? 'ASC' : 'DESC';

$allowedSortCols = [
    'id'               => 'id',
    'resi_no'          => 'resi_no',
    'operator_name'    => 'operator_name',
    'duration_seconds' => 'duration_seconds',
    'created_at'       => 'created_at',
    'video_filesize'   => 'video_filesize',
    'gdrive_url'       => 'gdrive_url'
];
$orderCol = $allowedSortCols[$sortBy] ?? 'id';

// Query rows
$querySql = "SELECT * FROM packings {$whereSql} ORDER BY {$orderCol} {$sortDir} LIMIT {$limit} OFFSET {$offset}";
$stmt = $db->prepare($querySql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Add video URL and check if file exists
foreach ($rows as &$row) {
    $row['video_url'] = 'uploads/videos/' . $row['video_filename'];
    $row['video_exists'] = file_exists(__DIR__ . '/../' . $row['video_url']);
    $row['formatted_duration'] = sprintf('%02d:%02d', floor($row['duration_seconds'] / 60), $row['duration_seconds'] % 60);
    $row['formatted_size'] = round($row['video_filesize'] / (1024 * 1024), 2) . ' MB';
    $row['formatted_date'] = date('d/m/Y H:i:s', strtotime($row['created_at']));
    $row['is_synced'] = !empty($row['gdrive_url']);
    $row['gdrive_url'] = $row['gdrive_url'] ?? '';
    $row['synced_at_formatted'] = !empty($row['synced_at']) ? date('d/m/Y H:i', strtotime($row['synced_at'])) : '-';
}

// Calculate total packings today
$todayDate = date('Y-m-d');
$stmtToday = $db->prepare("SELECT COUNT(*) as cnt FROM packings WHERE DATE(created_at) = ?");
$stmtToday->execute([$todayDate]);
$todayTotal = intval($stmtToday->fetch()['cnt'] ?? 0);

echo json_encode([
    'success'     => true,
    'total'       => $totalRows,
    'today_total' => $todayTotal,
    'page'        => $page,
    'limit'       => $limit,
    'total_pages' => ceil($totalRows / $limit),
    'data'        => $rows,
    'stats'       => [
        'total'          => $totalRows,
        'avg_duration'   => $avgDur,
        'formatted_avg'  => $formattedAvg,
        'storage'        => $storageFormatted,
        'today_packings' => $todayTotal,
        'today_count'    => $todayTotal
    ]
]);
