<?php
// export.php — Export ke Excel (.xls) dengan link video
require_once __DIR__ . "/config/auth.php";
requireRole("admin");
date_default_timezone_set("Asia/Jakarta");

$db = getDB();

$search     = trim($_GET["search"]      ?? "");
$operatorId = intval($_GET["operator_id"] ?? 0);
$dateFrom   = trim($_GET["date_from"]   ?? "");
$dateTo     = trim($_GET["date_to"]     ?? "");

$whereClauses = [];
$params       = [];

if ($operatorId > 0) {
    $whereClauses[] = "user_id = ?";
    $params[]       = $operatorId;
}
if (!empty($search)) {
    $whereClauses[] = "(resi_no LIKE ? OR operator_name LIKE ?)";
    $params[]       = "%{$search}%";
    $params[]       = "%{$search}%";
}
if (!empty($dateFrom)) {
    $whereClauses[] = "DATE(created_at) >= ?";
    $params[]       = $dateFrom;
}
if (!empty($dateTo)) {
    $whereClauses[] = "DATE(created_at) <= ?";
    $params[]       = $dateTo;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
$stmt     = $db->prepare("SELECT * FROM packings {$whereSql} ORDER BY id DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Deteksi base URL server
$protocol = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
$host     = $_SERVER["HTTP_HOST"] ?? "localhost";
$basePath = rtrim(dirname($_SERVER["PHP_SELF"]), "/\\");
$basePath = preg_replace("#/export$#", "", $basePath);
$baseUrl  = "{$protocol}://{$host}{$basePath}";

function xesc(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, "UTF-8");
}

$filename = "rekap_packing_" . date("Ymd_His") . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

ob_start();

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<?mso-application progid=\"Excel.Sheet\"?>\n";
echo "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
echo "          xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
echo "          xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n";

// Styles
echo "<Styles>\n";
$styles = [
    "header" => ["bg"=>"#1e40af","fg"=>"#FFFFFF","bold"=>1,"align"=>"Center","alt"=>0],
    "data"   => ["bg"=>"","fg"=>"#000000","bold"=>0,"align"=>"Left","alt"=>0],
    "num"    => ["bg"=>"","fg"=>"#000000","bold"=>0,"align"=>"Right","alt"=>0],
    "link"   => ["bg"=>"","fg"=>"#2563eb","bold"=>0,"align"=>"Left","alt"=>0],
    "alt"    => ["bg"=>"#f1f5f9","fg"=>"#000000","bold"=>0,"align"=>"Left","alt"=>1],
    "altnum" => ["bg"=>"#f1f5f9","fg"=>"#000000","bold"=>0,"align"=>"Right","alt"=>1],
    "altlink"=> ["bg"=>"#f1f5f9","fg"=>"#2563eb","bold"=>0,"align"=>"Left","alt"=>1],
];
foreach ($styles as $id => $s) {
    $underline = strpos($id,"link")!==false ? " ss:Underline=\"Single\"" : "";
    $interior  = !empty($s["bg"]) ? "<Interior ss:Color=\"{$s["bg"]}\" ss:Pattern=\"Solid\"/>\n" : "";
    $alignAttr = !empty($s["align"]) ? " ss:Horizontal=\"{$s["align"]}\"" : "";
    echo "<Style ss:ID=\"{$id}\">\n";
    echo "<Alignment{$alignAttr} ss:Vertical=\"Center\"/>\n";
    echo "<Borders>\n<Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#cbd5e1\"/>\n";
    echo "<Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#cbd5e1\"/>\n";
    echo "<Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#cbd5e1\"/>\n</Borders>\n";
    echo "<Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Color=\"{$s["fg"]}\"{$underline}";
    if ($s["bold"]) echo " ss:Bold=\"1\"";
    echo "/>\n";
    echo $interior;
    echo "</Style>\n";
}
echo "</Styles>\n";

// Sheet Data
echo "<Worksheet ss:Name=\"Rekap Packing\">\n<Table>\n";
echo "<Column ss:Width=\"40\"/><Column ss:Width=\"180\"/><Column ss:Width=\"130\"/>";
echo "<Column ss:Width=\"145\"/><Column ss:Width=\"145\"/><Column ss:Width=\"65\"/>";
echo "<Column ss:Width=\"80\"/><Column ss:Width=\"85\"/><Column ss:Width=\"110\"/><Column ss:Width=\"280\"/>\n";

// Header
echo "<Row ss:Height=\"28\">\n";
foreach (["No","No Resi / Invoice","Nama Operator","Waktu Mulai","Waktu Selesai","Durasi (dtk)","Durasi Format","Ukuran (MB)","Link Video","URL Video Lengkap"] as $h) {
    echo "<Cell ss:StyleID=\"header\"><Data ss:Type=\"String\">" . xesc($h) . "</Data></Cell>\n";
}
echo "</Row>\n";

// Data rows
$no = 1;
foreach ($rows as $row) {
    $isAlt     = ($no % 2 === 0);
    $sD        = $isAlt ? "alt"     : "data";
    $sN        = $isAlt ? "altnum"  : "num";
    $sL        = $isAlt ? "altlink" : "link";
    $durMin    = sprintf("%02d:%02d", floor($row["duration_seconds"]/60), $row["duration_seconds"]%60);
    $sizeMb    = number_format($row["video_filesize"]/(1024*1024), 2, ".", "");
    $videoUrl  = $baseUrl . "/uploads/videos/" . $row["video_filename"];

    echo "<Row ss:Height=\"20\">\n";
    echo "<Cell ss:StyleID=\"{$sN}\"><Data ss:Type=\"Number\">{$no}</Data></Cell>\n";
    echo "<Cell ss:StyleID=\"{$sD}\"><Data ss:Type=\"String\">" . xesc($row["resi_no"]) . "</Data></Cell>\n";
    echo "<Cell ss:StyleID=\"{$sD}\"><Data ss:Type=\"String\">" . xesc($row["operator_name"]) . "</Data></Cell>\n";
    echo "<Cell ss:StyleID=\"{$sD}\"><Data ss:Type=\"String\">" . xesc($row["start_time"]) . "</Data></Cell>\n";
    echo "<Cell ss:StyleID=\"{$sD}\"><Data ss:Type=\"String\">" . xesc($row["end_time"]) . "</Data></Cell>\n";
    echo "<Cell ss:StyleID=\"{$sN}\"><Data ss:Type=\"Number\">" . intval($row["duration_seconds"]) . "</Data></Cell>\n";
    echo "<Cell ss:StyleID=\"{$sD}\"><Data ss:Type=\"String\">" . xesc($durMin) . "</Data></Cell>\n";
    echo "<Cell ss:StyleID=\"{$sN}\"><Data ss:Type=\"Number\">{$sizeMb}</Data></Cell>\n";
    echo "<Cell ss:StyleID=\"{$sL}\" ss:HRef=\"" . xesc($videoUrl) . "\"><Data ss:Type=\"String\">\xe2\x96\xb6 Putar / Download</Data></Cell>\n";
    echo "<Cell ss:StyleID=\"{$sD}\"><Data ss:Type=\"String\">" . xesc($videoUrl) . "</Data></Cell>\n";
    echo "</Row>\n";
    $no++;
}

echo "</Table>\n";
// Freeze row 1
echo "<WorksheetOptions xmlns=\"urn:schemas-microsoft-com:office:excel\">\n";
echo "<Selected/><FreezePanes/><FrozenNoSplit/>\n";
echo "<SplitHorizontal>1</SplitHorizontal><TopRowBottomPane>1</TopRowBottomPane>\n";
echo "<ActivePane>2</ActivePane><Panes><Pane><Number>3</Number></Pane><Pane><Number>2</Number><ActiveRow>1</ActiveRow></Pane></Panes>\n";
echo "</WorksheetOptions>\n</Worksheet>\n";

// Sheet Ringkasan
$totalRows  = count($rows);
$totalSec   = array_sum(array_column($rows, "duration_seconds"));
$totalBytes = array_sum(array_column($rows, "video_filesize"));
$avgSec     = $totalRows > 0 ? round($totalSec / $totalRows) : 0;

echo "<Worksheet ss:Name=\"Ringkasan\">\n<Table>\n";
echo "<Column ss:Width=\"200\"/><Column ss:Width=\"130\"/>\n";
echo "<Row ss:Height=\"24\"><Cell ss:StyleID=\"header\"><Data ss:Type=\"String\">Keterangan</Data></Cell><Cell ss:StyleID=\"header\"><Data ss:Type=\"String\">Nilai</Data></Cell></Row>\n";
$summary = [
    ["Total Paket Dipacking",   $totalRows,             "Number"],
    ["Total Durasi (menit)",    round($totalSec/60,1),  "Number"],
    ["Rata-rata Durasi (dtk)",  $avgSec,                "Number"],
    ["Total Ukuran Video (MB)", round($totalBytes/(1024*1024),2),"Number"],
    ["Tanggal Export",          date("d/m/Y H:i:s"),    "String"],
];
foreach ($summary as $idx => [$label, $val, $type]) {
    $s = ($idx % 2 === 0) ? "data" : "alt";
    $sn = ($idx % 2 === 0) ? "num" : "altnum";
    echo "<Row>\n";
    echo "<Cell ss:StyleID=\"{$s}\"><Data ss:Type=\"String\">" . xesc($label) . "</Data></Cell>\n";
    echo "<Cell ss:StyleID=\"{$sn}\"><Data ss:Type=\"{$type}\">" . xesc((string)$val) . "</Data></Cell>\n";
    echo "</Row>\n";
}
echo "</Table>\n</Worksheet>\n</Workbook>\n";

ob_end_flush();
exit;
