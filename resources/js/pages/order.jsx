import { Link, router, usePage } from '@inertiajs/react';
import { cn, formatIDR } from '@/lib/utils';
import MainLayout from '@/layouts/main-layout';
import { ShippingIcon } from '@/components/icons';
import { useState } from 'react';
import { toast } from 'sonner';
import { confirmDestructive, confirmDialog } from '@/lib/dialog';

const statusStyles = {
  Waiting: 'bg-yellow-500/30 text-yellow-200',
  'On The Way': 'bg-blue-500/30 text-blue-200',
  Delivered: 'bg-green-500/30 text-green-200',
  Cancelled: 'bg-red-500/30 text-red-200',
  Unknown: 'bg-gray-500/30 text-gray-200',
};

const paymentStyles = {
  paid: 'bg-green-500/30 text-green-200',
  pending: 'bg-yellow-500/30 text-yellow-200',
  unpaid: 'bg-gray-500/30 text-gray-200',
  cancelled: 'bg-red-500/30 text-red-200',
  denied: 'bg-red-500/30 text-red-200',
  expired: 'bg-red-500/30 text-red-200',
  refunded: 'bg-blue-500/30 text-blue-200',
  Unknown: 'bg-gray-500/30 text-gray-200',
};

const refundReasons = [
  'Barang rusak saat diterima',
  'Barang tidak sesuai deskripsi',
  'Barang tidak lengkap',
  'Barang tidak sampai',
  'Salah barang dikirim',
  'Kualitas barang tidak sesuai',
  'Lainnya',
];

