import { usePage } from '@inertiajs/react';
import { formatIDR } from '../lib/utils';
import MainLayout from '../layouts/main-layout';

export default function OrderPage() {
  const { orders } = usePage().props;

  return (
    <section className="min-h-screen w-full bg-gradient-to-br from-[#2F3E46] via-[#354F52] to-[#B77C4C] px-6 pt-32 pb-20 text-[#E9E19E] md:px-12">
      <div className="mx-auto max-w-6xl">
        <h2 className="mb-10 text-center text-4xl font-bold">📦 Pesananmu</h2>
        {orders.length === 0 ? (
          <div className="py-20 text-center text-lg text-[#E9E19E]/90 italic">
            Belum ada pesanan yang masuk 🕯️
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
                        className="rounded-full px-3 py-1 text-sm"
                        // @if(order.status === 'Waiting') bg-yellow-500/30 text-yellow-200
                        // @elseif(order.status === 'On The Way') bg-blue-500/30 text-blue-200
                        // @elseif(order.status === 'Delivered') bg-green-500/30 text-green-200
                        // @elseif(order.status === 'Cancelled') bg-red-500/30 text-red-200
                        // @else bg-gray-500/30 text-gray-200 @endif"
                      >
                        {order.status}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      {order.created_at.format('d M Y, H:i')}
                    </td>
                    <td className="px-6 py-4 text-center">
                      {['Waiting', 'On The Way'].includes(order.status) ? (
                        <form
                          action="{ route('order.cancel', order.id) }"
                          method="POST"
                          onSubmit="return confirm('Yakin ingin membatalkan pesanan ini?');"
                        >
                          @csrf @method('DELETE')
                          <button className="font-semibold text-red-400 transition hover:text-red-300">
                            Batalkan
                          </button>
                        </form>
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
