# Dokumentasi Sistem Pembayaran Barter

## Overview
Sistem pembayaran untuk selisih harga pada fitur barter telah ditambahkan. Ketika dua seller melakukan barter produk dengan harga berbeda, seller yang memiliki produk dengan harga lebih murah harus membayar selisih harga melalui Midtrans sebelum kepemilikan produk ditukar.

## Alur Kerja

### 1. Pengajuan Barter
- Seller A mengajukan barter produk miliknya dengan produk Seller B
- Sistem otomatis menghitung selisih harga:
  - Jika harga produk Seller A < harga produk Seller B
  - Maka `additional_cash` = harga produk Seller B - harga produk Seller A
  - Seller A harus membayar `additional_cash` jika barter disetujui

### 2. Persetujuan Barter
- Seller B menerima pengajuan barter
- Jika **tidak ada** `additional_cash` (harga sama atau produk Seller A lebih mahal):
  - ✅ Kepemilikan produk **langsung ditukar**
  - Status barter: `accepted`
  - Payment status: `not_required`

- Jika **ada** `additional_cash` (produk Seller A lebih murah):
  - ⏳ Kepemilikan produk **belum ditukar**
  - Status barter: `accepted`
  - Payment status: `pending`
  - Seller A harus melakukan pembayaran untuk menyelesaikan barter

### 3. Pembayaran (jika diperlukan)
- Seller A (requester) membuka halaman barter
- Muncul tombol **"💳 Bayar Sekarang"** dengan animasi pulse
- Klik tombol tersebut akan membuka halaman pembayaran Midtrans
- Midtrans Snap popup otomatis muncul
- Seller A menyelesaikan pembayaran

### 4. Callback dari Midtrans
- Midtrans mengirim notifikasi ke endpoint: `/midtrans/barter/notification`
- Controller `BarterPaymentNotificationController` memproses notifikasi
- Jika pembayaran **berhasil** (`paid`):
  - ✅ Kepemilikan kedua produk **ditukar**
  - Payment status: `paid`
  - Timestamp `paid_at` dicatat
  - Pengajuan barter pending lain yang melibatkan kedua produk dibatalkan otomatis

- Jika pembayaran **gagal/expired** (`failed`, `expired`):
  - ❌ Kepemilikan produk **belum ditukar**
  - Payment status: `failed` atau `expired`
  - Seller A bisa mencoba pembayaran lagi

## File yang Dimodifikasi/Dibuat

### 1. Database Migration
**File:** `database/migrations/2026_07_27_000001_add_payment_fields_to_barter_requests_table.php`

Kolom baru di tabel `barter_requests`:
- `payment_status`: enum ('not_required', 'pending', 'paid', 'failed', 'expired')
- `payment_reference`: reference ID untuk tracking (format: `BARTER-{public_id}-{timestamp}`)
- `snap_token`: token dari Midtrans untuk Snap popup
- `midtrans_transaction_id`: transaction ID dari Midtrans
- `paid_at`: timestamp kapan pembayaran selesai

### 2. Model
**File:** `app/Models/BarterRequest.php`

**Konstanta Payment Status:**
```php
public const PAYMENT_NOT_REQUIRED = 'not_required';
public const PAYMENT_PENDING = 'pending';
public const PAYMENT_PAID = 'paid';
public const PAYMENT_FAILED = 'failed';
public const PAYMENT_EXPIRED = 'expired';
```

**Helper Methods:**
- `requiresPayment()`: Cek apakah ada additional_cash
- `isPaymentCompleted()`: Cek apakah payment sudah selesai atau tidak diperlukan
- `isReadyToExchange()`: Cek apakah barter siap untuk tukar produk

### 3. Controller
**File:** `app/Http/Controllers/BarterController.php`

**Method yang Dimodifikasi:**
- `accept()`: Cek apakah ada payment, jika ya set status pending dan tunggu payment, jika tidak langsung tukar produk

**Method Baru:**
- `pay()`: Generate Midtrans Snap Token dan render halaman pembayaran
  - URL: `GET /seller/barter/{barter}/pay`
  - Validasi: hanya requester yang bisa bayar
  - Generate atau reuse snap token
  - Render view `seller/barter/payment`

**Helper Methods Baru:**
- `exchangeProducts()`: Tukar kepemilikan produk setelah barter selesai
- `cancelOtherPendingRequests()`: Batalkan pengajuan pending lain yang melibatkan produk yang sama

### 4. Webhook Controller
**File:** `app/Http/Controllers/BarterPaymentNotificationController.php`

Controller baru untuk handle webhook dari Midtrans:
- Validasi signature Midtrans
- Update payment status berdasarkan notifikasi
- Jika payment berhasil: tukar kepemilikan produk dalam transaction
- Jika payment gagal: update status saja
- Logging untuk tracking

