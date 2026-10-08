<?php
// config/video.php
// Helper terpusat untuk file video packing (raw upload + hasil kompresi FFmpeg)

define('VIDEOS_DIR', realpath(__DIR__ . '/../uploads/videos') ?: (__DIR__ . '/../uploads/videos'));

/**
 * Nama file hasil kompresi dari sebuah nama file video.
 * contoh: RESI_20260101_120000_ab12cd.webm  ->  RESI_20260101_120000_ab12cd_cmp.mp4
 */
function compressedVideoName(string $filename): string {
    $base = pathinfo($filename, PATHINFO_FILENAME);
    if (str_ends_with($base, '_cmp')) {
        return $base . '.mp4';
    }
    return $base . '_cmp.mp4';
}

/**
 * Menentukan file video yang siap dipakai untuk sebuah row packing.
 * - Jika FFmpeg sudah selesai (file _cmp.mp4 ada & file mentah sudah dihapus)
 *   maka row di database diperbarui ke file hasil kompresi.
 * - Mengembalikan nama file yang ada di disk (atau nama di DB jika tidak ada).
 */
function resolvePackingVideo(array &$row, ?PDO $db = null): string {
    $filename = (string)($row['video_filename'] ?? '');
    if ($filename === '') return '';

    $rawPath = VIDEOS_DIR . DIRECTORY_SEPARATOR . $filename;
    $cmpName = compressedVideoName($filename);
    $cmpPath = VIDEOS_DIR . DIRECTORY_SEPARATOR . $cmpName;

    $rawExists = is_file($rawPath);
    $cmpExists = is_file($cmpPath) && filesize($cmpPath) > 1000;

    if ($cmpExists && $cmpName !== $filename && !$rawExists) {
        // Kompresi selesai: pindahkan referensi database ke file hasil kompresi
        $row['video_filename'] = $cmpName;
        $row['video_filesize'] = filesize($cmpPath);
        if ($db && !empty($row['id'])) {
            try {
                $up = $db->prepare("UPDATE packings SET video_filename = ?, video_filesize = ? WHERE id = ?");
                $up->execute([$cmpName, $row['video_filesize'], (int)$row['id']]);
            } catch (Throwable $e) {
                error_log('resolvePackingVideo update failed: ' . $e->getMessage());
            }
        }
        return $cmpName;
    }

    return $filename;
}

/**
 * Folder sementara untuk upload bertahap (chunked). Mengembalikan path folder atau null bila gagal.
 */
function chunkUploadDir(string $uploadId, bool $create = true): ?string {
    if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $uploadId)) return null;
    $base = __DIR__ . '/../uploads/tmp';
    if (!is_dir($base)) {
        @mkdir($base, 0777, true);
    }
    // Jangan biarkan potongan video bisa diakses langsung lewat web
    if (is_dir($base) && !is_file($base . '/.htaccess')) {
        @file_put_contents($base . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
    $dir = $base . '/' . $uploadId;
    if (!is_dir($dir)) {
        if (!$create) return null;
        @mkdir($dir, 0777, true);
    }
    $real = realpath($dir);
    return ($real && is_dir($real) && is_writable($real)) ? $real : null;
}

/**
 * Menggabungkan semua chunk menjadi satu file tujuan. Mengembalikan null jika sukses, atau pesan error.
 */
function assembleChunks(string $uploadId, int $totalChunks, string $destPath): ?string {
    $dir = chunkUploadDir($uploadId, false);
    if (!$dir) return 'Potongan upload tidak ditemukan di server (upload_id tidak dikenal).';

    $missing = [];
    for ($i = 0; $i < $totalChunks; $i++) {
        if (!is_file($dir . DIRECTORY_SEPARATOR . $i . '.part')) $missing[] = $i;
    }
    if ($missing) {
        return 'Potongan video belum lengkap (hilang: ' . implode(',', array_slice($missing, 0, 10)) . (count($missing) > 10 ? ',…' : '') . ').';
    }

    $out = @fopen($destPath, 'wb');
    if (!$out) return 'Gagal membuat file video tujuan (cek izin folder uploads/videos).';
    for ($i = 0; $i < $totalChunks; $i++) {
        $in = @fopen($dir . DIRECTORY_SEPARATOR . $i . '.part', 'rb');
        if (!$in) { fclose($out); @unlink($destPath); return "Gagal membaca potongan #{$i}."; }
        while (!feof($in)) {
            $buf = fread($in, 1048576);
            if ($buf === false) break;
            if (fwrite($out, $buf) === false) {
                fclose($in); fclose($out); @unlink($destPath);
                return 'Gagal menulis file video (disk penuh / kuota habis?).';
            }
        }
        fclose($in);
    }
    fclose($out);
    removeChunkDir($uploadId);
    return null;
}

function removeChunkDir(string $uploadId): void {
    $dir = chunkUploadDir($uploadId, false);
    if (!$dir) return;
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) { @unlink($f); }
    @rmdir($dir);
}

/**
 * Bersihkan folder chunk yang terbengkalai (> 24 jam). Dipanggil sesekali.
 */
