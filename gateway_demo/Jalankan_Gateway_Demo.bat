@echo off
cd /d "%~dp0"
echo Gateway SIMULASI (bukan layanan resmi Kelompok 1): http://127.0.0.1:8101
"C:\xampp\php\php.exe" -S 127.0.0.1:8101 -t . index.php
pause
