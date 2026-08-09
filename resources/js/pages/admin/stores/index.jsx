import { Head, Link, router, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import ActionMenu from '@/components/ui/action-menu';
import {
  DeleteIcon,
  EditIcon,
  EmptyStateIcon,
  StoreIcon,
  SuccessIcon,
} from '@/components/icons';
import { confirmDestructive } from '@/lib/dialog';

export default function AdminStores() {
  const { stores, success } = usePage().props;

  const handleDelete = async (publicId) => {
    const ok = await confirmDestructive({
      title: 'Hapus toko ini?',
      description:
        'Semua produk milik toko ini ikut terhapus dan tidak dapat dikembalikan.',
    });

    if (ok) {
      router.delete(`/admin/stores/${publicId}`, {
        preserveScroll: true,
      });
    }
  };

  return (
    <>
      <Head title="Kelola Toko" />
      <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        {success && (
          <div className="mb-6 rounded-lg border-l-4 border-green-500 bg-green-50 px-4 py-3 text-green-700 shadow-sm">
            <p className="flex items-center gap-2 font-semibold">
              <SuccessIcon className="h-5 w-5 shrink-0" />
              {success}
            </p>
          </div>
        )}

        <div className="rounded-2xl bg-white p-6 shadow-md shadow-[#53685B]/20">
          <h2 className="mb-6 text-2xl font-bold text-[#53685B]">
            <StoreIcon className="mr-2 inline h-6 w-6 align-text-bottom" />
            Kelola Data Toko
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
                    <tr
                      key={store.public_id}
                      className="border-t hover:bg-gray-50"
                    >
                      <td className="px-4 py-3 font-semibold">
                        {store.public_id}
                      </td>
                      <td className="px-4 py-3 font-semibold">
                        {store.store_name}
                      </td>
                      <td className="px-4 py-3">
                        {store.user?.username || '-'}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-600">
                        {store.location || '-'}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex justify-center">
                          <ActionMenu
                            items={[
                              {
                                label: 'Edit',
                                icon: <EditIcon />,
                                href: `/admin/stores/${store.public_id}/edit`,
                              },
                              {
                                label: 'Hapus',
                                icon: <DeleteIcon />,
                                variant: 'destructive',
                                onClick: () => handleDelete(store.public_id),
                              },
                            ]}
                          />
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
                      <div className="flex flex-col items-center gap-2">
                        <EmptyStateIcon className="h-10 w-10 text-gray-300" />
                        <p className="text-lg">Belum ada toko</p>
                      </div>
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
