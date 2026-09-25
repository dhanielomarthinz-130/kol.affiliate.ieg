<?php
// api/check_resi.php
// Cek apakah nomor resi sudah pernah discan/disimpan sebelumnya
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../config/auth.php';
date_default_timezone_set('Asia/Jakarta');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['exists' => false]);
    exit;
}

$resi = trim($_GET['resi'] ?? '');
if (empty($resi)) {
    echo json_encode(['exists' => false]);
    exit;
}

$db   = getDB();
$stmt = $db->prepare("SELECT id, operator_name, created_at FROM packings WHERE resi_no = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$resi]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

// Format tanggal di PHP agar kompatibel dengan SQLite & MySQL
$scanTime = null;
if ($row && !empty($row['created_at'])) {
    try {
        $dt = new DateTime($row['created_at']);
        $scanTime = $dt->format('d/m/Y H:i');
    } catch (Exception $e) {
        $scanTime = $row['created_at'];
    }
}

echo json_encode([
    'exists'   => (bool)$row,
    'id'       => $row['id'] ?? null,
    'operator' => $row['operator_name'] ?? null,
    'time'     => $scanTime
]);

