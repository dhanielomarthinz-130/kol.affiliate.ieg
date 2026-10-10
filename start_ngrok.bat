@echo off
title "Ngrok Tunnel - KOL Packing and Inbound Return"
set "LOCKFILE=%TEMP%\start_ngrok_kol.lock"

9>>"%LOCKFILE%" (
    call :main
    exit /b
)

echo ========================================================
echo  [INFO] Ngrok sudah aktif dan sedang berjalan!
echo  Tidak perlu membuka lebih dari satu jendela agar tidak bentrok.
echo ========================================================
echo  Jendela ini akan ditutup otomatis...
ping 127.0.0.1 -n 4 >nul
exit /b

:main
echo ========================================================
echo  Ngrok Public Tunnel (Multi-App: KOL Packing & Inbound Return)
echo ========================================================
echo  Domain: https://unmoving-faculty-bok.ngrok-free.dev
echo  Local:  http://127.0.0.1 (Port 80)
echo ========================================================
echo  Links:
echo   - KOL Packing:    https://unmoving-faculty-bok.ngrok-free.dev/kol.ieg/packing
echo   - Inbound Return: https://unmoving-faculty-bok.ngrok-free.dev/inbound_return/login
echo ========================================================
echo.

:: Hentikan ngrok orphan lama jika ada
taskkill /F /IM ngrok.exe >nul 2>&1

:run_ngrok
echo [%date% %time%] Memulai koneksi Ngrok...
"C:\laragon\bin\ngrok\ngrok.exe" http 127.0.0.1:80 --url=unmoving-faculty-bok.ngrok-free.dev --host-header=localhost
echo.
echo [!] Ngrok terhenti atau koneksi terputus. Mencoba menghubungkan kembali dalam 5 detik...
ping 127.0.0.1 -n 6 >nul
goto run_ngrok
