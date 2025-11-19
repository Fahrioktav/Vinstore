import { Link, usePage } from '@inertiajs/react';
import MainLayout from '../../layouts/main-layout';
import { formatIDR } from '../../lib/utils';

export default function StoreShowPage() {
  const { store } = usePage().props;

  return (
    <div className="mx-auto max-w-6xl px-4 py-6">
      <div className="mb-6">
        <h2 className="text-2xl font-bold">Toko: {store.name}</h2>
        <p className="text-gray-600">
          Pemilik: {store.user.first_name} {store.user.last_name}
        </p>
      </div>

      <div className="grid gap-6 md:grid-cols-3">
        {store.products.length > 0 ? (
          store.products.map((product) => (
            <div className="rounded-xl bg-white p-4 shadow-md" key={product.id}>
              <img
                src={`/${product.image}`}
                className="mb-3 h-48 w-full rounded object-cover"
                alt="Produk"
              />
              <h3 className="text-lg font-semibold">{product.name}</h3>
              <p className="mb-1 text-sm text-gray-600">
                Kategori: {product.category}
              </p>
              <p className="mb-1 font-bold text-gray-800">
                {formatIDR(product.price)}
              </p>
              <p className="mb-1 text-sm text-gray-600">
                Stok: {product.stock}
              </p>
              <p className="mb-2 text-sm text-gray-700">
                {product.description.substring(100)}
              </p>

              {product.stock > 0 ? (
                <Link
                  href={`/checkout/show/${product.id}`}
                  className="block rounded-md bg-[#53685B] px-4 py-2 text-center font-semibold text-white hover:bg-[#3c4a3e]"
                >
                  Pesan Sekarang
                </Link>
              ) : (
                <div className="text-center font-semibold text-red-500">
                  Stok Habis
                </div>
              )}
            </div>
          ))
        ) : (
          <p>Toko ini belum memiliki produk.</p>
        )}
      </div>
    </div>
  );
}

StoreShowPage.layout = (page) => {
  const { store } = page.props;

  return (
    <MainLayout
      title="Detail Toko"
      heroText={`🛍️ Toko: ${store.user.first_name} ${store.user.last_name}`}
    >
      {page}
    </MainLayout>
  );
};
