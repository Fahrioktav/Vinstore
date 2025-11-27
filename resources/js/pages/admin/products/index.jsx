import { Head, Link, router, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR } from '@/lib/utils';

export default function AdminProducts() {
  const { products, success } = usePage().props;

  const handleDelete = (id) => {
    if (confirm('Yakin ingin menghapus produk ini?')) {
      router.delete(`/admin/products/${id}`, {
        preserveScroll: true,
      });
    }
  };

  const getStockColor = (stock) => {
    if (stock > 10) return 'bg-green-100 text-green-700';
    if (stock > 0) return 'bg-yellow-100 text-yellow-700';
    return 'bg-red-100 text-red-700';
  };

  return (
    <>
      <Head title="Kelola Produk" />
      <div className="mx-auto max-w-7xl px-6 py-8">
        {success && (
          <div className="mb-6 rounded-lg border-l-4 border-green-500 bg-green-50 px-4 py-3 text-green-700 shadow-sm">
            <p className="font-semibold">✓ {success}</p>
          </div>
        )}

        <div className="rounded-2xl bg-white p-6 shadow-md shadow-[#53685B]/20">
          <h2 className="mb-6 text-2xl font-bold text-[#53685B]">
            📦 Kelola Data Produk
          </h2>
          <div className="overflow-x-auto">
            <table className="w-full border border-gray-200 text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="px-4 py-3 text-left">Foto</th>
                  <th className="px-4 py-3 text-left">Nama Produk</th>
                  <th className="px-4 py-3 text-left">Toko</th>
                  <th className="px-4 py-3 text-left">Stok</th>
                  <th className="px-4 py-3 text-left">Harga</th>
                  <th className="px-4 py-3 text-left">Kategori</th>
                  <th className="px-4 py-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {products.length > 0 ? (
                  products.map((product) => (
                    <tr key={product.id} className="border-t hover:bg-gray-50">
                      <td className="px-4 py-3">
                        <img
                          src={`/${product.image}`}
                          className="h-16 w-16 rounded-lg object-cover shadow-sm"
                          alt={product.name}
                        />
                      </td>
                      <td className="px-4 py-3">
                        <p className="font-semibold text-gray-800">
                          {product.name}
                        </p>
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
                            className="rounded-lg bg-[#53685B] px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#3c4a3e] hover:shadow-md"
                          >
                            ✏️ Edit
                          </Link>
                          <button
                            onClick={() => handleDelete(product.id)}
                            className="rounded-lg bg-red-500 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-red-600 hover:shadow-md"
                          >
                            🗑️ Hapus
                          </button>
                        </div>
                      </td>
                    </tr>
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
    </>
  );
}

AdminProducts.layout = (page) => (
  <MainLayout title="Kelola Produk">{page}</MainLayout>
);
