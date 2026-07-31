# Fitur Pembayaran Tambahan untuk Barter

## Overview
Modifikasi pada fitur barter agar seller yang produknya lebih murah wajib menambah pembayaran sesuai dengan selisih harga. Perhitungan dilakukan otomatis oleh sistem.

## Perubahan yang Dilakukan

### 1. Backend - BarterController
**File**: `app/Http/Controllers/BarterController.php`

**Metode**: `store(Request $request, Product $product)`

**Perubahan**:
- Menghapus validasi manual untuk `additional_cash`
- Menambahkan perhitungan otomatis selisih harga
- Formula: `additional_cash = max(0, requested_price - offered_price)`
- Jika produk yang ditawarkan lebih murah, selisih harga akan disimpan sebagai `additional_cash`
- Jika produk yang ditawarkan lebih mahal atau sama, `additional_cash = 0`

**Kode**:
```php
// Hitung selisih harga otomatis
$offeredPrice = (float) $offeredProduct->price;
$requestedPrice = (float) $requestedProduct->price;
$additionalCash = max(0, $requestedPrice - $offeredPrice);

BarterRequest::create([
    // ... fields lain
    'additional_cash' => $additionalCash,
]);

// Notifikasi berbeda berdasarkan apakah ada pembayaran tambahan
if ($additionalCash > 0) {
    return back()->with('success', 'Pengajuan barter berhasil dikirim. Anda perlu membayar tambahan ' . number_format($additionalCash, 0, ',', '.') . ' jika barter disetujui.');
}
```

### 2. Frontend - Barter Index UI
**File**: `resources/js/Pages/seller/barter/index.jsx`

#### A. OfferModal (Modal Pengajuan Barter)

**Perubahan**:
1. **Menghapus input manual `additional_cash`**
   - Field input number untuk tambahan uang dihapus
   - Perhitungan sepenuhnya otomatis

2. **Tampilan Selisih Harga**
   - Badge kuning (⚠️) jika produk seller lebih murah (ada pembayaran tambahan)
   - Badge hijau (✅) jika produk seller lebih mahal atau sama
   - Menampilkan selisih harga dalam format IDR yang besar dan jelas

3. **Konfirmasi Dialog**
   - Muncul dialog konfirmasi sebelum submit jika ada pembayaran tambahan
   - User harus setuju untuk melanjutkan pengajuan

**Kode**:
```jsx
const priceDiff = offered != null ? Number(target.price) - Number(offered.price) : 0;
const additionalCash = Math.max(0, priceDiff);

// Konfirmasi jika ada pembayaran tambahan
if (additionalCash > 0) {
  const confirmMsg = `Anda akan menambah pembayaran sebesar ${formatIDR(additionalCash)} karena produk yang Anda tawarkan lebih murah. Lanjutkan?`;
  if (!confirm(confirmMsg)) {
    return;
  }
}
```

**UI Badge**:
```jsx
{offered && priceDiff !== 0 && (
  <div className={`rounded-lg border p-4 ${
    priceDiff > 0 
      ? 'border-yellow-300 bg-yellow-50'  // Ada pembayaran
      : 'border-green-300 bg-green-50'    // Tidak ada pembayaran
  }`}>
    <div className="flex items-center gap-2 mb-2">
      <span className="text-2xl">{priceDiff > 0 ? '💰' : '✅'}</span>
      <span className="font-semibold text-gray-900">
        {priceDiff > 0 ? 'Pembayaran Tambahan Diperlukan' : 'Produk Anda Lebih Mahal'}
      </span>
    </div>
    <p className="text-sm text-gray-700 mb-2">
      Selisih harga: <span className="font-bold text-lg">{formatIDR(Math.abs(priceDiff))}</span>
    </p>
    {/* ... pesan informasi */}
  </div>
)}
```

#### B. RequestCard (Kartu Permintaan Barter)

**Perubahan**:
1. **Badge Pembayaran Tambahan**
   - Ditampilkan dengan border kuning dan background kuning muda
   - Icon 💰 untuk visual yang jelas
   - Menjelaskan siapa yang harus membayar

2. **Informasi Kontekstual**
   - Jika incoming request: "Seller pengaju harus membayar tambahan ini jika Anda setujui"
   - Jika outgoing request: "Anda harus membayar tambahan ini jika barter disetujui"

**Kode**:
```jsx
{additionalCash > 0 && (
  <div className="mt-4 rounded-lg border border-yellow-300 bg-yellow-50 p-3">
    <div className="flex items-center gap-2">
      <span className="text-xl">💰</span>
      <div>
        <p className="text-sm font-semibold text-gray-900">
          Pembayaran Tambahan: {formatIDR(additionalCash)}
        </p>
        <p className="text-xs text-gray-600">
          {counterpartLabel === 'Dari' 
            ? 'Seller pengaju harus membayar tambahan ini jika Anda setujui'
            : 'Anda harus membayar tambahan ini jika barter disetujui'
          }
        </p>
      </div>
    </div>
  </div>
)}
```