export default function OrderPage() {
  const { orders, flash } = usePage().props;
  const [refundOrderId, setRefundOrderId] = useState(null);
  const [refundReason, setRefundReason] = useState(refundReasons[0]);
  const [customReason, setCustomReason] = useState('');
  const [refundProofImage, setRefundProofImage] = useState(null);

  // Dialognya asinkron, jadi polanya dibalik dibanding confirm() bawaan:
  // pengirimannya tidak lagi "dibatalkan setelah terlanjur jalan", melainkan
  // baru dijalankan setelah pengguna menjawab.
  const cancelOrder = async (publicId) => {
    const ok = await confirmDestructive({
      title: 'Batalkan pesanan ini?',
      description:
        'Pesanan yang dibatalkan tidak bisa dilanjutkan lagi. Stok yang ditahan akan dilepas.',
      confirmLabel: 'Ya, batalkan',
    });

    if (ok) router.delete(`/order/${publicId}`, { preserveScroll: true });
  };

  const confirmReceived = async (publicId) => {
    const ok = await confirmDialog({
      title: 'Konfirmasi barang sudah diterima?',
      description:
        'Dana akan diteruskan ke penjual dan tindakan ini tidak dapat dibatalkan.',
      confirmLabel: 'Ya, sudah diterima',
    });

    if (ok)
      router.post(`/order/${publicId}/confirm`, {}, { preserveScroll: true });
  };

  const openRefundForm = (orderPublicId) => {
    setRefundOrderId(orderPublicId);
    setRefundReason(refundReasons[0]);
    setCustomReason('');
    setRefundProofImage(null);
  };

  const closeRefundForm = () => {
    setRefundOrderId(null);
    setCustomReason('');
    setRefundProofImage(null);
  };

  const submitRefund = (e) => {
    e.preventDefault();

    const reason =
      refundReason === 'Lainnya'
        ? customReason.trim()
        : customReason.trim()
          ? `${refundReason}. Catatan: ${customReason.trim()}`
          : refundReason;

    if (!reason || reason.length < 10) {
      toast.error('Alasan refund minimal 10 karakter.');
      return;
    }

    router.post(
      `/order/${refundOrderId}/refund`,
      {
        reason,
        proof_image: refundProofImage,
      },
      {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeRefundForm,
      }
    );
  };

  return (
    <section className="w-full px-6 pt-32 pb-20 text-[#E9E19E] md:px-12">
      <div className="mx-auto max-w-6xl">
        <h2 className="mb-10 text-center text-2xl font-bold sm:text-4xl">
          Pesananmu
        </h2>
        {flash?.success && (
          <div className="mb-6 rounded-lg border border-green-300 bg-green-500/20 px-4 py-3 text-green-100">
            {flash.success}
          </div>
        )}
        {flash?.error && (
          <div className="mb-6 rounded-lg border border-red-300 bg-red-500/20 px-4 py-3 text-red-100">
            {flash.error}
          </div>
        )}
        {orders.length === 0 ? (
          <div className="py-20 text-center text-lg text-[#E9E19E]/90 italic">
            Kamu belum memesan apapun
          </div>
        ) : (
          <div className="overflow-x-auto rounded-2xl border border-white/20 bg-white/10 shadow-xl backdrop-blur-md">
            <table className="w-full min-w-[48rem] table-auto text-left text-[#E9E19E]">
              <thead>
                <tr className="bg-[#E9E19E]/10 text-xs tracking-wider uppercase">
                  <th className="w-[18%] px-3 py-4">Item</th>
                  <th className="w-[6%] px-3 py-4">Qty</th>
                  <th className="w-[10%] px-3 py-4">Harga</th>
                  <th className="w-[11%] px-3 py-4">Pembayaran</th>
                  <th className="w-[10%] px-3 py-4">Status</th>
                  <th className="w-[12%] px-3 py-4">Nomor Resi</th>
                  <th className="w-[10%] px-3 py-4">Tanggal</th>
                  <th className="w-[8%] px-3 py-4 text-center">Invoice</th>
                  <th className="w-[15%] px-3 py-4 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {orders.map((order) => (
                  <tr
                    className="border-b border-white/10 transition hover:bg-white/10"
                    key={order.public_id}
                  >
                    <td className="px-3 py-4 font-semibold">
                      <div className="truncate" title={order.display_item_name}>
                        {order.display_item_name}
                      </div>
                    </td>
                    <td className="px-3 py-4 text-center">{order.quantity}</td>
                    <td className="px-3 py-4 text-sm">
                      {formatIDR(order.price)}
                    </td>
                    <td className="px-3 py-4">
                      <span
                        className={cn(
                          'block rounded-full px-2 py-1 text-center text-xs capitalize',
                          paymentStyles[order.payment_status] ??
                            paymentStyles.Unknown
                        )}
                      >
                        {order.payment_status || 'unpaid'}
                      </span>
                      {order.refund_request && (
                        <p className="mt-1 text-center text-xs text-[#E9E19E]/80 capitalize">
                          Refund: {order.refund_request.status}
                        </p>
                      )}
                    </td>
                    <td className="px-3 py-4 whitespace-nowrap">
                      <span
                        className={cn(
                          'inline-flex items-center rounded-full px-2 py-1 text-xs whitespace-nowrap',
                          statusStyles[order.status] ?? statusStyles.Unknown
                        )}
                      >
                        {order.status}
                      </span>
                    </td>
                    <td className="px-3 py-4">
                      {order.tracking_number ? (
                        <div className="flex flex-col gap-0.5">
                          <span
                            className="truncate font-mono text-xs font-semibold text-[#E9E19E]"
                            title={order.tracking_number}
                          >
                            {order.tracking_number}
                          </span>
                          <span className="inline-flex items-center gap-1 text-xs text-[#E9E19E]/70">
                            <ShippingIcon className="h-3.5 w-3.5" />
                            Dikirim
                          </span>
                        </div>
                      ) : (
                        <span className="text-xs text-[#E9E19E]/50 italic">
                          Belum tersedia
                        </span>
                      )}
                    </td>
                    <td className="px-3 py-4 text-xs">
                      {orderDate(order.created_at)}
                    </td>
                    <td className="px-3 py-4 text-center">
                      {order.payment_status === 'paid' ? (
                        <Link
                          href={`/invoice/${order.public_id}`}
                          className="inline-flex items-center gap-1 rounded-lg bg-[#B77C4C] px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[#8d5e39]"
                        >
                          Invoice
                        </Link>
                      ) : (
                        <span className="text-gray-400">-</span>
                      )}
                    </td>
                    <td className="px-3 py-4 text-center">
                      <div className="flex flex-col items-center gap-2">
                        {order.snap_redirect_url &&
                          ['pending', 'unpaid'].includes(
                            order.payment_status
                          ) && (
                            <a
                              href={order.snap_redirect_url}
                              className="text-xs font-semibold text-yellow-300 transition hover:text-yellow-200"
                            >
                              Bayar
                            </a>
                          )}
                        {/* Pemenang lelang belum pernah mengisi tujuan
                            pengiriman, jadi ia diarahkan ke halaman pembayaran
                            lelang lebih dulu — di sanalah ongkirnya dihitung. */}
                        {!order.snap_redirect_url &&
                          order.auction &&
                          ['pending', 'unpaid'].includes(
                            order.payment_status
                          ) && (
                            <Link
                              href={`/auctions/${order.auction.public_id}/checkout`}
                              className="text-xs font-semibold text-yellow-300 transition hover:text-yellow-200"
                            >
                              Bayar
                            </Link>
                          )}
                        {/* Konfirmasi penerimaan oleh pembeli. Selama tombol ini
                            belum ditekan, dana masih ditahan dan tidak masuk ke
                            saldo penjual. */}
                        {order.payment_status === 'paid' &&
                          ['On The Way', 'Delivered'].includes(
                            order.status
                          ) && (
                            <button
                              type="button"
                              className="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-green-700"
                              onClick={() => confirmReceived(order.public_id)}
                            >
                              Barang Diterima
                            </button>
                          )}
                        {/* Pesanan lelang tidak punya tombol batal: hasil lelang
                            mengikat, dan membatalkannya sendiri akan membuat
                            deposit pemenang tersangkut tanpa pemilik. Yang tidak
                            jadi membayar cukup membiarkan tenggatnya lewat. */}
                        {['Waiting', 'On The Way'].includes(order.status) &&
                        !order.auction ? (
                          <button
                            type="button"
                            className="text-xs font-semibold text-red-400 transition hover:text-red-300"
                            onClick={() => cancelOrder(order.public_id)}
                          >
                            Batalkan
                          </button>
                        ) : (
                          <span className="text-gray-400">-</span>
                        )}
                        {order.payment_status === 'paid' &&
                          ['Delivered', 'Completed'].includes(order.status) &&
                          !order.refund_request && (
                            <button
                              type="button"
                              onClick={() => openRefundForm(order.public_id)}
                              className="text-xs font-semibold text-blue-300 transition hover:text-blue-200"
                            >
                              Ajukan Refund
                            </button>
                          )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {refundOrderId && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
          <div className="w-full max-w-lg rounded-2xl bg-white p-6 text-gray-800 shadow-2xl">
            <h3 className="text-xl font-bold text-[#53685B]">Ajukan Refund</h3>
            <form onSubmit={submitRefund} className="mt-5 space-y-4">
              <div>
                <label className="mb-2 block text-sm font-semibold">
                  Pilih alasan refund
                </label>
                <select
                  value={refundReason}
                  onChange={(e) => setRefundReason(e.target.value)}
                  className="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-[#53685B] focus:ring-2 focus:ring-[#53685B]"
                >
                  {refundReasons.map((reason) => (
                    <option key={reason} value={reason}>
                      {reason}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="mb-2 block text-sm font-semibold">
                  {refundReason === 'Lainnya'
                    ? 'Tulis alasan refund'
                    : 'Catatan tambahan'}
                </label>
                <textarea
                  value={customReason}
                  onChange={(e) => setCustomReason(e.target.value)}
                  rows="4"
                  className="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-[#53685B] focus:ring-2 focus:ring-[#53685B]"
                  placeholder={
                    refundReason === 'Lainnya'
                      ? 'Contoh: Barang yang diterima berbeda dari foto produk.'
                      : 'Opsional, jelaskan detail masalahnya.'
                  }
                  required={refundReason === 'Lainnya'}
                />
              </div>

              <div>
                <label className="mb-2 block text-sm font-semibold">
                  Upload foto bukti
                </label>
                <input
                  type="file"
                  accept="image/*"
                  onChange={(e) =>
                    setRefundProofImage(e.target.files[0] || null)
                  }
                  className="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-[#53685B] focus:ring-2 focus:ring-[#53685B]"
                />
                <p className="mt-1 text-xs text-gray-500">
                  Opsional. Format JPG/PNG, maksimal 2MB.
                </p>
              </div>

              <div className="flex justify-end gap-3">
                <button
                  type="button"
                  onClick={closeRefundForm}
                  className="rounded-lg bg-gray-200 px-5 py-2 font-semibold text-gray-700 hover:bg-gray-300"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="rounded-lg bg-[#53685B] px-5 py-2 font-semibold text-white hover:bg-[#3c4a3e]"
                >
                  Kirim Pengajuan
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </section>
  );
}

OrderPage.layout = (page) => <MainLayout title="Order">{page}</MainLayout>;

const formatter = new Intl.DateTimeFormat('id-ID', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
});

function orderDate(date) {
  return formatter.format(new Date(date));
}
