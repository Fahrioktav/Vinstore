import { Head, Link, router, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { cn, formatIDR, getProductCertificate, getProductImage } from '@/lib/utils';
import { BadgeIcon } from '@/components/icons';
import ActionMenu from '@/components/ui/action-menu';

const statusLabels = {
  pending_validator: 'Menunggu Validasi',
  pending_admin: 'Menunggu Admin',
  approved: 'Disetujui',
  rejected: 'Ditolak',
};

const statusColors = {
  pending_validator: 'bg-yellow-100 text-yellow-700',
  pending_admin: 'bg-blue-100 text-blue-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
};

export default function ValidatorDashboard() {
  const { pendingProducts, reviewedProducts, stats } = usePage().props;

  const handleApprove = (publicId) => {
    if (confirm('Validasi produk ini dan teruskan ke admin?')) {
      router.post(`/validator/products/${publicId}/approve`, {}, {
        preserveScroll: true,
      });
    }
  };

  const handleReject = (publicId) => {
    const rejectionReason = prompt('Alasan penolakan produk (opsional):') || '';
    router.post(
      `/validator/products/${publicId}/reject`,
      { rejection_reason: rejectionReason },
      { preserveScroll: true }
    );
  };

  const statCards = [
    { label: 'Menunggu Validasi', value: stats?.pending ?? 0, color: 'from-yellow-500 to-yellow-600' },
    { label: 'Diteruskan ke Admin', value: stats?.forwarded ?? 0, color: 'from-blue-500 to-blue-600' },
    { label: 'Ditolak', value: stats?.rejected ?? 0, color: 'from-red-500 to-red-600' },
  ];

  return (
    <>
      <Head title="Dashboard Validator" />
      <div className="mx-auto max-w-7xl px-6 py-8">
        <h2 className="mt-2 mb-6 text-3xl font-bold text-[#E9E19E]/90">
          Dashboard Validator Barang Antik
        </h2>

        {/* Stats */}
        <div className="mb-10 grid grid-cols-1 gap-6 sm:grid-cols-3">
          {statCards.map((stat, i) => (
            <div
              key={i}
              className="rounded-2xl border border-gray-100 bg-gradient-to-br from-white to-gray-50 p-6 shadow-lg shadow-[#53685B]/10"
            >
              <p className="text-3xl font-bold text-[#53685B]">{stat.value}</p>
              <p className="text-sm font-medium text-gray-600">{stat.label}</p>
            </div>
          ))}
        </div>

        {/* Pending validation */}
        <div className="mb-10 rounded-2xl bg-white p-6 shadow-md shadow-[#53685B]/20">
          <h2 className="mb-6 text-2xl font-bold text-[#53685B]">
            🔎 Produk Menunggu Validasi
          </h2>
          <div className="overflow-x-auto">
            <table className="w-full border border-gray-200 text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="px-4 py-3 text-left">Foto</th>
                  <th className="px-4 py-3 text-left">Nama Produk</th>
                  <th className="px-4 py-3 text-left">Toko</th>
                  <th className="px-4 py-3 text-left">Harga</th>
                  <th className="px-4 py-3 text-left">Kategori</th>
                  <th className="px-4 py-3 text-center">Sertifikat</th>
                  <th className="px-4 py-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {pendingProducts.length > 0 ? (
                  pendingProducts.map((product) => (
                    <tr key={product.public_id} className="border-t hover:bg-gray-50">
                      <td className="px-4 py-3">
                        <img
                          src={getProductImage(product)}
                          className="h-16 w-16 rounded-lg object-cover shadow-sm"
                          alt={product.name}
                        />
                      </td>
                      <td className="px-4 py-3">
                        <p className="font-semibold text-gray-800">{product.name}</p>
                        <p className="mt-1 text-xs text-gray-500">
                          {product.description?.substring(0, 40)}
                          {product.description?.length > 40 ? '...' : ''}
                        </p>
                      </td>
                      <td className="px-4 py-3 text-gray-700">
                        {product.store?.store_name || '-'}
                      </td>
                      <td className="px-4 py-3 font-bold text-[#53685B]">
                        {formatIDR(product.price)}
                      </td>
                      <td className="px-4 py-3 text-center">
                        <span className="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-700">
                          {product.category}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-center">
                        {product.certificate ? (
                          <a
                            href={getProductCertificate(product)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1 rounded-lg bg-green-100 px-3 py-1.5 text-xs font-semibold text-green-700 transition hover:bg-green-200"
                            title="Lihat Sertifikat"
                          >
                            <BadgeIcon className="h-4 w-4" />
                            Lihat
                          </a>
                        ) : (
                          <span className="text-xs text-gray-400">-</span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex justify-center">
                          <ActionMenu
                            items={[
                              {
                                label: 'Lihat Detail',
                                icon: '🔍',
                                href: `/validator/products/${product.public_id}`,
                              },
                              {
                                label: 'Validasi',
                                icon: '✅',
                                onClick: () => handleApprove(product.public_id),
                              },
                              {
                                label: 'Tolak',
                                icon: '🚫',
                                variant: 'destructive',
                                onClick: () => handleReject(product.public_id),
                              },
                            ]}
                          />
                        </div>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="7" className="px-4 py-8 text-center text-gray-500">
                      <p className="text-lg">✅ Tidak ada produk yang menunggu validasi</p>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Reviewed history */}
        <div className="rounded-2xl bg-white p-6 shadow-md shadow-[#53685B]/20">
          <h2 className="mb-6 text-2xl font-bold text-[#53685B]">
            🗂️ Riwayat Validasi
          </h2>
          <div className="overflow-x-auto">
            <table className="w-full border border-gray-200 text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="px-4 py-3 text-left">Nama Produk</th>
                  <th className="px-4 py-3 text-left">Toko</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-left">Catatan</th>
                  <th className="px-4 py-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {reviewedProducts.length > 0 ? (
                  reviewedProducts.map((product) => (
                    <tr key={product.public_id} className="border-t hover:bg-gray-50">
                      <td className="px-4 py-3 font-semibold text-gray-800">
                        {product.name}
                      </td>
                      <td className="px-4 py-3 text-gray-700">
                        {product.store?.store_name || '-'}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={cn(
                            'rounded-full px-3 py-1 text-xs font-semibold',
                            statusColors[product.approval_status] || 'bg-gray-100 text-gray-700'
                          )}
                        >
                          {statusLabels[product.approval_status] || product.approval_status}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-xs text-red-600">
                        {product.rejection_reason || '-'}
                      </td>
                      <td className="px-4 py-3 text-center">
                        <div className="flex justify-center">
                          <ActionMenu
                            items={[
                              {
                                label: 'Lihat Detail',
                                icon: '🔍',
                                href: `/validator/products/${product.public_id}`,
                              },
                            ]}
                          />
                        </div>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="5" className="px-4 py-8 text-center text-gray-500">
                      Belum ada riwayat validasi
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

ValidatorDashboard.layout = (page) => (
  <MainLayout title="Dashboard Validator">{page}</MainLayout>
);
