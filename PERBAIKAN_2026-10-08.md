# Catatan Perbaikan Bug — 8 Oktober 2026

## Submit / upload video (halaman Packing)
- **Upload gagal diam-diam**: jika server membalas bukan JSON (HTTP 413 / 500 / HTML error), tombol & card tetap "sukses" padahal data tidak tersimpan. Sekarang respon diparse aman, card ditandai **GAGAL**, counter hari ini dikembalikan, dan ada tombol **Coba Lagi** (video tetap tersimpan di memori browser untuk diupload ulang). Error jaringan di-retry otomatis 1x.
- **Video besar ditolak sebagai "Nomor Resi kosong"**: PHP mengosongkan `$_POST` bila request > `post_max_size`. Sekarang terdeteksi dan pesannya jelas; batas di `.htaccess` dinaikkan ke 1024M (dibungkus `IfModule` agar tidak 500 di hosting non-mod_php).
- **Operator macet di "Sedang menyimpan video..."** bila kamera terputus/recorder mati (`stop()` melempar error dan `isSaving` tidak pernah di-reset). Sekarang ada guard state + timeout.
- **Rekaman 0 byte** tidak lagi diupload — operator diberi peringatan.
- **Webcam murah (640x480) gagal total** karena constraint minimum 1280x720. Sekarang ada fallback bertingkat.
- **Session lock**: `save_packing.php` menahan file session selama FFmpeg/auto-sync berjalan, sehingga `check_resi` & `get_packings` dari operator yang sama ikut menggantung. Sekarang `session_write_close()` dipanggil sedini mungkin (juga di `sync_google.php`).
- File video mp4 dari browser (Chrome baru merekam mp4) kini dikenali dengan benar.
- Peringatan `beforeunload` jika masih ada upload tertunda.

## Kompresi FFmpeg
- Sebelumnya hasil kompresi `_cmp.mp4` **tidak pernah dipakai** (DB tetap menunjuk file mentah) → penyimpanan dobel & "hemat storage" tidak terjadi. Sekarang (`config/video.php`): FFmpeg menulis ke `.tmp` → `move` ke `_cmp.mp4` → hapus file mentah; DB otomatis diarahkan ke file hasil kompresi saat data dibaca. Jika FFmpeg gagal, file mentah tetap aman.
- Hapus data kini menghapus semua varian file (mentah, `_cmp.mp4`, `.tmp`, `_dl.mp4`).
- `download.php` memakai file hasil kompresi dan tidak mengirim hasil konversi yang gagal/parsial.
- "Bersihkan File Sampah" tidak lagi menghapus `.tmp` yang sedang ditulis FFmpeg.

## Admin
- **Superadmin tidak bisa hapus data packing** (403 karena cek `role !== 'admin'`). Diperbaiki.
- **`formatBytes` tidak didefinisikan** → modal "Kelola Data" tabel packings error. Ditambahkan.
- **Nama berisi tanda kutip (`'`) membuat tombol Edit/Hapus/PIN/Putar mati** (JS SyntaxError di `onclick`). Semua argumen `onclick` kini di-escape dengan `jsArg()` (admin.js, packing.js, packing.php).
- **Admin biasa klik chip "Sistem Normal" → halaman kosong** (view maintenance hanya untuk superadmin). Sekarang dialihkan ke data packing; chip & tombol "Kelola" hanya aktif untuk superadmin.
- Tombol toggle maintenance macet di "Memproses...". Diperbaiki.
- Tombol "Hentikan" batch sync mati setelah pemakaian pertama. Diperbaiki.
- Filter `?operator=` dari URL diabaikan saat load pertama. Diperbaiki.
- Tag HTML rusak di akhir blok maintenance `admin.php` (`</` menggantung, `</main>` ganda). Dirapikan.
- Tanggal `YYYY-MM-DD HH:MM:SS` kini diparse aman di Safari.
- `db_manager.php` tidak lagi mengirim hash password/PIN ke browser.
- `password_hash` bcrypt tidak lagi dihitung di **setiap request** (`migrateTables`/`initMySQL`).

## Login
- Login PIN yang gagal kini membuka kembali tab PIN dengan pesan yang jelas ("PIN salah / belum diatur"), bukan "Username atau password salah" di tab admin.

## File yang diubah
`.htaccess`, `admin.php`, `login.php`, `packing.php`, `download.php`, `api/save_packing.php`, `api/delete_packing.php`, `api/get_packings.php`, `api/sync_google.php`, `api/db_manager.php`, `api/maintenance.php`, `config/database.php`, `config/google_sync.php`, `config/video.php` (baru), `assets/js/admin.js`, `assets/js/packing.js`, `assets/css/style.css`.

Backup versi lama: folder `_backup_2026-10-08/`.

---

## Update 2 — upload bertahap untuk hosting dengan batas 10 MB (InfinityFree)
- **Penyebab error submit di produksi**: InfinityFree membatasi `post_max_size`/`upload_max_filesize` **10 MB per request** dan tidak bisa dinaikkan lewat `.htaccess`. Video HD > ±30 detik langsung ditolak server.
- **Solusi**: video > 4 MB kini dikirim **bertahap (chunk 4 MB)** ke `api/upload_chunk.php` (potongan disimpan di `uploads/tmp/<upload_id>/`, diblokir dari akses web), lalu dirakit oleh `api/save_packing.php`. Tiap potongan di-retry 3x; progres tampil di card riwayat ("mengupload 3/12 (25%)").
- `save_packing.php` hanya memakai mode *flush-lalu-lanjut* (Content-Length + Connection: close) bila memang ada FFmpeg/auto-sync; di hosting tanpa `exec` response dikirim normal.
- `config/video.php`: `canRunFfmpeg()`, `chunkUploadDir()`, `assembleChunks()`, `cleanupStaleChunkDirs()` (folder chunk terbengkalai > 24 jam dibersihkan otomatis / lewat menu Bersihkan File Sampah).
- File baru: `api/upload_chunk.php`. File diubah: `api/save_packing.php`, `api/maintenance.php`, `config/video.php`, `assets/js/packing.js`.
