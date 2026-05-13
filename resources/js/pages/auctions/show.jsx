import { Form, Link, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR, getAuctionImage } from '@/lib/utils';

export default function AuctionShow() {
  const { auction, user, flash } = usePage().props;
  const minimumBid =
    Number(auction.current_price || auction.starting_price) +
    Number(auction.min_increment);
  const isWinner = user && auction.winner && user.public_id === auction.winner.public_id;
  const canBid = auction.status === 'active';

  return (
    <section className="px-6 py-10 md:px-16">
      <div className="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[1fr_420px]">
        <div className="overflow-hidden rounded-lg bg-white shadow-md">
          <img
            src={getAuctionImage(auction)}
            alt={auction.name}
            className="h-96 w-full object-cover"
          />
          <div className="p-6">
            <div className="mb-4 flex flex-wrap items-center gap-3">
              <span className="rounded-full bg-[#53685B]/10 px-3 py-1 text-sm font-semibold capitalize text-[#53685B]">
                {auction.status}
              </span>
              <span className="text-sm text-gray-500">
                {auction.store?.store_name || 'Toko'}
              </span>
            </div>
            <h1 className="text-3xl font-bold text-[#2F3E46]">{auction.name}</h1>
            <p className="mt-4 whitespace-pre-line text-gray-600">
              {auction.description}
            </p>
          </div>
        </div>

        <aside className="space-y-6">
          <div className="rounded-lg bg-white p-6 shadow-md">
            {flash?.success && (
              <div className="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {flash.success}
              </div>
            )}
            {flash?.error && (
              <div className="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {flash.error}
              </div>
            )}

            <div className="grid grid-cols-2 gap-4 text-sm">
              <Info label="Harga awal" value={formatIDR(auction.starting_price)} />
              <Info label="Harga tertinggi" value={formatIDR(auction.current_price)} />
              <Info label="Minimal naik" value={formatIDR(auction.min_increment)} />
              <Info label="Jumlah penawar" value={`${auction.bids_count} bid`} />
              <Info label="Mulai" value={formatDateTime(auction.starts_at)} />
              <Info label="Selesai" value={formatDateTime(auction.ends_at)} />
            </div>

            {canBid ? (
              <Form
                method="POST"
                action={`/auctions/${auction.public_id}/bid`}
                className="mt-6 space-y-3"
                options={{ preserveScroll: true }}
              >
                {({ errors, processing }) => (
                  <>
                    <label className="block text-sm font-semibold text-gray-700">
                      Ajukan penawaran
                    </label>
                    <input
                      type="number"
                      name="amount"
                      min={minimumBid}
                      defaultValue={minimumBid}
                      className="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-[#53685B] focus:ring-2 focus:ring-[#53685B]"
                      required
                    />
                    {errors.amount && (
                      <p className="text-xs text-red-600">{errors.amount}</p>
                    )}
                    <button
                      type="submit"
                      disabled={processing}
                      className="w-full rounded-lg bg-[#B77C4C] px-5 py-3 font-semibold text-white transition hover:bg-[#8d5e39] disabled:opacity-50"
                    >
                      Tombol ajukan penawaran
                    </button>
                  </>
                )}
              </Form>
            ) : (
              <div className="mt-6 rounded-lg bg-gray-100 p-4 text-sm text-gray-600">
                Lelang tidak sedang aktif.
              </div>
            )}

            {auction.status === 'ended' && auction.winner && (
              <div className="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                <p className="font-semibold">
                  Pemenang: {auction.winner.username}
                </p>
                {isWinner && (
                  <Form method="POST" action={`/auctions/${auction.public_id}/pay`}>
                    <button className="mt-3 w-full rounded-lg bg-[#53685B] px-4 py-2 font-semibold text-white hover:bg-[#3c4a3e]">
                      Bayar Sekarang
                    </button>
                  </Form>
                )}
              </div>
            )}
          </div>

          <div className="rounded-lg bg-white p-6 shadow-md">
            <h2 className="mb-4 text-lg font-bold text-[#53685B]">
              Riwayat Bid
            </h2>
            <div className="overflow-hidden rounded-lg border border-gray-200">
              <table className="w-full text-sm">
                <thead className="bg-[#53685B] text-white">
                  <tr>
                    <th className="px-4 py-3 text-left">User</th>
                    <th className="px-4 py-3 text-right">Nominal</th>
                  </tr>
                </thead>
                <tbody>
                  {auction.bids.length > 0 ? (
                    auction.bids.map((bid) => (
                      <tr key={`${bid.user?.public_id}-${bid.amount}-${bid.created_at}`} className="border-t">
                        <td className="px-4 py-3">{bid.user?.username || 'User'}</td>
                        <td className="px-4 py-3 text-right font-semibold">
                          {formatIDR(bid.amount)}
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan="2" className="px-4 py-6 text-center text-gray-500">
                        Belum ada bid.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </aside>
      </div>
    </section>
  );
}

AuctionShow.layout = (page) => (
  <MainLayout title="Detail Lelang" heroText="Detail Lelang">
    {page}
  </MainLayout>
);

function Info({ label, value }) {
  return (
    <div>
      <p className="text-xs text-gray-500">{label}</p>
      <p className="mt-1 font-bold text-[#2F3E46]">{value}</p>
    </div>
  );
}

function formatDateTime(value) {
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}
