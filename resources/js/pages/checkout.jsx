import { Form, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR } from '@/lib/utils';

export default function CheckoutPage() {
  const { product } = usePage().props;

  return (
    <div className="mx-auto mt-10 max-w-3xl rounded-md border bg-white p-6 shadow-md">
      <h2 className="mb-6 text-2xl font-bold">Detail Checkout</h2>

      <div className="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
          <img
            src={`/${product.image}`}
            className="h-48 w-full rounded-md object-contain"
            alt={product.name}
          />
        </div>
        <div>
          <h3 className="text-xl font-semibold">{product.name}</h3>
          <p className="mt-2 text-gray-600">{product.description}</p>
          <p className="mt-4 text-lg font-bold">
            Harga: {formatIDR(product.price)}
          </p>
        </div>
      </div>

      <Form method="POST" action={`/cart/add/${product.id}`}>
        <input type="hidden" name="quantity" defaultValue="1" />
        <button className="rounded-md bg-yellow-300 px-6 py-2 font-semibold text-black hover:cursor-pointer hover:bg-yellow-400">
          🛒 Masukkan Keranjang
        </button>
      </Form>

      <Form method="POST" action={`/checkout/product/${product.id}`}>
        <input type="hidden" name="quantity" defaultValue="1" />
        <input type="hidden" name="payment_method" defaultValue="transfer" />
        {/* <!-- default / JS ganti -. */}
        <button className="my-2 rounded-md bg-[#53685B] px-6 py-2 font-semibold text-white hover:cursor-pointer hover:bg-[#3c4a3e]">
          ✅ Selesaikan Pesanan
        </button>
      </Form>

      <div className="mb-4">
        <label htmlFor="payment_method" className="block text-sm font-semibold">
          Metode Pembayaran
        </label>
        <select
          name="payment_method"
          id="payment_method"
          className="w-full rounded-md border border-gray-400 px-4 py-2"
          required
          defaultValue=""
        >
          <option value="" disabled>
            -- Pilih Metode Pembayaran --
          </option>
          <option value="transfer">Transfer Bank</option>
          <option value="cod">Cash on Delivery</option>
          <option value="ewallet">E-Wallet (OVO, GoPay, DANA)</option>
        </select>
      </div>
    </div>
  );
}

CheckoutPage.layout = (page) => (
  <MainLayout title="Checkout" heroText="Konfirmasi Pembelianmu">
    {page}
  </MainLayout>
);
