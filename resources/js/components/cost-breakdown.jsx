import { formatIDR } from '@/lib/utils';
import { regionLabel } from '@/lib/shipping';

/**
 * Rincian biaya checkout.
 *
 * Angkanya berasal dari pratinjau di resources/js/lib/shipping.js. Yang
 * ditagihkan tetap hasil hitungan server; tampilan ini ada supaya pembeli tahu
 * setiap rupiah yang ia bayar untuk apa.
 *
 * @param {object} summary hasil summarize() dari @/lib/shipping
 * @param {string|null} region 'jawa' | 'luar_jawa'; null bila titik antar belum
 *   dipilih sehingga wilayahnya belum bisa ditentukan
 * @param {object} rates prop feeRates dari controller
 */
export default function CostBreakdown({
  summary,
  region,
  hasDestination = true,
  rates,
  storeCount = 1,
  packagingType,
}) {
  // Ditandai bila yang menentukan tagihan adalah dimensi paketnya, bukan
  // beratnya — supaya pembeli tahu dari mana angkanya datang.
  const isVolumetric =
    summary.volumetric_weight_gram > summary.actual_weight_gram;

  // Berat yang benar-benar ditagihkan: paket di bawah batas terendah
  // dibulatkan naik, karena tingkat pertama adalah tarif dasar.
  const billableGram = Math.max(
    summary.weight_gram,
    Number(rates.weight.min_billable_gram)
  );

  const formatKg = (gram) =>
    `${(gram / 1000).toLocaleString('id-ID', { maximumFractionDigits: 2 })} kg`;

  const firstTier = rates.weight.tiers[0];

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
      hint: !hasDestination
        ? 'Pilih titik antar di peta agar wilayah tujuannya bisa ditentukan'
        : `Tarif ${regionLabel(region)} ${formatIDR(rates.shipping.base_fee[region])} per paket` +
          (storeCount > 1 ? ` (${storeCount} toko)` : ''),
    },
    {
      label: 'Biaya berat',
      value: summary.weight_fee,
      hint:
        `${formatKg(billableGram)}` +
        (billableGram > summary.weight_gram
          ? ` (dibulatkan dari ${formatKg(summary.weight_gram)} — minimum ${formatKg(Number(rates.weight.min_billable_gram))})`
          : '') +
        ` — tarif sampai ${formatKg(Number(firstTier.max_gram))} ${formatIDR(firstTier.fee)}, di atasnya ${formatIDR(rates.weight.tiers[1].fee)}` +
        (isVolumetric ? '. Dihitung dari berat volumetrik' : ''),
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
