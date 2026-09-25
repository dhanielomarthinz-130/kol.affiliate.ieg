<?php
// api/sync_google.php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/google_sync.php';

date_default_timezone_set('Asia/Jakarta');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
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

    echo json_encode([
        'success'              => true,
        'config'               => $config,
        'pending_count'        => $pendingCount,
        'filter_pending_count' => $filterPendingCount,
        'synced_count'         => $syncedCount
    ]);
    exit;
}

if ($action === 'save_config') {
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hanya admin yang dapat mengubah pengaturan.']);
        exit;
    }

    $gasUrl = trim($_POST['gas_webapp_url'] ?? '');
    $folderId = trim($_POST['folder_id'] ?? '');
    $autoSync = filter_var($_POST['auto_sync'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (!empty($gasUrl) && !filter_var($gasUrl, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Format URL Web App Google Apps Script tidak valid.']);
        exit;
    }

    $saved = saveGoogleSyncConfig([
        'gas_webapp_url' => $gasUrl,
        'folder_id'      => $folderId,
        'auto_sync'      => $autoSync
    ]);

    if ($saved) {
        echo json_encode(['success' => true, 'message' => 'Pengaturan sinkronisasi Google berhasil disimpan!']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file konfigurasi.']);
    }
    exit;
}

if ($action === 'test_connection') {
    $config = getGoogleSyncConfig();
    $gasUrl = trim($_POST['gas_webapp_url'] ?? $config['gas_webapp_url'] ?? '');

    if (empty($gasUrl)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Masukkan URL Google Apps Script terlebih dahulu.']);
        exit;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $gasUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    applyCurlDnsOptions($ch);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $raw = curl_exec($ch);
    $curlErr = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) {
        echo json_encode(['success' => false, 'message' => 'Koneksi gagal: ' . $curlErr]);
        exit;
    }

    if ($code === 401 || strpos($raw, 'accounts.google.com') !== false || strpos($raw, 'Sign in - Google Accounts') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Akses Ditolak (HTTP 401). Pada Google Apps Script, pengaturan "Who has access" (Siapa yang memiliki akses) belum diatur ke "Anyone" (Siapa saja). Silakan Deploy ulang Web App dan ubah ke "Anyone".'
        ]);
        exit;
    }

    $data = json_decode($raw, true);
    if (isset($data['success']) && $data['success']) {
        echo json_encode(['success' => true, 'message' => 'Terhubung! Webhook Google Apps Script siap digunakan.']);
    } elseif ($code === 200) {
        echo json_encode(['success' => true, 'message' => 'Terhubung ke Google Web App (HTTP 200 OK).']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Webhook merespon kode HTTP ' . $code . ': ' . substr($raw, 0, 150)]);
    }
    exit;
}

if ($action === 'sync_single') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID packing tidak valid.']);
        exit;
    }

    $res = sendPackingToGoogle($id);
    if ($res['success']) {
        echo json_encode($res);
    } else {
        http_response_code(400);
        echo json_encode($res);
    }
    exit;
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
        echo json_encode([
            'success'         => true,
            'message'         => 'Semua data yang sesuai filter sudah tersinkronkan ke Google Sheet & Drive!',
            'remaining_count' => 0,
            'synced'          => []
        ]);
        exit;
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

    echo json_encode([
        'success'         => true,
        'synced_items'    => $syncedResults,
        'remaining_count' => $remCount,
        'message'         => 'Berhasil memproses ' . count($syncedResults) . ' paket. Sisa filter: ' . $remCount
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
