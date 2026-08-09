import { useForm, usePage, router } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR, getProductImage } from '@/lib/utils';
import { openSnapPayment } from '@/lib/midtrans';
import { quoteLine, summarize } from '@/lib/shipping';
import DeliveryPointCard from '@/components/delivery-point-card';
import PackagingPicker from '@/components/packaging-picker';
import CostBreakdown from '@/components/cost-breakdown';
import { toast } from 'sonner';

export default function CheckoutPage() {
  const { product, user, flash, feeRates } = usePage().props;

  const { data, setData, post, processing, errors } = useForm({
    quantity: 1,
    shipping_address: user?.address || '',
    shipping_method: 'standard',
    packaging_type: feeRates.packaging.default,
    shipping_latitude: '',
    shipping_longitude: '',
    notes: '',
  });

  const unitPrice = product.price || 0;

  // Pratinjau biaya. Rumusnya cerminan App\Services\ShippingCostService; yang
  // ditagihkan tetap hasil hitungan server saat form dikirim.
  const quoteFor = (method, packagingType = data.packaging_type) =>
    quoteLine({
      product,
      quantity: data.quantity,
      unitPrice,
      method,
      destLat: data.shipping_latitude,
      destLng: data.shipping_longitude,
      packagingType,
      rates: feeRates,
    });

  const quote = useMemo(
    () => quoteFor(data.shipping_method),
    [
      data.quantity,
      data.shipping_method,
      data.packaging_type,
      data.shipping_latitude,
      data.shipping_longitude,
    ]
  );

  const summary = summarize([quote]);

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

  // Handle snap_token dari flash data.
  // openSnapPayment menunggu library Snap siap lebih dulu, supaya popup tidak
  // gagal muncul diam-diam saat skripnya belum selesai dimuat.
  useEffect(() => {
    if (!flash?.snap_token) {
      return;
    }

    openSnapPayment(flash.snap_token, {
      onSuccess: () => {
        toast.success('Pembayaran berhasil!');
        router.visit('/order');
      },
      onPending: () => {
        toast.info('Pembayaran sedang diproses');
        router.visit('/order');
      },
      onError: () => {
        toast.error('Pembayaran gagal. Silakan coba lagi.');
      },
      onClose: () => {
        toast.info('Anda menutup popup pembayaran');
      },
    }).catch((error) => {
      toast.error(error.message);
    });
  }, [flash?.snap_token]);

  const handleSubmit = (e) => {
    e.preventDefault();
    post(`/checkout/product/${product.public_id}`, {
      preserveScroll: true,
    });
  };

  return (
    <div className="mx-auto mt-6 max-w-5xl px-4 pb-10 sm:mt-10">
      <h2 className="mb-6 text-xl font-bold text-white sm:text-3xl">
        Checkout
      </h2>

      {flash?.success && (
        <div className="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
          {flash.success}
        </div>
      )}
      {flash?.error && (
        <div className="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {flash.error}
        </div>
      )}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Form Checkout */}
        <div className="space-y-6 lg:col-span-2">
          {/* Informasi Produk */}
          <div className="rounded-lg border bg-white p-6 shadow-md">
            <h3 className="mb-4 text-lg font-bold text-gray-900">
              Produk yang Dibeli
            </h3>
            <div className="flex gap-4">
              <img
                src={getProductImage(product)}
                alt={product.name}
                className="h-24 w-24 rounded-lg border object-cover"
              />
              <div className="flex-1">
                <h4 className="font-semibold text-gray-900">{product.name}</h4>
                <p className="mt-1 text-sm text-gray-500">{product.category}</p>
                <p className="mt-2 text-lg font-bold text-[#B77C4C]">
                  {formatIDR(unitPrice)}
                </p>
                <p className="mt-1 text-xs text-gray-500">
                  Stok tersedia: {product.available_stock} unit
                </p>
              </div>
            </div>
          </div>

          {/* Form */}
          <form onSubmit={handleSubmit} className="space-y-6">
            {/* Jumlah Produk */}
            <div className="rounded-lg border bg-white p-6 shadow-md">
              <h3 className="mb-4 text-lg font-bold text-gray-900">
                Jumlah Produk
              </h3>
              <div className="flex items-center gap-4">
                <button
                  type="button"
                  onClick={() =>
                    setData('quantity', Math.max(1, data.quantity - 1))
                  }
                  className="h-10 w-10 rounded-lg border border-gray-300 font-bold hover:bg-gray-100"
                  disabled={data.quantity <= 1}
                >
                  −
                </button>
                <input
                  type="number"
                  min="1"
                  max={product.available_stock}
                  value={data.quantity}
                  onChange={(e) =>
                    setData('quantity', parseInt(e.target.value) || 1)
                  }
                  className="w-20 rounded-lg border border-gray-300 px-4 py-2 text-center font-semibold"
                />
                <button
                  type="button"
                  onClick={() =>
                    setData(
                      'quantity',
                      Math.min(product.available_stock, data.quantity + 1)
                    )
                  }
                  className="h-10 w-10 rounded-lg border border-gray-300 font-bold hover:bg-gray-100"
                  disabled={data.quantity >= product.available_stock}
                >
                  +
                </button>
                <span className="text-sm text-gray-500">
                  (Max: {product.available_stock} unit)
                </span>
              </div>
              {errors.quantity && (
                <p className="mt-2 text-sm text-red-600">{errors.quantity}</p>
              )}
            </div>

            {/* Alamat Pengiriman */}
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

            {/* Titik Pengantaran */}
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
              stores={[product.store]}
              distanceKm={quote.distance_km}
              error={errors.shipping_latitude || errors.shipping_longitude}
            />

            {/* Metode Pengiriman */}
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
                      onChange={(e) =>
                        setData('shipping_method', e.target.value)
                      }
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

            {/* Jenis Pengemasan */}
            <PackagingPicker
              value={data.packaging_type}
              onChange={(type) => setData('packaging_type', type)}
              rates={feeRates}
              priceFor={(type) =>
                quoteFor(data.shipping_method, type).packaging_fee
              }
              error={errors.packaging_type}
            />

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
          </form>
        </div>

        {/* Ringkasan Pembayaran */}
        <div className="lg:col-span-1">
          <div className="sticky top-6 rounded-lg border bg-white p-6 shadow-md">
            <h3 className="mb-4 text-lg font-bold text-gray-900">
              Ringkasan Pembayaran
            </h3>

            <CostBreakdown
              summary={summary}
              distanceKm={quote.distance_km}
              rates={feeRates}
              packagingType={data.packaging_type}
            />

            <button
              type="submit"
              onClick={handleSubmit}
              disabled={processing || product.available_stock <= 0}
              className="mt-6 w-full rounded-lg bg-[#53685B] px-6 py-4 font-bold text-white transition hover:bg-[#3c4a3e] disabled:cursor-not-allowed disabled:opacity-50"
            >
              {processing ? 'Memproses...' : 'Bayar Sekarang'}
            </button>

            <p className="mt-4 text-center text-xs text-gray-500">
              Dengan melanjutkan, Anda menyetujui syarat & ketentuan yang
              berlaku
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

CheckoutPage.layout = (page) => (
  <MainLayout title="Checkout" heroText="Checkout">
    {page}
  </MainLayout>
);
