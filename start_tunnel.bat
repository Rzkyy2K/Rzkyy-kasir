@echo off
:loop
echo [%date% %time%] Memulai localtunnel...
call npx localtunnel --port 8000 --subdomain dry-jeans-sip
echo [%date% %time%] Localtunnel terputus, mencoba koneksi ulang dalam 2 detik...
timeout /t 2 /nobreak >nul
goto loop
