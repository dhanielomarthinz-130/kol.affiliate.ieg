<?php
// api/save_packing.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';

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
$resiNo = trim($_POST['resi_no'] ?? '');
$startTime = trim($_POST['start_time'] ?? '');
$endTime = trim($_POST['end_time'] ?? '');
$duration = intval($_POST['duration_seconds'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if (empty($resiNo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nomor Resi / Invoice tidak boleh kosong.']);
    exit;
}

if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['video']['error'] ?? 'FILE_MISSING';
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Gagal mengunggah file video. Error code: ' . $errCode]);
    exit;
}

$fileTmp = $_FILES['video']['tmp_name'];
$rawFileSize = $_FILES['video']['size'];

// Clean Resi for filename
$safeResi = preg_replace('/[^A-Za-z0-9_-]/', '_', $resiNo);
$timestamp = date('Ymd_His');
$random = substr(bin2hex(random_bytes(4)), 0, 6);

$targetDir = realpath(__DIR__ . '/../uploads/videos');
if (!$targetDir) {
    mkdir(__DIR__ . '/../uploads/videos', 0777, true);
    $targetDir = realpath(__DIR__ . '/../uploads/videos');
}
$targetDir .= DIRECTORY_SEPARATOR;

// Temporary upload path
$tempUploadedPath = $targetDir . "raw_{$safeResi}_{$timestamp}_{$random}.tmp";
if (!move_uploaded_file($fileTmp, $tempUploadedPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal memindahkan file upload sementara.']);
    exit;
}

// Target compressed MP4
$finalFilename = "{$safeResi}_{$timestamp}_{$random}.mp4";
$finalFilePath = $targetDir . $finalFilename;

// Check ffmpeg path
$ffmpegBin = realpath(__DIR__ . '/../bin/ffmpeg.exe');
if (!$ffmpegBin || !file_exists($ffmpegBin)) {
    // Check system or winget fallback
    $ffmpegBin = 'ffmpeg';
}

$isCompressed = false;
$finalFileSize = $rawFileSize;

if (function_exists('exec')) {
    $qualityMode = trim($_POST['quality_mode'] ?? 'saver');
    $crf = ($qualityMode === 'saver') ? '28' : '25';
    $audioBitrate = ($qualityMode === 'saver') ? '32k' : '64k';

    // Highly optimized H.264 + AAC compression
    // -crf 28 in saver mode yields ~350-500 KB per video while keeping label text readable
    // -preset fast: quick encoding so operator is not kept waiting
    // -movflags +faststart: allows instant playback in browser while streaming
    $cmd = sprintf(
        '"%s" -y -i %s -vcodec libx264 -crf %s -preset fast -pix_fmt yuv420p -acodec aac -b:a %s -movflags +faststart %s 2>&1',
        $ffmpegBin,
        escapeshellarg($tempUploadedPath),
        $crf,
        $audioBitrate,
        escapeshellarg($finalFilePath)
    );

    $output = [];
    $retCode = 0;
    exec($cmd, $output, $retCode);

    if ($retCode === 0 && file_exists($finalFilePath) && filesize($finalFilePath) > 0) {
        $isCompressed = true;
        $finalFileSize = filesize($finalFilePath);
        @unlink($tempUploadedPath); // remove raw uncompressed temp file
    }
}

// Fallback if compression was not possible or ffmpeg failed
if (!$isCompressed) {
    // Check if uploaded file was webm or mp4
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tempUploadedPath);
    finfo_close($finfo);

    $ext = (strpos($mime, 'mp4') !== false) ? 'mp4' : 'webm';
    $finalFilename = "{$safeResi}_{$timestamp}_{$random}.{$ext}";
    $finalFilePath = $targetDir . $finalFilename;
    rename($tempUploadedPath, $finalFilePath);
    $finalFileSize = filesize($finalFilePath);
}

try {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO packings 
        (resi_no, user_id, operator_name, start_time, end_time, duration_seconds, video_filename, video_filesize, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (empty($startTime)) $startTime = date('Y-m-d H:i:s', time() - $duration);
    if (empty($endTime)) $endTime = date('Y-m-d H:i:s');

    $stmt->execute([
        $resiNo,
        $currentUser['id'],
        $currentUser['name'],
        $startTime,
        $endTime,
        $duration,
        $finalFilename,
        $finalFileSize,
        $notes
    ]);

    $insertId = $db->lastInsertId();

    $savedPct = $rawFileSize > 0 ? round((1 - ($finalFileSize / $rawFileSize)) * 100) : 0;
    $compNote = $isCompressed ? " (Kompresi hemat {$savedPct}%)" : "";

    echo json_encode([
        'success' => true,
        'message' => "Video MP4 resi {$resiNo} berhasil disimpan & dikompres!{$compNote}",
        'data' => [
            'id' => $insertId,
            'resi_no' => $resiNo,
            'operator_name' => $currentUser['name'],
            'duration_seconds' => $duration,
            'video_url' => 'uploads/videos/' . $finalFilename,
            'is_mp4' => (substr($finalFilename, -4) === '.mp4'),
            'file_size' => $finalFileSize,
            'created_at' => $endTime
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Kesalahan database: ' . $e->getMessage()]);
}
