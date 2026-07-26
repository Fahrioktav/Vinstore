# Panduan Mengambil Screenshot untuk Laporan Skripsi

Script Playwright ini dibuat untuk mengotomasi pengambilan screenshot dari semua halaman penting di aplikasi Vinstore untuk keperluan dokumentasi laporan skripsi.

## 📋 Persiapan

### 1. Pastikan Dependencies Terinstall

```bash
npm install
npx playwright install
```

### 2. Setup Database dan User Testing

Script sudah dikonfigurasi menggunakan akun yang ada:

**User Buyer:**
- Email: `asep@example.com`
- Password: `password123`
- Role: `user`

**User Seller:**
- Email: `cikidaw@gmail.com`
- Password: `daw123`
- Role: `seller`
- **Penting**: Pastikan akun ini sudah memiliki toko!

**User Validator:**
- Email: `validator@gmail.com`
- Password: `password123`
- Role: `validator`

**User Admin:**
- Email: `adminganteng@gmail.com`
- Password: `password123`
- Role: `admin`

### 3. Jalankan Server Laravel

```bash
php artisan serve
```

Pastikan server berjalan di `http://localhost:8000`

## 🚀 Cara Menjalankan

### Opsi 1: Menjalankan Semua Screenshot (Headless)

```bash
npx playwright test tests/playwright/screenshot-all-pages.spec.js
```

### Opsi 2: Menjalankan dengan Browser Terlihat (Recommended)

```bash
npx playwright test tests/playwright/screenshot-all-pages.spec.js --headed
```

### Opsi 3: Menjalankan Test Spesifik

Jika hanya ingin screenshot halaman tertentu:

```bash
# Hanya halaman public
npx playwright test tests/playwright/screenshot-all-pages.spec.js --grep "Home|Login|Register"

# Hanya halaman admin
npx playwright test tests/playwright/screenshot-all-pages.spec.js --grep "Admin"

# Hanya halaman seller
npx playwright test tests/playwright/screenshot-all-pages.spec.js --grep "Seller"
```

### Opsi 4: Debug Mode

Jika ada masalah:

```bash
npx playwright test tests/playwright/screenshot-all-pages.spec.js --debug
```

## 📁 Hasil Screenshot

Semua screenshot akan disimpan di:

```
public/screenshots/laporan-skripsi/
```

Format nama file:
- `01-home-page.png` - Halaman utama
- `02-products-list.png` - Halaman daftar produk
- `03-product-detail.png` - Detail produk
- dst...

Total: **36 screenshot** mencakup semua fitur utama aplikasi.

## 📸 Daftar Halaman yang Di-screenshot

### Halaman Public (1-11)
1. ✅ Home/Landing Page
2. ✅ Daftar Produk
3. ✅ Detail Produk
4. ✅ Daftar Toko
5. ✅ Detail Toko
6. ✅ Daftar Lelang
7. ✅ Detail Lelang
8. ✅ Contact
9. ✅ Login
10. ✅ Register
11. ✅ Forgot Password

### Halaman User/Buyer (12-17)
12. ✅ Profil User
13. ✅ Keranjang Belanja
14. ✅ Checkout
15. ✅ Riwayat Order
16. ✅ Bantuan/Support
17. ✅ Register Toko

### Halaman Seller (18-22)
18. ✅ Dashboard Seller
19. ✅ Edit Toko
20. ✅ Tambah Produk
21. ✅ Barter
22. ✅ Buat Lelang

### Halaman Validator (23-24)
23. ✅ Dashboard Validator
24. ✅ Detail Validasi Produk

### Halaman Admin (25-36)
25. ✅ Dashboard Admin
26. ✅ Kelola Users
27. ✅ Kelola Sellers
28. ✅ Kelola Toko
29. ✅ Kelola Produk
30. ✅ Kelola Orders
31. ✅ Kelola Lelang
32. ✅ Kelola Refund
33. ✅ Kelola Pencairan
34. ✅ Kelola Kategori
35. ✅ Kelola Pesan Contact
36. ✅ Bantuan Admin

