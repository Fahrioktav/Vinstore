import LocationPicker from '@/components/location-picker';
import { DistanceIcon, WarningIcon } from '@/components/icons';

const hasCoords = (store) =>
  store &&
  store.latitude !== null &&
  store.latitude !== undefined &&
  store.longitude !== null &&
  store.longitude !== undefined;

/**
 * Pemilihan titik pengantaran di halaman checkout.
 *
 * Ongkir dihitung dari jarak toko ke titik ini, jadi pembeli perlu melihat
 * kedua titik sekaligus. Titik toko ikut ditampilkan sebagai penanda agar
 * jaraknya masuk akal secara visual, bukan sekadar angka.
 *
 * Titik ini opsional: pembeli yang menolak izin lokasi tetap bisa berbelanja,
 * hanya saja ongkirnya memakai tarif rata.
 */
export default function DeliveryPointCard({
  latitude,
  longitude,
  onChange,
  stores = [],
  distanceKm,
  error,
}) {
  const located = stores.filter(hasCoords);
  const storesWithoutCoords = stores.filter(
    (store) => store && !hasCoords(store)
  );
  const hasPoint =
    latitude !== '' && latitude !== null && latitude !== undefined;

  return (
    <div className="rounded-lg border bg-white p-6 shadow-md">
      <h3 className="mb-1 text-lg font-bold text-gray-900">
        Titik Pengantaran <span className="text-red-500">*</span>
      </h3>
      <p className="mb-4 text-sm text-gray-500">
        Wajib dipilih. Titik inilah tujuan pengiriman yang sebenarnya — ongkos
        kirim dihitung dari jarak toko ke sini, dan penjual melihat wilayahnya
        sebelum mengirim. Tekan <strong>Gunakan Lokasi Saya</strong> atau klik
        langsung pada peta.
      </p>

      <LocationPicker
        latitude={latitude === '' ? null : latitude}
        longitude={longitude === '' ? null : longitude}
        onChange={onChange}
        height={280}
        hint="Klik pada peta atau geser penanda untuk menentukan titik pengantaran."
        markers={located.map((store) => ({
          key: store.public_id,
          latitude: store.latitude,
          longitude: store.longitude,
          label: store.store_name,
        }))}
      />

      {hasPoint && distanceKm !== null && distanceKm !== undefined && (
        <p className="mt-3 flex items-center gap-2 rounded-lg bg-green-50 px-4 py-2 text-sm text-green-800">
          <DistanceIcon className="h-4 w-4 shrink-0" />
          <span>
            Jarak ke titik pengantaran:{' '}
            <strong>
              {distanceKm.toLocaleString('id-ID', { maximumFractionDigits: 1 })}{' '}
              km
            </strong>
          </span>
        </p>
      )}

      {!hasPoint && (
        <p className="mt-3 flex items-start gap-2 rounded-lg bg-yellow-50 px-4 py-2 text-sm text-yellow-800">
          <WarningIcon className="mt-0.5 h-4 w-4 shrink-0" />
          <span>
            Titik pengantaran belum dipilih. Checkout belum bisa dilanjutkan
            sampai Anda menentukannya.
          </span>
        </p>
      )}

      {hasPoint && storesWithoutCoords.length > 0 && (
        <p className="mt-3 flex items-start gap-2 rounded-lg bg-yellow-50 px-4 py-2 text-sm text-yellow-800">
          <WarningIcon className="mt-0.5 h-4 w-4 shrink-0" />
          <span>
            {storesWithoutCoords.length} toko belum menandai lokasinya di peta,
            sehingga ongkir toko tersebut memakai tarif rata.
          </span>
        </p>
      )}

      {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
    </div>
  );
}
