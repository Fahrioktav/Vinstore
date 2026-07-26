# 🎯 SIAP DIJALANKAN - Screenshot Vinstore

Script Playwright sudah dikonfigurasi menggunakan akun Anda!

## 🔑 Akun yang Digunakan

✅ **Buyer**: asep@example.com (password123)
✅ **Seller**: cikidaw@gmail.com (daw123)
✅ **Validator**: validator@gmail.com (password123)
✅ **Admin**: adminganteng@gmail.com (password123)

## ⚠️ PENTING - Cek Dulu!

Sebelum menjalankan screenshot, pastikan:

1. **Akun seller (cikidaw@gmail.com) SUDAH PUNYA TOKO**
   - Login sebagai seller
   - Pastikan toko sudah terdaftar
   - Jika belum, register toko dulu

2. **Ada data produk di database**
   - Minimal 3-5 produk untuk screenshot bagus
   - Produk dengan gambar berkualitas

3. **Ada kategori produk**
   - Screenshot admin kategori butuh data

4. **Server Laravel berjalan**
   ```bash
   php artisan serve
   ```

## 🚀 Cara Menjalankan

### Metode Termudah (Windows):
1. Buka Command Prompt/PowerShell
2. Masuk ke folder project: `cd path\to\Vinstore`
3. Pastikan server running: `php artisan serve`
4. Double-click: `run_screenshot.bat`

### Metode Manual:
```bash
# 1. Pastikan server running
php artisan serve

# 2. Buka terminal baru, jalankan screenshot
npx playwright test tests/playwright/screenshot-all-pages.spec.js --headed
```

## 📸 Hasil Screenshot

Semua screenshot (36 file) akan tersimpan di:
```
public/screenshots/laporan-skripsi/
```

## 🎬 Apa yang Akan Terjadi?

Browser Chrome akan terbuka otomatis dan:
1. Membuka setiap halaman satu per satu
2. Login otomatis dengan akun yang sesuai
3. Mengambil screenshot Full HD (1920x1080)
4. Menyimpan dengan nama terstruktur
5. Proses sekitar 10-15 menit

**Jangan sentuh mouse/keyboard saat proses berjalan!**

## 📋 Daftar Screenshot yang Akan Diambil

### Public (7 halaman)
- Home, Products, Stores, Auctions, Contact, Login, Register

### User/Buyer (6 halaman)
- Profile, Cart, Checkout, Orders, Support, Register Toko

### Seller (5 halaman)
- Dashboard, Edit Toko, Add Product, Barter, Create Auction

### Validator (2 halaman)
- Dashboard, Product Detail

### Admin (16 halaman)
- Dashboard, Users, Sellers, Stores, Products, Orders, Auctions, Refunds, Withdrawals, Categories, Contacts, Support

**Total: 36 Screenshot**

## 🔧 Jika Ada Error

### Error Login
```bash
# Cek akun di database
php artisan tinker
>>> User::where('email', 'cikidaw@gmail.com')->first();
```

### Error Seller Dashboard
Pastikan seller punya toko:
```bash
php artisan tinker
>>> $user = User::where('email', 'cikidaw@gmail.com')->first();
>>> $user->store; # Harus ada
```

### Screenshot Kosong
- Tunggu lebih lama (ada delay 1-2 detik per halaman)
- Jalankan dengan `--headed` untuk lihat prosesnya
- Cek log error di terminal

## 💡 Tips Hasil Maksimal

1. **Upload produk dengan foto bagus** sebelum screenshot
2. **Isi data lengkap** di profil, toko, dll
3. **Jangan ganggu browser** saat proses
4. **Gunakan mode `--headed`** untuk monitoring
5. **Review hasil** setelah selesai

## 📞 Bantuan

Jika ada masalah, cek file lengkap:
- `docs/SCREENSHOT_GUIDE.md` - Panduan detail
- `docs/SCREENSHOT_QUICK_REFERENCE.md` - Quick reference

## ✅ Checklist Sebelum Mulai

- [ ] Server Laravel running di http://localhost:8000
- [ ] Akun cikidaw@gmail.com sudah punya toko
- [ ] Ada produk di database (minimal 3-5)
- [ ] Ada kategori produk
- [ ] Playwright sudah terinstall (`npm install`)
- [ ] Port 8000 tidak dipakai aplikasi lain

Jika semua ✅, jalankan: `run_screenshot.bat`

---

**Selamat mengerjakan! 🎓**
