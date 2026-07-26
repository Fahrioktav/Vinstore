# 📸 Quick Reference - Screenshot Vinstore

## 🚀 Cara Cepat (Windows)

### Metode 1: Double-click (Paling Mudah)
1. Pastikan server running: `php artisan serve`
2. Double-click file: `run_screenshot.bat`
3. Tunggu proses selesai
4. Screenshot otomatis terbuka di folder

### Metode 2: Manual (Lebih Kontrol)

```bash
# 1. Jalankan server
php artisan serve

# 2. Setup user testing
php artisan db:seed --class=ScreenshotTestingSeeder

# 3. Jalankan screenshot
npx playwright test tests/playwright/screenshot-all-pages.spec.js --headed
```

## 📋 Dua Versi Script

### 1. **screenshot-all-pages.spec.js** (Lengkap)
- 36 halaman
- Semua fitur aplikasi
- Untuk dokumentasi lengkap
- Waktu: ~10 menit

```bash
npx playwright test tests/playwright/screenshot-all-pages.spec.js --headed
```

### 2. **screenshot-simple.spec.js** (Ringkas)
- 20 halaman utama
- Fitur penting saja
- Untuk presentasi
- Waktu: ~5 menit

```bash
npx playwright test tests/playwright/screenshot-simple.spec.js --headed
```

## 🔑 Kredensial Testing

| Role      | Email                      | Password      |
|-----------|----------------------------|---------------|
| Buyer     | asep@example.com          | password123   |
| Seller    | cikidaw@gmail.com         | daw123        |
| Validator | validator@gmail.com       | password123   |
| Admin     | adminganteng@gmail.com    | password123   |

## 📁 Lokasi Screenshot

```
public/screenshots/laporan-skripsi/
├── 01-home-page.png
├── 02-products-list.png
├── 03-product-detail.png
└── ... (36 files total)
```

## 🎯 Screenshot Spesifik

Hanya screenshot halaman tertentu:

```bash
# Hanya public pages
npx playwright test tests/playwright/screenshot-all-pages.spec.js --grep "Halaman Utama|Login|Register"

# Hanya admin
npx playwright test tests/playwright/screenshot-all-pages.spec.js --grep "Admin"

# Hanya seller
npx playwright test tests/playwright/screenshot-all-pages.spec.js --grep "Seller"

# Hanya satu test
npx playwright test tests/playwright/screenshot-all-pages.spec.js -g "Dashboard Admin"
```

## ⚙️ Opsi Tambahan

```bash
# Dengan browser terlihat (recommended)
--headed

# Mode debug (jika ada error)
--debug

# Spesifik browser
--project=chromium
--project=firefox
--project=webkit

# Parallel (lebih cepat, tapi bisa buggy untuk screenshot)
--workers=4
```

## 🔧 Troubleshooting Cepat

### Server tidak berjalan
```bash
php artisan serve
```

### User tidak ada
```bash
php artisan db:seed --class=ScreenshotTestingSeeder
```

### Screenshot kosong/error
```bash
# Jalankan dengan debug
npx playwright test tests/playwright/screenshot-all-pages.spec.js --debug
```

### Port 8000 sudah dipakai
Edit script, ubah `BASE_URL`:
```javascript
const BASE_URL = 'http://localhost:8001'; // Ubah port
```

## 💡 Tips Hasil Maksimal

1. **Persiapkan Data Bagus**
   ```bash
   php artisan migrate:fresh --seed
   ```

2. **Upload Gambar Produk Berkualitas**
   - Minimal 1200x1200 px
   - Format JPG/PNG

3. **Resolusi Optimal**
   - Default: 1920x1080 (Full HD)
   - Untuk laporan: Perfect!

4. **Waktu Eksekusi**
   - Lengkap: 10-15 menit
   - Simple: 5-8 menit
   - Jangan ganggu saat proses!

## 📊 Untuk Laporan Skripsi

### BAB 4 - Implementasi (UI/UX)
Gunakan screenshot:
- 01-home-page.png
- 09-login-page.png
- 18-seller-dashboard.png
- 25-admin-dashboard.png

### BAB 5 - Pengujian
Gunakan semua screenshot sebagai bukti testing blackbox.

### Lampiran
Sisipkan semua 36 screenshot sebagai dokumentasi lengkap.

## 🎓 Format Referensi di Dokumen

```
Gambar 4.1 Halaman Utama Vinstore
Sumber: Screenshot Aplikasi Vinstore (2026)
```

## 📝 Checklist Sebelum Screenshot

- [ ] Server Laravel running (`php artisan serve`)
- [ ] Database sudah ada data (produk, toko, dll)
- [ ] User testing sudah dibuat (jalankan seeder)
- [ ] Folder screenshot sudah dibuat
- [ ] Playwright sudah terinstall
- [ ] Port 8000 tidak dipakai aplikasi lain

## 🆘 Bantuan

Jika ada masalah:
1. Cek `docs/SCREENSHOT_GUIDE.md` - Panduan lengkap
2. Lihat log error di terminal
3. Jalankan dengan `--debug` untuk troubleshoot

---

**Selamat mengerjakan skripsi! 🎓📚**