### 5. Routes
**File:** `routes/web.php`

Routes baru:
```php
// Seller routes
Route::get('/barter/{barter}/pay', [BarterController::class, 'pay'])->name('barter.pay');

// Public webhook (no auth)
Route::post('/midtrans/barter/notification', BarterPaymentNotificationController::class)
    ->name('midtrans.barter.notification');
```

**File:** `bootstrap/app.php`

CSRF exception untuk webhook:
```php
$middleware->validateCsrfTokens(except: [
    'midtrans/notification',
    'midtrans/barter/notification', // ← baru
]);
```

### 6. Frontend Views
**File:** `resources/js/pages/seller/barter/index.jsx`

**Perubahan:**
- Tambah `PaymentStatusBadge` component untuk menampilkan status payment
- Update `RequestCard` untuk menampilkan info payment dengan warna dinamis
- Update `OutgoingTab` untuk menampilkan tombol **"💳 Bayar Sekarang"** jika:
  - Status barter: `accepted`
  - Payment status: `pending`
- Tombol payment memiliki animasi `animate-pulse` agar menarik perhatian

**File:** `resources/js/pages/seller/barter/payment.jsx` (BARU)

Halaman pembayaran dengan Midtrans Snap:
- Menampilkan detail barter (kedua produk yang akan ditukar)
- Menampilkan breakdown harga dan selisih
- Tombol pembayaran yang trigger Midtrans Snap popup
- Auto-trigger Snap popup 500ms setelah halaman load
- Handle callback: success, pending, error, close

## Konfigurasi

### Environment Variables
Pastikan variabel berikut sudah diset di `.env`:

```env
MIDTRANS_SERVER_KEY=your-server-key
MIDTRANS_CLIENT_KEY=your-client-key
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_SNAP_URL=https://app.sandbox.midtrans.com/snap/v1/transactions
MIDTRANS_API_URL=https://api.sandbox.midtrans.com
```

### Midtrans Dashboard
Pastikan webhook URL sudah dikonfigurasi di Midtrans Dashboard:
```
https://your-domain.com/midtrans/barter/notification
```

## Testing

### Scenario 1: Barter Tanpa Pembayaran (Harga Sama/Lebih Mahal)
1. Login sebagai Seller A
2. Ajukan barter dengan produk yang harga sama atau lebih mahal dari produk target
3. Login sebagai Seller B
4. Terima barter
5. ✅ Produk langsung tertukar, tidak perlu pembayaran

### Scenario 2: Barter Dengan Pembayaran (Harga Lebih Murah)
1. Login sebagai Seller A
2. Ajukan barter dengan produk yang harganya lebih murah dari produk target
3. Sistem menampilkan warning: "Anda perlu membayar tambahan Rp X jika barter disetujui"
4. Lanjutkan pengajuan
5. Login sebagai Seller B
6. Terima barter
7. Sistem menampilkan: "Requester harus melakukan pembayaran Rp X"
8. Login kembali sebagai Seller A
9. Tab "Permintaan Saya" menampilkan tombol **"💳 Bayar Sekarang"**
10. Klik tombol tersebut
11. Halaman payment terbuka, Midtrans Snap popup muncul otomatis
12. Pilih metode pembayaran (simulasi sandbox)
13. Selesaikan pembayaran
14. ✅ Produk otomatis tertukar setelah payment berhasil

### Testing Webhook Locally
Gunakan ngrok atau tunneling service untuk expose local server:
```bash
ngrok http 8000
```

Update webhook URL di Midtrans Dashboard dengan URL ngrok.

## Security

1. **Signature Validation**: Semua notifikasi dari Midtrans divalidasi signature-nya menggunakan server key
2. **CSRF Protection**: Route webhook dikecualikan dari CSRF karena berasal dari server eksternal
3. **Authorization Check**: 
   - Hanya requester yang bisa membayar
   - Hanya responder yang bisa accept/reject
4. **Database Transaction**: Tukar kepemilikan produk menggunakan DB transaction dengan row locking untuk mencegah race condition

## Troubleshooting

### Payment tidak ter-update setelah bayar / Stuck "Memproses..."

**Penyebab:**
Masalah ini terjadi ketika webhook dari Midtrans belum sampai atau lambat diproses, sehingga database belum terupdate.

**Solusi:**

#### Solusi 1: Polling Status (Sudah Diimplementasikan)
Frontend sekarang sudah dilengkapi dengan mekanisme polling otomatis:
- Setelah payment success, sistem akan mengecek status payment setiap 3 detik
- Maksimal 30 detik (10 kali pengecekan)
- Jika status sudah `paid`, otomatis redirect ke halaman barter

#### Solusi 2: Manual Check via Endpoint
Jika masih stuck, user bisa refresh halaman atau klik tombol "Bayar Sekarang" lagi. Sistem akan:
- Memanggil endpoint `/seller/barter/{barter}/payment-status`
- Query status ke Midtrans API langsung
- Update database jika payment sudah berhasil di Midtrans tapi belum ter-sync

