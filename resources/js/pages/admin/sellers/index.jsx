import { Head, Link, router, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import ActionMenu from '@/components/ui/action-menu';
import {
  BlockedIcon,
  EditIcon,
  EmptyStateIcon,
  StoreIcon,
  SuccessIcon,
} from '@/components/icons';
import { confirmDialog, promptDialog } from '@/lib/dialog';

export default function AdminSellers() {
  const { sellers, success } = usePage().props;

  // Akun tidak pernah dihapus, hanya dinonaktifkan. Menghapus seller ikut
  // menghapus produknya, padahal pesanan lama masih merujuk padanya
  // (temuan V4-12).
  const toggleActive = async (seller) => {
    if (seller.deactivated_at) {
      const ok = await confirmDialog({
        title: `Aktifkan kembali ${seller.username}?`,
        description: 'Produk tokonya akan tampil lagi di etalase.',
        confirmLabel: 'Ya, aktifkan',
      });

      if (ok) {
        router.delete(`/admin/sellers/${seller.public_id}`, {
          preserveScroll: true,
        });
      }

      return;
    }

    const reason = await promptDialog({
      title: `Nonaktifkan ${seller.username}?`,
      description:
        'Akun tidak bisa masuk lagi dan produk tokonya berhenti tampil di etalase. Produk maupun riwayat pesanannya tidak dihapus dan dapat dipulihkan kapan saja.',
      label: 'Alasan penonaktifan',
      placeholder:
        'Contoh: barang tidak sesuai deskripsi, tidak pernah mengirim...',
      confirmLabel: 'Nonaktifkan akun',
      variant: 'destructive',
    });

    if (reason === null) return;

    router.delete(`/admin/sellers/${seller.public_id}`, {
      data: { reason },
      preserveScroll: true,
    });
  };

  return (
    <>
      <Head title="Kelola Seller" />
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
            Kelola Data Seller
          </h2>
          <div className="overflow-x-auto">
            <table className="w-full border border-gray-200 text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="px-4 py-3 text-left">ID</th>
                  <th className="px-4 py-3 text-left">Username</th>
                  <th className="px-4 py-3 text-left">Email</th>
                  <th className="px-4 py-3 text-left">Nama Toko</th>
                  <th className="px-4 py-3 text-left">Lokasi Toko</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {sellers.length > 0 ? (
                  sellers.map((seller) => (
                    <tr
                      key={seller.public_id}
                      className="border-t hover:bg-gray-50"
                    >
                      <td className="px-4 py-3 font-semibold">
                        {seller.public_id}
                      </td>
                      <td className="px-4 py-3">{seller.username}</td>
                      <td className="px-4 py-3 text-sm text-gray-600">
                        {seller.email}
                      </td>
                      <td className="px-4 py-3">
                        {seller.store?.store_name ? (
                          <span className="font-medium text-gray-800">
                            {seller.store.store_name}
                          </span>
                        ) : (
                          <span className="text-xs text-gray-400 italic">
                            Belum punya toko
                          </span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-600">
                        {seller.store?.location || '-'}
                      </td>
                      <td className="px-4 py-3">
                        {seller.deactivated_at ? (
                          <span
                            className="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700"
                            title={seller.deactivation_reason || undefined}
                          >
                            Nonaktif
                          </span>
                        ) : (
                          <span className="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">
                            Aktif
                          </span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex justify-center">
                          <ActionMenu
                            items={[
                              {
                                label: 'Edit',
                                icon: <EditIcon />,
                                href: `/admin/sellers/${seller.public_id}/edit`,
                              },
                              seller.deactivated_at
                                ? {
                                    label: 'Aktifkan',
                                    icon: <SuccessIcon />,
                                    onClick: () => toggleActive(seller),
                                  }
                                : {
                                    label: 'Nonaktifkan',
                                    icon: <BlockedIcon />,
                                    variant: 'destructive',
                                    onClick: () => toggleActive(seller),
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
                      colSpan="7"
                      className="px-4 py-8 text-center text-gray-500"
                    >
                      <div className="flex flex-col items-center gap-2">
                        <EmptyStateIcon className="h-10 w-10 text-gray-300" />
                        <p className="text-lg">Belum ada seller</p>
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

AdminSellers.layout = (page) => (
  <MainLayout title="Kelola Seller">{page}</MainLayout>
);
