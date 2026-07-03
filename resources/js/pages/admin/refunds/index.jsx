import { Head, router, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR } from '@/lib/utils';
import ActionMenu from '@/components/ui/action-menu';

const statusColors = {
  pending: 'bg-yellow-100 text-yellow-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
};

export default function AdminRefunds() {
  const { refunds, success, error } = usePage().props;

  const approve = (publicId) => {
    const adminNote = prompt('Catatan admin (opsional):') || '';

    router.post(
      `/admin/refunds/${publicId}/approve`,
      { admin_note: adminNote },
      { preserveScroll: true }
    );
  };

  const reject = (publicId) => {
    const adminNote = prompt('Alasan penolakan refund (opsional):') || '';

    router.post(
      `/admin/refunds/${publicId}/reject`,
      { admin_note: adminNote },
      { preserveScroll: true }
    );
  };

  return (
    <>
      <Head title="Kelola Refund" />
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
          <h2 className="mb-6 text-2xl font-bold text-[#53685B]">
            Kelola Pengajuan Refund
          </h2>

          <div className="overflow-x-auto">
            <table className="w-full border border-gray-200 text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="px-4 py-3 text-left">ID Refund</th>
                  <th className="px-4 py-3 text-left">Customer</th>
                  <th className="px-4 py-3 text-left">Item</th>
                  <th className="px-4 py-3 text-left">Toko</th>
                  <th className="px-4 py-3 text-left">Total</th>
                  <th className="px-4 py-3 text-left">Alasan</th>
                  <th className="px-4 py-3 text-left">Bukti</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {refunds.length > 0 ? (
                  refunds.map((refund) => {
                    const order = refund.order;
                    const item = order?.product || order?.auction;

                    return (
                      <tr key={refund.public_id} className="border-t hover:bg-gray-50">
                        <td className="px-4 py-3 font-semibold">{refund.public_id}</td>
                        <td className="px-4 py-3">
                          <p className="font-semibold">
                            {refund.user?.first_name} {refund.user?.last_name}
                          </p>
                          <p className="text-xs text-gray-500">{refund.user?.email}</p>
                        </td>
                        <td className="px-4 py-3">
                          <p className="font-semibold">{item?.name || '-'}</p>
                          <p className="text-xs text-gray-500">
                            Order: {order?.public_id || '-'}
                          </p>
                        </td>
                        <td className="px-4 py-3">{order?.store?.store_name || '-'}</td>
                        <td className="px-4 py-3 font-bold text-[#53685B]">
                          {formatIDR(order?.price || 0)}
                        </td>
                        <td className="px-4 py-3">
                          <p className="max-w-64 whitespace-pre-line text-gray-700">
                            {refund.reason}
                          </p>
                          {refund.admin_note && (
                            <p className="mt-2 max-w-64 text-xs text-gray-500">
                              Catatan: {refund.admin_note}
                            </p>
                          )}
                        </td>
                        <td className="px-4 py-3">
                          {refund.proof_image ? (
                            <a
                              href={`/storage/${refund.proof_image}`}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="inline-block"
                            >
                              <img
                                src={`/storage/${refund.proof_image}`}
                                alt="Bukti refund"
                                className="h-16 w-16 rounded-lg object-cover shadow-sm"
                              />
                            </a>
                          ) : (
                            <span className="text-xs text-gray-400">-</span>
                          )}
                        </td>
                        <td className="px-4 py-3">
                          <span
                            className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${statusColors[refund.status] || 'bg-gray-100 text-gray-700'}`}
                          >
                            {refund.status}
                          </span>
                        </td>
                        <td className="px-4 py-3">
                          {refund.status === 'pending' ? (
                            <div className="flex justify-center">
                              <ActionMenu
                                items={[
                                  {
                                    label: 'Setujui',
                                    icon: '✅',
                                    onClick: () => approve(refund.public_id),
                                  },
                                  {
                                    label: 'Tolak',
                                    icon: '🚫',
                                    variant: 'destructive',
                                    onClick: () => reject(refund.public_id),
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
                    );
                  })
                ) : (
                  <tr>
                    <td colSpan="9" className="px-4 py-8 text-center text-gray-500">
                      Belum ada pengajuan refund.
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

AdminRefunds.layout = (page) => (
  <MainLayout title="Kelola Refund">{page}</MainLayout>
);
