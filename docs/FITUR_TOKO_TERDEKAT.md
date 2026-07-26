# Fitur Deteksi Toko Terdekat

## Deskripsi
Fitur ini memungkinkan user untuk menemukan toko barang antik terdekat berdasarkan lokasi mereka dalam radius 10 km. Fitur ini **terintegrasi langsung di halaman Toko** (`/toko`), sehingga user dapat dengan mudah beralih antara melihat semua toko atau hanya toko terdekat.

## Fitur Utama

### 1. Toggle Mode: Semua Toko vs Toko Terdekat
- **Mode Default**: Menampilkan semua toko yang terdaftar
- **Mode Nearby**: Klik tombol "📍 Cari Toko Terdekat (10 km)" untuk melihat toko dalam radius 10 km
- Tombol toggle untuk beralih antara kedua mode

### 2. Geolocation Otomatis
- Menggunakan HTML5 Geolocation API untuk mendapatkan lokasi user
- Request permission browser untuk akses lokasi
- Menampilkan peringatan jika akses lokasi ditolak atau tidak tersedia

### 3. Pencarian Berdasarkan Radius (10 km)
- Radius tetap 10 km untuk hasil yang relevan
- Menampilkan jumlah toko yang ditemukan dalam radius
- Pencarian dilakukan di backend menggunakan formula Haversine

### 4. Peta Interaktif (Leaflet)
- Peta yang sama untuk semua mode (semua toko atau nearby)
- Menampilkan marker untuk semua toko dengan koordinat
- Tooltip dan popup untuk setiap marker

### 5. Daftar Toko dengan Jarak
- Grid card menampilkan toko dalam radius (mode nearby)
- Setiap card menampilkan:
  - Foto toko
  - Nama toko
  - Lokasi
  - **Jarak dari user** (hanya di mode nearby): dalam meter jika < 1 km, dalam km jika >= 1 km
  - Tombol untuk melihat detail toko

### 6. Search Integration
- Search bar tetap berfungsi di kedua mode
- Di mode nearby, pencarian memfilter toko dalam radius

## Backend Implementation

### Model Store (`app/Models/Store.php`)

#### Method `calculateDistance($userLat, $userLng)`
Menghitung jarak antara toko dengan koordinat user menggunakan formula Haversine.

```php
public function calculateDistance(float $userLat, float $userLng): float
{
    // Menggunakan Haversine formula
    // Return: jarak dalam kilometer
}
```

#### Scope `nearby($query, $latitude, $longitude, $radius = 10)`
Query scope untuk mencari toko dalam radius tertentu menggunakan SQL.

```php
public function scopeNearby($query, float $latitude, float $longitude, float $radius = 10)
{
    // Menggunakan Haversine formula dalam SQL untuk efisiensi
    // Return: Query builder dengan kolom 'distance' tambahan
}
```

#### Static Method `getNearbyStores($latitude, $longitude, $radius = 10, $limit = null)`
Method helper untuk mendapatkan daftar toko terdekat.

```php
public static function getNearbyStores(float $latitude, float $longitude, float $radius = 10, ?int $limit = null)
{
    // Return: Collection of stores with distance
}
```

### Controller (`app/Http/Controllers/StoreController.php`)

#### Method: `index(Request $request)`
```
GET /toko
GET /toko?latitude={lat}&longitude={lng}&radius={km}
```

**Parameters:**
- `q` (optional): Keyword untuk search
- `latitude` (optional): Latitude user (-90 to 90) 
- `longitude` (optional): Longitude user (-180 to 180)
- `radius` (optional): Radius pencarian dalam km (default: 10)

**Behavior:**
- **Tanpa koordinat**: Menampilkan semua toko (mode default)
- **Dengan koordinat**: Menampilkan toko dalam radius menggunakan scope `nearby()`
- Search keyword berfungsi di kedua mode

**Return:** Inertia response ke halaman `toko` dengan data:
```php
[
    'stores' => $stores, // Collection dengan distance_text jika mode nearby
    'heroText' => 'Males Ke Pasar Barang Antik? Pesan VINSTORE Aja!',
    'showSearch' => true,
    'hasCoordinates' => boolean,
    'searchRadius' => float,
]
```

### Routes (`routes/web.php`)

```php
Route::get('/toko', [StoreController::class, 'index'])->name('toko.index');
Route::get('/toko/{store}', [StoreController::class, 'show'])->name('toko.show');
```

**Note:** Hanya satu route `/toko` yang menangani kedua mode (semua toko & nearby).

## Frontend Implementation

### Component (`resources/js/pages/toko.jsx`)

