import { Form, usePage } from '@inertiajs/react';
import { formatIDR } from '../lib/utils';
import MainLayout from '../layouts/main-layout';

export default function CartPage() {
  const { cartItems } = usePage().props;

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
              key={item.id}
              className="flex flex-col items-center gap-6 border-b pb-4 sm:flex-row"
            >
              {/* <!-- Gambar Produk -. */}
              <img
                src={`/${item.product.image}`}
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
                      Harga Satuan:{' '}
                      {formatIDR(item.product.price)}
                    </p>
                    <p className="mt-1 text-sm font-medium text-gray-100">
                      Subtotal:{' '}
                      <span className="font-semibold text-green-600">
                        {formatIDR(item.product.price * item.quantity)}
                      </span>
                    </p>
                  </div>

                  {/* <!-- Tombol Hapus -. */}
                  <Form method="DELETE" action={`/cart/${item.id}`}>
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

      {/* <!-- Tombol Checkout -. */}
      <div className="mt-10 text-right">
        <Form action="/checkout/cart" method="POST">
          <button
            type="submit"
            className="rounded bg-[#53685B] px-6 py-2 font-semibold text-white transition hover:bg-[#3c4a3e]"
          >
            Lanjut ke Checkout
          </button>
        </Form>
      </div>
    </div>
  );
}

CartPage.layout = (page) => (
  <MainLayout title="Semua Produk">{page}</MainLayout>
);
