<?php
// api/delete_packing.php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/video.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

// Admin maupun Superadmin boleh menghapus (sebelumnya superadmin tertolak 403)
if (!isAdminOrSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Hanya admin / superadmin yang dapat menghapus data.']);
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

    // Hapus semua varian file (mentah, hasil kompresi, temp)
    foreach (packingVideoVariants((string)$row['video_filename']) as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    $delStmt = $db->prepare("DELETE FROM packings WHERE id = ?");
    $delStmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Data dan rekaman video berhasil dihapus.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()]);
}
