import { Head, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR } from '@/lib/utils';
import { MoneyIcon } from '@/components/icons';

const sourceLabels = {
  service_fee: { label: 'Biaya Layanan', color: 'bg-green-100 text-green-700' },
  service_fee_reversal: {
    label: 'Pembalikan (Refund)',
    color: 'bg-red-100 text-red-700',
  },
};

export default function AdminRevenues() {
  const { revenues, summary, serviceFeePercent } = usePage().props;

  return (
    <>
      <Head title="Pendapatan Marketplace" />
      <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        {/* Saldo dompet admin */}
        <div className="mb-8 rounded-2xl bg-gradient-to-br from-[#53685B] to-[#3c4a3e] p-8 shadow-lg">
          <p className="text-sm font-medium text-[#E9E19E]/80">
            <MoneyIcon className="mr-2 inline h-6 w-6 align-text-bottom" />
            Saldo Dompet Admin
          </p>
          <p className="mt-1 text-3xl font-bold text-white sm:text-5xl">
            {formatIDR(summary.balance)}
          </p>
          <p className="mt-3 max-w-2xl text-sm text-white/70">
            Marketplace memungut{' '}
            <strong>biaya layanan {serviceFeePercent}%</strong> dari nilai
            barang pada setiap pesanan. Biaya itu baru menjadi pendapatan
            setelah pembayaran benar-benar lunas, dan dibalikkan kembali bila
            refund pesanannya disetujui.
          </p>
        </div>

        {/* Ringkasan */}
        <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <SummaryCard
            label="Biaya layanan masuk"
            value={formatIDR(summary.earned)}
            hint={`${summary.paid_order_count} pesanan lunas`}
            tone="text-green-700"
          />
          <SummaryCard
            label="Dibalikkan (refund)"
            value={formatIDR(summary.reversed)}
            hint="Dikembalikan ke pembeli"
            tone="text-red-600"
          />
          <SummaryCard
            label="Ongkir + biaya berat"
            value={formatIDR(
              summary.shipping_collected + summary.weight_collected
            )}
            hint="Bukan untung — biaya pengiriman"
            tone="text-gray-700"
          />
          <SummaryCard
            label="Biaya pengemasan"
            value={formatIDR(summary.packaging_collected)}
            hint="Bukan untung — hak seller"
            tone="text-gray-700"
          />
        </div>

        <div className="rounded-2xl bg-white p-6 shadow-md">
          <h2 className="mb-1 text-2xl font-bold text-[#53685B]">
            Buku Besar Pendapatan
          </h2>
          <p className="mb-6 text-sm text-gray-500">
            Setiap baris di bawah bisa ditelusuri ke pesanan yang
            menghasilkannya. Menampilkan {revenues.length} entri terbaru dari{' '}
            {summary.entry_count} entri.
          </p>

          <div className="overflow-x-auto">
            <table className="w-full border border-gray-200 text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="px-4 py-3 text-left">ID</th>
                  <th className="px-4 py-3 text-left">Jenis</th>
                  <th className="px-4 py-3 text-left">Pesanan</th>
                  <th className="px-4 py-3 text-left">Toko</th>
                  <th className="px-4 py-3 text-right">Nominal</th>
                  <th className="px-4 py-3 text-left">Waktu</th>
                </tr>
              </thead>
              <tbody>
                {revenues.map((revenue) => {
                  const source = sourceLabels[revenue.source] ?? {
                    label: revenue.source,
                    color: 'bg-gray-100 text-gray-700',
                  };

                  return (
                    <tr key={revenue.public_id} className="border-t">
                      <td className="px-4 py-3 font-mono text-xs">
                        {revenue.public_id}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`rounded-full px-2 py-1 text-xs font-semibold ${source.color}`}
                        >
                          {source.label}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        {revenue.order ? (
                          <span>
                            <span className="font-mono text-xs">
                              {revenue.order.public_id}
                            </span>
                            <span className="block text-xs text-gray-500">
                              {revenue.order.product_name}
                            </span>
                          </span>
                        ) : (
                          <span className="text-xs text-gray-400 italic">
                            Pesanan sudah dihapus
                          </span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-xs">
                        {revenue.order?.store_name ?? '-'}
                      </td>
                      <td
                        className={`px-4 py-3 text-right font-semibold ${
                          revenue.amount < 0 ? 'text-red-600' : 'text-green-700'
                        }`}
                      >
                        {revenue.amount < 0 ? '− ' : '+ '}
                        {formatIDR(Math.abs(revenue.amount))}
                      </td>
                      <td className="px-4 py-3 text-xs text-gray-500">
                        {new Date(revenue.created_at).toLocaleString('id-ID')}
                      </td>
                    </tr>
                  );
                })}

                {revenues.length === 0 && (
                  <tr>
                    <td
                      colSpan={6}
                      className="px-4 py-10 text-center text-gray-500 italic"
                    >
                      Belum ada pendapatan tercatat. Biaya layanan masuk setelah
                      ada pesanan yang lunas.
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

function SummaryCard({ label, value, hint, tone }) {
  return (
    <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-md">
      <p className="text-xs font-medium text-gray-500">{label}</p>
      <p className={`mt-1 text-2xl font-bold ${tone}`}>{value}</p>
      <p className="mt-1 text-xs text-gray-400">{hint}</p>
    </div>
  );
}

AdminRevenues.layout = (page) => (
  <MainLayout title="Pendapatan Marketplace">{page}</MainLayout>
);
