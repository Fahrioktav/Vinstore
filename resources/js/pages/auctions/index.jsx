import { Link, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR, getAuctionImage } from '@/lib/utils';

const statusLabel = {
  scheduled: 'Akan dimulai',
  active: 'Berlangsung',
  ended: 'Selesai',
  cancelled: 'Dibatalkan',
};

export default function AuctionsIndex() {
  const { auctions } = usePage().props;

  return (
    <section className="px-6 py-10 md:px-16">
      <div className="mb-8 flex items-end justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-[#E9E19E]">Lelang Barang Antik</h1>
          <p className="mt-2 text-sm text-gray-100">
            Ikuti lelang yang sudah diverifikasi admin Vinstore.
          </p>
        </div>
      </div>

      {auctions.length === 0 ? (
        <div className="rounded-lg bg-white p-8 text-center text-gray-500">
          Belum ada lelang yang tersedia.
        </div>
      ) : (
        <div className="grid gap-6 md:grid-cols-3">
          {auctions.map((auction) => (
            <Link
              href={`/auctions/${auction.public_id}`}
              key={auction.public_id}
              className="overflow-hidden rounded-lg bg-white shadow-md transition hover:-translate-y-1 hover:shadow-xl"
            >
              <img
                src={getAuctionImage(auction)}
                alt={auction.name}
                className="h-52 w-full object-cover"
              />
              <div className="p-5">
                <div className="mb-3 flex items-center justify-between gap-3">
                  <span className="rounded-full bg-[#53685B]/10 px-3 py-1 text-xs font-semibold text-[#53685B]">
                    {statusLabel[auction.status] || auction.status}
                  </span>
                  <span className="text-xs text-gray-500">
                    {auction.bids_count} bid
                  </span>
                </div>
                <h2 className="line-clamp-2 text-lg font-bold text-[#2F3E46]">
                  {auction.name}
                </h2>
                <p className="mt-2 text-sm text-gray-500">
                  {auction.store?.store_name || 'Toko'}
                </p>
                <div className="mt-4">
                  <p className="text-xs text-gray-500">Harga tertinggi</p>
                  <p className="text-xl font-bold text-[#B77C4C]">
                    {formatIDR(auction.current_price || auction.starting_price)}
                  </p>
                </div>
                <p className="mt-3 text-xs text-gray-500">
                  Selesai: {formatDateTime(auction.ends_at)}
                </p>
              </div>
            </Link>
          ))}
        </div>
      )}
    </section>
  );
}

AuctionsIndex.layout = (page) => (
  <MainLayout title="Lelang" heroText="Lelang Barang Antik">
    {page}
  </MainLayout>
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
