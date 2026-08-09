import { router, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { useParams } from '@/lib/utils';
import ProductCard from '@/components/product-card';
import SearchInput from '@/components/search-input';
import { useState } from 'react';

export default function ProductsPage() {
  const { paginatedProducts, showSearch, categories, filters } =
    usePage().props;
  const products = paginatedProducts.data;
  const q = useParams().get('q');
  const [filterData, setFilterData] = useState({
    category: filters?.category || '',
    min_price: filters?.min_price || '',
    max_price: filters?.max_price || '',
    in_stock: filters?.in_stock === '1' || filters?.in_stock === true,
    certified: filters?.certified === '1' || filters?.certified === true,
    sort: filters?.sort || 'latest',
  });

  const applyFilters = (e) => {
    e.preventDefault();

    router.get(
      '/products',
      {
        q: q || undefined,
        category: filterData.category || undefined,
        min_price: filterData.min_price || undefined,
        max_price: filterData.max_price || undefined,
        in_stock: filterData.in_stock ? 1 : undefined,
        certified: filterData.certified ? 1 : undefined,
        sort: filterData.sort !== 'latest' ? filterData.sort : undefined,
      },
      { preserveState: true, preserveScroll: true }
    );
  };

  const resetFilters = () => {
    router.get('/products', q ? { q } : {}, {
      preserveState: false,
      preserveScroll: true,
    });
  };

  return (
    <>
      {showSearch && (
        <SearchInput
          action="/products"
          defaultValue={q}
          placeholder="Cari barang antik favoritmu..."
        />
      )}

      <div className="px-4 py-8 sm:px-6 sm:py-10 md:px-16">
        <h2 className="mb-6 text-3xl font-bold text-[#E9E19E]">Semua Produk</h2>
        <form
          onSubmit={applyFilters}
          className="mb-8 rounded-lg bg-white p-5 shadow-md"
        >
          <div className="grid gap-4 md:grid-cols-3 lg:grid-cols-6">
            <label className="block">
              <span className="mb-1 block text-sm font-semibold text-gray-700">
                Kategori
              </span>
              <select
                value={filterData.category}
                onChange={(e) =>
                  setFilterData((data) => ({
                    ...data,
                    category: e.target.value,
                  }))
                }
                className="w-full rounded-lg border border-gray-300 px-3 py-2"
              >
                <option value="">Semua</option>
                {categories?.map((category) => (
                  <option key={category.name} value={category.name}>
                    {category.name}
                  </option>
                ))}
              </select>
            </label>
            <FilterInput
              label="Harga Min"
              value={filterData.min_price}
              onChange={(value) =>
                setFilterData((data) => ({ ...data, min_price: value }))
              }
            />
            <FilterInput
              label="Harga Max"
              value={filterData.max_price}
              onChange={(value) =>
                setFilterData((data) => ({ ...data, max_price: value }))
              }
            />
            <label className="block">
              <span className="mb-1 block text-sm font-semibold text-gray-700">
                Urutkan
              </span>
              <select
                value={filterData.sort}
                onChange={(e) =>
                  setFilterData((data) => ({ ...data, sort: e.target.value }))
                }
                className="w-full rounded-lg border border-gray-300 px-3 py-2"
              >
                <option value="latest">Terbaru</option>
                <option value="oldest">Terlama</option>
                <option value="price_low">Harga Terendah</option>
                <option value="price_high">Harga Tertinggi</option>
              </select>
            </label>
            <label className="flex items-center gap-2 pt-7 text-sm font-semibold text-gray-700">
              <input
                type="checkbox"
                checked={filterData.in_stock}
                onChange={(e) =>
                  setFilterData((data) => ({
                    ...data,
                    in_stock: e.target.checked,
                  }))
                }
              />
              Stok tersedia
            </label>
            <label className="flex items-center gap-2 pt-7 text-sm font-semibold text-gray-700">
              <input
                type="checkbox"
                checked={filterData.certified}
                onChange={(e) =>
                  setFilterData((data) => ({
                    ...data,
                    certified: e.target.checked,
                  }))
                }
              />
              Bersertifikat
            </label>
          </div>
          <div className="mt-4 flex gap-3">
            <button className="rounded-lg bg-[#53685B] px-5 py-2 font-semibold text-white hover:bg-[#3c4a3e]">
              Terapkan Filter
            </button>
            <button
              type="button"
              onClick={resetFilters}
              className="rounded-lg bg-gray-200 px-5 py-2 font-semibold text-gray-700 hover:bg-gray-300"
            >
              Reset
            </button>
          </div>
        </form>
        {products.length > 0 ? (
          <>
            <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
              {products.map((product) => (
                <ProductCard key={product.public_id} product={product} />
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

function FilterInput({ label, value, onChange }) {
  return (
    <label className="block">
      <span className="mb-1 block text-sm font-semibold text-gray-700">
        {label}
      </span>
      <input
        type="number"
        value={value}
        min="0"
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-lg border border-gray-300 px-3 py-2"
      />
    </label>
  );
}

ProductsPage.layout = (page) => (
  <MainLayout title="Semua Produk">{page}</MainLayout>
);
