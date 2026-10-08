<?php
// api/save_packing.php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
@ini_set('display_errors', '0');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/video.php';
date_default_timezone_set('Asia/Jakarta');

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function iniBytes(string $val): int {
    $val = trim($val);
    if ($val === '' || $val === '-1' || $val === '0') return PHP_INT_MAX;
    $unit = strtolower(substr($val, -1));
    $num = (float)$val;
    switch ($unit) {
        case 'g': $num *= 1024;
        case 'm': $num *= 1024;
        case 'k': $num *= 1024;
    }
    return (int)$num;
}

if (!isLoggedIn()) {
    respond(['success' => false, 'message' => 'Sesi berakhir, silakan login kembali.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Metode request tidak diizinkan.'], 405);
}

// -------------------------------------------------------
// GUARD: request melebihi post_max_size → PHP mengosongkan $_POST & $_FILES.
// Tanpa guard ini operator hanya melihat "Nomor Resi tidak boleh kosong".
// -------------------------------------------------------
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
$postMax   = iniBytes((string)ini_get('post_max_size'));
$uploadMax = iniBytes((string)ini_get('upload_max_filesize'));
if ($contentLength > 0 && empty($_POST) && empty($_FILES) && $contentLength > $postMax) {
    respond([
        'success' => false,
        'too_large' => true,
        'message' => 'Ukuran video (' . round($contentLength / 1048576, 1) . ' MB) melebihi batas server (post_max_size = ' . ini_get('post_max_size') . '). Naikkan post_max_size & upload_max_filesize di php.ini / .htaccess.'
    ], 413);
}

$currentUser = getCurrentUser();
// Lepas lock file session sedini mungkin: upload video bisa lama, dan tanpa ini
// request lain dari operator yang sama (check_resi, get_packings) ikut tertahan.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$resiNo      = trim($_POST['resi_no'] ?? '');
$startTime   = trim($_POST['start_time'] ?? '');
$endTime     = trim($_POST['end_time'] ?? '');
$duration    = intval($_POST['duration_seconds'] ?? 0);
$notes       = trim($_POST['notes'] ?? '');
$qualityMode = trim($_POST['quality_mode'] ?? 'hd');

if ($resiNo === '') {
    respond(['success' => false, 'message' => 'Nomor Resi / Invoice tidak boleh kosong.'], 400);
}

if (!isset($_FILES['video'])) {
    respond(['success' => false, 'message' => 'File video tidak terkirim ke server (FILE_MISSING).'], 400);
}

if ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    $errCode = (int)$_FILES['video']['error'];
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Ukuran video melebihi upload_max_filesize (' . ini_get('upload_max_filesize') . ') di server.',
        UPLOAD_ERR_FORM_SIZE  => 'Ukuran video melebihi batas form.',
        UPLOAD_ERR_PARTIAL    => 'Video hanya terkirim sebagian (koneksi terputus). Coba lagi.',
        UPLOAD_ERR_NO_FILE    => 'Tidak ada file video yang dikirim.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary upload di server tidak ada.',
        UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file ke disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload dihentikan oleh ekstensi PHP.',
    ];
    respond([
        'success' => false,
        'message' => ($uploadErrors[$errCode] ?? 'Gagal mengunggah file video.') . ' (Error code: ' . $errCode . ')'
    ], 400);
}

if ((int)$_FILES['video']['size'] <= 0) {
    respond(['success' => false, 'message' => 'File video kosong (0 byte). Rekaman tidak menghasilkan data — periksa kamera.'], 400);
}

// -------------------------------------------------------
// SERVER-SIDE DUPLICATE GUARD
// -------------------------------------------------------
$db = getDB();
$stmtCheck = $db->prepare("SELECT id, operator_name FROM packings WHERE resi_no = ? LIMIT 1");
$stmtCheck->execute([$resiNo]);
$existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
if ($existing) {
    respond([
        'success' => false,
        'duplicate' => true,
        'message' => "Resi {$resiNo} sudah pernah di-scan oleh operator: {$existing['operator_name']}."
    ], 409);
}

$fileTmp   = $_FILES['video']['tmp_name'];
$safeResi  = preg_replace('/[^A-Za-z0-9_-]/', '_', $resiNo);
$timestamp = date('Ymd_His');
$random    = substr(bin2hex(random_bytes(4)), 0, 6);

