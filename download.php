<?php
// download.php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/video.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die("ID data tidak valid.");
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM packings WHERE id = ?");
$stmt->execute([$id]);
$packing = $stmt->fetch();

if (!$packing) {
    http_response_code(404);
    die("Data rekaman packing tidak ditemukan.");
}

// Pakai file hasil kompresi bila sudah tersedia
$filename = resolvePackingVideo($packing, $db);
$filePath = realpath(VIDEOS_DIR . DIRECTORY_SEPARATOR . $filename);

if (!$filePath || !is_file($filePath)) {
    http_response_code(404);
    die("File video tidak ditemukan di server: " . htmlspecialchars($filename));
}

// Lepas lock session: readfile video besar bisa lama
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
@set_time_limit(0);
while (ob_get_level() > 0) { ob_end_clean(); }

$safeResi = preg_replace('/[^A-Za-z0-9_-]/', '_', $packing['resi_no']);
$dateStr = date('Ymd_His', strtotime($packing['created_at']));

function sendFile(string $path, string $downloadName, string $mime): void {
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

// 1) File sudah mp4 → kirim langsung
if (substr(strtolower($filePath), -4) === '.mp4') {
    sendFile($filePath, "PACKING_{$safeResi}_{$dateStr}.mp4", 'video/mp4');
}

// 2) File webm → konversi ke mp4 (sinkron) jika FFmpeg tersedia & belum ada hasil konversi
$mp4Path = substr($filePath, 0, -5) . '_dl.mp4';
$disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
$canExec = function_exists('exec') && !in_array('exec', $disabled, true);

if (!is_file($mp4Path) && $canExec) {
    $isWindows = (PHP_OS_FAMILY === 'Windows');
    $ffmpegBin = realpath(__DIR__ . '/bin/' . ($isWindows ? 'ffmpeg.exe' : 'ffmpeg')) ?: 'ffmpeg';
    $cmd = sprintf(
        '"%s" -y -hide_banner -loglevel error -i %s -vcodec libx264 -crf 21 -preset fast -pix_fmt yuv420p -acodec aac -b:a 96k -movflags +faststart -f mp4 %s 2>&1',
        $ffmpegBin,
        escapeshellarg($filePath),
        escapeshellarg($mp4Path)
    );
    exec($cmd, $out, $code);
    if ($code !== 0 && is_file($mp4Path)) {
        @unlink($mp4Path); // hasil konversi gagal/parsial jangan dipakai
    }
}

if (is_file($mp4Path) && filesize($mp4Path) > 0) {
    sendFile($mp4Path, "PACKING_{$safeResi}_{$dateStr}.mp4", 'video/mp4');
}

// 3) Fallback: kirim file webm apa adanya (ekstensi disesuaikan agar bisa diputar)
sendFile($filePath, "PACKING_{$safeResi}_{$dateStr}.webm", 'video/webm');
