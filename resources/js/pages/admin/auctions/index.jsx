import { Head, Link, router, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR, getAuctionImage } from '@/lib/utils';

export default function AdminAuctions() {
  const { auctions, success, error } = usePage().props;

  const approve = (publicId) => {
    router.post(`/admin/auctions/${publicId}/approve`, {}, { preserveScroll: true });
  };

  const reject = (publicId) => {
    const rejectionReason = prompt('Alasan penolakan lelang (opsional):') || '';
    router.post(
      `/admin/auctions/${publicId}/reject`,
      { rejection_reason: rejectionReason },
      { preserveScroll: true }
    );
  };

  const approvalColors = {
    approved: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    rejected: 'bg-red-100 text-red-700',
  };

  const statusColors = {
    pending: 'bg-gray-100 text-gray-700',
    scheduled: 'bg-blue-100 text-blue-700',
    active: 'bg-green-100 text-green-700',
    ended: 'bg-slate-100 text-slate-700',
    cancelled: 'bg-red-100 text-red-700',
  };

  return (
    <>
      <Head title="Kelola Lelang" />
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
            Kelola Lelang
          </h2>
          <div className="overflow-x-auto">
            <table className="w-full border border-gray-200 text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="px-4 py-3 text-left">Foto</th>
                  <th className="px-4 py-3 text-left">Barang</th>
                  <th className="px-4 py-3 text-left">Toko</th>
                  <th className="px-4 py-3 text-left">Harga</th>
                  <th className="px-4 py-3 text-left">Jadwal</th>
                  <th className="px-4 py-3 text-left">Approval</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {auctions.length > 0 ? (
                  auctions.map((auction) => (
                    <tr key={auction.public_id} className="border-t hover:bg-gray-50">
                      <td className="px-4 py-3">
                        <img
                          src={getAuctionImage(auction)}
                          alt={auction.name}
                          className="h-16 w-16 rounded-lg object-cover"
                        />
                      </td>
                      <td className="px-4 py-3">
                        <p className="font-semibold">{auction.name}</p>
                        <p className="text-xs text-gray-500">
                          {auction.bids_count} bid
                        </p>
                      </td>
                      <td className="px-4 py-3">{auction.store?.store_name || '-'}</td>
                      <td className="px-4 py-3">
                        <p className="font-semibold text-[#53685B]">
                          {formatIDR(auction.current_price)}
                        </p>
                        <p className="text-xs text-gray-500">
                          Awal {formatIDR(auction.starting_price)}
                        </p>
                      </td>
                      <td className="px-4 py-3 text-xs text-gray-600">
                        <p>{formatDateTime(auction.starts_at)}</p>
                        <p>{formatDateTime(auction.ends_at)}</p>
                      </td>
                      <td className="px-4 py-3">
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${approvalColors[auction.approval_status] || 'bg-gray-100 text-gray-700'}`}>
                          {auction.approval_status}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${statusColors[auction.status] || 'bg-gray-100 text-gray-700'}`}>
                          {auction.status}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex justify-center gap-2">
                          <Link
                            href={`/auctions/${auction.public_id}`}
                            className="rounded-lg bg-[#53685B] px-4 py-2 text-xs font-semibold text-white"
                          >
                            Detail
                          </Link>
                          {auction.approval_status !== 'approved' && (
                            <button
                              onClick={() => approve(auction.public_id)}
                              className="rounded-lg bg-green-600 px-4 py-2 text-xs font-semibold text-white"
                            >
                              Setujui
                            </button>
                          )}
                          {auction.approval_status !== 'rejected' && (
                            <button
                              onClick={() => reject(auction.public_id)}
                              className="rounded-lg bg-yellow-500 px-4 py-2 text-xs font-semibold text-white"
                            >
                              Tolak
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="8" className="px-4 py-8 text-center text-gray-500">
                      Belum ada lelang.
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

AdminAuctions.layout = (page) => (
  <MainLayout title="Kelola Lelang">{page}</MainLayout>
);

function formatDateTime(value) {
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}
