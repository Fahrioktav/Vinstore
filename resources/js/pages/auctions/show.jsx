import { Form, Link, usePage, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { NotificationIcon } from '@/components/icons';
import { formatIDR, getAuctionImage, getProductCertificate } from '@/lib/utils';
import { openSnapPayment } from '@/lib/midtrans';
import { toast } from 'sonner';

export default function AuctionShow() {
  const { auction: initialAuction, myDeposit, user, flash } = usePage().props;

  // State untuk real-time updates
  const [auction, setAuction] = useState(initialAuction);
  const [bids, setBids] = useState(initialAuction.bids || []);
  const [showNewBidNotification, setShowNewBidNotification] = useState(false);

  const minimumBid =
    Number(auction.current_price || auction.starting_price) +
    Number(auction.min_increment);
  const isWinner =
    user && auction.winner && user.public_id === auction.winner.public_id;

  // Lelang hanya untuk pembeli. Seller, validator, dan admin tetap boleh
  // membuka halaman ini, tetapi sebagai pengamat.
  const isPembeli = user?.role === 'user';
  const sedangBerjalan = auction.status === 'active';
  const canBid = sedangBerjalan && isPembeli;

  // Deposit sudah aktif berarti berhak menawar. `applied` ikut dianggap aktif
  // agar pemenang tidak kehilangan haknya.
  const depositActive = ['paid', 'applied'].includes(myDeposit?.status);
  const depositBlocking = initialAuction.requires_deposit && !depositActive;

  // Handle snap_token untuk pembayaran lelang maupun deposit.
  // Menunggu library Snap siap agar popup tidak gagal muncul diam-diam.
  useEffect(() => {
    if (!flash?.snap_token) {
      return;
    }

    const isDeposit = flash.snap_context === 'deposit';

    const afterPaid = () => {
      // Deposit tidak menghasilkan pesanan; yang berubah hanya hak menawar di
      // halaman ini, jadi cukup dimuat ulang.
      if (isDeposit) {
        router.reload({ only: ['auction', 'myDeposit'] });

        return;
      }

      router.visit('/order');
    };

    openSnapPayment(flash.snap_token, {
      onSuccess: () => {
        toast.success(
          isDeposit
            ? 'Deposit berhasil dibayar!'
            : 'Pembayaran lelang berhasil!'
        );
        afterPaid();
      },
      onPending: () => {
        toast.info('Pembayaran sedang diproses');
        afterPaid();
      },
      onError: () => {
        toast.error('Pembayaran gagal. Silakan coba lagi.');
      },
      onClose: () => {
        toast.info('Anda menutup popup pembayaran');
      },
    }).catch((error) => {
      toast.error(error.message);
    });
  }, [flash?.snap_token]);

  // WebSocket real-time listener
  useEffect(() => {
    if (!window.Echo || !auction.id) return;

    const channel = window.Echo.channel(`auction.${auction.id}`);

    channel.listen('AuctionBidPlaced', (event) => {
      console.log('New bid received:', event);

      // Update auction data
      setAuction((prev) => ({
        ...prev,
        current_price: event.auction.current_price,
        bids_count: event.auction.bids_count,
      }));

      // Tambahkan bid baru ke list (di paling atas)
      setBids((prev) => [event.bid, ...prev]);

      // Show notification
      setShowNewBidNotification(true);
      setTimeout(() => setShowNewBidNotification(false), 3000);

      // Optional: Play sound
      try {
        const audio = new Audio('/assets/notification.mp3');
        audio.volume = 0.3;
        audio.play().catch(() => {});
      } catch (e) {}
    });

    return () => {
      channel.stopListening('AuctionBidPlaced');
      window.Echo.leave(`auction.${auction.id}`);
    };
  }, [auction.id]);

  return (
    <section className="px-4 py-8 sm:px-6 sm:py-10 md:px-16">
      <div className="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[1fr_420px]">
        <div className="overflow-hidden rounded-lg bg-white shadow-md">
          <img
            src={getAuctionImage(auction)}
            alt={auction.name}
            className="h-96 w-full object-cover"
          />
          <div className="p-6">
            <div className="mb-4 flex flex-wrap items-center gap-3">
              <span className="rounded-full bg-[#53685B]/10 px-3 py-1 text-sm font-semibold text-[#53685B] capitalize">
                {auction.status}
              </span>
              <span className="text-sm text-gray-500">
                {auction.store?.store_name || 'Toko'}
              </span>
            </div>
            <h1 className="text-3xl font-bold text-[#2F3E46]">
              {auction.name}
            </h1>
            <p className="mt-4 whitespace-pre-line text-gray-600">
              {auction.description}
            </p>

            {initialAuction.certificate && (
              <div className="mt-6 rounded-lg border-2 border-green-200 bg-green-50 p-4">
                <p className="font-semibold text-green-800">
                  Barang Bersertifikat
                </p>
                <p className="mt-1 text-sm text-green-700">
                  Keasliannya dilengkapi sertifikat dan sudah diperiksa
                  validator.
                </p>
                <a
                  href={getProductCertificate(initialAuction)}
                  target="_blank"
                  rel="noreferrer"
                  className="mt-2 inline-block text-sm font-semibold text-blue-600 underline hover:text-blue-800"
                >
                  Lihat Sertifikat
                </a>
              </div>
            )}
          </div>
        </div>

        <aside className="space-y-6">
          <div className="rounded-lg bg-white p-6 shadow-md">
            {/* Real-time notification */}
            {showNewBidNotification && (
              <div className="mb-4 flex animate-pulse items-center gap-2 rounded border-l-4 border-blue-500 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                <NotificationIcon className="h-4 w-4" />
                Ada bid baru masuk!
              </div>
            )}

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

            <div className="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
              <Info
                label="Harga awal"
                value={formatIDR(initialAuction.starting_price)}
              />
              <Info
                label="Harga tertinggi"
                value={formatIDR(auction.current_price)}
              />
              <Info
                label="Minimal naik"
                value={formatIDR(initialAuction.min_increment)}
              />
              <Info
                label="Jumlah penawar"
                value={`${auction.bids_count} bid`}
              />
              <Info
                label="Mulai"
                value={formatDateTime(initialAuction.starts_at)}
              />
              <Info
                label="Selesai"
                value={formatDateTime(initialAuction.ends_at)}
              />
            </div>

            {initialAuction.requires_deposit && isPembeli && (
              <DepositPanel
                auction={initialAuction}
                deposit={myDeposit}
                depositActive={depositActive}
                canBid={canBid}
              />
            )}

            {canBid ? (
              depositBlocking ? (
                <div className="mt-6 rounded-lg bg-gray-100 p-4 text-sm text-gray-600">
                  Bayar deposit terlebih dahulu untuk dapat menawar.
                </div>
              ) : (
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
              )
            ) : (
              <div className="mt-6 rounded-lg bg-gray-100 p-4 text-sm text-gray-600">
                {!sedangBerjalan
                  ? 'Lelang tidak sedang aktif.'
                  : !user
                    ? 'Masuk dengan akun pembeli untuk mengikuti lelang ini.'
                    : 'Lelang hanya dapat diikuti oleh akun pembeli.'}
              </div>
            )}

            {auction.status === 'ended' && auction.winner && (
              <div className="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                <p className="font-semibold">
                  Pemenang: {auction.winner.username}
                </p>
                {isWinner && (
                  <Link
                    href={`/auctions/${auction.public_id}/checkout`}
                    className="mt-3 block w-full rounded-lg bg-[#53685B] px-4 py-2 text-center font-semibold text-white hover:bg-[#3c4a3e]"
                  >
                    Lanjut ke Pembayaran
                  </Link>
                )}
              </div>
            )}
          </div>

          <div className="rounded-lg bg-white p-6 shadow-md">
            <h2 className="mb-4 text-lg font-bold text-[#53685B]">
              Riwayat Bid
            </h2>
            <div className="overflow-x-auto rounded-lg border border-gray-200">
              <table className="w-full min-w-[20rem] text-sm">
                <thead className="bg-[#53685B] text-white">
                  <tr>
                    <th className="px-4 py-3 text-left">User</th>
                    <th className="px-4 py-3 text-right">Nominal</th>
                  </tr>
                </thead>
                <tbody>
                  {bids.length > 0 ? (
                    bids.map((bid, index) => (
                      <tr
                        key={`${bid.user?.public_id}-${bid.amount}-${bid.created_at || index}`}
                        className={`border-t ${index === 0 ? 'bg-blue-50' : ''}`}
                      >
                        <td className="px-4 py-3">
                          {bid.user?.username || bid.user?.name || 'User'}
                        </td>
                        <td className="px-4 py-3 text-right font-semibold">
                          {formatIDR(bid.amount)}
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td
                        colSpan="2"
                        className="px-4 py-6 text-center text-gray-500"
                      >
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

const DEPOSIT_STATUS_LABELS = {
  pending: 'Menunggu pembayaran',
  paid: 'Aktif',
  applied: 'Dipakai sebagai uang muka',
  refund_requested: 'Pengembalian diproses admin',
  refunded: 'Sudah dikembalikan',
  forfeited: 'Hangus',
  expired: 'Kedaluwarsa',
};

/**
 * Kotak status deposit peserta.
 *
 * Hanya muncul pada lelang yang memungut jaminan. Tugasnya menjawab satu
 * pertanyaan: apakah pembaca halaman ini sudah berhak menawar, dan kalau belum,
 * apa yang harus ia lakukan.
 */
function DepositPanel({ auction, deposit, depositActive, canBid }) {
  if (depositActive) {
    return (
      <div className="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
        <p className="font-semibold">
          Deposit aktif — {formatIDR(deposit.amount)}
        </p>
        <p className="mt-1 text-xs">
          {deposit.status === 'applied'
            ? 'Deposit ini dipakai sebagai uang muka pembayaran lelang Anda.'
            : 'Anda berhak menawar. Bila kalah, deposit dapat diajukan kembali lewat halaman Deposit Lelang.'}
        </p>
        <Link
          href="/deposit-lelang"
          className="mt-2 inline-block text-xs font-semibold underline"
        >
          Lihat deposit saya
        </Link>
      </div>
    );
  }

  return (
    <div className="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
      <p className="font-semibold">
        Lelang ini mewajibkan deposit {formatIDR(auction.deposit_amount)}
      </p>
      <p className="mt-1 text-xs">
        Sepuluh persen dari harga awal, dibayar sekali sebelum menawar. Bila
        Anda menang, deposit menjadi uang muka; bila kalah, deposit dapat
        diajukan kembali ke admin. Deposit hangus hanya bila Anda menang tetapi
        tidak membayar sampai tenggat.
      </p>

      {deposit && deposit.status !== 'pending' && (
        <p className="mt-2 text-xs font-semibold">
          Status deposit Anda:{' '}
          {DEPOSIT_STATUS_LABELS[deposit.status] ?? deposit.status}
        </p>
      )}

      {canBid && (
        <Form method="POST" action={`/auctions/${auction.public_id}/deposit`}>
          {({ processing }) => (
            <button
              type="submit"
              disabled={processing}
              className="mt-3 w-full rounded-lg bg-[#B77C4C] px-4 py-2 font-semibold text-white transition hover:bg-[#8d5e39] disabled:opacity-50"
            >
              {deposit?.status === 'pending'
                ? 'Lanjutkan Pembayaran Deposit'
                : 'Bayar Deposit'}
            </button>
          )}
        </Form>
      )}
    </div>
  );
}

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
