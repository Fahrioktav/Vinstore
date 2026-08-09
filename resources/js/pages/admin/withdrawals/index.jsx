import { Head, router, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR } from '@/lib/utils';
import ActionMenu from '@/components/ui/action-menu';
import { BlockedIcon, SuccessIcon } from '@/components/icons';
import { promptDialog } from '@/lib/dialog';

const statusColors = {
  pending: 'bg-yellow-100 text-yellow-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
};

export default function AdminWithdrawals() {
  const { withdrawals, success, error } = usePage().props;

  // Pencairan hanya boleh disetujui setelah admin benar-benar mentransfer dana
  // ke rekening yang diajukan seller. Bukti transfernya wajib diunggah sebagai
  // jejak audit atas uang yang keluar.
  const approve = (publicId) => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/png,image/jpeg';

    input.onchange = async () => {
      const file = input.files?.[0];

      if (!file) return;

      const adminNote = await promptDialog({
        title: 'Setujui pencairan ini?',
        description:
          'Setujui hanya setelah dana benar-benar ditransfer ke rekening seller.',
        label: 'Catatan pencairan',
        placeholder: 'Mis. nomor referensi transfer...',
        confirmLabel: 'Setujui pencairan',
      });

      if (adminNote === null) return;

      router.post(
        `/admin/withdrawals/${publicId}/approve`,
        { admin_note: adminNote, transfer_proof: file },
        { preserveScroll: true, forceFormData: true }
      );
    };

    input.click();
  };

  const reject = async (publicId) => {
    const adminNote = await promptDialog({
      title: 'Tolak pencairan ini?',
      description: 'Alasannya akan terlihat oleh seller yang mengajukan.',
      label: 'Alasan penolakan',
      placeholder: 'Contoh: rekening tidak sesuai nama pemilik toko...',
      confirmLabel: 'Tolak pencairan',
      variant: 'destructive',
    });

    if (adminNote === null) return;

    router.post(
      `/admin/withdrawals/${publicId}/reject`,
      { admin_note: adminNote },
      { preserveScroll: true }
    );
  };

  return (
    <>
      <Head title="Kelola Pencairan" />
      <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        {success && (
          <div className="mb-6 rounded-lg border-l-4 border-green-500 bg-green-50 px-4 py-3 text-green-700">
            {success}
          </div>
        )}
        {error && (
          <div className="mb-6 rounded-lg border-l-4 border-red-500 bg-red-50 px-4 py-3 text-red-700">
            {error}
          </div>
        )}

        <div className="rounded-2xl bg-white p-6 shadow-md">
          <h2 className="mb-6 text-2xl font-bold text-[#53685B]">
            Kelola Pencairan Seller
          </h2>
          <div className="overflow-x-auto">
            <table className="w-full border border-gray-200 text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="px-4 py-3 text-left">ID</th>
                  <th className="px-4 py-3 text-left">Toko</th>
                  <th className="px-4 py-3 text-left">Nominal</th>
                  <th className="px-4 py-3 text-left">Rekening</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-left">Catatan</th>
                  <th className="px-4 py-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {withdrawals.length > 0 ? (
                  withdrawals.map((withdrawal) => (
                    <tr
                      key={withdrawal.public_id}
                      className="border-t hover:bg-gray-50"
                    >
                      <td className="px-4 py-3 font-semibold">
                        {withdrawal.public_id}
                      </td>
                      <td className="px-4 py-3">
                        <p className="font-semibold">
                          {withdrawal.store?.store_name || '-'}
                        </p>
                        <p className="text-xs text-gray-500">
                          {withdrawal.store?.user?.email || '-'}
                        </p>
                      </td>
                      <td className="px-4 py-3 font-bold text-[#53685B]">
                        {formatIDR(withdrawal.amount)}
                      </td>
                      <td className="px-4 py-3">
                        <p>{withdrawal.bank_name}</p>
                        <p className="text-xs text-gray-500">
                          {withdrawal.account_number} a.n.{' '}
                          {withdrawal.account_holder}
                        </p>
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${statusColors[withdrawal.status] || 'bg-gray-100 text-gray-700'}`}
                        >
                          {withdrawal.status}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-gray-600">
                        {withdrawal.admin_note || '-'}
                      </td>
                      <td className="px-4 py-3">
                        {withdrawal.status === 'pending' ? (
                          <div className="flex justify-center">
                            <ActionMenu
                              items={[
                                {
                                  label: 'Setujui',
                                  icon: <SuccessIcon />,
                                  onClick: () => approve(withdrawal.public_id),
                                },
                                {
                                  label: 'Tolak',
                                  icon: <BlockedIcon />,
                                  variant: 'destructive',
                                  onClick: () => reject(withdrawal.public_id),
                                },
                              ]}
                            />
                          </div>
                        ) : (
                          <p className="text-center text-xs text-gray-500">
                            Diproses
                          </p>
                        )}
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td
                      colSpan="7"
                      className="px-4 py-8 text-center text-gray-500"
                    >
                      Belum ada pengajuan pencairan.
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

AdminWithdrawals.layout = (page) => (
  <MainLayout title="Kelola Pencairan">{page}</MainLayout>
);
