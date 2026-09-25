<?php
// config/google_sync.php
require_once __DIR__ . '/database.php';

define('GOOGLE_SYNC_CONFIG_FILE', __DIR__ . '/google_sync_config.json');

function getGoogleSyncConfig(): array {
    $defaults = [
        'gas_webapp_url' => '',
        'folder_id'      => '',
        'auto_sync'      => false
    ];

    if (!file_exists(GOOGLE_SYNC_CONFIG_FILE)) {
        return $defaults;
    }

    $raw = @file_get_contents(GOOGLE_SYNC_CONFIG_FILE);
    if (!$raw) return $defaults;

    $json = json_decode($raw, true);
    if (!is_array($json)) return $defaults;

    return array_merge($defaults, $json);
}

function saveGoogleSyncConfig(array $newConfig): bool {
    $current = getGoogleSyncConfig();
    $updated = [
        'gas_webapp_url' => trim($newConfig['gas_webapp_url'] ?? $current['gas_webapp_url']),
        'folder_id'      => trim($newConfig['folder_id'] ?? $current['folder_id']),
        'auto_sync'      => (bool)($newConfig['auto_sync'] ?? $current['auto_sync'])
    ];

    return (bool)file_put_contents(GOOGLE_SYNC_CONFIG_FILE, json_encode($updated, JSON_PRETTY_PRINT));
}

/**
 * Kirim rekaman packing ke Google Drive dan Google Sheets via Apps Script Web App
 */
function sendPackingToGoogle(int $packingId): array {
    $config = getGoogleSyncConfig();
    $webAppUrl = trim($config['gas_webapp_url'] ?? '');

    if (empty($webAppUrl)) {
        return [
            'success' => false,
            'message' => 'URL Google Apps Script Web App belum diatur di menu Pengaturan.'
        ];
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM packings WHERE id = ? LIMIT 1");
    $stmt->execute([$packingId]);
    $packing = $stmt->fetch();

    if (!$packing) {
        return [
            'success' => false,
            'message' => 'Data packing ID ' . $packingId . ' tidak ditemukan.'
        ];
    }

    // Cari file video (prioritaskan versi compressed _cmp.mp4 jika sudah selesai dibuat FFmpeg)
    $videosDir = realpath(__DIR__ . '/../uploads/videos');
    if (!$videosDir) {
        return [
            'success' => false,
            'message' => 'Folder uploads/videos tidak ditemukan.'
        ];
    }

    $baseFilename = $packing['video_filename'];
    $videoPath = $videosDir . DIRECTORY_SEPARATOR . $baseFilename;

    // Cek apakah ada file versi compressed (_cmp.mp4)
    $pathInfo = pathinfo($baseFilename);
    $cmpFilename = $pathInfo['filename'] . '_cmp.mp4';
    $cmpPath = $videosDir . DIRECTORY_SEPARATOR . $cmpFilename;

    if (file_exists($cmpPath) && filesize($cmpPath) > 1000) {
        $videoPath = $cmpPath;
        $finalFilename = $cmpFilename;
    } else {
        $finalFilename = $baseFilename;
    }

    $videoBase64 = '';
    $mimeType = 'video/mp4';

    if (file_exists($videoPath)) {
        $fileSize = filesize($videoPath);
        // Google Apps Script limit payload 50MB, cek batas aman 40MB
        if ($fileSize > 40 * 1024 * 1024) {
            return [
                'success' => false,
                'message' => 'Ukuran video (' . round($fileSize / (1024 * 1024), 1) . ' MB) melebihi batas upload Google Apps Script (40 MB).'
            ];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $videoPath);
        finfo_close($finfo);
        if ($mime) $mimeType = $mime;

        $videoContent = file_get_contents($videoPath);
        if ($videoContent !== false) {
            $videoBase64 = base64_encode($videoContent);
        }
    }

    $payload = [
        'folder_id'        => $config['folder_id'] ?? '',
        'resi_no'          => $packing['resi_no'],
        'operator_name'    => $packing['operator_name'],
        'start_time'       => $packing['start_time'],
        'end_time'         => $packing['end_time'],
        'duration_seconds' => (int)$packing['duration_seconds'],
        'notes'            => $packing['notes'] ?? '',
        'video_filename'   => $finalFilename,
        'mime_type'        => $mimeType,
        'video_base64'     => $videoBase64
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webAppUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180); // 3 menit untuk upload video
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $rawResponse = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) {
        return [
            'success' => false,
            'message' => 'Gagal terhubung ke Google Apps Script: ' . $curlErr
        ];
    }

    $res = json_decode($rawResponse, true);
    if (!is_array($res)) {
        return [
            'success' => false,
            'message' => 'Respon Google Apps Script tidak valid (HTTP ' . $httpCode . '): ' . substr($rawResponse, 0, 200)
        ];
    }

    if (empty($res['success'])) {
        return [
            'success' => false,
            'message' => $res['message'] ?? 'Gagal dari Google Apps Script.'
        ];
    }

    $driveUrl = $res['drive_url'] ?? '';
    $nowWib = date('Y-m-d H:i:s');

    // Update database
    $upStmt = $db->prepare("UPDATE packings SET gdrive_url = ?, synced_at = ? WHERE id = ?");
    $upStmt->execute([$driveUrl, $nowWib, $packingId]);

    return [
        'success'   => true,
        'message'   => $res['message'] ?? 'Berhasil sinkron ke Google Drive & Sheets!',
        'drive_url' => $driveUrl,
        'synced_at' => $nowWib
    ];
}
