import { formatIDR } from '@/lib/utils';
import { PackagingStandardIcon, PackagingWoodIcon } from '@/components/icons';

/**
 * Pilihan jenis pengemasan.
 *
 * Sengaja dibuat pilihan, bukan tarif tunggal: koin antik tidak perlu peti
 * kayu, guci keramik perlu. Memaksakan satu tarif untuk semua barang membuat
 * pembeli barang kecil membayar perlindungan yang tidak ia butuhkan.
 *
 * @param {object} rates prop feeRates dari controller
 * @param {(type: string) => number} priceFor biaya total untuk satu jenis,
 *        dihitung pemanggil karena bergantung jumlah barang dan jumlah toko
 * @param {string} name nama grup radio; wajib berbeda antar toko di keranjang,
 *        kalau tidak memilih di satu toko akan mematikan pilihan toko lain
 * @param {boolean} bare tanpa kartu sendiri, untuk dipakai di dalam kartu toko
 */
export default function PackagingPicker({
  value,
  onChange,
  rates,
  priceFor,
  error,
  name = 'packaging_type',
  bare = false,
}) {
  const options = Object.entries(rates.packaging.options);

  const body = (
    <>
      <div className="space-y-3">
        {options.map(([key, option]) => (
          <label
            key={key}
            className="flex cursor-pointer items-start gap-3 rounded-lg border-2 border-gray-200 p-4 hover:border-[#53685B] has-[:checked]:border-[#53685B] has-[:checked]:bg-[#53685B]/5"
          >
            <input
              type="radio"
              name={name}
              value={key}
              checked={value === key}
              onChange={(e) => onChange(e.target.value)}
              className="mt-1 h-4 w-4 text-[#53685B]"
            />
            <div className="flex-1">
              <p className="flex items-center gap-2 font-semibold text-gray-900">
                {key === 'kayu' ? (
                  <PackagingWoodIcon className="h-5 w-5 text-[#B77C4C]" />
                ) : (
                  <PackagingStandardIcon className="h-5 w-5 text-[#B77C4C]" />
                )}
                {option.label}
              </p>
              <p className="text-sm text-gray-500">{option.description}</p>
              <p className="mt-1 text-xs text-gray-400">
                {formatIDR(option.base_fee)}/paket +{' '}
                {formatIDR(option.per_item_fee)}/barang
              </p>
            </div>
            <p className="shrink-0 font-bold text-gray-900">
              {formatIDR(priceFor(key))}
            </p>
          </label>
        ))}
      </div>

      {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
    </>
  );

  // Di keranjang, pemilihnya sudah berada di dalam kartu toko — memberinya
  // kartu sendiri lagi menghasilkan kotak bertumpuk.
  if (bare) {
    return (
      <div className="border-t px-6 py-4">
        <h4 className="mb-1 text-sm font-bold text-gray-900">
          Jenis Pengemasan
        </h4>
        {body}
      </div>
    );
  }

  return (
    <div className="rounded-lg border bg-white p-6 shadow-md">
      <h3 className="mb-1 text-lg font-bold text-gray-900">Jenis Pengemasan</h3>
      {body}
    </div>
  );
}
