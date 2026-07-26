@echo off
echo ========================================
echo VINSTORE - Screenshot Generator
echo Untuk Laporan Skripsi
echo ========================================
echo.

echo [1/3] Memeriksa server Laravel...
curl -s http://localhost:8000 >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] Server Laravel tidak berjalan!
    echo [!] Jalankan: php artisan serve
    echo.
    pause
    exit /b 1
)
echo [OK] Server Laravel berjalan di http://localhost:8000
echo.

echo [2/3] Membuat folder screenshot...
if not exist "public\screenshots\laporan-skripsi" mkdir "public\screenshots\laporan-skripsi"
echo [OK] Folder screenshot siap
echo.

echo [3/3] Menjalankan Playwright untuk screenshot...
echo.
npx playwright test tests/playwright/screenshot-all-pages.spec.js --headed
echo.

echo ========================================
echo Screenshot selesai!
echo Hasil tersimpan di: public\screenshots\laporan-skripsi\
echo ========================================
echo.

explorer "public\screenshots\laporan-skripsi"

pause
