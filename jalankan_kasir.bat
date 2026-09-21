@echo off
title EduMart Kasir Server & Tunnel
echo ====================================================
echo      MEMULAI EDUMART KASIR + LOCALTUNNEL HP
echo ====================================================
echo.

:: 1. Buka Laravel server di jendela terpisah
start "EduMart Laravel Server (Port 8000)" cmd /k "php artisan serve"

:: 2. Jalankan localtunnel auto-reconnect di jendela ini
call start_tunnel.bat
