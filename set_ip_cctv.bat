@echo off
echo Mengatur IP Ethernet 2 untuk CCTV Dahua...
netsh interface ip set address name="Ethernet 2" static 192.168.1.99 255.255.255.0
echo.
echo ========================================================
echo IP Ethernet 2 (Dongle Ugreen) berhasil diatur ke 192.168.1.99
echo ========================================================
pause
