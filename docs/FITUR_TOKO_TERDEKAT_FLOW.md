# Flow Diagram - Fitur Toko Terdekat

## User Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    User buka /toko                          │
│                                                             │
│  ┌────────────────────────────────────────────────────┐   │
│  │  Halaman Toko (Mode Default)                       │   │
│  │  - Tampil semua toko                               │   │
│  │  - Button: [📍 Cari Toko Terdekat (10 km)]        │   │
│  │  - Peta dengan marker semua toko                   │   │
│  └────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                          │
                          │ User klik "Cari Toko Terdekat"
                          ▼
┌─────────────────────────────────────────────────────────────┐
│              Browser Request Geolocation                    │
│                                                             │
│  ┌──────────────────────────────────┐                      │
│  │  🌍 Allow location access?       │                      │
│  │  [Block]  [Allow]                │                      │
│  └──────────────────────────────────┘                      │
└─────────────────────────────────────────────────────────────┘
                          │
        ┌─────────────────┴─────────────────┐
        │                                   │
    [Allow]                            [Block]
        │                                   │
        ▼                                   ▼
┌──────────────────┐              ┌──────────────────┐
│ Get Coordinates  │              │ Show Error Alert │
│ lat: -6.2088     │              │ ⚠️ Lokasi ditolak│
│ lng: 106.8456    │              └──────────────────┘
└──────────────────┘
        │
        │ router.get('/toko?latitude=-6.2088&longitude=106.8456&radius=10')
        ▼
┌─────────────────────────────────────────────────────────────┐
│             Backend: StoreController@index                  │
│                                                             │
│  if (latitude && longitude) {                              │
│    $stores = Store::nearby($lat, $lng, 10)->get()         │
│    Add distance_text to each store                         │
│  }                                                          │
└─────────────────────────────────────────────────────────────┘
        │
        │ Return Inertia response with stores + distance
        ▼
┌─────────────────────────────────────────────────────────────┐
│              Halaman Toko (Mode Nearby)                     │
│                                                             │
│  ┌────────────────────────────────────────────────────┐   │
│  │  Toko Terdekat                                     │   │
│  │  ✓ Ditemukan 8 toko dalam radius 10 km            │   │
│  │  Button: [🗺️ Lihat Semua Toko]                    │   │
│  │                                                    │   │
│  │  Peta: Marker semua toko dalam radius             │   │
│  │                                                    │   │
│  │  Card Toko:                                        │   │
│  │  ┌──────────────────┐  ┌──────────────────┐      │   │
│  │  │ Toko A           │  │ Toko B           │      │   │
│  │  │ 📍 Jakarta       │  │ 📍 Bogor         │      │   │
│  │  │ 📏 2.5 km        │  │ 📏 8.3 km        │      │   │
│  │  │ [Lihat Toko]     │  │ [Lihat Toko]     │      │   │
│  │  └──────────────────┘  └──────────────────┘      │   │
│  └────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                          │
                          │ User klik "Lihat Semua Toko"
                          ▼
                  router.get('/toko')
                          │
                          ▼
            [Kembali ke Mode Default]
```

## Technical Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend (toko.jsx)                      │
└─────────────────────────────────────────────────────────────┘
                          │
                          ├─ State: showNearby = false
                          ├─ State: userLocation = null
                          ├─ State: loadingLocation = false
                          │
                          ▼
              ┌───────────────────────────┐
              │ loadNearbyStores()        │
              │ - navigator.geolocation   │
              │ - setUserLocation(coords) │
              │ - router.get() with params│
              └───────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                Backend (StoreController.php)                │
└─────────────────────────────────────────────────────────────┘
                          │
                          ├─ Get params: latitude, longitude, radius
                          ├─ if (coords) → Store::nearby()
                          ├─ else → Store::latest()
                          │
                          ▼
              ┌───────────────────────────┐
              │ Store::scopeNearby()      │
              │ - Haversine SQL query     │
              │ - Add distance column     │
              │ - Order by distance ASC   │
              └───────────────────────────┘
                          │
                          ▼
              ┌───────────────────────────┐
              │ Format stores             │
              │ - Add distance_text       │
              │ - "2.5 km" or "850 meter" │
              └───────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│              Return Inertia Response                        │
│  - stores (with distance if nearby)                         │
│  - hasCoordinates: boolean                                  │
│  - searchRadius: 10                                         │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
                  Frontend renders
                  - Show/hide distance
                  - Change button text
                  - Update title
```

## Database Query Flow (Haversine)

```sql
-- Mode Default (No Coordinates)
SELECT * FROM stores 
ORDER BY created_at DESC;

-- Mode Nearby (With Coordinates)
SELECT *,
  (6371 * acos(
    cos(radians(?))           -- User latitude
    * cos(radians(latitude))  -- Store latitude
    * cos(radians(longitude) - radians(?))  -- Longitude difference
    + sin(radians(?))         -- User latitude again
    * sin(radians(latitude))  -- Store latitude
  )) AS distance
FROM stores
WHERE latitude IS NOT NULL
  AND longitude IS NOT NULL
  AND (6371 * acos(...)) <= ?  -- Radius filter (10 km)
ORDER BY distance ASC;

-- Example with values:
-- User at (-6.2088, 106.8456)
-- Radius 10 km
-- Returns stores sorted by distance with 'distance' column
```

## Component Structure

```
resources/js/pages/toko.jsx
├─ Import: MainLayout
├─ Import: StoresMap (Leaflet map component)
├─ Import: SearchInput
├─ Props from backend:
│  ├─ stores (array)
│  ├─ hasCoordinates (boolean)
│  └─ searchRadius (number)
├─ States:
│  ├─ userLocation
│  ├─ locationError
│  ├─ loadingLocation
│  └─ showNearby
├─ Functions:
│  ├─ loadNearbyStores() → Get geolocation & reload page
│  └─ showAllStores() → Reload to /toko
└─ Render:
   ├─ SearchInput (always visible)
   ├─ Toggle Button (Cari Terdekat / Lihat Semua)
   ├─ Info Badge (jika nearby mode)
   ├─ StoresMap (with all stores)
   └─ Grid Cards (with distance if nearby)
```

## Error Handling Flow

```
User Click "Cari Toko Terdekat"
    │
    ├─ Navigator.geolocation available?
    │  ├─ No  → setLocationError("Browser tidak support")
    │  └─ Yes → Continue
    │
    ├─ User allow permission?
    │  ├─ No  → setLocationError("Izin lokasi ditolak")
    │  └─ Yes → Continue
    │
    ├─ Get position success?
    │  ├─ No  → setLocationError("Tidak dapat akses lokasi")
    │  └─ Yes → Continue
    │
    └─ Reload page with coordinates
       │
       ├─ Backend query success?
       │  ├─ No  → Show error page
       │  └─ Yes → Continue
       │
       └─ Render nearby stores
          │
          ├─ stores.length > 0?
          │  ├─ No  → Show "Tidak ada toko dalam radius"
          │  └─ Yes → Show stores with distance
```
