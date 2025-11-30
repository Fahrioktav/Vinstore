import { Form, Link, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR } from '@/lib/utils';

export default function ProductsPage() {
  const { user, paginatedProducts } = usePage().props;
  const products = paginatedProducts.data;

  return (
    <div className="px-6 py-10 md:px-16">
      <h2 className="mb-6 text-3xl font-bold text-white">Semua Produk</h2>
      {products.length > 0 ? (
        <>
          <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            {products.map((product) => (
              <div
                key={product.id}
                className="flex flex-col rounded-xl bg-white p-4 shadow-md transition hover:shadow-lg"
              >
                <div className="relative">
                  <img
                    src={`/${product.image}`}
                    className="mb-4 h-48 w-full rounded-md object-cover"
                  />
                  {product.certificate && (
                    <span className="absolute top-2 right-2 rounded-md bg-green-600 px-2 py-1 text-xs text-white shadow flex items-center gap-1">
                      <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                      </svg>
                      Bersertifikat
                    </span>
                  )}
                </div>
                <h3 className="mb-1 text-lg font-semibold text-[#3E2723]">
                  {product.name}
                </h3>
                <p className="mb-3 line-clamp-3 text-sm text-gray-600">
                  {product.description}
                </p>
                <div className="mt-auto">
                  <div className="flex items-center justify-between mb-2">
                    <span className="font-bold text-[#B77C4C]">
                      {formatIDR(product.price)}
                    </span>
                    <span className={`text-xs font-medium ${product.stock > 10 ? 'text-green-600' : product.stock > 0 ? 'text-orange-600' : 'text-red-600'}`}>
                      {product.stock > 0 ? `Stok: ${product.stock}` : 'Habis'}
                    </span>
                  </div>

                  {product.stock > 0 ? (
                    <Link href={`/checkout/show/${product.id}`}>
                      <button
                        type="submit"
                        className="w-full rounded-md bg-[#B77C4C] px-3 py-3 text-sm text-white hover:bg-[#a0683d]"
                      >
                        Order
                      </button>
                    </Link>
                  ) : (
                    <button disabled className="w-full rounded-md bg-gray-400 px-3 py-3 text-sm text-white cursor-not-allowed">
                      Stok Habis
                    </button>
                  )}
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