$targetDir = __DIR__ . '/../uploads/videos';
if (!is_dir($targetDir)) {
    @mkdir($targetDir, 0777, true);
}
$targetDir = realpath($targetDir);
if (!$targetDir || !is_writable($targetDir)) {
    respond(['success' => false, 'message' => 'Folder uploads/videos tidak ada atau tidak bisa ditulis.'], 500);
}
$targetDir .= DIRECTORY_SEPARATOR;

// STEP 1: Deteksi tipe file (finfo + fallback ke tipe yang dikirim browser)
$mime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = (string)finfo_file($finfo, $fileTmp);
    finfo_close($finfo);
}
$clientMime = strtolower((string)($_FILES['video']['type'] ?? ''));
$clientExt  = strtolower(pathinfo((string)$_FILES['video']['name'], PATHINFO_EXTENSION));
$isMp4 = str_contains($mime, 'mp4') || str_contains($mime, 'quicktime')
      || (!str_contains($mime, 'webm') && (str_contains($clientMime, 'mp4') || $clientExt === 'mp4'));
$rawExt = $isMp4 ? 'mp4' : 'webm';

// STEP 2: Pindahkan file
$finalFilename = "{$safeResi}_{$timestamp}_{$random}.{$rawExt}";
$finalFilePath = $targetDir . $finalFilename;

if (!move_uploaded_file($fileTmp, $finalFilePath)) {
    respond(['success' => false, 'message' => 'Gagal memindahkan file upload ke folder uploads/videos.'], 500);
}
$finalFileSize = filesize($finalFilePath);

// STEP 3: INSERT ke DB
$nowWib = date('Y-m-d H:i:s');
if ($startTime === '' || strtotime($startTime) === false) $startTime = date('Y-m-d H:i:s', time() - max(0, $duration));
if ($endTime === ''   || strtotime($endTime)   === false) $endTime   = $nowWib;
$userId = !empty($currentUser['id']) ? intval($currentUser['id']) : null;

try {
    $stmt = $db->prepare("INSERT INTO packings
        (resi_no, user_id, operator_name, start_time, end_time, duration_seconds, video_filename, video_filesize, notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $resiNo, $userId, $currentUser['name'],
        $startTime, $endTime, $duration,
        $finalFilename, $finalFileSize, $notes, $nowWib
    ]);
    $insertId = (int)$db->lastInsertId();
} catch (Exception $e) {
    @unlink($finalFilePath);
    respond(['success' => false, 'message' => 'Kesalahan database: ' . $e->getMessage()], 500);
}

// Kirim response sukses ke client SEBELUM proses berat (FFmpeg / Google Sync)
$responseJson = json_encode([
    'success' => true,
    'message' => "Video resi {$resiNo} berhasil disimpan!",
    'data'    => [
        'id'               => $insertId,
        'resi_no'          => $resiNo,
        'operator_name'    => $currentUser['name'],
        'duration_seconds' => $duration,
        'video_url'        => 'uploads/videos/' . $finalFilename,
        'is_mp4'           => ($rawExt === 'mp4'),
        'file_size'        => $finalFileSize,
        'created_at'       => $endTime
    ]
]);

// STEP 4: Flush response ke browser terlebih dahulu
ignore_user_abort(true);
set_time_limit(0);
@ini_set('zlib.output_compression', '0');
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
header('Connection: close');
header('Content-Length: ' . strlen($responseJson));
echo $responseJson;
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    while (ob_get_level() > 0) { ob_end_flush(); }
    flush();
}

// STEP 5: Kompresi FFmpeg di background (hasil → *_cmp.mp4, file mentah dihapus setelah sukses)
startBackgroundCompression($finalFilePath, $qualityMode);

// STEP 6: Auto Sync ke Google Sheets & Drive jika diaktifkan
try {
    require_once __DIR__ . '/../config/google_sync.php';
    $syncConfig = getGoogleSyncConfig();
    if (!empty($syncConfig['auto_sync']) && !empty($syncConfig['gas_webapp_url'])) {
        sendPackingToGoogle($insertId);
    }
} catch (Throwable $syncErr) {
    error_log("Auto sync failed for packing #{$insertId}: " . $syncErr->getMessage());
}