function cleanupStaleChunkDirs(int $maxAgeSec = 86400): int {
    $base = realpath(__DIR__ . '/../uploads/tmp');
    if (!$base) return 0;
    $n = 0;
    foreach (glob($base . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $d) {
        if ((time() - (int)@filemtime($d)) > $maxAgeSec) {
            foreach (glob($d . DIRECTORY_SEPARATOR . '*') ?: [] as $f) { @unlink($f); }
            if (@rmdir($d)) $n++;
        }
    }
    return $n;
}

/**
 * Semua varian file yang terkait sebuah video (mentah, kompresi, temp) — untuk dihapus bersama.
 */
function packingVideoVariants(string $filename): array {
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $base = preg_replace('/_cmp$/', '', $base);
    $dir = VIDEOS_DIR . DIRECTORY_SEPARATOR;
    $list = [
        $dir . $filename,
        $dir . $base . '.webm',
        $dir . $base . '.mp4',
        $dir . $base . '_cmp.mp4',
        $dir . $base . '_cmp.mp4.tmp',
        $dir . $base . '_dl.mp4',
    ];
    return array_values(array_unique($list));
}

/**
 * Menjalankan kompresi FFmpeg di background (tidak memblokir request).
 * Setelah sukses: hasil dipindah ke *_cmp.mp4 dan file mentah dihapus.
 * Jika gagal: file mentah tetap utuh.
 */
/**
 * Apakah server ini bisa menjalankan FFmpeg di background?
 * (exec/popen tidak dinonaktifkan DAN binary ffmpeg tersedia)
 */
function canRunFfmpeg(): bool {
    static $cached = null;
    if ($cached !== null) return $cached;

    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    $canExec = function_exists('popen') && function_exists('exec')
            && !in_array('exec', $disabled, true) && !in_array('popen', $disabled, true);
    if (!$canExec) return $cached = false;

    $isWindows = (PHP_OS_FAMILY === 'Windows');
    $bundled = realpath(__DIR__ . '/../bin/' . ($isWindows ? 'ffmpeg.exe' : 'ffmpeg'));
    if ($bundled) return $cached = true;

    // Cari di PATH
    $out = [];
    @exec(($isWindows ? 'where ffmpeg' : 'command -v ffmpeg') . ' 2>' . ($isWindows ? 'NUL' : '/dev/null'), $out, $code);
    return $cached = ($code === 0 && !empty($out));
}

function startBackgroundCompression(string $rawPath, string $qualityMode = 'hd'): bool {
    if (!canRunFfmpeg() || !is_file($rawPath)) return false;

    if ($qualityMode === 'ultra') {
        $crf = '20'; $audio = '128k';
    } elseif ($qualityMode === 'saver') {
        $crf = '26'; $audio = '48k';
    } else {
        $crf = '22'; $audio = '96k';
    }

    $cmpPath = dirname($rawPath) . DIRECTORY_SEPARATOR . compressedVideoName(basename($rawPath));
    $tmpPath = $cmpPath . '.tmp';
    $isWindows = (PHP_OS_FAMILY === 'Windows');

    $ffmpegBin = realpath(__DIR__ . '/../bin/' . ($isWindows ? 'ffmpeg.exe' : 'ffmpeg'));
    if (!$ffmpegBin) $ffmpegBin = 'ffmpeg';

    try {
        if ($isWindows) {
            // Tulis batch job agar rantai perintah (ffmpeg && move && del) aman dari masalah quoting nested
            $jobDir = sys_get_temp_dir();
            $jobFile = $jobDir . DIRECTORY_SEPARATOR . 'kol_ffmpeg_' . bin2hex(random_bytes(4)) . '.bat';
            $bat  = "@echo off\r\n";
            $bat .= "\"{$ffmpegBin}\" -y -hide_banner -loglevel error -i \"{$rawPath}\" -vcodec libx264 -crf {$crf} -preset fast -pix_fmt yuv420p -acodec aac -b:a {$audio} -movflags +faststart -f mp4 \"{$tmpPath}\"\r\n";
            $bat .= "if errorlevel 1 goto fail\r\n";
            $bat .= "move /Y \"{$tmpPath}\" \"{$cmpPath}\" >nul\r\n";
            $bat .= "if errorlevel 1 goto fail\r\n";
            $bat .= "del /Q \"{$rawPath}\"\r\n";
            $bat .= "goto end\r\n";
            $bat .= ":fail\r\n";
            $bat .= "if exist \"{$tmpPath}\" del /Q \"{$tmpPath}\"\r\n";
            $bat .= ":end\r\n";
            $bat .= "(goto) 2>nul & del \"%~f0\"\r\n";
            file_put_contents($jobFile, $bat);
            $cmd = 'cmd /c start /B "" "' . $jobFile . '" > NUL 2>&1';
            pclose(popen($cmd, 'r'));
        } else {
            $cmd = sprintf(
                '(%s -y -hide_banner -loglevel error -i %s -vcodec libx264 -crf %s -preset fast -pix_fmt yuv420p -acodec aac -b:a %s -movflags +faststart -f mp4 %s && mv -f %s %s && rm -f %s || rm -f %s) > /dev/null 2>&1 &',
                escapeshellcmd($ffmpegBin), escapeshellarg($rawPath), $crf, $audio, escapeshellarg($tmpPath),
                escapeshellarg($tmpPath), escapeshellarg($cmpPath), escapeshellarg($rawPath), escapeshellarg($tmpPath)
            );
            exec($cmd);
        }
        return true;
    } catch (Throwable $e) {
        error_log('startBackgroundCompression failed: ' . $e->getMessage());
        return false;
    }
}
