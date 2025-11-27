import { Head, Link, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR } from '@/lib/utils';

export default function AdminDashboard() {
  const {
    totalUsers,
    totalSellers,
    totalStores,
    totalProducts,
    totalOrders,
    totalCategories,
    products,
  } = usePage().props;

  const stats = [
    {
      label: 'Total User',
      value: totalUsers,
      icon: '/assets/1.png',
      href: '/admin/users',
      color: 'from-blue-500 to-blue-600',
    },
    {
      label: 'Total Seller',
      value: totalSellers,
      icon: '/assets/2.png',
      href: '/admin/sellers',
      color: 'from-green-500 to-green-600',
    },
    {
      label: 'Total Toko',
      value: totalStores,
      icon: '/assets/3.png',
      href: '/admin/stores',
      color: 'from-purple-500 to-purple-600',
    },
    {
      label: 'Total Barang',
      value: totalProducts,
      icon: '/assets/4.png',
      href: '/admin/products',
      color: 'from-orange-500 to-orange-600',
    },
    {
      label: 'Total Order',
      value: totalOrders,
      icon: '/assets/5.png',
      href: '/admin/orders',
      color: 'from-red-500 to-red-600',
    },
    {
      label: 'Category',
      value: totalCategories,
      icon: '/assets/6.png',
      href: '/admin/categories',
      color: 'from-indigo-500 to-indigo-600',
    },
  ];

  return (
    <div className="bg-white">
      <Head title="Admin Dashboard" />
      <div className="mx-auto max-w-7xl px-6 py-8">
        <h2 className="mt-2 mb-6 text-3xl font-bold text-[#53685B]">
          Dashboard Admin
        </h2>

        {/* Stats Grid */}
        <div className="mb-12 grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
          {stats.map((stat, index) => (
            <Link
              key={index}
              href={stat.href}
              className="group relative block rounded-2xl border border-gray-100 bg-gradient-to-br from-white to-gray-50 p-6 shadow-lg shadow-[#53685B]/10 transition-all duration-300 hover:scale-105 hover:shadow-xl"
            >
              <div className="flex items-center gap-4">
                <div className="rounded-xl bg-[#53685B]/10 p-3 transition group-hover:bg-[#53685B]/20">
                  <img src={stat.icon} className="h-8 w-8" alt={stat.label} />
                </div>
                <div>
                  <p className="text-3xl font-bold text-[#53685B]">
                    {stat.value}
                  </p>
                  <p className="text-sm font-medium text-gray-600">
                    {stat.label}
                  </p>
                </div>
              </div>
              <div className="absolute top-3 right-4 text-4xl text-[#53685B] opacity-20 transition group-hover:opacity-40">
                →
              </div>
            </Link>
          ))}
        </div>

        {/* Products Table */}
        <div className="mb-10 rounded-2xl bg-white p-6 shadow-lg shadow-[#53685B]/10">
          <div className="mb-6 flex items-center justify-between">
            <h2 className="text-2xl font-bold text-[#53685B]">
              📦 Daftar Produk Terbaru
            </h2>
            <Link
              href="/admin/products"
              className="text-sm font-semibold text-[#53685B] transition hover:text-[#3c4a3e] hover:underline"
            >
              Lihat Semua →
            </Link>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="rounded-tl-lg px-4 py-3 text-left">Foto</th>
                  <th className="px-4 py-3 text-left">Nama Barang</th>
                  <th className="px-4 py-3 text-left">Toko</th>
                  <th className="px-4 py-3 text-left">Stok</th>
                  <th className="px-4 py-3 text-left">Harga</th>
                  <th className="px-4 py-3 text-left">Kategori</th>
                  <th className="rounded-tr-lg px-4 py-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {products.length > 0 ? (
                  products.map((product) => (
                    <ProductRow key={product.id} product={product} />
                  ))
                ) : (
                  <tr>
                    <td
                      colSpan="7"
                      className="px-4 py-8 text-center text-gray-500"
                    >
                      <p className="text-lg">📦 Belum ada produk</p>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}

function ProductRow({ product }) {
  const getStockColor = (stock) => {
    if (stock > 10) return 'bg-green-100 text-green-700';
    if (stock > 0) return 'bg-yellow-100 text-yellow-700';
    return 'bg-red-100 text-red-700';
  };

  const handleDelete = () => {
    if (confirm('Yakin ingin menghapus produk ini?')) {
      // Using Inertia's delete method
      window.location.href = `/admin/products/${product.id}/delete`;
    }
  };

  return (
    <tr className="border-t border-gray-100 transition hover:bg-gray-50">
      <td className="px-4 py-3">
        <img
          src={`/${product.image}`}
          className="h-16 w-16 rounded-lg object-cover shadow-sm"
          alt={product.name}
        />
      </td>
      <td className="px-4 py-3">
        <p className="font-semibold text-gray-800">{product.name}</p>
        <p className="mt-1 text-xs text-gray-500">
          {product.description?.substring(0, 30)}
          {product.description?.length > 30 ? '...' : ''}
        </p>
      </td>
      <td className="px-4 py-3 text-gray-700">
        {product.store?.store_name || '-'}
      </td>
      <td className="px-4 py-3">
        <span
          className={`rounded-full px-2 py-1 text-xs font-semibold ${getStockColor(product.stock)}`}
        >
          {product.stock}
        </span>
      </td>
      <td className="px-4 py-3 font-bold text-[#53685B]">
        {formatIDR(product.price)}
      </td>
      <td className="px-4 py-3">
        <span className="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-700">
          {product.category}
        </span>
      </td>
      <td className="px-4 py-3">
        <div className="flex justify-center gap-2">
          <Link
            href={`/admin/products/${product.id}/edit`}
            className="rounded-lg bg-[#53685B] px-3 py-1 text-xs text-white transition hover:bg-[#3c4a3e]"
          >
            ✏️
          </Link>
          <button
            onClick={handleDelete}
            className="rounded-lg bg-red-500 px-3 py-1 text-xs text-white transition hover:bg-red-600"
          >
            🗑️
          </button>
        </div>
      </td>
    </tr>
  );
}

AdminDashboard.layout = (page) => (
  <MainLayout title="Admin Dashboard">{page}</MainLayout>
);
