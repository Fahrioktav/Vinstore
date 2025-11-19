import { Head, Link, router, usePage } from '@inertiajs/react';
import MainLayout from '../../../layouts/main-layout';
import { formatIDR } from '../../../lib/utils';

export default function AdminOrders() {
  const { orders, success } = usePage().props;

  const handleDelete = (id) => {
    if (confirm('Yakin ingin menghapus order ini?')) {
      router.delete(`/admin/orders/${id}`, {
        preserveScroll: true,
      });
    }
  };

  const statusColors = {
    Waiting: 'bg-yellow-100 text-yellow-700',
    Processing: 'bg-blue-100 text-blue-700',
    Completed: 'bg-green-100 text-green-700',
    Cancelled: 'bg-red-100 text-red-700',
  };

  return (
    <>
      <Head title="Kelola Order" />
      <div className="mx-auto max-w-7xl px-6 py-8">
        {success && (
          <div className="mb-6 rounded-lg border-l-4 border-green-500 bg-green-50 px-4 py-3 text-green-700 shadow-sm">
            <p className="font-semibold">✓ {success}</p>
          </div>
        )}

        <div className="rounded-2xl bg-white p-6 shadow-md shadow-[#53685B]/20">
          <h2 className="mb-6 text-2xl font-bold text-[#53685B]">
            📦 Kelola Data Order
          </h2>
          <div className="overflow-x-auto">
            <table className="w-full border border-gray-200 text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="px-4 py-3 text-left">ID Order</th>
                  <th className="px-4 py-3 text-left">Customer</th>
                  <th className="px-4 py-3 text-left">Produk</th>
                  <th className="px-4 py-3 text-left">Toko</th>
                  <th className="px-4 py-3 text-left">Qty</th>
                  <th className="px-4 py-3 text-left">Total Harga</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-left">Tanggal</th>
                  <th className="px-4 py-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {orders.length > 0 ? (
                  orders.map((order) => (
                    <tr key={order.id} className="border-t hover:bg-gray-50">
                      <td className="px-4 py-3 font-semibold">#{order.id}</td>
                      <td className="px-4 py-3">
                        <p className="font-semibold">
                          {order.user.first_name} {order.user.last_name}
                        </p>
                        <p className="text-xs text-gray-500">
                          {order.user.email}
                        </p>
                      </td>
                      <td className="px-4 py-3">{order.product?.name || '-'}</td>
                      <td className="px-4 py-3">{order.store?.store_name || '-'}</td>
                      <td className="px-4 py-3">{order.quantity}</td>
                      <td className="px-4 py-3 font-bold text-[#53685B]">
                        {formatIDR(order.price)}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`rounded-full px-3 py-1 text-xs font-semibold ${statusColors[order.status] || 'bg-gray-100 text-gray-700'}`}
                        >
                          {order.status}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-xs text-gray-600">
                        {new Date(order.created_at).toLocaleDateString(
                          'id-ID',
                          {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                          }
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex justify-center gap-2">
                          <Link
                            href={`/admin/orders/${order.id}/edit`}
                            className="rounded-lg bg-[#53685B] px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#3c4a3e] hover:shadow-md"
                          >
                            ✏️ Edit
                          </Link>
                          <button
                            onClick={() => handleDelete(order.id)}
                            className="rounded-lg bg-red-500 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-red-600 hover:shadow-md"
                          >
                            🗑️ Hapus
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td
                      colSpan="9"
                      className="px-4 py-8 text-center text-gray-500"
                    >
                      <p className="text-lg">📦 Belum ada order</p>
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

AdminOrders.layout = (page) => (
  <MainLayout title="Kelola Order">{page}</MainLayout>
);
