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

if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak: Menu Maintenance hanya dapat diakses oleh Superadmin.']);
    exit;
}

$db = getDB();
$action = $_REQUEST['action'] ?? 'get_info';

function formatBytes(int|float $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

if ($action === 'get_info') {
    $targetDir = realpath(__DIR__ . '/../uploads/videos');
    $videoCount = 0;
    $totalVideoSize = 0;
    $zeroByteFiles = 0;

    if ($targetDir && is_dir($targetDir)) {
        $files = scandir($targetDir);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
            $fullPath = $targetDir . DIRECTORY_SEPARATOR . $f;
            if (is_file($fullPath)) {
                $videoCount++;
                $sz = filesize($fullPath);
                $totalVideoSize += $sz;
                if ($sz === 0) {
                    $zeroByteFiles++;
                }
            }
        }
    }

    // Disk space info
    $diskDrive = substr(__DIR__, 0, 2);
    $freeDisk = @disk_free_space($diskDrive) ?: 0;
    $totalDisk = @disk_total_space($diskDrive) ?: 0;

    // Database size
    $dbSize = 0;
    if (file_exists(DB_SQLITE_FILE)) {
        $dbSize = filesize(DB_SQLITE_FILE);
    }

    // Packings stats
    $stmtTotal = $db->query("SELECT COUNT(*) as c FROM packings");
    $totalPackings = intval($stmtTotal->fetch()['c'] ?? 0);

    $stmtSynced = $db->query("SELECT COUNT(*) as c FROM packings WHERE gdrive_url IS NOT NULL AND gdrive_url != ''");
    $syncedPackings = intval($stmtSynced->fetch()['c'] ?? 0);

    $stmtPending = $db->query("SELECT COUNT(*) as c FROM packings WHERE gdrive_url IS NULL OR gdrive_url = ''");
    $pendingPackings = intval($stmtPending->fetch()['c'] ?? 0);

    // FFmpeg status
    $ffmpegBin = realpath(__DIR__ . '/../bin/ffmpeg.exe');
    $ffmpegInstalled = ($ffmpegBin && file_exists($ffmpegBin));

    echo json_encode([
        'success' => true,
        'system' => [
            'php_version'        => PHP_VERSION,
            'os'                 => PHP_OS,
            'server_software'    => $_SERVER['SERVER_SOFTWARE'] ?? 'PHP CLI/Apache',
            'upload_max_filesize'=> ini_get('upload_max_filesize'),
            'post_max_size'      => ini_get('post_max_size'),
            'memory_limit'       => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's'
        ],
        'storage' => [
            'disk_free_formatted' => formatBytes($freeDisk),
            'disk_total_formatted'=> formatBytes($totalDisk),
            'disk_used_percent'   => ($totalDisk > 0) ? round((($totalDisk - $freeDisk) / $totalDisk) * 100, 1) : 0,
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
    $by = $user['username'] ?? 'superadmin';
    $res = setMaintenanceMode($newStatus, $by);

    if ($res) {
        echo json_encode([
            'success' => true,
            'is_maintenance' => $newStatus,
            'message' => $newStatus 
                ? 'Mode Maintenance berhasil DIAKTIFKAN. Hanya Superadmin Daniel yang dapat mengakses sistem.' 
                : 'Mode Maintenance berhasil DINONAKTIFKAN. Sistem kembali beroperasi normal.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan status maintenance.']);
    }
    exit;
}

if ($action === 'optimize_db' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->exec("VACUUM");
        $db->exec("ANALYZE");
        echo json_encode(['success' => true, 'message' => 'Database berhasil dioptimalkan (VACUUM & ANALYZE selesai)!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal optimasi database: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'clean_temp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetDir = realpath(__DIR__ . '/../uploads/videos');
    $cleanedCount = 0;
    $freedBytes = 0;

    if ($targetDir && is_dir($targetDir)) {
        $files = scandir($targetDir);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
            $fullPath = $targetDir . DIRECTORY_SEPARATOR . $f;
            if (is_file($fullPath)) {
                $sz = filesize($fullPath);
                // Hapus jika ukuran 0 byte (corrupt / aborted) atau berakhiran .tmp
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
