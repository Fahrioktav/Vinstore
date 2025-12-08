import { usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import ProductCard from '@/components/product-card';

export default function StoreShowPage() {
  const { store } = usePage().props;

  return (
    <div className="px-6 py-10 md:px-16">
      <div className="mb-6">
        <h2 className="text-2xl font-bold">
          <span className="text-[#E9E19E]">Toko: </span>
          <span className="text-gray-100">{store.store_name}</span>
        </h2>
        <p>
          <span className="text-[#E9E19E]">Pemilik: </span>
          <span className="text-gray-100">
            {store.user.first_name} {store.user.last_name}
          </span>
        </p>
      </div>

      <div className="grid gap-6 md:grid-cols-3">
        {store.products.length > 0 ? (
          store.products.map((product) => <ProductCard product={product} />)
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
