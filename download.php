<?php
// download.php
require_once __DIR__ . '/config/auth.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("ID data tidak valid.");
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM packings WHERE id = ?");
$stmt->execute([$id]);
$packing = $stmt->fetch();

if (!$packing) {
    die("Data rekaman packing tidak ditemukan.");
}

$filename = $packing['video_filename'];
$filePath = realpath(__DIR__ . '/uploads/videos/' . $filename);

if (!$filePath || !file_exists($filePath)) {
    die("File video tidak ditemukan di server: " . htmlspecialchars($filename));
}

$safeResi = preg_replace('/[^A-Za-z0-9_-]/', '_', $packing['resi_no']);
$dateStr = date('Ymd_His', strtotime($packing['created_at']));
$downloadName = "PACKING_{$safeResi}_{$dateStr}.mp4";

// If file is already mp4, serve it directly
if (substr(strtolower($filePath), -4) === '.mp4') {
    header('Content-Description: File Transfer');
    header('Content-Type: video/mp4');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

// If file is webm, convert to mp4 on the fly if ffmpeg is available
$mp4Path = substr($filePath, 0, -5) . '.mp4';
$ffmpegBin = realpath(__DIR__ . '/bin/ffmpeg.exe') ?: 'ffmpeg';

if (!file_exists($mp4Path) && function_exists('exec')) {
    $cmd = sprintf(
        '"%s" -y -i %s -vcodec libx264 -crf 26 -preset fast -pix_fmt yuv420p -acodec aac -b:a 64k -movflags +faststart %s 2>&1',
        $ffmpegBin,
        escapeshellarg($filePath),
        escapeshellarg($mp4Path)
    );
    exec($cmd);
}

if (file_exists($mp4Path) && filesize($mp4Path) > 0) {
    header('Content-Description: File Transfer');
    header('Content-Type: video/mp4');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($mp4Path));
    readfile($mp4Path);
    exit;
}

// Fallback to serving existing file
header('Content-Description: File Transfer');
header('Content-Type: video/webm');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
