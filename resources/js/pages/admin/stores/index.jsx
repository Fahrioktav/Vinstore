import { Head, Link, router, usePage } from '@inertiajs/react';
import MainLayout from '../../../layouts/main-layout';

export default function AdminStores() {
  const { stores, success } = usePage().props;

  const handleDelete = (id) => {
    if (confirm('Yakin ingin menghapus toko ini? Semua produknya juga akan terhapus.')) {
      router.delete(`/admin/stores/${id}`, {
        preserveScroll: true,
      });
    }
  };

  return (
    <>
      <Head title="Kelola Toko" />
      <div className="mx-auto max-w-7xl px-6 py-8">
        {success && (
          <div className="mb-6 rounded-lg border-l-4 border-green-500 bg-green-50 px-4 py-3 text-green-700 shadow-sm">
            <p className="font-semibold">✓ {success}</p>
          </div>
        )}

        <div className="rounded-2xl bg-white p-6 shadow-md shadow-[#53685B]/20">
          <h2 className="mb-6 text-2xl font-bold text-[#53685B]">
            🏪 Kelola Data Toko
          </h2>
          <div className="overflow-x-auto">
            <table className="w-full border border-gray-200 text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="px-4 py-3 text-left">ID</th>
                  <th className="px-4 py-3 text-left">Nama Toko</th>
                  <th className="px-4 py-3 text-left">Owner</th>
                  <th className="px-4 py-3 text-left">Alamat</th>
                  <th className="px-4 py-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {stores.length > 0 ? (
                  stores.map((store) => (
                    <tr key={store.id} className="border-t hover:bg-gray-50">
                      <td className="px-4 py-3 font-semibold">{store.id}</td>
                      <td className="px-4 py-3 font-semibold">{store.store_name}</td>
                      <td className="px-4 py-3">{store.user?.username || '-'}</td>
                      <td className="px-4 py-3 text-sm text-gray-600">
                        {store.location || '-'}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex justify-center gap-2">
                          <Link
                            href={`/admin/stores/${store.id}/edit`}
                            className="rounded-lg bg-[#53685B] px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#3c4a3e] hover:shadow-md"
                          >
                            ✏️ Edit
                          </Link>
                          <button
                            onClick={() => handleDelete(store.id)}
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
                      colSpan="5"
                      className="px-4 py-8 text-center text-gray-500"
                    >
                      <p className="text-lg">🏪 Belum ada toko</p>
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

AdminStores.layout = (page) => (
  <MainLayout title="Kelola Toko">{page}</MainLayout>
);
