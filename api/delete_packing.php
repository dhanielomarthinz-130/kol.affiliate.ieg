<?php
// api/delete_packing.php
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
    echo json_encode(['success' => false, 'message' => 'Hanya admin yang dapat menghapus data.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID data tidak valid.']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT video_filename FROM packings WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan.']);
        exit;
    }

    $filePath = __DIR__ . '/../uploads/videos/' . $row['video_filename'];
    if (file_exists($filePath)) {
        @unlink($filePath);
    }

    $delStmt = $db->prepare("DELETE FROM packings WHERE id = ?");
    $delStmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Data dan rekaman video berhasil dihapus.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()]);
}
