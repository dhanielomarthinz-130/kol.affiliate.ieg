@echo off
title Uninstall Ngrok Windows Service
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

echo Menghentikan Ngrok Service...
"C:\laragon\bin\ngrok\ngrok.exe" service stop
echo Menghapus Ngrok Service...
"C:\laragon\bin\ngrok\ngrok.exe" service uninstall

echo.
echo ========================================================
echo  [SELESAI] Ngrok Windows Service berhasil di-uninstall.
echo ========================================================
pause
