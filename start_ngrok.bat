@echo off
if "%~1"==":watchdog" goto watchdog

title [SERVER AKTIF] Ngrok Public Tunnel - KOL Packing & Inbound Return
color 0B

:: Bersihkan ngrok background lama agar berpindah tampil di jendela ini
taskkill /F /IM ngrok.exe >nul 2>&1

cls
echo ==============================================================================
echo        SISTEM SERVER UTAMA: KOL PACKING STATION ^& INBOUND RETURN
echo ==============================================================================
echo.

:: 1. Cek MySQL (Port 3306)
echo [*] Memeriksa Database MySQL (Port 3306)...
netstat -ano | findstr /C:":3306 " | findstr "LISTENING" >nul
if errorlevel 1 (
    tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | findstr /I "mysqld.exe" >nul
    if errorlevel 1 (
        echo     - MySQL belum aktif, menyalakan MySQL sekarang...
        start "MySQL Server" /min "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe" --defaults-file=C:\laragon\data\mysql-8.4\my.ini --standalone
        ping 127.0.0.1 -n 3 >nul
    )
)
echo     [OK] MySQL Database AKTIF ^& SIAP DIGUNAKAN.
echo.

:: 2. Cek Apache (Port 80)
echo [*] Memeriksa Web Server Apache (Port 80)...
netstat -ano | findstr /C:":80 " | findstr "LISTENING" >nul
if errorlevel 1 (
    echo     - Apache belum aktif, menyalakan Apache sekarang...
    taskkill /F /IM httpd.exe >nul 2>&1
    start "Apache Web Server" /min "C:\laragon\bin\apache\httpd-2.4.68-260617-Win64-VS18\bin\httpd.exe" -d C:/laragon/bin/apache/httpd-2.4.68-260617-Win64-VS18
    ping 127.0.0.1 -n 3 >nul
)
echo     [OK] Web Server Apache AKTIF ^& SIAP DIGUNAKAN.
echo.

:: 3. Jalankan Auto-Healing Watchdog di latar belakang
echo [*] Mengaktifkan Auto-Healing Watchdog...
start "KOL Service Watchdog" /min cmd /c call "%~f0" :watchdog
echo     [OK] Auto-Healing Watchdog AKTIF (Menjaga Apache ^& MySQL selalu hidup).
echo.

echo ==============================================================================
echo                             LINK AKSES ONLINE RESMI
echo ==============================================================================
echo  1. KOL Packing    : https://unmoving-faculty-bok.ngrok-free.dev/kol.ieg/login
echo  2. Inbound Return : https://unmoving-faculty-bok.ngrok-free.dev/inbound_return/login
echo  3. Traffic Monitor: http://127.0.0.1:4040
echo ==============================================================================
echo.
echo  ==========================================================================
echo   PERINGATAN PENTING:
echo   JANGAN TUTUP JENDELA COMMAND PROMPT INI! (BOLEH DI-MINIMIZE)
echo   Jika jendela ini ditutup [X], maka koneksi online di HP/Laptop luar
echo   otomatis akan PUTUS!
echo  ==========================================================================
echo.
echo Menghubungkan tunnel publik Ngrok...
echo.

:run_ngrok
"C:\laragon\bin\ngrok\ngrok.exe" http 127.0.0.1:80 --url=unmoving-faculty-bok.ngrok-free.dev --host-header=localhost
echo.
echo ==============================================================================
echo [!] Tunnel Ngrok terputus atau koneksi internet terganggu.
echo     Mengecek ulang servis dan menyambungkan kembali dalam 5 detik...
echo ==============================================================================
ping 127.0.0.1 -n 6 >nul

:: Pastikan Apache dan MySQL tetap up sebelum reconnect
netstat -ano | findstr /C:":80 " | findstr "LISTENING" >nul
if errorlevel 1 (
    taskkill /F /IM httpd.exe >nul 2>&1
    start "Apache Web Server" /min "C:\laragon\bin\apache\httpd-2.4.68-260617-Win64-VS18\bin\httpd.exe" -d C:/laragon/bin/apache/httpd-2.4.68-260617-Win64-VS18
)
netstat -ano | findstr /C:":3306 " | findstr "LISTENING" >nul
if errorlevel 1 (
    tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | findstr /I "mysqld.exe" >nul
    if errorlevel 1 (
        start "MySQL Server" /min "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe" --defaults-file=C:\laragon\data\mysql-8.4\my.ini --standalone
    )
)

goto run_ngrok

:: ========================================================
:: Background Watchdog: Menjaga Apache & MySQL selalu hidup
:: ========================================================
:watchdog
set "WLOCK=%TEMP%\start_watchdog_kol.lock"
9>>"%WLOCK%" (
    goto watchdog_loop
)
exit /b

:watchdog_loop
ping 127.0.0.1 -n 6 >nul

:: Cek apakah ngrok masih berjalan. Jika jendela ngrok ditutup, watchdog ikut ditutup
tasklist /FI "IMAGENAME eq ngrok.exe" 2>nul | findstr /I "ngrok.exe" >nul
if errorlevel 1 exit /b

:: Pantau Apache di port 80
netstat -ano | findstr /C:":80 " | findstr "LISTENING" >nul
if errorlevel 1 (
    echo [%date% %time%] [WATCHDOG] Apache terputus/mati! Memulihkan Apache...
    taskkill /F /IM httpd.exe >nul 2>&1
    start "Apache Web Server" /min "C:\laragon\bin\apache\httpd-2.4.68-260617-Win64-VS18\bin\httpd.exe" -d C:/laragon/bin/apache/httpd-2.4.68-260617-Win64-VS18
)

:: Pantau MySQL di port 3306
netstat -ano | findstr /C:":3306 " | findstr "LISTENING" >nul
if errorlevel 1 (
    tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | findstr /I "mysqld.exe" >nul
    if errorlevel 1 (
        echo [%date% %time%] [WATCHDOG] MySQL terputus/mati! Memulihkan MySQL...
        start "MySQL Server" /min "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe" --defaults-file=C:\laragon\data\mysql-8.4\my.ini --standalone
    )
)

goto watchdog_loop