**Dependencies:**
- `react-leaflet`: Untuk komponen peta (StoresMap)
- `@inertiajs/react`: Untuk navigasi dan state management

**State Management:**
- `userLocation`: Koordinat user (dari geolocation)
- `locationError`: Error geolocation
- `loadingLocation`: Status loading saat request geolocation
- `showNearby`: Boolean untuk mode nearby/all stores

**Features:**
- Toggle button untuk beralih antara "Semua Toko" dan "Toko Terdekat"
- Automatic geolocation saat klik "Cari Toko Terdekat"
- Reload page dengan parameter `latitude`, `longitude`, dan `radius=10`
- Menampilkan jarak pada card toko (hanya di mode nearby)
- Loading states dan error handling
- Info badge jumlah toko ditemukan

**User Flow:**
1. User buka `/toko` → melihat semua toko
2. Klik "📍 Cari Toko Terdekat (10 km)"
3. Browser request location permission
4. Setelah approve, page reload dengan `?latitude={lat}&longitude={lng}&radius=10`
5. Backend return toko dalam radius 10 km dengan jarak
6. Frontend display toko dengan info jarak
7. User dapat klik "🗺️ Lihat Semua Toko" untuk kembali ke mode default

## Formula Haversine

Formula yang digunakan untuk menghitung jarak antara dua titik koordinat di permukaan bumi:

```
a = sin²(Δφ/2) + cos φ1 ⋅ cos φ2 ⋅ sin²(Δλ/2)
c = 2 ⋅ atan2(√a, √(1−a))
d = R ⋅ c
```

Dimana:
- φ = latitude (dalam radian)
- λ = longitude (dalam radian)
- R = radius bumi (6371 km)
- d = jarak dalam kilometer

## Cara Menggunakan

### Sebagai User:

1. **Akses Halaman Toko**
   - Klik "Toko" di navbar
   - Atau akses langsung: `http://localhost:8000/toko`
   - Anda akan melihat semua toko yang terdaftar

2. **Lihat Toko Terdekat**
   - Klik tombol "📍 Cari Toko Terdekat (10 km)"
   - Browser akan meminta izin akses lokasi
   - Klik "Allow" untuk menggunakan lokasi real-time

3. **Lihat Hasil**
   - **Judul berubah** menjadi "Toko Terdekat"
   - **Info badge** menampilkan jumlah toko ditemukan
   - **Card toko** menampilkan jarak (contoh: "2.5 km" atau "850 meter")
   - **Peta** menampilkan lokasi semua toko dalam radius

4. **Kembali ke Semua Toko**
   - Klik tombol "🗺️ Lihat Semua Toko"
   - Akan kembali ke mode default

5. **Search di Mode Nearby**
   - Search bar tetap berfungsi
   - Hasil search akan memfilter toko dalam radius 10 km

### Sebagai Developer:

**Menambahkan data lokasi toko:**

```php
// Di form register/edit toko
$store = Store::create([
    'store_name' => 'Toko Antik ABC',
    'latitude' => -6.2088,  // Jakarta
    'longitude' => 106.8456,
    // ... field lainnya
]);
```

**Test manually dengan URL:**

```
# Mode default (semua toko)
http://localhost:8000/toko

# Mode nearby (10 km dari koordinat)
http://localhost:8000/toko?latitude=-6.2088&longitude=106.8456&radius=10

# Nearby + search
http://localhost:8000/toko?latitude=-6.2088&longitude=106.8456&radius=10&q=antik
```

**Custom query di backend:**

```php
// Mendapatkan toko terdekat dalam radius 10 km
$stores = Store::getNearbyStores($latitude, $longitude, 10);

// Atau menggunakan scope dengan filter tambahan
$stores = Store::nearby($latitude, $longitude, 10)
    ->where('category', 'Furniture')
    ->get();

// Di controller, sudah otomatis menambahkan distance_text
foreach ($stores as $store) {
    echo $store->distance_text; // "2.5 km" atau "850 meter"
}
```

## Catatan Penting

1. **Database**: Kolom `latitude` dan `longitude` harus berisi nilai yang valid. Null values akan diabaikan dalam pencarian.

2. **Performa**: 
   - Haversine formula dalam SQL cukup efisien untuk < 10,000 toko
   - Untuk dataset lebih besar, pertimbangkan menggunakan spatial indexes (PostGIS, MySQL Spatial)

3. **Akurasi**:
   - Haversine mengasumsikan bumi bulat sempurna
   - Akurasi ~99.5% untuk jarak pendek (< 100 km)
   - Untuk akurasi lebih tinggi, gunakan Vincenty formula

