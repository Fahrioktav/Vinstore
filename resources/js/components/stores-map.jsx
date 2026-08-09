import { useEffect, useMemo, useState } from 'react';
import {
  MapContainer,
  TileLayer,
  Marker,
  Popup,
  Tooltip,
  Circle,
  useMap,
} from 'react-leaflet';
import { Link } from '@inertiajs/react';
import 'leaflet/dist/leaflet.css';
import L, {
  setupLeafletIcons,
  userLocationIcon,
  INDONESIA_CENTER,
} from '@/lib/leaflet-setup';
import { haversineKm } from '@/lib/shipping';
import {
  DistanceIcon,
  LocationIcon,
  SpinnerIcon,
  WarningIcon,
} from '@/components/icons';

setupLeafletIcons();

const hasCoords = (store) =>
  store.latitude !== null &&
  store.latitude !== undefined &&
  store.longitude !== null &&
  store.longitude !== undefined;

/**
 * Sesuaikan tampilan peta agar seluruh titik penting muat sekaligus.
 *
 * Tanpa ini, peta hanya berpusat pada toko pertama sehingga pin lokasi user
 * bisa berada jauh di luar layar — justru menghilangkan gunanya menampilkan
 * "toko mana yang terdekat".
 */
function FitToPoints({ points }) {
  const map = useMap();

  useEffect(() => {
    if (points.length === 0) return;

    if (points.length === 1) {
      map.setView(points[0], Math.max(map.getZoom(), 13));

      return;
    }

    map.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 15 });
  }, [JSON.stringify(points), map]);

  return null;
}

/**
 * Peta sebaran toko, lengkap dengan penanda posisi pembeli.
 *
 * @param {Array} stores
 * @param {number} height - tinggi peta dalam px
 * @param {{latitude: number, longitude: number}|null} userLocation
 *        Titik pembeli bila sudah diketahui halaman induk (mis. hasil
 *        pencarian toko terdekat). Bila null, pembeli bisa menekan tombol
 *        di bawah peta untuk menampilkannya.
 * @param {number|null} radiusKm - lingkaran radius pencarian di sekitar user
 */
