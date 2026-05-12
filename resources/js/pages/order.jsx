import { Form, Link, usePage } from '@inertiajs/react';
import { cn, formatIDR } from '@/lib/utils';
import MainLayout from '@/layouts/main-layout';

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

export default function OrderPage() {
  const { orders } = usePage().props;

  const onSubmit = (e) => {
    if (!confirm('Yakin ingin membatalkan pesanan ini?')) {
      e.preventDefault();
    }
  };

  return (
    <section className="w-full px-6 pt-32 pb-20 text-[#E9E19E] md:px-12">
      <div className="mx-auto max-w-6xl">
        <h2 className="mb-10 text-center text-4xl font-bold">Pesananmu</h2>
        {orders.length === 0 ? (
          <div className="py-20 text-center text-lg text-[#E9E19E]/90 italic">
            Kamu belum memesan apapun
          </div>
        ) : (
          <div className="overflow-x-auto rounded-2xl border border-white/20 bg-white/10 shadow-xl backdrop-blur-md">
            <table className="min-w-full text-left text-[#E9E19E]">
              <thead>
                <tr className="bg-[#E9E19E]/10 text-sm tracking-wider uppercase">
                  <th className="px-6 py-4">Item</th>
                  <th className="px-6 py-4">Jumlah</th>
                  <th className="px-6 py-4">Harga</th>
                  <th className="px-6 py-4">Pembayaran</th>
                  <th className="px-6 py-4">Status</th>
                  <th className="px-6 py-4">Tanggal</th>
                  <th className="px-6 py-4 text-center">Invoice</th>
                  <th className="px-6 py-4 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {orders.map((order) => (
                  <tr
                    className="border-b border-white/10 transition hover:bg-white/10"
                    key={order.public_id}
                  >
                    <td className="px-6 py-4 font-semibold">
                      {order.product.name ?? 'Produk tidak ditemukan'}
                    </td>
                    <td className="px-6 py-4">{order.quantity}</td>
                    <td className="px-6 py-4">{formatIDR(order.price)}</td>
                    <td className="px-6 py-4">
                      <span
                        className={cn(
                          'rounded-full px-3 py-1 text-sm capitalize',
                          paymentStyles[order.payment_status] ??
                            paymentStyles.Unknown
                        )}
                      >
                        {order.payment_status || 'unpaid'}
                      </span>
                    </td>
                    <td className="px-7 py-4 whitespace-nowrap">
                      <span
                        className={cn(
                          'inline-flex min-w-max items-center rounded-full px-4 py-1 text-sm whitespace-nowrap',
                          statusStyles[order.status] ?? statusStyles.Unknown
                        )}
                      >
                        {order.status}
                      </span>
                    </td>
                    <td className="px-6 py-4">{orderDate(order.created_at)}</td>
                    <td className="px-6 py-4 text-center">
                      <Link
                        href={`/invoice/${order.public_id}`}
                        className="inline-flex items-center gap-1 rounded-lg bg-[#B77C4C] px-4 py-2 font-semibold text-white transition hover:bg-[#8d5e39]"
                      >
                        Invoice
                      </Link>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <div className="flex flex-col items-center gap-3">
                        {order.snap_redirect_url &&
                          ['pending', 'unpaid'].includes(
                            order.payment_status
                          ) && (
                            <a
                              href={order.snap_redirect_url}
                              className="font-semibold text-yellow-300 transition hover:text-yellow-200"
                            >
                              Bayar
                            </a>
                          )}
                        {['Waiting', 'On The Way'].includes(order.status) ? (
                          <Form
                            action={`/order/${order.public_id}`}
                            method="DELETE"
                          >
                            <button
                              type="submit"
                              className="font-semibold text-red-400 transition hover:text-red-300"
                              onClick={onSubmit}
                            >
                              Batalkan
                            </button>
                          </Form>
                        ) : (
                          <span className="text-gray-400">-</span>
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
