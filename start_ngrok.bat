@echo off
title Ngrok Tunnel - KOL Affiliate Packing
echo ========================================================
echo  KOL Packing Station - Ngrok Public Tunnel
echo ========================================================
echo  Domain: https://unmoving-faculty-bok.ngrok-free.dev
echo  Local:  http://kol-ieg.test (Port 80)
echo ========================================================
echo.
"C:\laragon\bin\ngrok\ngrok.exe" http 80 --url=unmoving-faculty-bok.ngrok-free.dev --host-header=kol-ieg.test
pause
