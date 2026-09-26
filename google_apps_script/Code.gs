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
      var filename = data.video_filename || ("Packing_" + (data.resi_no || "unknown") + ".mp4");
      var mimeType = data.mime_type || "video/mp4";
      
      // Cegah duplikasi file di Drive: jika file dengan nama sama sudah ada, gunakan file yang ada
      var existingFiles = targetFolder.getFilesByName(filename);
      var driveFile;
      if (existingFiles.hasNext()) {
        driveFile = existingFiles.next();
      } else {
        var videoBytes = Utilities.base64Decode(data.video_base64);
        var blob = Utilities.newBlob(videoBytes, mimeType, filename);
        driveFile = targetFolder.createFile(blob);
        
        // Set akses agar siapa saja yang memiliki link dapat melihat video (untuk audit)
        try {
          driveFile.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
        } catch (pErr) {}
      }

      fileId = driveFile.getId();
      driveUrl = "https://drive.google.com/file/d/" + fileId + "/view?usp=sharing";
    }

    // 3. Masukkan Data ke Google Sheet (Anti-Duplikasi berdasarkan No Resi)
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

    // Cek apakah No Resi sudah pernah dicatat di Google Sheet untuk mencegah duplikasi (Anti-Double Sync)
    var lastRow = sheet.getLastRow();
    var targetRow = -1;
    var searchResi = String(data.resi_no || "").trim().toLowerCase();

    if (lastRow > 1 && searchResi !== "") {
      var resiColumnValues = sheet.getRange(2, 2, lastRow - 1, 1).getValues();
      for (var r = 0; r < resiColumnValues.length; r++) {
        var existingResi = String(resiColumnValues[r][0] || "").trim().toLowerCase();
        if (existingResi === searchResi) {
          targetRow = r + 2; // Baris riil di sheet (1-based, baris 1 adalah header)
          break;
        }
      }
    }

    if (targetRow > 0) {
      // Jika resi sudah ada, pertahankan link Drive lama jika payload saat ini kosong
      if (driveUrl === "") {
        var existingDriveUrl = sheet.getRange(targetRow, 8).getValue();
        if (existingDriveUrl) {
          newRow[7] = existingDriveUrl;
          driveUrl = existingDriveUrl;
        }
      }
      // Update baris yang sudah ada (tidak menambah baris baru agar tidak double)
      sheet.getRange(targetRow, 1, 1, newRow.length).setValues([newRow]);
    } else {
      // Jika resi belum ada, baru tambahkan baris baru
      sheet.appendRow(newRow);
      targetRow = sheet.getLastRow();
    }

    // Buat link video clickable jika ada link
    if (driveUrl !== "") {
      try {
        var linkCell = sheet.getRange(targetRow, 8);
        linkCell.setFontColor("#2563eb");
        linkCell.setFontLine("underline");
      } catch (styleErr) {
        // Abaikan issue formatting agar sinkronisasi data tetap berhasil
      }
    }

    return ContentService.createTextOutput(JSON.stringify({
      success: true,
      message: (targetRow > 0 && targetRow !== sheet.getLastRow() ? "Data diperbarui (update)" : "Data berhasil disimpan") + " untuk resi " + (data.resi_no || "") + "!",
      drive_url: driveUrl,
      file_id: fileId,
      sheet_row: targetRow
    })).setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({
      success: false,
      message: "Terjadi kesalahan di Google Apps Script: " + err.toString()
    })).setMimeType(ContentService.MimeType.JSON);
  }
}
