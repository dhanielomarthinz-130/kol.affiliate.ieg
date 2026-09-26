<?php
// api/maintenance.php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../config/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? 'get_info';

// Status check can be accessed by all logged-in admin / superadmin
if ($action === 'get_status') {
    $cfg = getMaintenanceConfig();
    echo json_encode([
        'success'        => true,
        'is_maintenance' => !empty($cfg['is_maintenance']),
        'config'         => $cfg
    ]);
    exit;
}

// All other maintenance operations require Superadmin
if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak: Menu Maintenance hanya dapat diakses oleh Superadmin.']);
    exit;
}

function formatBytes(int|float $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

try {
    $db = getDB();

    if ($action === 'get_info') {
        $targetDir = realpath(__DIR__ . '/../uploads/videos');
        $videoCount = 0;
        $totalVideoSize = 0;
        $zeroByteFiles = 0;

        if ($targetDir && is_dir($targetDir)) {
            $files = @scandir($targetDir) ?: [];
            foreach ($files as $f) {
                if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
                $fullPath = $targetDir . DIRECTORY_SEPARATOR . $f;
                if (is_file($fullPath)) {
                    $videoCount++;
                    $sz = @filesize($fullPath) ?: 0;
                    $totalVideoSize += $sz;
                    if ($sz === 0) {
                        $zeroByteFiles++;
                    }
                }
            }
        }

        // Disk space info safely
        $diskDrive = substr(__DIR__, 0, 2);
        $freeDisk = 0;
        $totalDisk = 0;
        if (function_exists('disk_free_space')) {
            try {
                $freeDisk = @disk_free_space($diskDrive) ?: 0;
            } catch (Throwable $e) {}
        }
        if (function_exists('disk_total_space')) {
            try {
                $totalDisk = @disk_total_space($diskDrive) ?: 0;
            } catch (Throwable $e) {}
        }

        // Database size
        $dbSize = 0;
        if (file_exists(DB_SQLITE_FILE)) {
            $dbSize = @filesize(DB_SQLITE_FILE) ?: 0;
        }

        // Packings stats safely
        $totalPackings = 0;
        $syncedPackings = 0;
        $pendingPackings = 0;
        try {
            $stmtTotal = $db->query("SELECT COUNT(*) as c FROM packings");
            $totalPackings = intval($stmtTotal->fetch()['c'] ?? 0);

            $stmtSynced = $db->query("SELECT COUNT(*) as c FROM packings WHERE gdrive_url IS NOT NULL AND gdrive_url != ''");
            $syncedPackings = intval($stmtSynced->fetch()['c'] ?? 0);

            $pendingPackings = max(0, $totalPackings - $syncedPackings);
        } catch (Throwable $e) {
            // Keep defaults
        }

        // FFmpeg status
        $ffmpegBin = realpath(__DIR__ . '/../bin/ffmpeg.exe');
        $ffmpegInstalled = ($ffmpegBin && file_exists($ffmpegBin));

        echo json_encode([
            'success' => true,
            'system' => [
                'php_version'        => PHP_VERSION,
                'os'                 => PHP_OS,
                'server_software'    => $_SERVER['SERVER_SOFTWARE'] ?? 'PHP CLI/Apache',
                'upload_max_filesize'=> function_exists('ini_get') ? ini_get('upload_max_filesize') : 'N/A',
                'post_max_size'      => function_exists('ini_get') ? ini_get('post_max_size') : 'N/A',
                'memory_limit'       => function_exists('ini_get') ? ini_get('memory_limit') : 'N/A',
                'max_execution_time' => (function_exists('ini_get') ? ini_get('max_execution_time') : '0') . 's'
            ],
            'storage' => [
                'disk_free_formatted' => ($freeDisk > 0) ? formatBytes($freeDisk) : 'Tersedia',
                'disk_total_formatted'=> ($totalDisk > 0) ? formatBytes($totalDisk) : 'Tersedia',
                'disk_used_percent'   => ($totalDisk > 0 && $freeDisk > 0) ? round((($totalDisk - $freeDisk) / $totalDisk) * 100, 1) : 0,
                'video_count'         => $videoCount,
                'video_size_formatted'=> formatBytes($totalVideoSize),
                'zero_byte_count'     => $zeroByteFiles,
                'db_size_formatted'   => formatBytes($dbSize)
            ],
            'sync_stats' => [
                'total_packings'   => $totalPackings,
                'synced_packings'  => $syncedPackings,
                'pending_packings' => $pendingPackings
            ],
            'ffmpeg' => [
                'installed' => $ffmpegInstalled,
                'path'      => $ffmpegBin ?: 'Standard System Path'
            ],
            'maintenance_mode' => getMaintenanceConfig()
        ]);
        exit;
    }

    if ($action === 'toggle_maintenance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $currentConfig = getMaintenanceConfig();
        $newStatus = !($currentConfig['is_maintenance'] ?? false);
        $user = getCurrentUser();
        $by = $user['username'] ?? 'Daniel';
        $res = setMaintenanceMode($newStatus, $by);

        if ($res) {
            echo json_encode([
                'success' => true,
                'is_maintenance' => $newStatus,
                'message' => $newStatus 
                    ? 'Mode Maintenance berhasil DIAKTIFKAN. Hanya Superadmin Daniel yang dapat mengakses sistem.' 
                    : 'Mode Maintenance berhasil DINONAKTIFKAN. Seluruh pengguna dapat kembali login secara normal.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file konfigurasi maintenance. Pastikan folder config memiliki izin tulis.']);
        }
        exit;
    }

    if ($action === 'optimize_db' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $db->exec("VACUUM");
        $db->exec("ANALYZE");
        echo json_encode(['success' => true, 'message' => 'Database berhasil dioptimalkan (VACUUM & ANALYZE selesai)!']);
        exit;
    }

    if ($action === 'clean_temp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $targetDir = realpath(__DIR__ . '/../uploads/videos');
        $cleanedCount = 0;
        $freedBytes = 0;

        if ($targetDir && is_dir($targetDir)) {
            $files = @scandir($targetDir) ?: [];
            foreach ($files as $f) {
                if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
                $fullPath = $targetDir . DIRECTORY_SEPARATOR . $f;
                if (is_file($fullPath)) {
                    $sz = @filesize($fullPath) ?: 0;
                    if ($sz === 0 || str_ends_with(strtolower($f), '.tmp')) {
                        @unlink($fullPath);
                        $cleanedCount++;
                        $freedBytes += $sz;
                    }
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Pembersihan selesai! {$cleanedCount} file tidak valid / sampah berhasil dihapus.",
            'cleaned_count' => $cleanedCount
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aksi maintenance tidak valid.']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ]);
}
