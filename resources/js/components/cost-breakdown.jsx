import { formatIDR } from '@/lib/utils';

/**
 * Rincian biaya checkout.
 *
 * Angkanya berasal dari pratinjau di resources/js/lib/shipping.js. Yang
 * ditagihkan tetap hasil hitungan server; tampilan ini ada supaya pembeli tahu
 * setiap rupiah yang ia bayar untuk apa.
 *
 * @param {object} summary hasil summarize() dari @/lib/shipping
 * @param {number|null} distanceKm jarak yang dipakai, null bila belum ada titik
 * @param {object} rates prop feeRates dari controller
 */
export default function CostBreakdown({
  summary,
  distanceKm,
  rates,
  storeCount = 1,
  packagingType,
}) {
  const formatKm = (km) =>
    `${km.toLocaleString('id-ID', { maximumFractionDigits: 1 })} km`;

  // Ditandai bila yang menentukan tagihan adalah dimensi paketnya, bukan
  // beratnya — supaya pembeli tahu dari mana angkanya datang.
  const isVolumetric =
    summary.volumetric_weight_gram > summary.actual_weight_gram;

  // Di keranjang, tiap toko bisa memilih pengemasan yang berbeda; saat itu
  // `packagingType` tidak dikirim dan labelnya tidak boleh mengklaim satu jenis.
  const packagingLabel = packagingType
    ? rates.packaging.options[packagingType]?.label
    : storeCount > 1
      ? 'Sesuai pilihan tiap toko'
      : rates.packaging.options[rates.packaging.default]?.label;

  const rows = [
    {
      label: 'Subtotal barang',
      value: summary.subtotal,
    },
    {
      label: 'Biaya pengiriman',
      value: summary.shipping_cost,
      hint:
        distanceKm === null
          ? 'Tarif rata — pilih titik antar di peta untuk hitungan per km'
          : `${formatKm(distanceKm)} × ${formatIDR(rates.shipping.per_km)}/km + dasar ${formatIDR(rates.shipping.base_fee)}` +
            (storeCount > 1 ? ` (${storeCount} toko)` : ''),
    },
    {
      label: 'Biaya berat',
      value: summary.weight_fee,
      hint:
        `${(summary.weight_gram / 1000).toLocaleString('id-ID', {
          maximumFractionDigits: 2,
        })} kg × ${formatIDR(rates.weight.per_kg)}/kg (dibulatkan ke atas)` +
        (isVolumetric ? ' — dihitung dari berat volumetrik' : ''),
    },
    {
      label: 'Biaya pengemasan',
      value: summary.packaging_fee,
      hint: packagingLabel,
    },
    {
      label: 'Biaya layanan',
      value: summary.service_fee,
      hint: `${rates.service_fee.percent}% dari nilai barang — biaya operasional marketplace`,
    },
  ];

  return (
    <div>
      <div className="space-y-3 border-b border-gray-200 pb-4">
        {rows.map((row) => (
          <div key={row.label} className="flex justify-between gap-4 text-sm">
            <span className="text-gray-600">
              {row.label}
              {row.hint && (
                <span className="block text-[11px] leading-tight text-gray-400">
                  {row.hint}
                </span>
              )}
            </span>
            <span className="shrink-0 font-semibold text-gray-900">
              {formatIDR(row.value)}
            </span>
          </div>
        ))}
      </div>

      <div className="mt-4 flex items-center justify-between">
        <span className="text-lg font-bold text-gray-900">Total</span>
        <span className="text-2xl font-bold text-[#B77C4C]">
          {formatIDR(summary.total)}
        </span>
      </div>
    </div>
  );
}
