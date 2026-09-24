<?php
// export.php
require_once __DIR__ . '/config/auth.php';
requireRole('admin');

$db = getDB();

$search = trim($_GET['search'] ?? '');
$operatorId = intval($_GET['operator_id'] ?? 0);
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$whereClauses = [];
$params = [];

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

$querySql = "SELECT * FROM packings {$whereSql} ORDER BY id DESC";
$stmt = $db->prepare($querySql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filename = "rekap_packing_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// UTF-8 BOM for Microsoft Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header columns
fputcsv($output, [
    'No',
    'No Resi / Invoice',
    'Nama Operator',
    'Waktu Mulai',
    'Waktu Selesai',
    'Durasi (Detik)',
    'Durasi Format',
    'Ukuran File Video (MB)',
    'Nama File Video',
    'Tanggal Catat'
]);

$no = 1;
foreach ($rows as $row) {
    $durMin = sprintf('%02d:%02d', floor($row['duration_seconds'] / 60), $row['duration_seconds'] % 60);
    $sizeMb = round($row['video_filesize'] / (1024 * 1024), 2);
    
    fputcsv($output, [
        $no++,
        $row['resi_no'],
        $row['operator_name'],
        $row['start_time'],
        $row['end_time'],
        $row['duration_seconds'],
        $durMin,
        $sizeMb,
        $row['video_filename'],
        $row['created_at']
    ]);
}

fclose($output);
exit;
