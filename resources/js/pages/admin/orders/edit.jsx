import { Head, Link, useForm, usePage } from '@inertiajs/react';
import MainLayout from '../../../layouts/main-layout';

export default function EditOrder() {
  const { order } = usePage().props;
  const { data, setData, put, processing, errors } = useForm({
    status: order.status || 'Waiting',
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    put(`/admin/orders/${order.id}`, {
      preserveScroll: true,
    });
  };

  return (
    <>
      <Head title="Edit Status Order" />
      <div className="mx-auto max-w-4xl px-6 py-8">
        <div className="rounded-2xl bg-white p-8 shadow-md shadow-[#53685B]/20">
          <h2 className="mb-6 text-2xl font-bold text-[#53685B]">
            ✏️ Edit Order #{order.id}
          </h2>

          <div className="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-6">
            <h3 className="mb-4 text-lg font-semibold text-[#53685B]">
              📦 Detail Order
            </h3>
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p className="text-gray-600">Customer</p>
                <p className="font-semibold">
                  {order.user.first_name} {order.user.last_name}
                </p>
                <p className="text-xs text-gray-500">{order.user.email}</p>
              </div>
              <div>
                <p className="text-gray-600">Produk</p>
                <p className="font-semibold">{order.product.name}</p>
              </div>
              <div>
                <p className="text-gray-600">Toko</p>
                <p className="font-semibold">{order.store?.store_name || '-'}</p>
              </div>
              <div>
                <p className="text-gray-600">Quantity</p>
                <p className="font-semibold">{order.quantity} pcs</p>
              </div>
              <div>
                <p className="text-gray-600">Total Harga</p>
                <p className="font-bold text-[#53685B]">
                  Rp{order.price?.toLocaleString('id-ID')}
                </p>
              </div>
              <div>
                <p className="text-gray-600">Tanggal Order</p>
                <p className="font-semibold">
                  {new Date(order.created_at).toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                  })}
                </p>
              </div>
            </div>
          </div>

          <form onSubmit={handleSubmit}>
            <div className="mb-6">
              <label className="mb-2 block text-sm font-bold text-gray-700">
                Status Order
              </label>
              <select
                value={data.status}
                onChange={(e) => setData('status', e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-4 py-3 transition focus:border-[#53685B] focus:ring-2 focus:ring-[#53685B]"
                required
              >
                <option value="Waiting">⏳ Waiting (Menunggu)</option>
                <option value="Processing">🔄 Processing (Diproses)</option>
                <option value="Completed">✅ Completed (Selesai)</option>
                <option value="Cancelled">❌ Cancelled (Dibatalkan)</option>
              </select>
              {errors.status && (
                <p className="mt-1 text-xs text-red-500">{errors.status}</p>
              )}
            </div>

            <div className="flex gap-4">
              <button
                type="submit"
                disabled={processing}
                className="flex-1 rounded-lg bg-[#53685B] px-6 py-3 font-bold text-white shadow-md transition hover:bg-[#3c4a3e] hover:shadow-lg disabled:opacity-50"
              >
                💾 Simpan Perubahan
              </button>
              <Link
                href="/admin/orders"
                className="flex-1 rounded-lg bg-gray-300 px-6 py-3 text-center font-bold text-gray-800 shadow-md transition hover:bg-gray-400 hover:shadow-lg"
              >
                ← Kembali
              </Link>
            </div>
          </form>
        </div>
      </div>
    </>
  );
}

EditOrder.layout = (page) => (
  <MainLayout title="Edit Status Order">{page}</MainLayout>
);
