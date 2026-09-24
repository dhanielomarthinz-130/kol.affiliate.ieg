# 📦 KOL Affiliate Packing Video Recording & Audit System

Sistem otomatisasi perekaman video hasil packaging barang untuk KOL Affiliate / Warehouse E-commerce.
Terintegrasi langsung dengan Barcode Scanner (USB / Wireless) untuk merekam dan mengakhiri video secara otomatis tanpa perlu menyentuh mouse atau keyboard.

---

## 🚀 Alur Kerja Perekaman (Workflow Operator)

1. **Buka Halaman Packing (`packing.php`)**:
   - Kamera feed otomatis aktif dengan status **STANDBY (SIAP SCAN)**.
   - Kolom scanner otomatis **Auto-Focus**.
2. **Scan Invoice / Resi Pertama Kali**:
   - Sistem membunyikan nada **Chirp Mulai** (Beep).
   - Status berubah seketika menjadi **● RECORDING: [NO RESI]** (border merah pulsasi).
   - Stopwatch / Timer mulai menghitung durasi packing (`00:00:00`).
   - Kamera merekam proses operator memasukkan produk, bubble wrap, kardus, hingga menempelkan label resi.
3. **Scan No Resi Yang Sama (Akhiri & Kompres Otomatis)**:
   - Sistem mendeteksi kecocokan nomor resi yang sedang direkam.
   - Perekaman langsung diakhiri (**Stop Recording**).
   - Video otomatis dikompresi langsung menggunakan mesin **FFmpeg H.264 (CRF 26, Preset Fast, AAC 64k)** yang memotong ukuran file hingga **70% - 85% lebih hemat** tanpa mengurangi ketajaman teks/label pada paket.
   - File tersimpan langsung sebagai format **`.mp4`** (atau dikonversi otomatis) di [uploads/videos/](file:///c:/xampp/htdocs/kol.ieg/uploads/videos/).
   - Data otomatis tersimpan ke database (No Resi, Operator, Waktu Mulai, Waktu Selesai, Durasi, Ukuran File Hemat).
   - Bunyi melodi sukses (**Victory Chime**).
   - Notifikasi sukses muncul dan masuk ke panel riwayat operator.
   - Status kembali ke **STANDBY** dan kolom input langsung siap menerima scan paket berikutnya!
4. **Download Video MP4 Kualitas Jernih**:
   - Baik di halaman Operator maupun Admin, terdapat tombol **Download MP4**.
   - Video diunduh sebagai file `.mp4` standar (kompatibel penuh di HP Android, iPhone, Windows Media Player, WhatsApp, dsb) dengan resolusi HD 720p yang jernih untuk membaca teks label/barcode resi.

---

## 👥 Hak Akses & Akun Default

Sistem menyediakan 2 Role sesuai permintaan:

| Role | Username | Password | Fitur & Tampilan |
|---|---|---|---|
| **👷 Operator** | `operator` | `operator123` | **Khusus Halaman Kerja Packing**: Tampilan bersih, scanner autofocus, live camera feed + timestamp WIB, live timer, status overlay, audio visualizer, riwayat rekaman hari ini. |
| **👑 Admin** | `admin` | `admin123` | **Dashboard Lengkap**: KPI statistik harian & total, filter pencarian (Resi, Operator, Rentang Tanggal), tabel lengkap hasil packaging, **Video Player Modal dengan kontrol kecepatan audit (1x, 1.25x, 1.5x, 2x)**, tombol download video, ekspor data ke Excel/CSV, dan manajemen tambah/hapus akun operator. |

---

## 🌐 Akses Sistem

- **Local Development**:
  - `http://localhost/kol.ieg/`
- **Live Production (InfinityFree)**:
  - `http://iegkolaffiliate.xo.je/` (Auto-deployed via GitHub Actions)

> **Catatan Izin Kamera**:
> Saat pertama kali membuka halaman packing, browser akan meminta izin akses kamera (**Allow Camera**). Pastikan klik **Izinkan / Allow** agar feed kamera dan rekaman dapat berjalan optimal.

---

## 📁 Struktur File & Direktori

```
kol.ieg/
├── api/
│   ├── delete_packing.php   # API hapus data & video (Admin only)
│   ├── get_packings.php     # API query data dengan pagination & filter
│   ├── save_packing.php     # API upload & simpan video rekaman + SQLite
│   ├── stats.php            # API statistik KPI & leaderboard operator
│   └── users.php            # API manajemen user operator
├── assets/
│   ├── css/
│   │   └── style.css        # Desain modern dark mode & glowing indicators
│   └── js/
│       ├── admin.js         # Logika dashboard, modal player audit, filter, user CRUD
│       └── packing.js       # Logika scanner barcode, MediaRecorder, Web Audio synthesis
├── config/
│   ├── auth.php             # Session management & proteksi role access
│   └── database.php         # PDO SQLite auto-setup & database seeding
├── database/
│   └── kol_packing.sqlite   # Database SQLite mandiri (Zero-config)
├── uploads/
│   └── videos/              # Direktori arsip rekaman video (.webm)
├── admin.php                # Halaman Dashboard Admin
├── export.php               # Generator export data ke CSV/Excel
├── index.php                # Router otomatis berdasarkan role user
├── login.php                # Halaman login modern dengan tombol tes instan
├── logout.php               # Script logout
└── packing.php              # Halaman Kerja Operator Packing
```
