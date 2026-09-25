<?php
// api/save_packing.php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../config/auth.php';
date_default_timezone_set('Asia/Jakarta');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi berakhir, silakan login kembali.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

$currentUser = getCurrentUser();
$resiNo      = trim($_POST['resi_no'] ?? '');
$startTime   = trim($_POST['start_time'] ?? '');
$endTime     = trim($_POST['end_time'] ?? '');
$duration    = intval($_POST['duration_seconds'] ?? 0);
$notes       = trim($_POST['notes'] ?? '');

if (empty($resiNo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nomor Resi / Invoice tidak boleh kosong.']);
    exit;
}

// -------------------------------------------------------
// SERVER-SIDE DUPLICATE GUARD: Cek resi sudah pernah ada
// -------------------------------------------------------
$dbCheck = getDB();
$stmtCheck = $dbCheck->prepare("SELECT id, operator_name FROM packings WHERE resi_no = ? LIMIT 1");
$stmtCheck->execute([$resiNo]);
$existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
if ($existing) {
    http_response_code(409); // Conflict
    echo json_encode([
        'success' => false,
        'duplicate' => true,
        'message' => "Resi {$resiNo} sudah pernah di-scan oleh operator: {$existing['operator_name']}."
    ]);
    exit;
}

if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['video']['error'] ?? 'FILE_MISSING';
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Gagal mengunggah file video. Error code: ' . $errCode]);
    exit;
}

$fileTmp     = $_FILES['video']['tmp_name'];
$rawFileSize = $_FILES['video']['size'];

$safeResi  = preg_replace('/[^A-Za-z0-9_-]/', '_', $resiNo);
$timestamp = date('Ymd_His');
$random    = substr(bin2hex(random_bytes(4)), 0, 6);

$targetDir = realpath(__DIR__ . '/../uploads/videos');
if (!$targetDir) {
    mkdir(__DIR__ . '/../uploads/videos', 0777, true);
    $targetDir = realpath(__DIR__ . '/../uploads/videos');
}
$targetDir .= DIRECTORY_SEPARATOR;

// STEP 1: Deteksi mime type
$finfo  = finfo_open(FILEINFO_MIME_TYPE);
$mime   = finfo_file($finfo, $fileTmp);
finfo_close($finfo);
$rawExt = (str_contains($mime, 'mp4')) ? 'mp4' : 'webm';

// STEP 2: Pindah file langsung tanpa menunggu FFmpeg
$finalFilename = "{$safeResi}_{$timestamp}_{$random}.{$rawExt}";
$finalFilePath = $targetDir . $finalFilename;

if (!move_uploaded_file($fileTmp, $finalFilePath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal memindahkan file upload.']);
    exit;
}
$finalFileSize = filesize($finalFilePath);

// STEP 3: INSERT ke DB segera
try {
    $db     = getDB();
    $nowWib = date('Y-m-d H:i:s');
    if (empty($startTime)) $startTime = date('Y-m-d H:i:s', time() - $duration);
    if (empty($endTime))   $endTime   = $nowWib;
    $userId = !empty($currentUser['id']) ? intval($currentUser['id']) : null;

    $stmt = $db->prepare("INSERT INTO packings 
        (resi_no, user_id, operator_name, start_time, end_time, duration_seconds, video_filename, video_filesize, notes, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $resiNo, $userId, $currentUser['name'],
        $startTime, $endTime, $duration,
        $finalFilename, $finalFileSize, $notes, $nowWib
    ]);

    $insertId = $db->lastInsertId();

    // Kirim response sukses ke client SEBELUM FFmpeg
    echo json_encode([
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

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Kesalahan database: ' . $e->getMessage()]);
    exit;
}

// STEP 4: Flush response ke browser terlebih dahulu
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ignore_user_abort(true);
    if (ob_get_level() > 0) { ob_end_flush(); }
    flush();
}

// STEP 5: FFmpeg async non-blocking di background (Windows)
$canExec = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))));
$ffmpegBin = realpath(__DIR__ . '/../bin/ffmpeg.exe');
if (!$ffmpegBin || !file_exists($ffmpegBin)) { $ffmpegBin = 'ffmpeg'; }

if ($canExec) {
    $qualityMode  = trim($_POST['quality_mode'] ?? 'saver');
    $crf          = ($qualityMode === 'saver') ? '28' : '25';
    $audioBitrate = ($qualityMode === 'saver') ? '32k' : '64k';
    $cmpFilePath  = $targetDir . "{$safeResi}_{$timestamp}_{$random}_cmp.mp4";

    $cmd = sprintf(
        'cmd /c start /B "" "%s" -y -i "%s" -vcodec libx264 -crf %s -preset fast -pix_fmt yuv420p -acodec aac -b:a %s -movflags +faststart "%s" > NUL 2>&1',
        $ffmpegBin, $finalFilePath, $crf, $audioBitrate, $cmpFilePath
    );
    pclose(popen($cmd, 'r'));
}
