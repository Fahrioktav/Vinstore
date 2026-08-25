# Setup Google Login & Forgot Password

## 🔐 Fitur yang Ditambahkan

### 1. **Google OAuth Login**
- Login menggunakan akun Google
- Auto-create user baru jika belum terdaftar
- Sinkronisasi data user dari Google

### 2. **Forgot Password**
- Reset password via email
- Link reset password yang aman dengan token
- Validasi token expiration

---

## ⚙️ Setup Google OAuth

### Step 1: Buat Google OAuth Credentials

1. Buka [Google Cloud Console](https://console.cloud.google.com/)
2. Buat project baru atau pilih project yang ada
3. Navigate ke **APIs & Services** → **Credentials**
4. Klik **Create Credentials** → **OAuth 2.0 Client ID**
5. Pilih **Web Application**
6. Tambahkan **Authorized redirect URIs**:
   ```
   http://localhost:8000/auth/google/callback
   http://127.0.0.1:8000/auth/google/callback
   ```
   Untuk production, tambahkan URL deploy yang persis sama, misalnya:
   ```
   https://domain-anda.com/auth/google/callback
   ```
   Jangan memakai path yang berbeda, trailing slash berbeda, atau host yang masih localhost.

7. Copy **Client ID** dan **Client Secret**

### Step 2: Update File `.env`

Tambahkan credentials Google OAuth ke file `.env`:

```env
# Google OAuth
GOOGLE_CLIENT_ID=your-google-client-id-here
GOOGLE_CLIENT_SECRET=your-google-client-secret-here
GOOGLE_REDIRECT_URI=${APP_URL}/auth/google/callback
```

Setelah deploy dan mengubah `.env`, jalankan:
```bash
php artisan config:clear
php artisan cache:clear
```

Pastikan `APP_URL` sudah mengarah ke domain production, bukan `http://localhost:8000`.

---

## 📧 Setup Email untuk Forgot Password

### Option 1: Gmail SMTP (Untuk Testing)

**PENTING**: Untuk Gmail, Anda perlu menggunakan [App Password](https://myaccount.google.com/apppasswords), bukan password biasa.

Update `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password-here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Option 2: Mailtrap (Untuk Development)

1. Buat account di [Mailtrap.io](https://mailtrap.io/)
2. Copy credentials dari inbox Anda

Update `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@vinstore.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Option 3: Production Email Service

Untuk production, gunakan:
- **SendGrid**
- **Mailgun**
- **Amazon SES**
- **Postmark**

---

## 🚀 Testing

### Test Google Login:
1. Jalankan server: `php artisan serve`
2. Buka: `http://localhost:8000/login`
3. Klik tombol **"Masuk dengan Google"**
4. Pilih akun Google
5. Akan otomatis login dan redirect ke dashboard

### Test Forgot Password:
1. Buka: `http://localhost:8000/login`
2. Klik **"Lupa Password?"**
3. Masukkan email yang terdaftar
4. Cek inbox email untuk link reset
5. Klik link dan masukkan password baru

---

## 📱 Flow Diagram

### Google OAuth Flow:
```
User -> Klik "Login with Google"
     -> Redirect ke Google
     -> User pilih akun
     -> Google callback
     -> Cari user berdasarkan google_id  (HANYA google_id)
        ketemu                                   -> login
        tidak ketemu, email dipakai akun lain    -> DITOLAK
        tidak ketemu, email belum terdaftar      -> buat akun baru -> login
```

**Pencocokan lewat alamat email sudah dihapus** (temuan T-01). Aplikasi ini tidak pernah memverifikasi email saat pendaftaran biasa, sehingga siapa pun bisa mendaftar memakai alamat orang lain — dan ketika pemilik asli alamat itu menekan "Login with Google", ia justru masuk ke akun si pendaftar yang masih memegang passwordnya.

### Menautkan Google ke akun lama:
```
User -> Login pakai email & password
     -> Halaman Profil -> "Hubungkan Akun Google"
     -> Redirect ke Google -> pilih akun
     -> google_id disimpan ke akun yang sedang login
```

Melepas tautannya juga dari halaman Profil, dan **ditolak bila akun itu belum punya password** — akun yang lahir dari Google tidak punya password sama sekali, jadi melepas tautannya berarti mengunci pemiliknya di luar.

### Forgot Password Flow:
```
Tautan "Lupa Password?" di halaman login SENGAJA DISEMBUNYIKAN
selama MAIL_MAILER masih 'log'.

Pemulihan ditempuh lewat admin:
  Pengguna menghubungi admin (mis. lewat halaman /contact)
  -> Admin: Kelola User -> "Tautan Reset Password"
  -> Tautan sekali pakai ditampilkan sekali untuk disalin
  -> Pengguna membuka tautan -> masukkan password baru
  -> Redirect ke login
```

Tautan itu memakai token bawaan Laravel — masa berlaku, sifat sekali-pakai, dan seluruh penjagaannya sama dengan yang biasa dikirim lewat surel. Admin tidak pernah mengetahui password barunya.

Untuk mengembalikan alur surel: arahkan `MAIL_MAILER` ke SMTP sungguhan, lalu hidupkan kembali `<Link href="/forgot-password">` di `resources/js/pages/auth/login.jsx`. Route `password.request` dan `password.email` masih terdaftar (temuan V11-03).

---

## 🔒 Security Notes

1. **Google OAuth**:
   - Gunakan HTTPS di production
   - Jangan commit credentials ke Git
   - Batasi authorized domains di Google Console
   - Akun dicocokkan **hanya** lewat `google_id`; jangan pernah menambahkan kembali pencocokan lewat email selama pendaftaran biasa belum memverifikasi alamat email (temuan T-01)
   - `email_verified` dari Google diperiksa sebelum akun baru dibuat

2. **Password Reset**:
   - Token akan expire setelah 60 menit (default Laravel)
   - Token hanya bisa digunakan sekali
   - Email harus terdaftar di database

---

## 🛠️ Troubleshooting

### Google Login Error "400: redirect_uri_mismatch"
- Pastikan URL di Google Console sama dengan `GOOGLE_REDIRECT_URI` di `.env`
- Cek tidak ada trailing slash

### Email Tidak Terkirim
- Cek konfigurasi MAIL di `.env`
- Test dengan: `php artisan tinker` → `Mail::raw('Test', function($msg) { $msg->to('test@example.com'); });`
- Cek log di `storage/logs/laravel.log`

### Migration Error
- Jalankan: `php artisan migrate:fresh` (WARNING: hapus semua data)
- Atau rollback: `php artisan migrate:rollback` lalu `php artisan migrate`

---

## 📝 Files Created/Modified

### New Files:
- `app/Http/Controllers/Auth/SocialAuthController.php`
- `app/Http/Controllers/Auth/ForgotPasswordController.php`
- `app/Http/Controllers/Auth/ResetPasswordController.php`
- `resources/js/pages/auth/forgot-password.jsx`
- `resources/js/pages/auth/reset-password.jsx`
- `database/migrations/2026_03_01_000001_add_google_id_to_users_table.php`
- `database/migrations/2026_03_01_000002_create_password_reset_tokens_table.php`

### Modified Files:
- `config/services.php` - Added Google OAuth config
- `routes/web.php` - Added auth routes
- `resources/js/pages/auth/login.jsx` - Added Google button & forgot password link

---

## 🎯 Next Steps

Setelah Google API Key tersedia, Anda bisa:
1. ✅ Setup Google OAuth credentials
2. ✅ Update `.env` dengan credentials
3. ✅ Setup email configuration
4. ✅ Test login dengan Google
5. ✅ Test forgot password flow

---

**Need help?** Check Laravel documentation:
- [Socialite](https://laravel.com/docs/12.x/socialite)
- [Password Reset](https://laravel.com/docs/12.x/passwords)
- [Mail](https://laravel.com/docs/12.x/mail)
