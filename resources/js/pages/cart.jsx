import { Form, useForm, usePage } from '@inertiajs/react';
import { formatIDR, getProductImage } from '@/lib/utils';
import MainLayout from '@/layouts/main-layout';

export default function CartPage() {
  const { cartItems, user } = usePage().props;

  const { data, setData, post, processing, errors } = useForm({
    shipping_address: user?.address || '',
    shipping_method: 'standard',
    notes: '',
  });

  const subtotal = cartItems.reduce(
    (total, item) => total + item.product.price * item.quantity,
    0
  );

  // Ongkir ditagih sekali per toko — samakan dengan perhitungan di
  // OrderController::checkoutFromCart() supaya total yang dilihat pembeli
  // cocok dengan tagihan Midtrans.
  const storeCount = new Set(
    cartItems.map((item) => item.product.store?.public_id ?? 'none')
  ).size;
  const shippingRate = data.shipping_method === 'express' ? 25000 : 10000;
  const shippingCost = storeCount * shippingRate;
  const total = subtotal + shippingCost;

  const handleSubmit = (e) => {
    e.preventDefault();
    post('/checkout/cart');
  };

  return (
    <div className="mx-auto max-w-4xl py-8">
      <h2 className="mb-6 text-center text-2xl font-bold text-gray-100">
        🛒 Keranjang Belanja Kamu
      </h2>
      {cartItems.length === 0 ? (
        <div className="text-center text-gray-100">
          Keranjang kamu masih kosong.
        </div>
      ) : (
        <div className="space-y-6 text-gray-100">
          {cartItems.map((item) => (
            <div
              key={item.public_id}
              className="flex flex-col items-center gap-6 border-b pb-4 sm:flex-row"
            >
              {/* <!-- Gambar Produk -. */}
              <img
                src={getProductImage(item.product)}
                alt={item.product.name}
                className="h-28 w-28 rounded-md border object-cover"
              />

              {/* <!-- Info Produk -. */}
              <div className="w-full flex-1">
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-lg font-semibold">{item.product.name}</p>
                    <p className="mt-1 text-sm text-gray-100">
                      Jumlah: {item.quantity}
                    </p>
                    <p className="text-sm text-gray-100">
                      Harga Satuan: {formatIDR(item.product.price)}
                    </p>
                    <p className="mt-1 text-sm font-medium text-gray-100">
                      Subtotal:{' '}
                      <span className="font-semibold text-green-600">
                        {formatIDR(item.product.price * item.quantity)}
                      </span>
                    </p>
                  </div>

                  {/* <!-- Tombol Hapus -. */}
                  <Form method="DELETE" action={`/cart/${item.public_id}`}>
                    <button className="mt-1 text-sm font-medium text-red-500 hover:text-red-700">
                      Hapus
                    </button>
                  </Form>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {cartItems.length > 0 && (
        <form onSubmit={handleSubmit} className="mt-10 space-y-6">
          {/* Alamat Pengiriman */}
          <div className="rounded-lg border bg-white p-6 shadow-md">
            <h3 className="mb-4 text-lg font-bold text-gray-900">
              Alamat Pengiriman
            </h3>
            <textarea
              value={data.shipping_address}
              onChange={(e) => setData('shipping_address', e.target.value)}
              placeholder="Masukkan alamat lengkap pengiriman..."
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

          {/* Metode Pengiriman */}
          <div className="rounded-lg border bg-white p-6 shadow-md">
            <h3 className="mb-4 text-lg font-bold text-gray-900">
              Metode Pengiriman
            </h3>
            <div className="space-y-3">
              <label className="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-gray-200 p-4 hover:border-[#53685B] has-[:checked]:border-[#53685B] has-[:checked]:bg-[#53685B]/5">
                <input
                  type="radio"
                  name="shipping_method"
                  value="standard"
                  checked={data.shipping_method === 'standard'}
                  onChange={(e) => setData('shipping_method', e.target.value)}
                  className="h-4 w-4 text-[#53685B]"
                />
                <div className="flex-1">
                  <p className="font-semibold text-gray-900">
                    Pengiriman Standard
                  </p>
                  <p className="text-sm text-gray-500">
                    Estimasi 3-5 hari kerja
                  </p>
                </div>
                <p className="font-bold text-gray-900">{formatIDR(10000)}</p>
              </label>

              <label className="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-gray-200 p-4 hover:border-[#53685B] has-[:checked]:border-[#53685B] has-[:checked]:bg-[#53685B]/5">
                <input
                  type="radio"
                  name="shipping_method"
                  value="express"
                  checked={data.shipping_method === 'express'}
                  onChange={(e) => setData('shipping_method', e.target.value)}
                  className="h-4 w-4 text-[#53685B]"
                />
                <div className="flex-1">
                  <p className="font-semibold text-gray-900">
                    Pengiriman Express
                  </p>
                  <p className="text-sm text-gray-500">
                    Estimasi 1-2 hari kerja
                  </p>
                </div>
                <p className="font-bold text-gray-900">{formatIDR(25000)}</p>
              </label>
            </div>
            {storeCount > 1 && (
              <p className="mt-3 text-xs text-gray-500">
                Belanjaan Anda berasal dari {storeCount} toko, sehingga ongkir
                dihitung {storeCount}x.
              </p>
            )}
            {errors.shipping_method && (
              <p className="mt-2 text-sm text-red-600">
                {errors.shipping_method}
              </p>
            )}
          </div>

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

          {/* Ringkasan Pembayaran */}
          <div className="rounded-lg border bg-white p-6 shadow-md">
            <h3 className="mb-4 text-lg font-bold text-gray-900">
              Ringkasan Pembayaran
            </h3>
            <div className="space-y-3 border-b border-gray-200 pb-4">
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Subtotal</span>
                <span className="font-semibold text-gray-900">
                  {formatIDR(subtotal)}
                </span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Biaya Pengiriman</span>
                <span className="font-semibold text-gray-900">
                  {formatIDR(shippingCost)}
                </span>
              </div>
            </div>
            <div className="mt-4 flex justify-between">
              <span className="text-lg font-bold text-gray-900">Total</span>
              <span className="text-2xl font-bold text-[#B77C4C]">
                {formatIDR(total)}
              </span>
            </div>

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
