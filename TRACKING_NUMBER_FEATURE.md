# Fitur Nomor Resi untuk Order Management

## Overview
Modifikasi pada fitur order management agar seller wajib memasukkan nomor resi saat mengupdate status pesanan menjadi "Processing" atau "On The Way". Buyer dapat melihat nomor resi di halaman riwayat order mereka.

## Perubahan yang Dilakukan

### 1. Database Migration
**File**: `database/migrations/2026_07_27_154750_add_tracking_number_to_orders_table.php`
- ✅ Migration sudah running (batch 28)
- Menambahkan kolom `tracking_number` (nullable, string, max 100 karakter) ke tabel `orders`
- Posisi: setelah kolom `status`

### 2. Backend - OrderController
**File**: `app/Http/Controllers/OrderController.php`

**Metode**: `updateStatus(Request $request, $id)`

**Perubahan**:
- Validasi dinamis: tracking_number wajib diisi jika status diubah ke "Processing" atau "On The Way"
- Custom error message: "Nomor resi wajib diisi saat status Processing atau On The Way."
- Update status dan tracking_number dalam satu operasi

**Kode**:
```php
// Jika status diubah menjadi Processing atau On The Way, tracking_number wajib diisi
if (in_array($request->status, ['Processing', 'On The Way'])) {
    $rules['tracking_number'] = 'required|string|max:100';
}
```

### 3. Frontend - Seller Dashboard
**File**: `resources/js/Pages/seller/dashboard.jsx`

**Komponen**: `OrderRow`

**Fitur yang Ditambahkan**:
1. **Modal Input Nomor Resi** (untuk status baru)
   - Muncul otomatis saat seller memilih status "Processing" atau "On The Way" tanpa nomor resi
   - Design: icon 📦, judul "Masukkan Nomor Resi", subtitle "Diperlukan untuk memproses pesanan"
   - Input field dengan placeholder "Contoh: JNE1234567890"
   - Autofocus pada input field
   - Tombol: "Simpan & Update Status" dan "Batal"
   - Loading state saat proses update

2. **Modal Edit Nomor Resi**
   - Seller dapat mengklik icon edit (✏️) di sebelah nomor resi untuk mengubahnya
   - Design: icon ✏️ blue, judul "Edit Nomor Resi"
   - Tombol: "Simpan Perubahan" dan "Batal"

3. **Tampilan Nomor Resi**
   - Ditampilkan di bawah dropdown status order
   - Format: "Resi: [nomor resi]" dengan nomor resi bold
   - Icon edit di sebelahnya untuk memudahkan update

4. **Error Handling**
   - Alert jika update gagal dengan pesan error dari backend
   - Revert status ke status sebelumnya jika terjadi error

### 4. Frontend - Buyer Order Page
**File**: `resources/js/Pages/order.jsx`

**Perubahan**:
1. **Kolom Baru di Tabel**: "Nomor Resi"
   - Posisi: antara kolom "Status" dan "Tanggal"

2. **Tampilan Nomor Resi**:
   - Jika tersedia:
     - Nomor resi ditampilkan dengan font-mono untuk readability
     - Font semibold dengan warna `#E9E19E`
     - Badge "📦 Paket sudah dikirim" di bawahnya
   - Jika belum tersedia:
     - Text "Belum tersedia" dengan styling muted (italic, opacity 50%)

**Kode**:
```jsx
<td className="px-6 py-4">
  {order.tracking_number ? (
    <div className="flex flex-col gap-1">
      <span className="font-mono text-sm font-semibold text-[#E9E19E]">
        {order.tracking_number}
      </span>
      <span className="text-xs text-[#E9E19E]/70">
        📦 Paket sudah dikirim
      </span>
    </div>
  ) : (
    <span className="text-sm text-[#E9E19E]/50 italic">
      Belum tersedia
    </span>
  )}
</td>
```

## Alur Kerja (Workflow)