export default function StoresMap({
  stores = [],
  height = 420,
  userLocation = null,
  radiusKm = null,
}) {
  const located = useMemo(() => stores.filter(hasCoords), [stores]);

  // Lokasi yang diminta sendiri lewat tombol, dipakai bila halaman induk
  // belum memberikannya.
  const [ownLocation, setOwnLocation] = useState(null);
  const [locating, setLocating] = useState(false);
  const [locationError, setLocationError] = useState(null);

  const point = userLocation ?? ownLocation;
  const userPoint = point
    ? [Number(point.latitude), Number(point.longitude)]
    : null;

  const requestLocation = () => {
    if (!navigator.geolocation) {
      setLocationError('Browser Anda tidak mendukung geolocation.');

      return;
    }

    setLocating(true);
    setLocationError(null);
    navigator.geolocation.getCurrentPosition(
      (position) => {
        setOwnLocation({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
        });
        setLocating(false);
      },
      () => {
        setLocationError(
          'Tidak dapat mengakses lokasi Anda. Pastikan izin lokasi diaktifkan.'
        );
        setLocating(false);
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  };

  // Jarak tiap toko dari pembeli, dihitung di klien supaya tetap tampil
  // walau halaman tidak sedang dalam mode "toko terdekat".
  const distanceTo = (store) =>
    userPoint
      ? haversineKm(
          userPoint[0],
          userPoint[1],
          Number(store.latitude),
          Number(store.longitude)
        )
      : null;

  const nearest = useMemo(() => {
    if (!userPoint || located.length === 0) return null;

    return located.reduce((closest, store) =>
      distanceTo(store) < distanceTo(closest) ? store : closest
    );
  }, [located, userPoint?.[0], userPoint?.[1]]);

  const points = [
    ...located.map((store) => [
      Number(store.latitude),
      Number(store.longitude),
    ]),
    ...(userPoint ? [userPoint] : []),
  ];

  const center = points[0] ?? INDONESIA_CENTER;

  if (located.length === 0 && !userPoint) {
    return (
      <div
        className="flex items-center justify-center rounded-2xl border border-gray-200 bg-white/80 text-sm text-gray-500"
        style={{ height }}
      >
        Belum ada toko dengan titik lokasi pada peta.
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <div
        className="overflow-hidden rounded-2xl border border-gray-200 shadow-lg"
        style={{ height }}
      >
        <MapContainer
          center={center}
          zoom={located.length === 1 ? 13 : 5}
          scrollWheelZoom
          style={{ height: '100%', width: '100%' }}
        >
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          />

          <FitToPoints points={points} />

          {/* Posisi pembeli */}
          {userPoint && (
            <>
              {radiusKm > 0 && (
                <Circle
                  center={userPoint}
                  radius={radiusKm * 1000}
                  pathOptions={{
                    color: '#2563eb',
                    fillColor: '#2563eb',
                    fillOpacity: 0.08,
                    weight: 1,
                  }}
                />
              )}
              <Marker
                position={userPoint}
                icon={userLocationIcon()}
                zIndexOffset={1000}
              >
                <Tooltip permanent direction="top" offset={[0, -14]}>
                  <span className="inline-flex items-center gap-1 text-xs font-semibold text-blue-700">
                    <LocationIcon className="h-3 w-3" />
                    Lokasi Anda
                  </span>
                </Tooltip>
              </Marker>
            </>
          )}

          {located.map((store) => {
            const km = distanceTo(store);
            const isNearest = nearest?.public_id === store.public_id;

            return (
              <Marker
                key={store.public_id}
                position={[Number(store.latitude), Number(store.longitude)]}
              >
                <Tooltip permanent direction="top" offset={[0, -40]}>
                  <span className="text-xs font-semibold">
                    {store.store_name}
                    {km !== null && (
                      <span className="font-normal text-gray-500">
                        {' '}
                        ·{' '}
                        {km < 1
                          ? `${Math.round(km * 1000)} m`
                          : `${km.toFixed(1)} km`}
                      </span>
                    )}
                  </span>
                </Tooltip>
                <Popup>
                  <div className="space-y-1">
                    <p className="text-sm font-semibold text-[#2F3E46] capitalize">
                      {store.store_name}
                      {isNearest && (
                        <span className="ml-1 rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-semibold text-green-700">
                          TERDEKAT
                        </span>
                      )}
                    </p>
                    {store.location && (
                      <p className="flex items-center gap-1 text-xs text-gray-600">
                        <LocationIcon className="h-3 w-3 shrink-0" />
                        {store.location}
                      </p>
                    )}
                    {km !== null && (
                      <p className="flex items-center gap-1 text-xs font-semibold text-[#B77C4C]">
                        <DistanceIcon className="h-3 w-3 shrink-0" />
                        {km < 1
                          ? `${Math.round(km * 1000)} meter`
                          : `${km.toFixed(1)} km`}{' '}
                        dari lokasi Anda
                      </p>
                    )}
                    <Link
                      href={`/toko/${store.public_id}`}
                      className="inline-block text-xs font-semibold text-[#B77C4C] hover:underline"
                    >
                      Lihat Toko →
                    </Link>
                  </div>
                </Popup>
              </Marker>
            );
          })}
        </MapContainer>
      </div>

      {!userPoint && (
        <div className="flex flex-wrap items-center gap-3">
          <button
            type="button"
            onClick={requestLocation}
            disabled={locating}
            className="inline-flex items-center gap-1.5 rounded-lg bg-[#4a5b4d] px-4 py-2 text-xs font-semibold text-white transition hover:bg-[#3c4a3e] disabled:cursor-not-allowed disabled:opacity-50"
          >
            {locating ? (
              <>
                <SpinnerIcon className="h-3.5 w-3.5" />
                Mencari lokasi...
              </>
            ) : (
              <>
                <LocationIcon className="h-3.5 w-3.5" />
                Tampilkan Lokasi Saya di Peta
              </>
            )}
          </button>
          <span className="text-xs text-gray-300">
            Lokasi Anda hanya dipakai di browser untuk menampilkan pin dan
            menghitung jarak.
          </span>
        </div>
      )}

      {locationError && (
        <p className="flex items-center gap-1.5 text-xs text-yellow-300">
          <WarningIcon className="h-4 w-4 shrink-0" />
          {locationError}
        </p>
      )}

      {userPoint && nearest && (
        <p className="text-xs text-gray-300">
          Toko terdekat dari lokasi Anda:{' '}
          <span className="font-semibold text-[#E9E19E]">
            {nearest.store_name}
          </span>{' '}
          ({distanceTo(nearest).toFixed(1)} km)
        </p>
      )}
    </div>
  );
}
