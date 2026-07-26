import { useState, useEffect } from 'react';
import { router, Link, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { getStoreImage, useParams } from '@/lib/utils';
import SearchInput from '@/components/search-input';
import StoresMap from '@/components/stores-map';

export default function TokoPage() {
  const { showSearch, stores, hasCoordinates, searchRadius } = usePage().props;
  const q = useParams().get('q');

  const [userLocation, setUserLocation] = useState(null);
  const [locationError, setLocationError] = useState(null);
  const [loadingLocation, setLoadingLocation] = useState(false);
  const [showNearby, setShowNearby] = useState(hasCoordinates || false);

  // Fungsi untuk mendapatkan lokasi user dan reload halaman dengan parameter
  const loadNearbyStores = () => {
    if ('geolocation' in navigator) {
      setLoadingLocation(true);
      navigator.geolocation.getCurrentPosition(
        (position) => {
          const { latitude, longitude } = position.coords;
          setUserLocation({ latitude, longitude });
          setLocationError(null);
          setLoadingLocation(false);
          setShowNearby(true);

          // Reload halaman dengan parameter koordinat
          const params = new URLSearchParams(window.location.search);
          params.set('latitude', latitude);
          params.set('longitude', longitude);
          params.set('radius', '10'); // Default 10 km
          
          router.get(`/toko?${params.toString()}`, {}, { 
            preserveState: false,
            preserveScroll: true 
          });
        },
        (err) => {
          console.error('Geolocation error:', err);
          setLocationError(
            'Tidak dapat mengakses lokasi Anda. Pastikan izin lokasi diaktifkan.'
          );
          setLoadingLocation(false);
        }
      );
    } else {
      setLocationError('Browser Anda tidak mendukung geolocation.');
    }
  };

  // Fungsi untuk menampilkan semua toko
  const showAllStores = () => {
    setShowNearby(false);
    setUserLocation(null);
    router.get('/toko', {}, { 
      preserveState: false,
      preserveScroll: true 
    });
  };

  return (
    <>
      {showSearch && (
        <SearchInput
          action="/toko"
          defaultValue={q}
          placeholder="Cari toko antik favoritmu..."
        />
      )}

      <section className="relative w-full overflow-hidden px-6 py-12 md:px-12">
        {/* Judul */}
        <h2 className="mb-6 pt-10 text-center text-3xl font-bold tracking-wide text-[#E9E19E] md:text-4xl">
          {showNearby ? 'Toko Terdekat' : 'Semua Toko'}
        </h2>

        {/* Toggle Button untuk Nearby / Semua Toko */}
        <div className="mx-auto mb-8 flex max-w-md justify-center gap-4">
          {!showNearby ? (
            <button
              onClick={loadNearbyStores}
              disabled={loadingLocation}
              className="flex items-center gap-2 rounded-lg bg-[#B77C4C] px-6 py-3 font-semibold text-white transition-all duration-200 hover:bg-[#9e6538] disabled:bg-gray-400"
            >
              {loadingLocation ? (
                <>
                  <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></div>
                  Mencari lokasi...
                </>
              ) : (
                <>
                  📍 Cari Toko Terdekat (10 km)
                </>
              )}
            </button>
          ) : (
            <button
              onClick={showAllStores}
              className="flex items-center gap-2 rounded-lg bg-gray-600 px-6 py-3 font-semibold text-white transition-all duration-200 hover:bg-gray-700"
            >
              🗺️ Lihat Semua Toko
            </button>
          )}
        </div>

        {/* Location Error Alert */}
        {locationError && (
          <div className="mx-auto mb-6 max-w-3xl rounded-lg border border-yellow-500 bg-yellow-50 p-4 text-sm text-yellow-800">
            ⚠️ {locationError}
          </div>
        )}

        {/* Info Badge jika mode nearby */}
        {showNearby && stores.length > 0 && (
          <div className="mx-auto mb-8 max-w-3xl rounded-lg bg-green-50 p-4 text-center text-sm text-green-800">
            ✓ Ditemukan <span className="font-semibold">{stores.length}</span>{' '}
            toko dalam radius <span className="font-semibold">{searchRadius} km</span> dari lokasi Anda
          </div>
        )}

        {showNearby && stores.length === 0 && (
          <div className="mx-auto mb-8 max-w-3xl rounded-lg bg-gray-50 p-4 text-center text-sm text-gray-800">
            Tidak ada toko ditemukan dalam radius {searchRadius} km. Coba lihat semua toko.
          </div>
        )}

        {/* PETA SEBARAN TOKO */}
        <div className="mx-auto mb-12 max-w-7xl">
          <h3 className="mb-4 text-center text-xl font-semibold text-[#E9E19E]">
            🗺️ Peta Lokasi Toko
          </h3>
          <StoresMap stores={stores} />
        </div>

        {/* GRID TOKO */}
        <div className="mx-auto grid max-w-7xl grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
          {stores.map((store) => (
            <div
              className="overflow-hidden rounded-2xl border border-gray-200 bg-white/90 shadow-lg backdrop-blur-md transition-transform duration-300 hover:scale-105"
              key={store.public_id}
            >
              {/* GAMBAR TOKO */}
              <img
                src={getStoreImage(store)}
                alt={store.store_name}
                className="h-48 w-full object-cover"
              />

              {/* DETAIL TOKO */}
              <div className="p-6">
                <h3 className="mb-2 text-xl font-semibold capitalize text-[#2F3E46]">
                  {store.store_name}
                </h3>
                <p className="mb-2 flex items-center gap-2 text-sm text-gray-600">
                  📍 {store.location}
                </p>
                
                {/* Tampilkan jarak jika mode nearby */}
                {showNearby && store.distance_text && (
                  <p className="mb-2 flex items-center gap-2 text-sm font-semibold text-[#B77C4C]">
                    📏 {store.distance_text}
                  </p>
                )}

                <Link
                  href={`/toko/${store.public_id}`}
                  className="block rounded-lg bg-[#B77C4C] py-2 text-center font-semibold text-white transition-all duration-200 hover:bg-[#9e6538]"
                >
                  Lihat Toko
                </Link>
              </div>
            </div>
          ))}

          {stores.length === 0 && !showNearby && (
            <div className="col-span-full text-center text-lg italic text-white">
              Tidak ada toko yang ditemukan 🕯️
            </div>
          )}
        </div>

        {/* DEKORASI LATAR */}
        <div className="pointer-events-none absolute left-0 top-0 -z-10 h-64 w-64 rounded-full bg-[#E9E19E]/20 blur-3xl"></div>
        <div className="pointer-events-none absolute bottom-0 right-0 -z-10 h-96 w-96 rounded-full bg-[#B77C4C]/20 blur-3xl"></div>
      </section>
    </>
  );
}

TokoPage.layout = (page) => <MainLayout title="Toko">{page}</MainLayout>;
