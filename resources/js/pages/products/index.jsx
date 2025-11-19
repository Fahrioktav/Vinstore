import { Form, usePage } from '@inertiajs/react';
import MainLayout from '../../layouts/main-layout';
import { formatIDR } from '../../lib/utils';

export default function ProductsPage() {
  const { paginatedProducts } = usePage().props;
  const products = paginatedProducts.data;

  return (
    <div className="px-6 py-10 md:px-16">
      <h2 className="mb-6 text-3xl font-bold text-[#3E2723]">Semua Produk</h2>
      {products.length > 0 ? (
        <>
          <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            {products.map((product) => (
              <div
                key={product.id}
                className="flex flex-col rounded-xl bg-white p-4 shadow-md transition hover:shadow-lg"
              >
                <img
                  src={`/${product.image}`}
                  className="mb-4 h-48 w-full rounded-md object-cover"
                />
                <h3 className="mb-1 text-lg font-semibold text-[#3E2723]">
                  {product.name}
                </h3>
                <p className="mb-3 line-clamp-3 text-sm text-gray-600">
                  {product.description}
                </p>
                <div className="mt-auto flex items-center justify-between">
                  <span className="font-bold text-[#B77C4C]">
                    {formatIDR(product.price)}
                  </span>
                  <Form action={`/checkout/show/${product.id}`} method="GET">
                    <button
                      type="submit"
                      className="rounded-md bg-[#B77C4C] px-3 py-1 text-sm text-white hover:bg-[#a0683d]"
                    >
                      Order
                    </button>
                  </Form>
                </div>
              </div>
            ))}
          </div>
          {/* <div className="mt-10">{products.links}</div> */}
        </>
      ) : (
        <p className="text-gray-600">Belum ada produk yang tersedia.</p>
      )}
    </div>
  );
}

ProductsPage.layout = (page) => (
  <MainLayout title="Semua Produk">{page}</MainLayout>
);
