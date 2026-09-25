# Panduan Integrasi Google Sheets & Google Drive (Opsi 1 - Google Apps Script)

Fitur ini memungkinkan sistem secara otomatis:
1. Mengunggah file video rekaman packing (MP4) ke **Google Drive** Anda.
2. Mengambil link view / sharing video Google Drive tersebut (`https://drive.google.com/file/d/.../view`).
3. Mencatat data hasil packing (**No Resi, Nama Operator, Jam Mulai, Jam Selesai, Durasi, Link Google Drive, Catatan**) ke baris baru di **Google Sheets**.
4. Menyimpan link Google Drive ke database lokal sehingga bisa dibuka / diputar langsung dari portal admin maupun diunduh via Excel.

---

## Langkah 1: Siapkan Google Sheets & Script

1. Buka browser dan buat spreadsheet baru di [Google Sheets](https://sheets.new).
2. Beri nama file Google Sheets Anda, misalnya: `Rekap Hasil Packing KOL`.
3. Pada menu atas, klik **Ekstensi (Extensions)** > pilih **Apps Script**.
4. Hapus seluruh baris kode default yang ada di dalam file `Code.gs`.
5. Buka file `google_apps_script/Code.gs` yang ada di proyek ini (atau salin kodenya), lalu **tempelkan (paste)** ke editor Google Apps Script tersebut.
6. *(Opsional)* Jika Anda ingin video tersimpan ke folder Google Drive tertentu:
   - Buat folder di Google Drive (misal bernama `Video Packing`).
   - Buka folder tersebut, lalu lihat URL di address bar browser:
     `https://drive.google.com/drive/folders/1ABCxyz123456789...`
   - Salin kode ID foldernya (`1ABCxyz123456789...`).
   - Masukkan ID tersebut pada variabel `var DEFAULT_FOLDER_ID = "1ABCxyz...";` di baris atas `Code.gs` (atau nanti bisa diisi di menu admin aplikasi).
7. Klik ikon disket / tekan `Ctrl + S` untuk menyimpan script.

---

## Langkah 2: Deploy Sebagai Aplikasi Web (Web App)

1. Di halaman Google Apps Script, klik tombol biru **Terapkan (Deploy)** di pojok kanan atas > pilih **Penerapan baru (New deployment)**.
2. Di sebelah tulisan *Pilih jenis*, klik ikon **Gerigi (Settings)** > pilih **Aplikasi Web (Web app)**.
3. Atur pengaturannya seperti berikut:
   - **Deskripsi:** `KOL Packing Sync Webhook`
   - **Jalankan sebagai (Execute as):** `Saya (Me / email Anda)`
   - **Siapa yang memiliki akses (Who has access):** **`Siapa saja (Anyone)`** *(PENTING: Jangan pilih 'Hanya saya' agar PHP bisa mengirim data).*
4. Klik tombol **Terapkan (Deploy)**.
5. Google akan meminta izin otorisasi akses:
   - Klik **Beri akses (Authorize access)** / **Tinjau izin (Review permissions)**.
   - Pilih akun Google Anda.
   - Jika muncul peringatan *"Google hasn't verified this app"*, klik **Lanjutan (Advanced)** di kiri bawah > klik **Buka Untitled project (tidak aman)**.
   - Klik **Izinkan (Allow)**.
6. Salin **URL Aplikasi Web (Web app URL)** yang diberikan.
   - Format URL contoh: `https://script.google.com/macros/s/AKfycb.../exec`

---

## Langkah 3: Masukkan URL ke Portal Admin

1. Buka portal Admin KOL Packing Anda di browser (`http://localhost/kol.ieg/admin.php` atau `admin`).
2. Klik tombol **Setting Google** (atau menu **Google Drive & Sheet** di sidebar).
3. Tempelkan URL Web App yang tadi Anda salin ke kolom **URL Web App Google Apps Script**.
4. *(Opsional)* Jika ingin diarahkan ke folder tertentu, isi kolom **ID Folder Google Drive**.
5. Centang **Auto-Sync Otomatis Setiap Scan Selesai** jika ingin video & data otomatis dikirim ke Google Drive/Sheets setiap operator selesai scan resi.
6. Klik tombol **Uji Koneksi** untuk memastikan webhook terhubung.
7. Klik **Simpan Pengaturan**.

---

## Langkah 4: Cara Penggunaan

1. **Sinkronisasi Massal (Batch Sync):**
   - Di dashboard admin, klik tombol **Sync ke Google Sheet**.
   - Akan muncul jendela progres bar yang mengunggah paket satu per satu secara berurutan tanpa membebani browser.
   - Setelah selesai, status setiap resi di tabel akan berubah memiliki tombol hijau **Drive** yang langsung membuka videonya di Google Drive.
2. **Sinkronisasi Per Baris:**
   - Pada tabel hasil packing, klik tombol **Sync** pada baris resi tertentu untuk mengunggah paket tersebut.
3. **Download Excel:**
   - Klik tombol **Excel** / **Ekspor Excel**. File spreadsheet yang diunduh kini memiliki kolom **Link Google Drive** yang aktif dan dapat diklik.