#### Solusi 3: Testing Webhook di Local (Developer)

**A. Menggunakan Test Command:**
```bash
# Cari payment_reference dari database
php artisan tinker
>>> $barter = \App\Models\BarterRequest::where('status', 'accepted')->first();
>>> $barter->payment_reference;
# Output: BARTER-BRT12345678-1722074400

# Simulasi webhook success
php artisan test:barter-webhook BARTER-BRT12345678-1722074400 settlement

# Simulasi webhook failed
php artisan test:barter-webhook BARTER-BRT12345678-1722074400 deny
```

**B. Menggunakan ngrok (Untuk testing dengan Midtrans Sandbox):**
```bash
# Install ngrok
# https://ngrok.com/download

# Jalankan ngrok
ngrok http 8000

# Copy URL ngrok (contoh: https://abc123.ngrok.io)
# Set di Midtrans Dashboard > Settings > Configuration > Payment Notification URL:
https://abc123.ngrok.io/midtrans/barter/notification

# Sekarang webhook dari Midtrans akan sampai ke local
```

**C. Cek Log Laravel:**
```bash
# Lihat log real-time
tail -f storage/logs/laravel.log

# Cari error terkait barter payment
grep -i "barter" storage/logs/laravel.log
grep -i "payment" storage/logs/laravel.log
```

### Webhook tidak sampai ke server

**Penyebab:**
- Route webhook diblokir firewall/web server
- CSRF protection tidak dikonfigurasi dengan benar
- Midtrans Dashboard webhook URL salah

**Solusi:**
1. Pastikan route `/midtrans/barter/notification` bisa diakses dari luar:
   ```bash
   curl -X POST https://your-domain.com/midtrans/barter/notification \
     -H "Content-Type: application/json" \
     -d '{"order_id": "test"}'
   ```

2. Cek CSRF exception di `bootstrap/app.php`:
   ```php
   $middleware->validateCsrfTokens(except: [
       'midtrans/notification',
       'midtrans/barter/notification', // ← Harus ada
   ]);
   ```

3. Cek webhook URL di Midtrans Dashboard:
   - Login ke dashboard.midtrans.com (sandbox) atau dashboard.midtrans.com (production)
   - Settings > Configuration
   - Payment Notification URL: `https://your-domain.com/midtrans/barter/notification`
   - Jangan lupa **Save**

### Produk tidak ditukar meskipun payment_status = paid

**Penyebab:**
- Error saat proses exchange di webhook controller
- Produk sudah dihapus
- Race condition

**Solusi:**
1. Cek log error:
   ```bash
   grep "Barter products exchanged" storage/logs/laravel.log
   grep "Error processing barter payment" storage/logs/laravel.log
   ```

2. Manual exchange via tinker (emergency):
   ```php
   php artisan tinker
   
   >>> use App\Models\BarterRequest;
   >>> use App\Models\Product;
   >>> use Illuminate\Support\Facades\DB;
   
   >>> $barter = BarterRequest::where('public_id', 'BRT12345678')->first();
   >>> 
   >>> DB::transaction(function() use ($barter) {
   ...     $offered = Product::find($barter->offered_product_id);
   ...     $requested = Product::find($barter->requested_product_id);
   ...     
   ...     $temp = $offered->store_id;
   ...     $offered->store_id = $requested->store_id;
   ...     $requested->store_id = $temp;
   ...     
   ...     $offered->is_barterable = false;
   ...     $requested->is_barterable = false;
   ...     
   ...     $offered->save();
   ...     $requested->save();
   ... });
   ```

### Snap popup tidak muncul
- Cek apakah Midtrans Snap script sudah di-load di `resources/views/app.blade.php`
- Buka console browser untuk cek error JavaScript
- Pastikan `MIDTRANS_CLIENT_KEY` sudah benar

### Produk tidak ditukar meskipun sudah bayar
- Cek log error di `storage/logs/laravel.log`
- Cek payment_status di database: `SELECT * FROM barter_requests WHERE public_id = 'BRT...'`
- Pastikan webhook berhasil diproses (status code 200)

## Future Improvements

1. **Auto-cancel barter jika payment expired**: Tambah scheduled job untuk cancel barter yang payment-nya expired > 24 jam
2. **Email notification**: Kirim email ke requester ketika payment diperlukan
3. **Payment reminder**: Reminder otomatis jika payment belum diselesaikan setelah X hari
4. **Refund mechanism**: Jika responder cancel barter setelah payment, berikan refund otomatis
5. **Payment history**: Halaman riwayat pembayaran barter untuk seller

## Support

Jika ada pertanyaan atau issue, silakan buka issue di repository atau hubungi developer.
