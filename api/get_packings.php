<?php
// api/get_packings.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';

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

if ($currentUser['role'] === 'operator') {
    // By default, operator can only see their own recent packings today
    $whereClauses[] = "user_id = ?";
    $params[] = $currentUser['id'];
} else if ($operatorId > 0) {
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

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM packings {$whereSql}");
$countStmt->execute($params);
$totalRows = $countStmt->fetch()['total'];

// Query rows
$querySql = "SELECT * FROM packings {$whereSql} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";
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
}

echo json_encode([
    'success' => true,
    'total' => $totalRows,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalRows / $limit),
    'data' => $rows
]);
