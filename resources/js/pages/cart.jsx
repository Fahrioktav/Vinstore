import { Form, useForm, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { formatIDR, getProductImage } from '@/lib/utils';
import { quoteCart, storeKeyOf, summarize } from '@/lib/shipping';
import DeliveryPointCard from '@/components/delivery-point-card';
import PackagingPicker from '@/components/packaging-picker';
import CostBreakdown from '@/components/cost-breakdown';
import { CartIcon, StoreIcon } from '@/components/icons';
import MainLayout from '@/layouts/main-layout';

const SHIPPING_OPTIONS = [
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

export default function CartPage() {
  const { cartItems, user, feeRates } = usePage().props;

  // Barang dikelompokkan per toko karena tiap toko dikirim sebagai paket
  // sendiri: ongkir dan kemasan dasarnya terpisah, dan sejak sekarang metode
  // pengiriman serta jenis pengemasannya pun dipilih sendiri-sendiri.
  const groups = useMemo(() => {
    const map = new Map();

    cartItems.forEach((item) => {
      const key = storeKeyOf(item);

      if (!map.has(key)) {
        map.set(key, {
          key,
          store: item.product?.store ?? null,
          name: item.product?.store?.store_name ?? 'Tanpa toko',
          items: [],
        });
      }

      map.get(key).items.push(item);
    });

    return [...map.values()];
  }, [cartItems]);

  const { data, setData, post, processing, errors } = useForm({
    shipping_address: user?.address || '',
    shipping_latitude: '',
    shipping_longitude: '',
    notes: '',
    // Satu entri per toko. Inilah yang divalidasi
    // OrderController::cartShippingRules() sebagai store_options.*.
    store_options: Object.fromEntries(
      groups.map((group) => [
        group.key,
        {
          shipping_method: 'standard',
          packaging_type: feeRates.packaging.default,
        },
      ])
    ),
  });

  const setStoreOption = (storeKey, field, value) => {
    setData('store_options', {
      ...data.store_options,
      [storeKey]: { ...data.store_options[storeKey], [field]: value },
    });
  };

  // Pratinjau biaya. Rumusnya cerminan ShippingCostService; yang ditagihkan
  // tetap hasil hitungan server saat form dikirim.
  const quoteWith = (storeOptions) =>
    quoteCart({
      cartItems,
      storeOptions,
      destLat: data.shipping_latitude,
      destLng: data.shipping_longitude,
      rates: feeRates,
    });

  const lines = useMemo(
    () => quoteWith(data.store_options),
    [
      cartItems,
      data.store_options,
      data.shipping_latitude,
      data.shipping_longitude,
    ]
  );

  const summary = summarize(lines);

  // Biaya satu toko bila salah satu opsinya diubah — dipakai menampilkan harga
  // pada tiap pilihan tanpa mengubah state.
  const storeTotalIf = (storeKey, field, value) => {
    const probe = {
      ...data.store_options,
      [storeKey]: { ...data.store_options[storeKey], [field]: value },
    };

    return summarize(quoteWith(probe).filter((l) => l.storeKey === storeKey));
  };

  const linesOf = (storeKey) => lines.filter((l) => l.storeKey === storeKey);

  const stores = groups.map((g) => g.store).filter(Boolean);

  // Jarak yang ditampilkan: yang terjauh di antara toko-toko dalam keranjang.
  const distances = lines
    .map((line) => line.distance_km)
    .filter((km) => km !== null);
  const distanceKm = distances.length ? Math.max(...distances) : null;

  const handleSubmit = (e) => {
    e.preventDefault();
    post('/checkout/cart');
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-6 sm:py-8">
      <h2 className="mb-6 flex items-center justify-center gap-2 text-center text-2xl font-bold text-gray-100">
        <CartIcon className="h-7 w-7" />
        Keranjang Belanja Kamu
      </h2>

      {cartItems.length === 0 ? (
        <div className="text-center text-gray-100">
          Keranjang kamu masih kosong.
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Alamat Pengiriman — berlaku untuk seluruh keranjang */}
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

          {/* Titik Pengantaran — juga berlaku untuk seluruh keranjang */}
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
            stores={stores}
            distanceKm={distanceKm}
            error={errors.shipping_latitude || errors.shipping_longitude}
          />

          {groups.length > 1 && (
            <p className="rounded-lg bg-white/10 px-4 py-3 text-sm text-gray-100">
              Belanjaan Anda berasal dari <strong>{groups.length} toko</strong>.
              Tiap toko dikirim sebagai paket terpisah, jadi pengiriman dan
              pengemasannya dipilih sendiri-sendiri — tetapi pembayarannya tetap
              satu kali.
            </p>
          )}

          {/* Satu blok per toko */}
          {groups.map((group) => {
            const groupLines = linesOf(group.key);
            const groupSummary = summarize(groupLines);
            const options = data.store_options[group.key] ?? {};

            return (
              <div
                key={group.key}
                className="overflow-hidden rounded-lg border bg-white shadow-md"
              >
                {/* Kepala: nama toko */}
                <div className="flex items-center gap-2 border-b bg-gray-50 px-6 py-4">
                  <StoreIcon className="h-5 w-5 text-[#53685B]" />
                  <h3 className="font-bold text-gray-900 capitalize">
                    {group.name}
                  </h3>
                  <span className="ml-auto text-sm text-gray-500">
                    {group.items.length} barang
                  </span>
                </div>

                {/* Barang milik toko ini */}
                <div className="divide-y">
                  {group.items.map((item) => (
                    <div
                      key={item.public_id}
                      className="flex items-center gap-4 px-6 py-4"
                    >
                      <img
                        src={getProductImage(item.product)}
                        alt={item.product.name}
                        className="h-20 w-20 rounded-md border object-cover"
                      />
                      <div className="flex-1">
                        <p className="font-semibold text-gray-900">
                          {item.product.name}
                        </p>
                        <p className="mt-1 text-sm text-gray-500">
                          {item.quantity} × {formatIDR(item.product.price)}
                        </p>
                        <p className="mt-1 text-sm font-semibold text-[#53685B]">
                          {formatIDR(item.product.price * item.quantity)}
                        </p>
                      </div>
                      <Form method="DELETE" action={`/cart/${item.public_id}`}>
                        <button className="text-sm font-medium text-red-500 hover:text-red-700">
                          Hapus
                        </button>
                      </Form>
                    </div>
                  ))}
                </div>

                {/* Metode pengiriman khusus toko ini */}
                <div className="border-t px-6 py-4">
                  <h4 className="mb-3 text-sm font-bold text-gray-900">
                    Metode Pengiriman
                  </h4>
                  <div className="space-y-2">
                    {SHIPPING_OPTIONS.map((option) => (
                      <label
                        key={option.value}
                        className="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-gray-200 p-3 hover:border-[#53685B] has-[:checked]:border-[#53685B] has-[:checked]:bg-[#53685B]/5"
                      >
                        <input
                          type="radio"
                          name={`shipping_method_${group.key}`}
                          value={option.value}
                          checked={options.shipping_method === option.value}
                          onChange={(e) =>
                            setStoreOption(
                              group.key,
                              'shipping_method',
                              e.target.value
                            )
                          }
                          className="h-4 w-4 text-[#53685B]"
                        />
                        <div className="flex-1">
                          <p className="text-sm font-semibold text-gray-900">
                            {option.title}
                          </p>
                          <p className="text-xs text-gray-500">{option.note}</p>
                        </div>
                        <p className="text-sm font-bold text-gray-900">
                          {formatIDR(
                            storeTotalIf(
                              group.key,
                              'shipping_method',
                              option.value
                            ).shipping_cost
                          )}
                        </p>
                      </label>
                    ))}
                  </div>
                  {errors[`store_options.${group.key}.shipping_method`] && (
                    <p className="mt-2 text-sm text-red-600">
                      {errors[`store_options.${group.key}.shipping_method`]}
                    </p>
                  )}
                </div>

                {/* Pengemasan khusus toko ini */}
                <PackagingPicker
                  value={options.packaging_type}
                  onChange={(type) =>
                    setStoreOption(group.key, 'packaging_type', type)
                  }
                  rates={feeRates}
                  priceFor={(type) =>
                    storeTotalIf(group.key, 'packaging_type', type)
                      .packaging_fee
                  }
                  error={errors[`store_options.${group.key}.packaging_type`]}
                  name={`packaging_type_${group.key}`}
                  bare
                />

                {/* Subtotal toko ini */}
                <div className="flex items-center justify-between border-t bg-gray-50 px-6 py-3">
                  <span className="text-sm font-semibold text-gray-600">
                    Subtotal {group.name}
                  </span>
                  <span className="font-bold text-[#53685B]">
                    {formatIDR(groupSummary.total)}
                  </span>
                </div>
              </div>
            );
          })}

          {/* Catatan untuk Penjual */}
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

          {/* Ringkasan seluruh keranjang — satu pembayaran */}
          <div className="rounded-lg border bg-white p-6 shadow-md">
            <h3 className="mb-1 text-lg font-bold text-gray-900">
              Ringkasan Pembayaran
            </h3>
            <p className="mb-4 text-sm text-gray-500">
              Seluruh toko dibayar sekaligus dalam satu transaksi.
            </p>

            <CostBreakdown
              summary={summary}
              distanceKm={distanceKm}
              rates={feeRates}
              storeCount={groups.length}
            />

            <button
              type="submit"
              disabled={processing}
              className="mt-6 w-full rounded bg-[#53685B] px-6 py-3 font-semibold text-white transition hover:bg-[#3c4a3e] disabled:cursor-not-allowed disabled:opacity-50"
            >
              {processing ? 'Memproses...' : 'Lanjut ke Checkout'}
            </button>
          </div>
        </form>
      )}
    </div>
  );
}

CartPage.layout = (page) => (
  <MainLayout title="Semua Produk">{page}</MainLayout>
);