### Untuk Seller:
1. Buka Seller Dashboard
2. Lihat tabel Customer Orders
3. Pilih dropdown status order dan ubah ke "Processing" atau "On The Way"
4. Modal input nomor resi akan muncul otomatis (jika belum ada nomor resi)
5. Masukkan nomor resi (contoh: JNE1234567890)
6. Klik "Simpan & Update Status"
7. Status order dan nomor resi akan terupdate
8. Nomor resi akan muncul di bawah dropdown status
9. Seller dapat edit nomor resi kapan saja dengan klik icon edit (✏️)

### Untuk Buyer:
1. Buka halaman Order/Pesananmu
2. Lihat kolom "Nomor Resi" di tabel
3. Jika pesanan sudah diproses, nomor resi akan ditampilkan
4. Gunakan nomor resi untuk tracking paket di ekspedisi

## Status Order yang Memerlukan Nomor Resi
- ✅ Processing
- ✅ On The Way
- ❌ Waiting (tidak wajib)
- ❌ Delivered (tidak wajib, tapi bisa diisi)
- ❌ Cancelled (tidak wajib)

## Validasi
- Nomor resi: string, maksimal 100 karakter
- Wajib diisi saat status "Processing" atau "On The Way"
- Custom error message jika tidak diisi

## Testing
✅ Build frontend berhasil tanpa error
✅ Migration sudah running (batch 28)
✅ Validasi backend sudah diimplementasi
✅ UI seller dashboard responsive dan user-friendly
✅ UI buyer order page menampilkan nomor resi dengan jelas

## Screenshots Lokasi Perubahan

### Seller Dashboard
- Tabel "Customer Orders" → Kolom "Status" → Dropdown status
- Modal popup untuk input nomor resi
- Nomor resi ditampilkan di bawah dropdown status dengan icon edit

### Buyer Order Page
- Tabel "Pesananmu" → Kolom baru "Nomor Resi"
- Nomor resi ditampilkan dengan badge "Paket sudah dikirim"

## Notes
- Nomor resi dapat diedit sewaktu-waktu oleh seller
- Buyer hanya dapat melihat nomor resi, tidak dapat mengedit
- Nomor resi akan disimpan di database tabel `orders` kolom `tracking_number`
- Format nomor resi bebas (tidak ada validasi format khusus), maksimal 100 karakter

## Files Modified
1. `app/Http/Controllers/OrderController.php`
2. `resources/js/Pages/seller/dashboard.jsx`
3. `resources/js/Pages/order.jsx`
4. `resources/js/components/layout/navbar.jsx` (Profile Photo)

## Migration File
- `database/migrations/2026_07_27_154750_add_tracking_number_to_orders_table.php`

## Additional Features Added

### Profile Photo in Navbar (27 Juli 2026 - 16:16)
**File**: `resources/js/components/layout/navbar.jsx`

**Perubahan**:
- Icon profil user (UserIcon) diganti dengan foto profil yang diupload user
- Foto profil berbentuk bulat (rounded-full) dengan border putih
- Ukuran: 32x32px (h-8 w-8)
- Jika user belum upload foto, menggunakan avatar default dari UI Avatars
- Username ditampilkan di sebelah foto (hidden di mobile, muncul di tablet keatas)
- Menggunakan fungsi `getUserImage(user)` dari utils

**Styling**:
```jsx
<img 
  src={getUserImage(user)} 
  alt={user.username}
  className="h-8 w-8 rounded-full object-cover border-2 border-white/50"
/>
```

**Fitur**:
- ✅ Foto profil otomatis dari database user
- ✅ Fallback ke UI Avatars jika tidak ada foto
- ✅ Responsive (username hidden di mobile)
- ✅ Border putih transparan untuk kontras

### Modal Background Update (27 Juli 2026 - 16:08)
**File**: `resources/js/Pages/seller/dashboard.jsx`

**Perubahan**:
- Background modal diubah dari hitam pekat (bg-black bg-opacity-50) menjadi lebih terang
- Menggunakan `bg-black/30 backdrop-blur-sm` untuk efek modern
- Animasi smooth dengan `animate-in fade-in zoom-in-95 duration-200`

**Hasil**:
- Background tidak lagi hitam pekat
- Efek blur membuat tampilan lebih elegan
- Modal lebih nyaman dilihat

---
**Tanggal Update Terakhir**: 27 Juli 2026 16:16
**Status**: ✅ Completed
