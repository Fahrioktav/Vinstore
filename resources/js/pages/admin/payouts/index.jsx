import { Head, router, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR } from '@/lib/utils';
import ActionMenu from '@/components/ui/action-menu';

const statusColors = {
  pending: 'bg-yellow-100 text-yellow-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
};

export default function AdminPayouts() {
  const { payouts, success, error } = usePage().props;

  // Pencairan hanya boleh disetujui setelah admin benar-benar mentransfer dana
  // ke rekening yang diajukan seller. Bukti transfernya wajib diunggah sebagai
  // jejak audit atas uang yang keluar.
  const approve = (publicId) => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/png,image/jpeg';

    input.onchange = () => {
      const file = input.files?.[0];

      if (!file) return;

      const adminNote = prompt('Catatan pencairan (opsional):') || '';

      router.post(
        `/admin/payouts/${publicId}/approve`,
        { admin_note: adminNote, transfer_proof: file },
        { preserveScroll: true, forceFormData: true }
      );
    };

    input.click();
  };

  const reject = (publicId) => {
    const adminNote = prompt('Alasan penolakan pencairan (opsional):') || '';

    router.post(
      `/admin/payouts/${publicId}/reject`,
      { admin_note: adminNote },
      { preserveScroll: true }
    );
  };

  return (
    <>
      <Head title="Pencairan Dana Pesanan" />
      <div className="mx-auto max-w-7xl px-6 py-8">
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
          <h2 className="mb-2 text-2xl font-bold text-[#53685B]">
            Pencairan Dana Pesanan
          </h2>
          <p className="mb-6 text-sm text-gray-500">
            Setujui hanya setelah dana benar-benar ditransfer ke rekening
            seller. Bukti transfer wajib diunggah.
          </p>
          <div className="overflow-x-auto">
            <table className="w-full border border-gray-200 text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="px-4 py-3 text-left">ID</th>
                  <th className="px-4 py-3 text-left">Sumber</th>
                  <th className="px-4 py-3 text-left">Toko</th>
                  <th className="px-4 py-3 text-left">Nominal</th>
                  <th className="px-4 py-3 text-left">Rekening</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-left">Catatan</th>
                  <th className="px-4 py-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {payouts.length > 0 ? (
                  payouts.map((payout) => (
                    <tr
                      key={payout.public_id}
                      className="border-t hover:bg-gray-50"
                    >
                      <td className="px-4 py-3 font-semibold">
                        {payout.public_id}
                      </td>
                      <td className="px-4 py-3">
                        {payout.source_type === 'barter' ? (
                          <>
                            <span className="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-semibold text-purple-700">
                              Barter
                            </span>
                            <p className="mt-1 font-semibold">
                              {payout.barter_request?.public_id || '-'}
                            </p>
                            <p className="text-xs text-gray-500">
                              {payout.barter_request?.offered_product?.name ||
                                '?'}{' '}
                              ⇄{' '}
                              {payout.barter_request?.requested_product?.name ||
                                '?'}
                            </p>
                            <p className="text-xs text-gray-400">
                              Selisih dibayar{' '}
                              {payout.barter_request?.requester_store
                                ?.store_name || 'pengaju'}
                            </p>
                          </>
                        ) : (
                          <>
                            <span className="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-700">
                              Pesanan
                            </span>
                            <p className="mt-1 font-semibold">
                              {payout.order?.public_id || '-'}
                            </p>
                            <p className="text-xs text-gray-500">
                              {payout.order?.display_item_name || '-'}
                            </p>
                            <p className="text-xs text-gray-400">
                              Status: {payout.order?.status || '-'}
                            </p>
                          </>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <p className="font-semibold">
                          {payout.store?.store_name || '-'}
                        </p>
                        <p className="text-xs text-gray-500">
                          {payout.store?.user?.email || '-'}
                        </p>
                      </td>
                      <td className="px-4 py-3 font-bold text-[#53685B]">
                        {formatIDR(payout.amount)}
                      </td>
                      <td className="px-4 py-3">
                        <p>{payout.bank_name}</p>
                        <p className="text-xs text-gray-500">
                          {payout.account_number} a.n. {payout.account_holder}
                        </p>
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${statusColors[payout.status] || 'bg-gray-100 text-gray-700'}`}
                        >
                          {payout.status}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-gray-600">
                        {payout.admin_note || '-'}
                      </td>
                      <td className="px-4 py-3">
                        {payout.status === 'pending' ? (
                          <div className="flex justify-center">
                            <ActionMenu
                              items={[
                                {
                                  label: 'Setujui',
                                  icon: '✅',
                                  onClick: () => approve(payout.public_id),
                                },
                                {
                                  label: 'Tolak',
                                  icon: '🚫',
                                  variant: 'destructive',
                                  onClick: () => reject(payout.public_id),
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
                      colSpan="8"
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

AdminPayouts.layout = (page) => (
  <MainLayout title="Pencairan Dana Pesanan">{page}</MainLayout>
);
