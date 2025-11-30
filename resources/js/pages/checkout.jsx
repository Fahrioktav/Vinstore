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
          {product.certificate && (
            <div className="mt-4 rounded-lg bg-green-50 border border-green-200 p-3">
              <p className="text-sm font-semibold text-green-800 mb-2 flex items-center gap-2">
                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                </svg>
                Produk Bersertifikat
              </p>
              <a 
                href={`/${product.certificate}`} 
                target="_blank"
                rel="noopener noreferrer"
                className="text-sm text-blue-600 hover:underline flex items-center gap-1"
              >
                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" />
                </svg>
                Lihat Sertifikat Keaslian
              </a>
            </div>
          )}
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