4. **Privacy**:
   - Lokasi user tidak disimpan di server
   - Hanya koordinat yang dikirim untuk pencarian

5. **Browser Support**:
   - Geolocation API didukung oleh semua browser modern
   - HTTPS required untuk geolocation (kecuali localhost)

## Testing

### Manual Testing:

1. Register beberapa toko dengan koordinat berbeda (gunakan form register toko)
2. Akses `/toko` - verifikasi semua toko tampil
3. Klik "📍 Cari Toko Terdekat (10 km)"
4. Verifikasi:
   - ✓ Browser request location permission
   - ✓ Setelah approve, page reload dengan parameter latitude & longitude
   - ✓ Judul berubah menjadi "Toko Terdekat"
   - ✓ Badge info menampilkan jumlah toko ditemukan
   - ✓ Card toko menampilkan jarak (contoh: "2.5 km")
   - ✓ Toko diurutkan berdasarkan jarak (terdekat dulu)
   - ✓ Tombol berubah menjadi "🗺️ Lihat Semua Toko"
5. Klik "🗺️ Lihat Semua Toko"
6. Verifikasi:
   - ✓ Kembali ke mode default
   - ✓ Semua toko tampil kembali
   - ✓ Jarak tidak ditampilkan di card

### Manual URL Testing:

```bash
# Test mode default
http://localhost:8000/toko

# Test nearby dengan koordinat Jakarta
http://localhost:8000/toko?latitude=-6.2088&longitude=106.8456&radius=10

# Test nearby + search
http://localhost:8000/toko?latitude=-6.2088&longitude=106.8456&radius=10&q=furniture
```

## Troubleshooting

**Peta tidak muncul:**
- Pastikan `npm run build` berhasil
- Clear browser cache
- Periksa console browser untuk error
- Pastikan komponen StoresMap tidak error

**Geolocation tidak bekerja:**
- Pastikan menggunakan HTTPS atau localhost
- Check browser permissions untuk location
- Buka Settings > Privacy > Location di browser
- Jika denied, reload dan allow permission

**Jarak tidak akurat:**
- Verifikasi koordinat toko di database
- Pastikan latitude dan longitude tidak terbalik
- Check bahwa koordinat dalam format decimal degrees (bukan DMS)
- Formula Haversine akurat untuk jarak < 100 km

**Toko tidak muncul di mode nearby:**
- Verifikasi toko memiliki latitude dan longitude yang valid (tidak null)
- Check database: `SELECT * FROM stores WHERE latitude IS NOT NULL`
- Pastikan jarak toko < 10 km dari lokasi user
- Test dengan koordinat yang pasti ada toko

**Tombol loading terus-menerus:**
- Check console browser untuk error JavaScript
- Verifikasi geolocation callback berjalan
- Pastikan browser support geolocation
- Try reload page

**Search tidak bekerja di mode nearby:**
- Verifikasi parameter `q` tetap ada di URL
- Check controller logic untuk filter keyword
- Test manual dengan URL: `/toko?latitude=...&longitude=...&q=test`

## Future Improvements

1. **Adjustable Radius**: Tambahkan slider untuk user mengatur radius (5, 10, 15, 20 km)
2. **Caching**: Cache hasil pencarian berdasarkan koordinat dan radius
3. **Filter Kategori**: Tambahkan filter kategori toko di mode nearby
4. **Sorting Options**: Opsi sort by distance, rating, atau populer
5. **Save Location**: Simpan lokasi favorit user (home, work)
6. **Route/Direction**: Tampilkan route dari lokasi user ke toko
7. **Browser Notification**: Notif jika ada toko baru dalam radius
8. **Spatial Index**: Implementasi MySQL Spatial untuk performa dataset besar
9. **User Marker on Map**: Tampilkan marker user di peta (saat ini hanya toko)
10. **Distance Unit**: Toggle antara kilometer dan mil

## Changelog

**Version 2.0 (Current)** - Integrasi ke Halaman Toko
- ✅ Fitur nearby terintegrasi langsung di `/toko`
- ✅ Toggle button untuk beralih mode
- ✅ Radius fixed 10 km untuk konsistensi
- ✅ Tampilan jarak di card toko
- ✅ Search integration di mode nearby
- ❌ Removed: Halaman `/toko/nearby` terpisah
- ❌ Removed: API endpoint `/api/toko/nearby`
- ❌ Removed: Adjustable radius slider

**Version 1.0** - Halaman Terpisah (Deprecated)
- Halaman nearby stores terpisah di `/toko/nearby`
- API endpoint untuk fetch nearby stores
- Adjustable radius 1-100 km
- Custom markers untuk user dan toko
