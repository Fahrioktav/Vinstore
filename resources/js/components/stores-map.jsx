import { useMemo } from 'react';
import { MapContainer, TileLayer, Marker, Popup, Tooltip } from 'react-leaflet';
import { Link } from '@inertiajs/react';
import 'leaflet/dist/leaflet.css';
import { setupLeafletIcons, INDONESIA_CENTER } from '@/lib/leaflet-setup';

setupLeafletIcons();

const hasCoords = (store) =>
  store.latitude !== null &&
  store.latitude !== undefined &&
  store.longitude !== null &&
  store.longitude !== undefined;

/**
 * Peta yang menampilkan sebaran lokasi semua toko yang punya koordinat.
 *
 * @param {Array} stores
 * @param {number} height - tinggi peta dalam px
 */
export default function StoresMap({ stores = [], height = 420 }) {
  const located = useMemo(() => stores.filter(hasCoords), [stores]);

  // Tentukan pusat peta: titik toko pertama yang punya koordinat, atau tengah Indonesia.
  const center = located.length
    ? [Number(located[0].latitude), Number(located[0].longitude)]
    : INDONESIA_CENTER;

  if (located.length === 0) {
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
        {located.map((store) => (
          <Marker
            key={store.public_id}
            position={[Number(store.latitude), Number(store.longitude)]}
          >
            <Tooltip permanent direction="top" offset={[0, -40]}>
              <span className="font-semibold text-xs">
                {store.store_name}
              </span>
            </Tooltip>
            <Popup>
              <div className="space-y-1">
                <p className="text-sm font-semibold text-[#2F3E46] capitalize">
                  {store.store_name}
                </p>
                {store.location && (
                  <p className="text-xs text-gray-600">📍 {store.location}</p>
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
        ))}
      </MapContainer>
    </div>
  );
}
