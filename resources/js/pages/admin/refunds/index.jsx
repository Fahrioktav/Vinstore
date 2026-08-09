import { Head, router, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR } from '@/lib/utils';
import ActionMenu from '@/components/ui/action-menu';
import { BarterIcon, BlockedIcon, SuccessIcon } from '@/components/icons';
import { promptDialog } from '@/lib/dialog';

const statusColors = {
  pending: 'bg-yellow-100 text-yellow-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
};

export default function AdminRefunds() {
  const { refunds, success, error } = usePage().props;

  const approve = async (publicId) => {
    const adminNote = await promptDialog({
      title: 'Setujui pengajuan refund?',
      description:
        'Dana dikembalikan ke pembeli dan biaya layanan atas pesanan ini ikut dibatalkan.',
      label: 'Catatan admin',
      placeholder: 'Catatan untuk arsip, mis. nomor referensi transfer...',
      confirmLabel: 'Setujui refund',
    });

    if (adminNote === null) return;

    router.post(
      `/admin/refunds/${publicId}/approve`,
      { admin_note: adminNote },
      { preserveScroll: true }
    );
  };

  const reject = async (publicId) => {
    const adminNote = await promptDialog({
      title: 'Tolak pengajuan refund?',
      description: 'Alasannya akan terlihat oleh pembeli yang mengajukan.',
      label: 'Alasan penolakan',
      placeholder: 'Contoh: bukti tidak cukup, barang sudah diterima utuh...',
      confirmLabel: 'Tolak refund',
      variant: 'destructive',
    });

    if (adminNote === null) return;

    router.post(
      `/admin/refunds/${publicId}/reject`,
      { admin_note: adminNote },
      { preserveScroll: true }
    );
  };

  return (
    <>
      <Head title="Kelola Refund" />
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
                    const barter = refund.barter_request;
                    const isBarter = refund.source_type === 'barter';
                    const item = order?.product || order?.auction;

                    // Sanggahan barter menyangkut selisih uangnya, bukan harga
                    // pesanan — nominal dan pihak terkaitnya berbeda sumber.
                    // Nama diambil dari snapshot pada baris barter/pesanan,
                    // bukan dari relasinya: produk atau tokonya bisa saja sudah
                    // dihapus dan relasinya null (temuan V3-01).
                    // Barter menyangkut dua produk sekaligus, jadi labelnya
                    // berupa elemen agar panah penukarnya bisa memakai ikon.
                    const itemLabel = isBarter ? (
                      <span className="inline-flex items-center gap-1">
                        {barter?.display_offered_product_name || '?'}
                        <BarterIcon className="h-3.5 w-3.5 shrink-0" />
                        {barter?.display_requested_product_name || '?'}
                      </span>
                    ) : (
                      order?.display_item_name || item?.name || '-'
                    );
                    const referenceLabel = isBarter
                      ? `Barter: ${barter?.public_id || '-'}`
                      : `Order: ${order?.public_id || '-'}`;
                    const storeLabel = isBarter
                      ? barter?.display_responder_store_name || '-'
                      : order?.display_store_name || '-';
                    const amount = isBarter
                      ? barter?.additional_cash || 0
                      : order?.price || 0;

                    return (
                      <tr
                        key={refund.public_id}
                        className="border-t hover:bg-gray-50"
                      >
                        <td className="px-4 py-3 font-semibold">
                          {refund.public_id}
                        </td>
                        <td className="px-4 py-3">
                          <p className="font-semibold">
                            {refund.user?.first_name} {refund.user?.last_name}
                          </p>
                          <p className="text-xs text-gray-500">
                            {refund.user?.email}
                          </p>
                        </td>
                        <td className="px-4 py-3">
                          <span
                            className={`rounded-full px-2 py-0.5 text-xs font-semibold ${isBarter ? 'bg-purple-100 text-purple-700' : 'bg-sky-100 text-sky-700'}`}
                          >
                            {isBarter ? 'Barter' : 'Pesanan'}
                          </span>
                          <p className="mt-1 font-semibold">{itemLabel}</p>
                          <p className="text-xs text-gray-500">
                            {referenceLabel}
                          </p>
                        </td>
                        <td className="px-4 py-3">{storeLabel}</td>
                        <td className="px-4 py-3 font-bold text-[#53685B]">
                          {formatIDR(amount)}
                        </td>
                        <td className="px-4 py-3">
                          {/* Bukti objektif untuk memutuskan sengketa barter:
                              siapa yang sudah mengisi resi dan siapa belum. */}
                          {isBarter && (
                            <div className="mb-2 rounded-md bg-gray-50 p-2 text-xs">
                              <p
                                className={
                                  barter?.requester_shipped_at
                                    ? 'text-green-700'
                                    : 'font-semibold text-red-700'
                                }
                              >
                                Pengaju:{' '}
                                {barter?.requester_shipped_at
                                  ? `kirim (${barter.requester_tracking_number || '-'})`
                                  : 'belum kirim'}
                              </p>
                              <p
                                className={
                                  barter?.responder_shipped_at
                                    ? 'text-green-700'
                                    : 'font-semibold text-red-700'
                                }
                              >
                                Penerima:{' '}
                                {barter?.responder_shipped_at
                                  ? `kirim (${barter.responder_tracking_number || '-'})`
                                  : 'belum kirim'}
                              </p>
                            </div>
                          )}
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
                                    icon: <SuccessIcon />,
                                    onClick: () => approve(refund.public_id),
                                  },
                                  {
                                    label: 'Tolak',
                                    icon: <BlockedIcon />,
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
                    <td
                      colSpan="9"
                      className="px-4 py-8 text-center text-gray-500"
                    >
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
