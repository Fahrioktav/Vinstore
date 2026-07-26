@echo off
echo ================================================
echo      VINSTORE - Starting Laravel Server
echo ================================================
echo.
echo Menghapus environment variables Laragon...
powershell -Command "Remove-Item Env:\DB_* -ErrorAction SilentlyContinue"
echo.
echo Starting server di http://localhost:8000
echo Press Ctrl+C untuk stop server
echo ================================================
echo.

php artisan serve

pause
