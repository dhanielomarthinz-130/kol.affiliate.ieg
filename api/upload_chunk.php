<?php
// api/upload_chunk.php
// Menerima potongan (chunk) video ≤ beberapa MB per request, agar video besar tetap bisa
// diupload di hosting dengan batas post_max_size / upload_max_filesize kecil (mis. InfinityFree 10 MB).
// Potongan disimpan di uploads/tmp/<upload_id>/<index>.part dan dirakit oleh api/save_packing.php.
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
@ini_set('display_errors', '0');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/video.php';

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if (!isLoggedIn()) {
    respond(['success' => false, 'message' => 'Sesi berakhir, silakan login kembali.'], 401);
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Metode request tidak diizinkan.'], 405);
}

// Request melebihi post_max_size → $_POST kosong
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    respond([
        'success' => false,
        'too_large' => true,
        'message' => 'Potongan video melebihi batas server (post_max_size = ' . ini_get('post_max_size') . '). Perkecil ukuran chunk.'
    ], 413);
}

$uploadId   = trim($_POST['upload_id'] ?? '');
$chunkIndex = intval($_POST['chunk_index'] ?? -1);
$totalChunks = intval($_POST['total_chunks'] ?? 0);

if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $uploadId)) {
    respond(['success' => false, 'message' => 'upload_id tidak valid.'], 400);
}
if ($chunkIndex < 0 || $totalChunks < 1 || $chunkIndex >= $totalChunks || $totalChunks > 5000) {
    respond(['success' => false, 'message' => 'Indeks chunk tidak valid.'], 400);
}
if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
    $err = (int)($_FILES['chunk']['error'] ?? -1);
    $msg = ($err === UPLOAD_ERR_INI_SIZE) ? 'Chunk melebihi upload_max_filesize (' . ini_get('upload_max_filesize') . ').' : 'Chunk tidak terkirim (error code ' . $err . ').';
    respond(['success' => false, 'message' => $msg], 400);
}
if ((int)$_FILES['chunk']['size'] <= 0) {
    respond(['success' => false, 'message' => 'Chunk kosong (0 byte).'], 400);
}

$dir = chunkUploadDir($uploadId);
if (!$dir) {
    respond(['success' => false, 'message' => 'Folder uploads/tmp tidak bisa dibuat / ditulis.'], 500);
}

$target = $dir . DIRECTORY_SEPARATOR . $chunkIndex . '.part';
if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $target)) {
    respond(['success' => false, 'message' => 'Gagal menyimpan chunk ke disk (cek kuota / izin folder uploads/tmp).'], 500);
}

// Hitung chunk yang sudah diterima
$received = count(glob($dir . DIRECTORY_SEPARATOR . '*.part') ?: []);

respond([
    'success'      => true,
    'upload_id'    => $uploadId,
    'chunk_index'  => $chunkIndex,
    'received'     => $received,
    'total_chunks' => $totalChunks,
    'size'         => filesize($target)
]);
