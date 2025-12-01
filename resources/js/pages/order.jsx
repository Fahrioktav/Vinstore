import { Form, usePage } from '@inertiajs/react';
import { cn, formatIDR } from '@/lib/utils';
import MainLayout from '@/layouts/main-layout';

const statusStyles = {
  Waiting: 'bg-yellow-500/30 text-yellow-200',
  'On The Way': 'bg-blue-500/30 text-blue-200',
  Delivered: 'bg-green-500/30 text-green-200',
  Cancelled: 'bg-red-500/30 text-red-200',
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
        <h2 className="mb-10 text-center text-4xl font-bold">📦 Pesananmu</h2>
        {orders.length === 0 ? (
          <div className="py-20 text-center text-lg text-[#E9E19E]/90 italic">
            Kamu belum memesan apapun 🕯️
          </div>
        ) : (
          <div className="overflow-x-auto rounded-2xl border border-white/20 bg-white/10 shadow-xl backdrop-blur-md">
            <table className="min-w-full text-left text-[#E9E19E]">
              <thead>
                <tr className="bg-[#E9E19E]/10 text-sm tracking-wider uppercase">
                  <th className="px-6 py-4">Item</th>
                  <th className="px-6 py-4">Jumlah</th>
                  <th className="px-6 py-4">Harga</th>
                  <th className="px-6 py-4">Status</th>
                  <th className="px-6 py-4">Tanggal</th>
                  <th className="px-6 py-4 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {orders.map((order) => (
                  <tr
                    className="border-b border-white/10 transition hover:bg-white/10"
                    key={order.id}
                  >
                    <td className="px-6 py-4 font-semibold">
                      {order.product.name ?? 'Produk tidak ditemukan'}
                    </td>
                    <td className="px-6 py-4">{order.quantity}</td>
                    <td className="px-6 py-4">{formatIDR(order.price)}</td>
                    <td className="px-6 py-4">
                      <span
                        className={cn(
                          'rounded-full px-3 py-1 text-sm',
                          statusStyles[order.status] ?? statusStyles.Unknown
                        )}
                      >
                        {order.status}
                      </span>
                    </td>
                    <td className="px-6 py-4">{orderDate(order.created_at)}</td>
                    <td className="px-6 py-4 text-center">
                      {['Waiting', 'On The Way'].includes(order.status) ? (
                        <Form action={`/order/${order.id}`} method="DELETE">
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

// Format manual pakai Intl.DateTimeFormat
const formatter = new Intl.DateTimeFormat('id-ID', {
  day: '2-digit',
  month: 'short', // Jan, Feb, Mar...
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
});

function orderDate(date) {
  return formatter.format(new Date(date));
}