## ⚙️ Kustomisasi

### Mengubah Resolusi Screenshot

Edit file `screenshot-all-pages.spec.js`:

```javascript
test.use({
  viewport: { width: 1920, height: 1080 }, // Ubah di sini
});
```

Resolusi yang disarankan:
- **1920x1080** (Full HD) - Default, bagus untuk laporan
- **1366x768** (HD) - Ukuran laptop standar
- **1440x900** (MacBook) - Jika ingin tampilan MacBook

### Mengubah Lokasi Penyimpanan

Edit variabel `SCREENSHOT_DIR`:

```javascript
const SCREENSHOT_DIR = path.join(__dirname, '../../public/screenshots/laporan-skripsi');
```

### Menambah Screenshot Halaman Baru

Tambahkan test baru:

```javascript
test('37 - Nama Halaman Baru', async ({ page }) => {
  await login(page, 'admin'); // Jika perlu login
  await page.goto(`${BASE_URL}/path/halaman`);
  await takeScreenshot(page, '37-nama-file');
});
```

## 🔧 Troubleshooting

### Screenshot Kosong/Gagal
- Pastikan server Laravel running di port 8000
- Pastikan database sudah di-seed dengan user testing
- Coba jalankan dengan `--headed` untuk melihat prosesnya

### User Login Gagal
- Cek kredensial:
  - Buyer: asep@example.com / password123
  - Seller: cikidaw@gmail.com / daw123
  - Validator: validator@gmail.com / password123
  - Admin: adminganteng@gmail.com / password123
- Pastikan user memiliki role yang benar
- Coba login manual dulu melalui browser

### Halaman Tidak Ditemukan
- Pastikan semua route ada dan aktif
- Cek apakah ada middleware yang memblokir akses
- Pastikan database memiliki data (produk, toko, dll)

### Screenshot Terpotong
- Ubah `fullPage` menjadi `true` di fungsi `takeScreenshot()`
- Tambah waktu tunggu: `await page.waitForTimeout(2000);`

## 💡 Tips

1. **Persiapkan Data Dummy yang Bagus**
   - Upload produk dengan foto berkualitas
   - Buat toko dengan deskripsi lengkap
   - Isi data yang realistis untuk screenshot bagus

2. **Jalankan di Jam Tidak Sibuk**
   - Proses screenshot memakan waktu ~5-10 menit
   - Jangan gunakan server untuk hal lain saat screenshot

3. **Review Hasil Screenshot**
   - Cek semua screenshot setelah selesai
   - Pastikan tidak ada error page
   - Pastikan semua elemen terlihat jelas

4. **Untuk Laporan Skripsi**
   - Screenshot sudah di-optimize untuk resolusi Full HD
   - File PNG berkualitas tinggi, siap untuk dokumentasi
   - Nama file berurutan memudahkan penyusunan di dokumen

## 📝 Catatan

- Screenshot diambil dengan resolusi **1920x1080** (Full HD)
- Animasi di-disable untuk konsistensi
- Full page screenshot untuk tangkap semua konten
- Menggunakan `networkidle` untuk memastikan halaman sudah load sempurna

## 🎓 Untuk Laporan Skripsi

Screenshot ini siap digunakan untuk:
- BAB 4: Implementasi (tampilan antarmuka sistem)
- BAB 5: Pengujian (dokumentasi hasil testing)
- Lampiran: Dokumentasi lengkap aplikasi

Semua screenshot sudah diberi nomor urut dan nama yang deskriptif untuk memudahkan referensi dalam dokumen.

---

**Dibuat oleh:** Fahri Octavian  
**Untuk:** Laporan Skripsi - Sistem E-Commerce Barang Antik Vinstore  
**Tanggal:** 26 Juli 2026