## Alur Kerja (Workflow)

### Untuk Seller Pengaju:
1. Buka halaman Barter (`/seller/barter`)
2. Tab "Produk Tersedia" → pilih produk yang diinginkan
3. Klik "Ajukan Barter"
4. Pilih produk sendiri yang akan ditawarkan
5. **Sistem otomatis menghitung selisih harga**:
   - Jika produk Anda lebih murah → Badge kuning "Pembayaran Tambahan Diperlukan"
   - Jika produk Anda lebih mahal → Badge hijau "Produk Anda Lebih Mahal"
6. Isi catatan (opsional)
7. Klik "Kirim Pengajuan"
8. **Jika ada pembayaran tambahan**: Dialog konfirmasi muncul
9. Setelah disetujui, cek tab "Permintaan Saya" untuk melihat status

### Untuk Seller Responder (Penerima Request):
1. Buka halaman Barter → Tab "Permintaan Masuk"
2. Lihat detail pengajuan barter
3. **Badge pembayaran tambahan** akan muncul jika seller pengaju harus bayar tambahan
4. Klik "Setujui" atau "Tolak"
5. Jika disetujui, kepemilikan produk akan ditukar

## Contoh Perhitungan

### Contoh 1: Produk Seller Lebih Murah
- Produk yang ditawarkan: Rp 100.000
- Produk yang diminta: Rp 150.000
- **Additional Cash**: Rp 50.000 (seller pengaju harus bayar tambahan)

### Contoh 2: Produk Seller Lebih Mahal
- Produk yang ditawarkan: Rp 200.000
- Produk yang diminta: Rp 150.000
- **Additional Cash**: Rp 0 (tidak ada pembayaran tambahan)

### Contoh 3: Harga Sama
- Produk yang ditawarkan: Rp 150.000
- Produk yang diminta: Rp 150.000
- **Additional Cash**: Rp 0 (tidak ada pembayaran tambahan)

## Database Schema

Tabel `barter_requests` sudah memiliki kolom:
```sql
additional_cash DECIMAL(12, 2) DEFAULT 0
```

Kolom ini menyimpan jumlah pembayaran tambahan yang harus dibayar seller pengaju.

## Fitur yang Belum Diimplementasikan

⚠️ **Pembayaran Actual via Midtrans**: 
Saat ini `additional_cash` hanya dicatat di database dan ditampilkan sebagai informasi. Implementasi pembayaran aktual menggunakan Midtrans untuk `additional_cash` memerlukan:
- Tabel payment/transaction tersendiri untuk barter
- Integrasi webhook Midtrans
- Validasi pembayaran sebelum kepemilikan produk ditukar
- Flow pembayaran yang kompleks

Untuk saat ini, `additional_cash` berfungsi sebagai **informasi kesepakatan** antara dua seller.

## Screenshots & UI Preview

### Modal Pengajuan Barter
**Skenario 1: Ada Pembayaran Tambahan**
- Badge kuning dengan icon 💰
- Text: "Pembayaran Tambahan Diperlukan"
- Menampilkan selisih harga dalam font besar
- Peringatan: "⚠️ Produk Anda lebih murah. Anda harus membayar tambahan sebesar..."

**Skenario 2: Tidak Ada Pembayaran**
- Badge hijau dengan icon ✅
- Text: "Produk Anda Lebih Mahal"
- Info: "✨ Produk Anda lebih mahal. Tidak ada pembayaran tambahan diperlukan."

### Kartu Permintaan (Incoming/Outgoing)
- Badge kuning prominent jika ada `additional_cash > 0`
- Icon 💰 + Nominal pembayaran
- Penjelasan kontekstual siapa yang harus bayar

## Files Modified
1. `app/Http/Controllers/BarterController.php` (Backend logic)
2. `resources/js/Pages/seller/barter/index.jsx` (Frontend UI)

## Testing Checklist
- [x] Perhitungan selisih harga otomatis bekerja
- [x] UI menampilkan badge yang sesuai (kuning/hijau)
- [x] Konfirmasi dialog muncul jika ada pembayaran
- [x] Info pembayaran ditampilkan di RequestCard
- [x] Build frontend berhasil tanpa error
- [ ] Integrasi pembayaran Midtrans (belum diimplementasikan)

## Notes
1. Sistem ini mendorong barter yang "fair" dengan transparansi harga
2. Seller dengan produk lebih murah harus menambah pembayaran untuk menyeimbangkan nilai
3. Implementasi saat ini fokus pada **informasi dan transparansi**, bukan enforcement pembayaran

---
**Tanggal**: 27 Juli 2026 16:20
**Status**: ✅ Completed (Core Feature)
**Pending**: Integrasi pembayaran Midtrans
