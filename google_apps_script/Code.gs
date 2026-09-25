/**
 * ====================================================================
 * KOL PACKING - GOOGLE DRIVE & GOOGLE SHEET SYNC WEBHOOK (Apps Script)
 * ====================================================================
 * 
 * PANDUAN DEPLOY:
 * 1. Buka Google Sheets Anda (atau buat spreadsheet baru di https://sheets.new)
 * 2. Klik menu 'Ekstensi' (Extensions) > 'Apps Script'
 * 3. Hapus semua kode bawaan, lalu COPY-PASTE seluruh isi file ini ke 'Code.gs'
 * 4. (Opsional) Masukkan ID Folder Google Drive Anda di variabel DEFAULT_FOLDER_ID di bawah
 * 5. Klik tombol biru 'Terapkan' (Deploy) di kanan atas > pilih 'Penerapan baru' (New deployment)
 * 6. Klik ikon gerigi (Select type) > pilih 'Aplikasi Web' (Web app)
 *    - Deskripsi: KOL Packing Sync
 *    - Jalankan sebagai: 'Saya' (Me / email Anda)
 *    - Siapa yang memiliki akses: 'Siapa saja' (Anyone)  <--- PENTING SEKALI!
 * 7. Klik 'Terapkan' (Deploy)
 * 8. Berikan otorisasi izin Google jika diminta (Review permissions > Pilih akun > Advanced > Go to Untitled (unsafe) > Allow)
 * 9. Salin 'URL Aplikasi Web' (Web app URL), contohnya berakhiran `/exec`
 * 10. Masukkan URL tersebut ke menu Pengaturan Google Sync di Portal Admin KOL Packing!
 */

// Masukkan ID Folder Google Drive target di sini
var DEFAULT_FOLDER_ID = "1ArUc5cSTO-decvyF0auMmTFuF3v_fon7";

// Nama Tab Sheet tempat data disimpan
var SHEET_NAME = "Data Packing";

function extractFolderId(idOrUrl) {
  if (!idOrUrl) return "";
  idOrUrl = String(idOrUrl).trim();
  var match = idOrUrl.match(/\/folders\/([a-zA-Z0-9_-]+)/);
  if (match && match[1]) {
    return match[1];
  }
  return idOrUrl;
}

function doGet(e) {
  return ContentService.createTextOutput(JSON.stringify({
    success: true,
    message: "KOL Packing Google Apps Script Webhook aktif dan siap menerima data!"
  })).setMimeType(ContentService.MimeType.JSON);
}

function doPost(e) {
  try {
    if (!e || !e.postData || !e.postData.contents) {
      return ContentService.createTextOutput(JSON.stringify({
        success: false,
        message: "Tidak ada payload data yang diterima."
      })).setMimeType(ContentService.MimeType.JSON);
    }

    var data = JSON.parse(e.postData.contents);

    // 1. Tentukan Folder Google Drive (bisa kirim ID murni atau full link URL)
    var rawFolderId = (data.folder_id && String(data.folder_id).trim() !== "") ? String(data.folder_id).trim() : DEFAULT_FOLDER_ID;
    var folderId = extractFolderId(rawFolderId);
    var targetFolder;
    if (folderId && folderId !== "") {
      try {
        targetFolder = DriveApp.getFolderById(folderId);
      } catch (fErr) {
        targetFolder = DriveApp.getRootFolder();
      }
    } else {
      targetFolder = DriveApp.getRootFolder();
    }

    // 2. Simpan Video ke Google Drive (jika data video dikirim)
    var driveUrl = "";
    var fileId = "";
    
    if (data.video_base64 && data.video_base64.length > 0) {
      var videoBytes = Utilities.base64Decode(data.video_base64);
      var filename = data.video_filename || ("Packing_" + (data.resi_no || "unknown") + ".mp4");
      var mimeType = data.mime_type || "video/mp4";
      
      var blob = Utilities.newBlob(videoBytes, mimeType, filename);
      var driveFile = targetFolder.createFile(blob);
      
      // Set akses agar siapa saja yang memiliki link dapat melihat video (untuk audit)
      try {
        driveFile.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
      } catch (pErr) {}

      fileId = driveFile.getId();
      driveUrl = "https://drive.google.com/file/d/" + fileId + "/view?usp=sharing";
    }

    // 3. Masukkan Data ke Google Sheet
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    var sheet = ss.getSheetByName(SHEET_NAME);
    if (!sheet) {
      sheet = ss.insertSheet(SHEET_NAME);
    }

    // Buat baris header jika sheet baru / masih kosong
    if (sheet.getLastRow() === 0) {
      var headers = [
        "Tanggal Sync (WIB)",
        "No Resi / Invoice",
        "Nama Operator",
        "Waktu Mulai Packing",
        "Waktu Selesai Packing",
        "Durasi (Detik)",
        "Durasi Format",
        "Link Video Google Drive",
        "Catatan"
      ];
      sheet.appendRow(headers);
      
      // Styling header agar rapi dan profesional
      var headerRange = sheet.getRange(1, 1, 1, headers.length);
      headerRange.setFontWeight("bold");
      headerRange.setBackground("#0f172a");
      headerRange.setFontColor("#f8fafc");
      headerRange.setHorizontalAlignment("center");
      sheet.setFrozenRows(1);
    }

    // Format durasi
    var durSec = parseInt(data.duration_seconds || 0, 10);
    var formattedDur = Math.floor(durSec / 60) + "m " + (durSec % 60) + "s";
    var nowString = Utilities.formatDate(new Date(), "Asia/Jakarta", "dd/MM/yyyy HH:mm:ss");

    var newRow = [
      nowString,
      data.resi_no || "",
      data.operator_name || "",
      data.start_time || "",
      data.end_time || "",
      durSec,
      formattedDur,
      driveUrl,
      data.notes || ""
    ];

    sheet.appendRow(newRow);
    var lastRow = sheet.getLastRow();

    // Buat link video clickable jika ada link
    if (driveUrl !== "") {
      sheet.getRange(lastRow, 8).setFontColor("#2563eb").setFontUnderline(true);
    }

    return ContentService.createTextOutput(JSON.stringify({
      success: true,
      message: "Data dan video resi " + (data.resi_no || "") + " berhasil disinkronkan ke Google Sheet & Drive!",
      drive_url: driveUrl,
      file_id: fileId,
      sheet_row: lastRow
    })).setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({
      success: false,
      message: "Terjadi kesalahan di Google Apps Script: " + err.toString()
    })).setMimeType(ContentService.MimeType.JSON);
  }
}
