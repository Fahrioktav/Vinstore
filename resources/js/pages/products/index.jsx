import { Form, Link, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR, useParams } from '@/lib/utils';
import ProductCard from '@/components/product-card';
import SearchInput from '@/components/search-input';

export default function ProductsPage() {
  const { paginatedProducts, showSearch } = usePage().props;
  const products = paginatedProducts.data;

  // Keyword pencarian nama, kategori, deskripsi barang
  const q = useParams().get('q');

  return (
    <>
      {showSearch && (
        <SearchInput
          action="/products"
          defaultValue={q}
          placeholder="Cari barang antik favoritmu..."
        />
      )}

      <div className="px-6 py-10 md:px-16">
        <h2 className="mb-6 text-3xl font-bold text-[#E9E19E]">Semua Produk</h2>
        {products.length > 0 ? (
          <>
            <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
              {products.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          </>
        ) : (
          <p className="text-gray-600">Belum ada produk yang tersedia.</p>
        )}
      </div>
    </>
  );
}

ProductsPage.layout = (page) => (
  <MainLayout title="Semua Produk">{page}</MainLayout>
);
