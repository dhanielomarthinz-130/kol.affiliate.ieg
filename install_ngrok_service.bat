@echo off
title Install Ngrok Windows Service
:: Check Admin Privileges
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ========================================================
    echo  [PERINGATAN] BUTUH HAK AKSES ADMINISTRATOR!
    echo  Silakan klik kanan file ini, lalu pilih:
    echo  "Run as administrator" (Jalankan sebagai administrator)
    echo ========================================================
    pause
    exit /b 1
)

echo Menghentikan service lama (jika ada)...
"C:\laragon\bin\ngrok\ngrok.exe" service stop >nul 2>&1
"C:\laragon\bin\ngrok\ngrok.exe" service uninstall >nul 2>&1

echo Menginstall Ngrok sebagai Windows Service...
"C:\laragon\bin\ngrok\ngrok.exe" service install --config="C:\Users\USER\AppData\Local\ngrok\ngrok.yml"
if %errorLevel% neq 0 (
    echo [GAGAL] Terjadi kesalahan saat instalasi service.
    pause
    exit /b 1
)

echo Memulai Ngrok Service...
"C:\laragon\bin\ngrok\ngrok.exe" service start

echo.
echo ===================================================================
echo  [BERHASIL] Ngrok sekarang aktif sebagai Windows Service!
echo  - Berjalan otomatis di background saat server/komputer menyala
echo  - Tidak akan tertutup/mati karena tidak memakai jendela CMD
echo  - Otomatis restart jika server reboot
echo ===================================================================
pause
