<?php
// api/sync_google.php
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function respondJson(array $data, int $code = 200): void {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

try {
    @set_time_limit(90);
    require_once __DIR__ . '/../config/auth.php';
    require_once __DIR__ . '/../config/google_sync.php';

    date_default_timezone_set('Asia/Jakarta');

    if (!isLoggedIn()) {
        respondJson(['success' => false, 'message' => 'Sesi login telah berakhir. Silakan login kembali.'], 401);
    }

    $currentUser = getCurrentUser();
    $isAdmin = isAdminOrSuperAdmin();
    $action = trim($_GET['action'] ?? $_POST['action'] ?? '');

    function buildFilterWhere(array $input): array {
        $where = ["(gdrive_url IS NULL OR gdrive_url = '')"];
        $params = [];

        $operatorId = intval($input['operator_id'] ?? 0);
        $search     = trim($input['search'] ?? '');
        $dateFrom   = trim($input['date_from'] ?? '');
        $dateTo     = trim($input['date_to'] ?? '');

        if ($operatorId > 0) {
            $where[] = "user_id = ?";
            $params[] = $operatorId;
        }
        if (!empty($search)) {
            $where[] = "(resi_no LIKE ? OR operator_name LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if (!empty($dateFrom)) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $dateTo;
        }

        return [$where, $params];
    }

    if ($action === 'get_config') {
        $config = getGoogleSyncConfig();
        $db = getDB();

        // Hitung total pending secara keseluruhan
        $stmt = $db->query("SELECT COUNT(*) as pending_count FROM packings WHERE gdrive_url IS NULL OR gdrive_url = ''");
        $pendingCount = intval($stmt->fetch()['pending_count'] ?? 0);

        // Hitung total sudah sync
        $stmtSynced = $db->query("SELECT COUNT(*) as synced_count FROM packings WHERE gdrive_url IS NOT NULL AND gdrive_url != ''");
        $syncedCount = intval($stmtSynced->fetch()['synced_count'] ?? 0);

        // Hitung juga pending berdasarkan filter aktif jika ada
        [$whereF, $paramsF] = buildFilterWhere($_GET);
        $whereFSql = "WHERE " . implode(" AND ", $whereF);
        $stmtFilter = $db->prepare("SELECT COUNT(*) as filter_pending FROM packings {$whereFSql}");
        $stmtFilter->execute($paramsF);
        $filterPendingCount = intval($stmtFilter->fetch()['filter_pending'] ?? 0);

        respondJson([
            'success'              => true,
            'config'               => $config,
            'pending_count'        => $pendingCount,
            'filter_pending_count' => $filterPendingCount,
            'synced_count'         => $syncedCount
        ]);
    }

    if ($action === 'save_config') {
        if (!$isAdmin) {
            respondJson(['success' => false, 'message' => 'Hanya admin yang dapat mengubah pengaturan.'], 403);
        }

        $gasUrl = trim($_POST['gas_webapp_url'] ?? '');
        $folderId = trim($_POST['folder_id'] ?? '');
        $autoSync = filter_var($_POST['auto_sync'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!empty($gasUrl) && !filter_var($gasUrl, FILTER_VALIDATE_URL)) {
            respondJson(['success' => false, 'message' => 'Format URL Web App Google Apps Script tidak valid.'], 400);
        }

        $saved = saveGoogleSyncConfig([
            'gas_webapp_url' => $gasUrl,
            'folder_id'      => $folderId,
            'auto_sync'      => $autoSync
        ]);

        if ($saved) {
            respondJson(['success' => true, 'message' => 'Pengaturan sinkronisasi Google berhasil disimpan!']);
        } else {
            respondJson(['success' => false, 'message' => 'Gagal menyimpan file konfigurasi.'], 500);
        }
    }

    if ($action === 'test_connection') {
        $config = getGoogleSyncConfig();
        $gasUrl = trim($_POST['gas_webapp_url'] ?? $config['gas_webapp_url'] ?? '');

        if (empty($gasUrl)) {
            respondJson(['success' => false, 'message' => 'Masukkan URL Google Apps Script terlebih dahulu.'], 400);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $gasUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
        applyCurlDnsOptions($ch);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr) {
            respondJson(['success' => false, 'message' => 'Koneksi gagal: ' . $curlErr]);
        }

        if ($code === 401 || strpos($raw, 'accounts.google.com') !== false || strpos($raw, 'Sign in - Google Accounts') !== false) {
            respondJson([
                'success' => false,
                'message' => 'Akses Ditolak (HTTP 401). Pada Google Apps Script, pengaturan "Who has access" belum diatur ke "Anyone". Silakan Deploy ulang Web App dan ubah ke "Anyone".'
            ]);
        }

        $data = json_decode($raw, true);
        if (isset($data['success']) && $data['success']) {
            respondJson(['success' => true, 'message' => 'Terhubung! Webhook Google Apps Script siap digunakan.']);
        } elseif ($code === 200) {
            respondJson(['success' => true, 'message' => 'Terhubung ke Google Web App (HTTP 200 OK).']);
        } else {
            respondJson(['success' => false, 'message' => 'Webhook merespon kode HTTP ' . $code . ': ' . substr($raw, 0, 150)]);
        }
    }

    if ($action === 'sync_single') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            respondJson(['success' => false, 'message' => 'ID packing tidak valid.'], 400);
        }

        $res = sendPackingToGoogle($id);
        respondJson($res, $res['success'] ? 200 : 400);
    }

    if ($action === 'sync_pending') {
        $db = getDB();
        $limit = max(1, min(5, intval($_POST['batch_size'] ?? 1)));

        // Terapkan filter yang aktif saat sync dijalankan!
        [$whereClauses, $params] = buildFilterWhere($_POST);
        $whereSql = "WHERE " . implode(" AND ", $whereClauses);

        $sql = "SELECT id, resi_no FROM packings {$whereSql} ORDER BY id ASC LIMIT " . intval($limit);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $pendingList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pendingList)) {
            respondJson([
                'success'         => true,
                'message'         => 'Semua data yang sesuai filter sudah tersinkronkan ke Google Sheet & Drive!',
                'remaining_count' => 0,
                'synced'          => []
            ]);
        }

        $syncedResults = [];
        foreach ($pendingList as $item) {
            $result = sendPackingToGoogle((int)$item['id']);
            $syncedResults[] = [
                'id'        => $item['id'],
                'resi_no'   => $item['resi_no'],
                'success'   => $result['success'],
                'message'   => $result['message'],
                'drive_url' => $result['drive_url'] ?? null
            ];
        }

        // Hitung sisa sesuai filter aktif
        $stmtRem = $db->prepare("SELECT COUNT(*) as rem FROM packings {$whereSql}");
        $stmtRem->execute($params);
        $remCount = intval($stmtRem->fetch()['rem'] ?? 0);

        respondJson([
            'success'         => true,
            'synced_items'    => $syncedResults,
            'remaining_count' => $remCount,
            'message'         => 'Berhasil memproses ' . count($syncedResults) . ' paket. Sisa filter: ' . $remCount
        ]);
    }

    respondJson(['success' => false, 'message' => 'Aksi tidak dikenal.'], 400);

} catch (\Throwable $e) {
    respondJson([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem di server: ' . $e->getMessage()
    ], 500);
}
