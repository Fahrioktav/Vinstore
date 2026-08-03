import { useEffect, useState } from 'react';
import {
  MapContainer,
  TileLayer,
  Marker,
  useMap,
  useMapEvents,
} from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import { setupLeafletIcons, INDONESIA_CENTER } from '@/lib/leaflet-setup';

setupLeafletIcons();

// Memindahkan view peta ketika koordinat berubah dari luar (mis. tombol GPS).
function RecenterMap({ position }) {
  const map = useMap();

  useEffect(() => {
    if (position) {
      map.setView(position, Math.max(map.getZoom(), 13));
    }
  }, [position, map]);

  return null;
}

// Menangkap klik pada peta untuk menetapkan marker.
function ClickHandler({ onPick }) {
  useMapEvents({
    click(e) {
      onPick(e.latlng.lat, e.latlng.lng);
    },
  });

  return null;
}

/**
 * Peta interaktif untuk memilih lokasi toko.
 *
 * @param {number|null} latitude
 * @param {number|null} longitude
 * @param {(lat: number, lng: number) => void} onChange
 * @param {number} height - tinggi peta dalam px
 */
export default function LocationPicker({
  latitude,
  longitude,
  onChange,
  height = 320,
}) {
  const hasPosition =
    latitude !== null &&
    latitude !== undefined &&
    latitude !== '' &&
    longitude !== null &&
    longitude !== undefined &&
    longitude !== '';

  const position = hasPosition ? [Number(latitude), Number(longitude)] : null;

  const [locating, setLocating] = useState(false);
  const [error, setError] = useState(null);

  const handlePick = (lat, lng) => {
    onChange(Number(lat.toFixed(7)), Number(lng.toFixed(7)));
  };

  const handleUseMyLocation = () => {
    if (!navigator.geolocation) {
      setError('Browser tidak mendukung geolocation.');
      return;
    }

    setLocating(true);
    setError(null);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        handlePick(pos.coords.latitude, pos.coords.longitude);
        setLocating(false);
      },
      () => {
        setError(
          'Gagal mengambil lokasi. Izinkan akses lokasi atau pilih manual di peta.'
        );
        setLocating(false);
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  };

  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-xs text-gray-500">
          Klik pada peta atau geser penanda untuk menentukan titik lokasi toko.
        </p>
        <button
          type="button"
          onClick={handleUseMyLocation}
          disabled={locating}
          className="rounded-lg bg-[#4a5b4d] px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[#3c4a3e] disabled:cursor-not-allowed disabled:opacity-50"
        >
          {locating ? 'Mencari lokasi...' : '📍 Gunakan Lokasi Saya'}
        </button>
      </div>

      <div
        className="overflow-hidden rounded-lg border border-gray-300"
        style={{ height }}
      >
        <MapContainer
          center={position || INDONESIA_CENTER}
          zoom={position ? 13 : 5}
          scrollWheelZoom
          style={{ height: '100%', width: '100%' }}
        >
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          />
          <ClickHandler onPick={handlePick} />
          {position && (
            <>
              <RecenterMap position={position} />
              <Marker
                position={position}
                draggable
                eventHandlers={{
                  dragend: (e) => {
                    const { lat, lng } = e.target.getLatLng();
                    handlePick(lat, lng);
                  },
                }}
              />
            </>
          )}
        </MapContainer>
      </div>

      <div className="flex flex-wrap gap-4 text-xs text-gray-600">
        <span>
          Latitude:{' '}
          <strong>{hasPosition ? Number(latitude).toFixed(6) : '-'}</strong>
        </span>
        <span>
          Longitude:{' '}
          <strong>{hasPosition ? Number(longitude).toFixed(6) : '-'}</strong>
        </span>
      </div>

      {error && <p className="text-xs text-red-500">{error}</p>}
    </div>
  );
}
