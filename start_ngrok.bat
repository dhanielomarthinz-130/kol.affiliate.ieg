@echo off
title Ngrok Tunnel - KOL Packing & Inbound Return
echo ========================================================
echo  Ngrok Public Tunnel (Multi-App: KOL Packing & Inbound Return)
echo ========================================================
echo  Domain: https://unmoving-faculty-bok.ngrok-free.dev
echo  Local:  http://localhost (Port 80)
echo ========================================================
echo  Links:
echo   - KOL Packing:    https://unmoving-faculty-bok.ngrok-free.dev/kol.ieg/packing
echo   - Inbound Return: https://unmoving-faculty-bok.ngrok-free.dev/inbound_return/login
echo ========================================================
echo.
"C:\laragon\bin\ngrok\ngrok.exe" http 80 --url=unmoving-faculty-bok.ngrok-free.dev --host-header=localhost
pause
