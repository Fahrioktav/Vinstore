import { useForm, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR, getAuctionImage } from '@/lib/utils';
import { quoteLine, summarize } from '@/lib/shipping';
import DeliveryPointCard from '@/components/delivery-point-card';
import PackagingPicker from '@/components/packaging-picker';
import CostBreakdown from '@/components/cost-breakdown';

/**
 * Halaman pembayaran pemenang lelang.
 *
 * Lelang tidak melewati checkout, jadi pemenang baru memilih titik antar dan
 * pengemasannya di sini. Sampai halaman ini dikirimkan, pesanannya masih berisi
 * harga menang saja — biayanya belum bisa dihitung karena tujuannya belum
 * diketahui.
 *
 * Popup Snap-nya sendiri dibuka di halaman detail lelang, tempat pengguna
 * dikembalikan setelah form ini terkirim.
 */
export default function AuctionCheckoutPage() {
  const { auction, order, user, feeRates } = usePage().props;

  const { data, setData, post, processing, errors } = useForm({
    shipping_address: order.shipping_address || user?.address || '',
    shipping_method: 'standard',
    packaging_type: feeRates.packaging.default,
    shipping_latitude: '',
    shipping_longitude: '',
    notes: '',
  });

  const winningPrice = Number(order.product_price) || 0;
  const depositCredit = Number(order.deposit_credit) || 0;

  // Pratinjau biaya. Berat dan dimensinya milik lelang, jadi objek lelang
  // dipakai apa adanya sebagai "barang" — kolomnya sama dengan produk.
  const quoteFor = (method, packagingType = data.packaging_type) =>
    quoteLine({
      product: auction,
      quantity: 1,
      unitPrice: winningPrice,
      method,
      destLat: data.shipping_latitude,
      destLng: data.shipping_longitude,
      packagingType,
      rates: feeRates,
    });

  const quote = useMemo(
    () => quoteFor(data.shipping_method),
    [
      data.shipping_method,
      data.packaging_type,
      data.shipping_latitude,
      data.shipping_longitude,
    ]
  );

  const summary = summarize([quote]);
  const amountDue = Math.max(0, summary.total - depositCredit);

  const shippingOptions = [
    {
      value: 'standard',
      title: 'Pengiriman Standard',
      note: 'Estimasi 3-5 hari kerja',
    },
    {
      value: 'express',
      title: 'Pengiriman Express',
      note: 'Estimasi 1-2 hari kerja',
    },
  ];

  const handleSubmit = (e) => {
    e.preventDefault();
    post(`/auctions/${auction.public_id}/pay`, { preserveScroll: true });
  };

  return (
    <section className="px-4 py-8 sm:px-6 md:px-16">
      <div className="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[1fr_400px]">
        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="flex gap-4 rounded-lg border bg-white p-6 shadow-md">
            <img
              src={getAuctionImage(auction)}
              alt={auction.name}
              className="h-24 w-24 shrink-0 rounded-lg object-cover"
            />
            <div>
              <p className="text-xs text-gray-500">Barang lelang dimenangkan</p>
              <h2 className="text-lg font-bold text-gray-900">
                {auction.name}
              </h2>
              <p className="mt-1 text-sm text-gray-500">
                {auction.store?.store_name || 'Toko'}
              </p>
              <p className="mt-2 font-bold text-[#B77C4C]">
                {formatIDR(winningPrice)}
              </p>
            </div>
          </div>

          <div className="rounded-lg border bg-white p-6 shadow-md">
            <h3 className="mb-4 text-lg font-bold text-gray-900">
              Detail Alamat
            </h3>
            <p className="mb-3 text-sm text-gray-500">
              Nama jalan, nomor rumah, dan patokan. Wilayah tujuannya sendiri
              diambil dari titik peta di bawah.
            </p>
            <textarea
              value={data.shipping_address}
              onChange={(e) => setData('shipping_address', e.target.value)}
              placeholder="Contoh: Jl. Kaliurang No. 10, sebelah masjid, pagar hijau"
              rows="4"
              className="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-[#53685B] focus:ring-2 focus:ring-[#53685B]"
              required
            />
            {errors.shipping_address && (
              <p className="mt-2 text-sm text-red-600">
                {errors.shipping_address}
              </p>
            )}
          </div>

          <DeliveryPointCard
            latitude={data.shipping_latitude}
            longitude={data.shipping_longitude}
            onChange={(lat, lng) => {
              setData((current) => ({
                ...current,
                shipping_latitude: lat,
                shipping_longitude: lng,
              }));
            }}
            stores={[auction.store]}
            distanceKm={quote.distance_km}
            error={errors.shipping_latitude || errors.shipping_longitude}
          />

          <div className="rounded-lg border bg-white p-6 shadow-md">
            <h3 className="mb-4 text-lg font-bold text-gray-900">
              Metode Pengiriman
            </h3>
            <div className="space-y-3">
              {shippingOptions.map((option) => (
                <label
                  key={option.value}
                  className="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-gray-200 p-4 hover:border-[#53685B] has-[:checked]:border-[#53685B] has-[:checked]:bg-[#53685B]/5"
                >
                  <input
                    type="radio"
                    name="shipping_method"
                    value={option.value}
                    checked={data.shipping_method === option.value}
                    onChange={(e) => setData('shipping_method', e.target.value)}
                    className="h-4 w-4 text-[#53685B]"
                  />
                  <div className="flex-1">
                    <p className="font-semibold text-gray-900">
                      {option.title}
                    </p>
                    <p className="text-sm text-gray-500">{option.note}</p>
                  </div>
                  <p className="font-bold text-gray-900">
                    {formatIDR(quoteFor(option.value).shipping_cost)}
                  </p>
                </label>
              ))}
            </div>
            {errors.shipping_method && (
              <p className="mt-2 text-sm text-red-600">
                {errors.shipping_method}
              </p>
            )}
          </div>

          <PackagingPicker
            value={data.packaging_type}
            onChange={(type) => setData('packaging_type', type)}
            rates={feeRates}
            priceFor={(type) =>
              quoteFor(data.shipping_method, type).packaging_fee
            }
            error={errors.packaging_type}
          />

          <div className="rounded-lg border bg-white p-6 shadow-md">
            <h3 className="mb-4 text-lg font-bold text-gray-900">
              Catatan untuk Penjual (Opsional)
            </h3>
            <textarea
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
              placeholder="Contoh: Kirim pagi hari, tolong packing bubble wrap ekstra..."
              rows="3"
              className="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-[#53685B] focus:ring-2 focus:ring-[#53685B]"
            />
            {errors.notes && (
              <p className="mt-2 text-sm text-red-600">{errors.notes}</p>
            )}
          </div>
        </form>

        <div>
          <div className="sticky top-6 rounded-lg border bg-white p-6 shadow-md">
            <h3 className="mb-4 text-lg font-bold text-gray-900">
              Ringkasan Pembayaran
            </h3>

            <CostBreakdown
              summary={summary}
              region={quote.region}
              hasDestination={quote.distance_km !== null}
              rates={feeRates}
              packagingType={data.packaging_type}
            />

            {depositCredit > 0 && (
              <div className="mt-4 space-y-2 border-t border-gray-200 pt-4 text-sm">
                <div className="flex justify-between text-gray-600">
                  <span>
                    Deposit sudah dibayar
                    <span className="block text-[11px] text-gray-400">
                      Dipotong dari tagihan sebagai uang muka
                    </span>
                  </span>
                  <span className="shrink-0 font-semibold text-green-700">
                    − {formatIDR(depositCredit)}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="font-bold text-gray-900">Sisa bayar</span>
                  <span className="text-xl font-bold text-[#B77C4C]">
                    {formatIDR(amountDue)}
                  </span>
                </div>
              </div>
            )}

            <button
              type="submit"
              onClick={handleSubmit}
              disabled={processing}
              className="mt-6 w-full rounded-lg bg-[#53685B] px-6 py-4 font-bold text-white transition hover:bg-[#3c4a3e] disabled:cursor-not-allowed disabled:opacity-50"
            >
              {processing ? 'Memproses...' : 'Lanjut ke Pembayaran'}
            </button>

            <p className="mt-4 text-center text-xs text-gray-500">
              Angka di atas masih pratinjau. Yang ditagihkan adalah hasil
              hitungan server setelah form ini dikirim.
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}

AuctionCheckoutPage.layout = (page) => (
  <MainLayout title="Pembayaran Lelang" heroText="Pembayaran Lelang">
    {page}
  </MainLayout>
);
