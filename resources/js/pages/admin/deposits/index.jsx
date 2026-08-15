import { Head, router, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR } from '@/lib/utils';
import ActionMenu from '@/components/ui/action-menu';
import { BlockedIcon, SuccessIcon } from '@/components/icons';
import { promptDialog } from '@/lib/dialog';

const statusColors = {
  pending: 'bg-gray-100 text-gray-700',
  paid: 'bg-blue-100 text-blue-700',
  applied: 'bg-green-100 text-green-700',
  refund_requested: 'bg-yellow-100 text-yellow-700',
  refunded: 'bg-green-100 text-green-700',
  forfeited: 'bg-red-100 text-red-700',
  expired: 'bg-gray-100 text-gray-500',
};

const statusLabels = {
  pending: 'Menunggu pembayaran',
  paid: 'Aktif',
  applied: 'Jadi uang muka',
  refund_requested: 'Minta dikembalikan',
  refunded: 'Sudah dikembalikan',
  forfeited: 'Hangus',
  expired: 'Kedaluwarsa',
};

export default function AdminAuctionDeposits() {
  const { deposits, flash } = usePage().props;

  // Sama seperti pencairan saldo toko: pengembalian hanya boleh disetujui
  // setelah dananya benar-benar ditransfer, dan buktinya wajib diunggah.
  const approve = (publicId) => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/png,image/jpeg';

    input.onchange = async () => {
      const file = input.files?.[0];

      if (!file) return;

      const adminNote = await promptDialog({
        title: 'Setujui pengembalian deposit ini?',
        description:
          'Setujui hanya setelah dana benar-benar ditransfer ke rekening pembeli.',
        label: 'Catatan pengembalian',
        placeholder: 'Mis. nomor referensi transfer...',
        confirmLabel: 'Setujui pengembalian',
      });

      if (adminNote === null) return;

      router.post(
        `/admin/deposit-lelang/${publicId}/approve`,
        { admin_note: adminNote, transfer_proof: file },
        { preserveScroll: true, forceFormData: true }
      );
    };

    input.click();
  };

  const reject = async (publicId) => {
    const adminNote = await promptDialog({
      title: 'Tolak pengajuan ini?',
      description:
        'Depositnya kembali aktif sehingga pembeli dapat memperbaiki nomor rekeningnya lalu mengajukan ulang.',
      label: 'Alasan penolakan',
      placeholder: 'Contoh: nomor rekening tidak sesuai nama pemilik akun...',
      confirmLabel: 'Tolak pengajuan',
      variant: 'destructive',
    });

    if (!adminNote) return;

    router.post(
      `/admin/deposit-lelang/${publicId}/reject`,
      { admin_note: adminNote },
      { preserveScroll: true }
    );
  };

  return (
    <>
      <Head title="Kelola Deposit Lelang" />
      <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        {flash?.success && (
          <div className="mb-6 rounded-lg border-l-4 border-green-500 bg-green-50 px-4 py-3 text-green-700">
            {flash.success}
          </div>
        )}
        {flash?.error && (
          <div className="mb-6 rounded-lg border-l-4 border-red-500 bg-red-50 px-4 py-3 text-red-700">
            {flash.error}
          </div>
        )}

        <div className="rounded-2xl bg-white p-6 shadow-md">
          <h2 className="mb-2 text-2xl font-bold text-[#53685B]">
            Kelola Deposit Lelang
          </h2>
          <p className="mb-6 text-sm text-gray-500">
            Peserta yang kalah dapat meminta uang jaminannya kembali. Transfer
            dananya ke rekening yang tercantum, lalu setujui sambil mengunggah
            bukti transfernya.
          </p>

          <div className="overflow-x-auto">
            <table className="w-full border border-gray-200 text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="px-4 py-3 text-left">ID</th>
                  <th className="px-4 py-3 text-left">Lelang</th>
                  <th className="px-4 py-3 text-left">Pembeli</th>
                  <th className="px-4 py-3 text-left">Nominal</th>
                  <th className="px-4 py-3 text-left">Rekening Tujuan</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-left">Catatan</th>
                  <th className="px-4 py-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {deposits.length > 0 ? (
                  deposits.map((deposit) => (
                    <tr
                      key={deposit.public_id}
                      className="border-t hover:bg-gray-50"
                    >
                      <td className="px-4 py-3 font-semibold">
                        {deposit.public_id}
                      </td>
                      <td className="px-4 py-3">
                        <p className="font-semibold">
                          {deposit.auction?.name || '-'}
                        </p>
                        <p className="text-xs text-gray-500">
                          {deposit.auction?.store?.store_name || '-'}
                        </p>
                      </td>
                      <td className="px-4 py-3">
                        <p>{deposit.user?.username || '-'}</p>
                        <p className="text-xs text-gray-500">
                          {deposit.user?.email || '-'}
                        </p>
                      </td>
                      <td className="px-4 py-3 font-bold text-[#53685B]">
                        {formatIDR(deposit.amount)}
                      </td>
                      <td className="px-4 py-3">
                        {deposit.bank_name ? (
                          <>
                            <p>{deposit.bank_name}</p>
                            <p className="text-xs text-gray-500">
                              {deposit.account_number} a.n.{' '}
                              {deposit.account_holder}
                            </p>
                          </>
                        ) : (
                          <span className="text-xs text-gray-400">
                            Belum diajukan
                          </span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`rounded-full px-3 py-1 text-xs font-semibold ${statusColors[deposit.status] || 'bg-gray-100 text-gray-700'}`}
                        >
                          {statusLabels[deposit.status] || deposit.status}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-gray-600">
                        {deposit.admin_note || '-'}
                      </td>
                      <td className="px-4 py-3">
                        {deposit.status === 'refund_requested' ? (
                          <div className="flex justify-center">
                            <ActionMenu
                              items={[
                                {
                                  label: 'Setujui',
                                  icon: <SuccessIcon />,
                                  onClick: () => approve(deposit.public_id),
                                },
                                {
                                  label: 'Tolak',
                                  icon: <BlockedIcon />,
                                  variant: 'destructive',
                                  onClick: () => reject(deposit.public_id),
                                },
                              ]}
                            />
                          </div>
                        ) : (
                          <p className="text-center text-xs text-gray-500">
                            Tidak ada aksi
                          </p>
                        )}
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td
                      colSpan="8"
                      className="px-4 py-8 text-center text-gray-500"
                    >
                      Belum ada deposit lelang.
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

AdminAuctionDeposits.layout = (page) => (
  <MainLayout title="Kelola Deposit Lelang">{page}</MainLayout>
);
