import { router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR, getProductImage } from '@/lib/utils';

const STATUS_LABEL = {
  pending: { text: 'Menunggu', className: 'bg-yellow-100 text-yellow-800' },
  accepted: { text: 'Disetujui', className: 'bg-green-100 text-green-800' },
  shipping: { text: 'Saling Kirim', className: 'bg-blue-100 text-blue-800' },
  completed: { text: 'Selesai', className: 'bg-emerald-100 text-emerald-800' },
  rejected: { text: 'Ditolak', className: 'bg-red-100 text-red-700' },
  cancelled: { text: 'Dibatalkan', className: 'bg-gray-200 text-gray-600' },
};

/**
 * Panel pengiriman dua arah.
 *
 * Kepemilikan produk baru berpindah setelah KEDUA seller saling mengirim
 * barang dan saling mengonfirmasi penerimaan (temuan T-08). `role` menentukan
 * kolom mana yang menjadi "saya": requester atau responder.
 */
/**
 * Sisa waktu menuju tenggat pengiriman, diperbarui tiap menit.
 *
 * Ditampilkan ke KEDUA pihak, tapi nadanya berbeda: yang belum mengirim
 * didesak, yang sudah mengirim diberi tahu kapan ia boleh melapor.
 */
function ShippingCountdown({ deadlineAt, iShipped }) {
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), 60_000);
    return () => clearInterval(timer);
  }, []);

  if (!deadlineAt) {
    return null;
  }

  const deadline = new Date(deadlineAt).getTime();
  const remaining = deadline - now;
  const overdue = remaining <= 0;

  const totalMinutes = Math.floor(Math.abs(remaining) / 60_000);
  const days = Math.floor(totalMinutes / (60 * 24));
  const hours = Math.floor((totalMinutes % (60 * 24)) / 60);
  const minutes = totalMinutes % 60;

  const parts = [];
  if (days > 0) parts.push(`${days} hari`);
  if (hours > 0) parts.push(`${hours} jam`);
  if (days === 0 && hours === 0) parts.push(`${minutes} menit`);

  const urgent = !overdue && remaining < 24 * 60 * 60 * 1000;

  const tone = overdue
    ? 'border-red-300 bg-red-50 text-red-800'
    : urgent
      ? 'border-amber-300 bg-amber-50 text-amber-900'
      : 'border-gray-200 bg-white text-gray-700';

  const label = overdue
    ? `Tenggat pengiriman lewat ${parts.join(' ')} yang lalu`
    : `Sisa waktu kirim: ${parts.join(' ')}`;

  const hint = overdue
    ? iShipped
      ? 'Anda dapat melaporkan pihak lawan ke admin.'
      : 'Segera isi resi sebelum dilaporkan ke admin.'
    : iShipped
      ? 'Jika pihak lawan tidak mengirim sampai tenggat, Anda bisa melapor ke admin.'
      : 'Isi nomor resi sebelum tenggat agar barter tidak dibatalkan.';

  return (
    <div className={`mb-3 rounded-md border px-3 py-2 text-xs ${tone}`}>
      <p className="font-semibold">
        {overdue ? '⚠️ ' : '⏳ '}
        {label}
      </p>
      <p className="mt-0.5 opacity-80">{hint}</p>
      <p className="mt-0.5 opacity-60">
        Batas: {new Date(deadlineAt).toLocaleString('id-ID')}
      </p>
    </div>
  );
}

function ShipmentPanel({ req, role }) {
  const counterpart = role === 'requester' ? 'responder' : 'requester';

  const myTracking = req[`${role}_tracking_number`];
  const myShippedAt = req[`${role}_shipped_at`];
  const iReceived = req[`${role}_received_at`];

  const theirShippedAt = req[`${counterpart}_shipped_at`];
  const theirTracking = req[`${counterpart}_tracking_number`];
  const theyReceived = req[`${counterpart}_received_at`];

  const [tracking, setTracking] = useState('');

  const submitTracking = (e) => {
    e.preventDefault();
    if (!tracking.trim()) return;

    router.post(
      `/seller/barter/${req.public_id}/ship`,
      { tracking_number: tracking.trim() },
      { preserveScroll: true, onSuccess: () => setTracking('') }
    );
  };

  const confirmReceipt = () => {
    if (!confirm('Konfirmasi bahwa barang dari pihak lain sudah Anda terima?'))
      return;

    router.post(
      `/seller/barter/${req.public_id}/receive`,
      {},
      { preserveScroll: true }
    );
  };

  return (
    <div className="w-full rounded-lg border border-blue-200 bg-blue-50/60 p-4">
      <p className="mb-3 text-sm font-semibold text-blue-900">
        Pengiriman barang — kepemilikan berpindah setelah kedua pihak saling
        mengonfirmasi penerimaan.
      </p>

      {/* Countdown hanya relevan selama masih ada yang belum mengisi resi. */}
      {(!myShippedAt || !theirShippedAt) && (
        <ShippingCountdown
          deadlineAt={req.shipping_deadline_at}
          iShipped={Boolean(myShippedAt)}
        />
      )}

      <div className="grid gap-4 md:grid-cols-2">
        {/* Sisi saya: mengirim */}
        <div className="rounded-md bg-white p-3">
          <p className="mb-2 text-xs font-semibold text-gray-500">
            Pengiriman Anda
          </p>
          {myShippedAt ? (
            <p className="text-sm text-gray-800">
              Resi:{' '}
              <span className="font-mono font-semibold">{myTracking}</span>
              <br />
              <span className="text-xs text-gray-500">
                {theyReceived
                  ? 'Sudah diterima pihak lain ✅'
                  : 'Menunggu konfirmasi pihak lain'}
              </span>
            </p>
          ) : (
            <form onSubmit={submitTracking} className="flex flex-col gap-2">
              <input
                type="text"
                value={tracking}
                onChange={(e) => setTracking(e.target.value)}
                placeholder="Nomor resi pengiriman"
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-[#53685B] focus:outline-none"
              />
              <button
                type="submit"
                className="rounded-md bg-[#53685B] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#3f5047]"
              >
                Kirim &amp; Simpan Resi
              </button>
            </form>
          )}
        </div>

        {/* Sisi lawan: menerima */}
        <div className="rounded-md bg-white p-3">
          <p className="mb-2 text-xs font-semibold text-gray-500">
            Kiriman dari pihak lain
          </p>
          {theirShippedAt ? (
            <>
              <p className="mb-2 text-sm text-gray-800">
                Resi:{' '}
                <span className="font-mono font-semibold">{theirTracking}</span>
              </p>
              {iReceived ? (
                <p className="text-xs font-semibold text-emerald-700">
                  Anda sudah mengonfirmasi penerimaan ✅
                </p>
              ) : (
                <button
                  onClick={confirmReceipt}
                  className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                >
                  Barang Diterima
                </button>
              )}
            </>
          ) : (
            <p className="text-sm text-gray-500">
              Pihak lain belum mengirimkan barangnya.
            </p>
          )}
        </div>
      </div>
    </div>
  );
}

function StatusBadge({ status }) {
  const conf = STATUS_LABEL[status] ?? STATUS_LABEL.pending;
  return (
    <span
      className={`rounded-full px-3 py-1 text-xs font-semibold ${conf.className}`}
    >
      {conf.text}
    </span>
  );
}

function PaymentStatusBadge({ status }) {
  const labels = {
    not_required: {
      text: 'Tanpa Bayar',
      className: 'bg-gray-100 text-gray-600',
    },
    pending: {
      text: 'Menunggu Bayar',
      className: 'bg-orange-100 text-orange-700',
    },
    paid: { text: 'Sudah Bayar', className: 'bg-green-100 text-green-700' },
    failed: { text: 'Bayar Gagal', className: 'bg-red-100 text-red-700' },
    expired: {
      text: 'Bayar Kadaluarsa',
      className: 'bg-gray-100 text-gray-600',
    },
  };
  const conf = labels[status] ?? labels.pending;
  return (
    <span
      className={`rounded-full px-3 py-1 text-xs font-semibold ${conf.className}`}
    >
      {conf.text}
    </span>
  );
}

export default function SellerBarterPage() {
  const {
    availableProducts = [],
    myProducts = [],
    incomingRequests = [],
    outgoingRequests = [],
    bankPrefill = {},
  } = usePage().props;

  const [tab, setTab] = useState('available');
  const [target, setTarget] = useState(null); // produk seller lain yang ingin dibarter

  const pendingIncoming = incomingRequests.filter(
    (r) => r.status === 'pending'
  );

  return (
    <div className="px-6 py-10 md:px-16">
      <h1 className="mb-2 text-3xl font-bold text-[#E9E19E]">Barter Produk</h1>
      <p className="mb-6 max-w-3xl text-sm text-white/80">
        Tukar produk Anda dengan produk seller lain.{' '}
      </p>

      {/* Tabs */}
      <div className="mb-6 flex flex-wrap gap-2">
        <TabButton
          active={tab === 'available'}
          onClick={() => setTab('available')}
        >
          Produk Tersedia ({availableProducts.length})
        </TabButton>
        <TabButton
          active={tab === 'incoming'}
          onClick={() => setTab('incoming')}
        >
          Permintaan Masuk ({pendingIncoming.length})
        </TabButton>
        <TabButton
          active={tab === 'outgoing'}
          onClick={() => setTab('outgoing')}
        >
          Permintaan Saya ({outgoingRequests.length})
        </TabButton>
      </div>

      {tab === 'available' && (
        <AvailableTab
          products={availableProducts}
          canOffer={myProducts.length > 0}
          onOffer={(product) => setTarget(product)}
        />
      )}

      {tab === 'incoming' && (
        <IncomingTab requests={incomingRequests} bankPrefill={bankPrefill} />
      )}

      {tab === 'outgoing' && <OutgoingTab requests={outgoingRequests} />}

      {target && (
        <OfferModal
          target={target}
          myProducts={myProducts}
          onClose={() => setTarget(null)}
        />
      )}
    </div>
  );
}

function TabButton({ active, onClick, children }) {
  return (
    <button
      onClick={onClick}
      className={`rounded-full px-5 py-2 text-sm font-semibold transition ${
        active
          ? 'bg-[#E9E19E] text-[#2F3E46]'
          : 'bg-white/10 text-white hover:bg-white/20'
      }`}
    >
      {children}
    </button>
  );
}

function AvailableTab({ products, canOffer, onOffer }) {
  if (products.length === 0) {
    return (
      <p className="rounded-lg bg-white/90 p-6 text-gray-600">
        Belum ada produk untuk dibarter saat ini.
      </p>
    );
  }

  return (
    <>
      {!canOffer && (
        <div className="mb-4 rounded-lg border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800">
          Anda belum punya produk yang bisa ditawarkan. Tandai salah satu produk
          Anda sebagai <span className="font-semibold">bisa dibarter</span> saat
          menambah/mengedit produk agar bisa mengajukan barter.
        </div>
      )}
      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
        {products.map((product) => (
          <div
            key={product.public_id}
            className="flex flex-col overflow-hidden rounded-lg bg-white shadow-md"
          >
            <img
              src={getProductImage(product)}
              alt={product.name}
              className="h-44 w-full object-cover"
            />
            <div className="flex grow flex-col p-4">
              <span className="mb-1 text-xs font-medium text-[#B77C4C]">
                {product.store?.store_name ?? 'Toko'}
              </span>
              <h3 className="mb-1 font-semibold text-[#3E2723]">
                {product.name}
              </h3>
              <p className="mb-2 line-clamp-2 text-xs text-gray-500">
                {product.description}
              </p>
              <div className="mt-auto">
                <p className="mb-3 font-bold text-[#B77C4C]">
                  {formatIDR(product.price)}
                </p>
                <button
                  disabled={!canOffer}
                  onClick={() => onOffer(product)}
                  className="w-full rounded-md bg-[#53685B] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#3c4a3e] disabled:cursor-not-allowed disabled:bg-gray-400"
                >
                  Ajukan Barter
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>
    </>
  );
}

function IncomingTab({ requests, bankPrefill }) {
  if (requests.length === 0) {
    return (
      <p className="rounded-lg bg-white/90 p-6 text-gray-600">
        Belum ada permintaan barter yang masuk.
      </p>
    );
  }

  const accept = (publicId) =>
    router.post(
      `/seller/barter/${publicId}/accept`,
      {},
      { preserveScroll: true }
    );
  const reject = (publicId) =>
    router.post(
      `/seller/barter/${publicId}/reject`,
      {},
      { preserveScroll: true }
    );

  return (
    <div className="flex flex-col gap-4">
      {requests.map((req) => (
        <RequestCard
          key={req.public_id}
          req={req}
          counterpartLabel="Dari"
          counterpartName={req.requester_store?.store_name}
          // produk yang mereka tawarkan ke saya
          theirProduct={req.offered_product}
          // produk saya yang mereka minta
          myProduct={req.requested_product}
          theirLabel="Mereka menawarkan"
          myLabel="Untuk produk Anda"
        >
          {req.status === 'pending' ? (
            <div className="flex gap-2">
              <button
                onClick={() => accept(req.public_id)}
                className="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700"
              >
                Setujui
              </button>
              <button
                onClick={() => reject(req.public_id)}
                className="rounded-md bg-red-500 px-4 py-2 text-sm font-semibold text-white hover:bg-red-600"
              >
                Tolak
              </button>
            </div>
          ) : req.status === 'shipping' ? (
            <div className="flex flex-col items-end gap-3">
              <ShipmentPanel req={req} role="responder" />
              <StalledNotice req={req} />
            </div>
          ) : req.can_request_payout ? (
            <BarterPayoutButton req={req} bankPrefill={bankPrefill} />
          ) : (
            <div className="text-right">
              <StatusBadge status={req.status} />
              {req.payout_block_reason && !req.latest_payout && (
                <p className="mt-2 text-xs text-gray-500 italic">
                  {req.payout_block_reason}
                </p>
              )}
            </div>
          )}
        </RequestCard>
      ))}
    </div>
  );
}

function OutgoingTab({ requests }) {
  if (requests.length === 0) {
    return (
      <p className="rounded-lg bg-white/90 p-6 text-gray-600">
        Anda belum mengajukan barter apa pun.
      </p>
    );
  }

  const cancel = (publicId) =>
    router.post(
      `/seller/barter/${publicId}/cancel`,
      {},
      { preserveScroll: true }
    );

  const pay = (publicId) => router.get(`/seller/barter/${publicId}/pay`);

  return (
    <div className="flex flex-col gap-4">
      {requests.map((req) => (
        <RequestCard
          key={req.public_id}
          counterpartLabel="Ke"
          counterpartName={req.responder_store?.store_name}
          // produk saya yang saya tawarkan
          theirProduct={req.offered_product}
          // produk seller lain yang saya minta
          myProduct={req.requested_product}
          theirLabel="Anda menawarkan"
          myLabel="Untuk produk mereka"
          req={req}
        >
          {req.status === 'pending' ? (
            <button
              onClick={() => cancel(req.public_id)}
              className="rounded-md bg-gray-500 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-600"
            >
              Batalkan
            </button>
          ) : req.status === 'accepted' && req.payment_status === 'pending' ? (
            <button
              onClick={() => pay(req.public_id)}
              className="animate-pulse rounded-md bg-green-600 px-6 py-3 text-sm font-bold text-white shadow-lg hover:bg-green-700"
            >
              💳 Bayar Sekarang
            </button>
          ) : req.status === 'shipping' ? (
            <div className="flex flex-col items-end gap-3">
              <ShipmentPanel req={req} role="requester" />
              <StalledNotice req={req} />
              {req.can_request_refund && !req.can_report_stalled && (
                <BarterRefundButton req={req} />
              )}
            </div>
          ) : req.can_request_refund ? (
            <BarterRefundButton req={req} />
          ) : (
            <StatusBadge status={req.status} />
          )}
        </RequestCard>
      ))}
    </div>
  );
}

const moneyStatusColors = {
  pending: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
};

/**
 * Responder mencairkan selisih uang barter setelah barter selesai.
 * Data bank di-prefill dari pengajuan terakhir, tapi tetap bisa diubah.
 */
function BarterPayoutButton({ req, bankPrefill }) {
  const [open, setOpen] = useState(false);

  const form = useForm({
    bank_name: bankPrefill?.bank_name || '',
    account_number: bankPrefill?.account_number || '',
    account_holder: bankPrefill?.account_holder || '',
  });

  const submit = (e) => {
    e.preventDefault();
    form.post(`/seller/barter/${req.public_id}/payout`, {
      preserveScroll: true,
      onSuccess: () => setOpen(false),
    });
  };

  return (
    <>
      <button
        onClick={() => setOpen(true)}
        className="rounded-md bg-[#53685B] px-4 py-2 text-sm font-semibold text-white hover:bg-[#3c4a3e]"
      >
        💰 Ajukan Pencairan
      </button>

      {open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4 backdrop-blur-sm">
          <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
            <h3 className="mb-1 text-xl font-bold text-gray-900">
              Ajukan Pencairan Selisih Barter
            </h3>
            <p className="mb-4 text-sm text-gray-600">Barter {req.public_id}</p>

            <div className="mb-5 rounded-lg bg-gray-50 p-4">
              <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Nominal</span>
                <span className="text-xl font-bold text-[#53685B]">
                  {formatIDR(req.additional_cash)}
                </span>
              </div>
              <p className="mt-2 text-xs text-gray-500">
                Selisih harga yang dibayar pengaju. Admin mentransfernya ke
                rekening di bawah setelah pengajuan disetujui.
              </p>
            </div>

            <form onSubmit={submit} className="space-y-4">
              <BankField
                label="Nama Bank"
                value={form.data.bank_name}
                onChange={(v) => form.setData('bank_name', v)}
                error={form.errors.bank_name}
              />
              <BankField
                label="Nomor Rekening"
                value={form.data.account_number}
                onChange={(v) => form.setData('account_number', v)}
                error={form.errors.account_number}
              />
              <BankField
                label="Nama Pemilik Rekening"
                value={form.data.account_holder}
                onChange={(v) => form.setData('account_holder', v)}
                error={form.errors.account_holder}
              />

              <div className="flex gap-3 pt-2">
                <button
                  type="submit"
                  disabled={form.processing}
                  className="flex-1 rounded-lg bg-[#53685B] px-4 py-3 font-semibold text-white hover:bg-[#3c4a3e] disabled:opacity-50"
                >
                  {form.processing ? 'Mengirim...' : 'Ajukan'}
                </button>
                <button
                  type="button"
                  onClick={() => setOpen(false)}
                  className="flex-1 rounded-lg bg-gray-200 px-4 py-3 font-semibold text-gray-700 hover:bg-gray-300"
                >
                  Batal
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </>
  );
}

/**
 * Requester meminta selisih uangnya kembali ketika barter tidak pernah tuntas.
 */
function BarterRefundButton({ req }) {
  const [open, setOpen] = useState(false);

  const form = useForm({ reason: '' });

  const submit = (e) => {
    e.preventDefault();
    form.post(`/seller/barter/${req.public_id}/refund`, {
      preserveScroll: true,
      onSuccess: () => setOpen(false),
    });
  };

  return (
    <>
      <button
        onClick={() => setOpen(true)}
        className="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"
      >
        ↩️ Minta Pengembalian Dana
      </button>

      {open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4 backdrop-blur-sm">
          <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
            <h3 className="mb-1 text-xl font-bold text-gray-900">
              Minta Pengembalian Dana
            </h3>
            <p className="mb-4 text-sm text-gray-600">Barter {req.public_id}</p>

            <div className="mb-5 rounded-lg bg-gray-50 p-4">
              <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Nominal</span>
                <span className="text-xl font-bold text-amber-700">
                  {formatIDR(req.additional_cash)}
                </span>
              </div>
              <p className="mt-2 text-xs text-gray-500">
                Admin akan memeriksa status pengiriman kedua pihak sebelum
                memutuskan. Jika disetujui, barter dibatalkan dan kedua produk
                dilepas dari kunci barter.
              </p>
            </div>

            <form onSubmit={submit} className="space-y-4">
              <div>
                <label className="mb-2 block text-sm font-semibold text-gray-700">
                  Alasan
                </label>
                <textarea
                  value={form.data.reason}
                  onChange={(e) => form.setData('reason', e.target.value)}
                  rows="4"
                  placeholder="Contoh: Pihak lawan tidak pernah mengirimkan barangnya sejak barter disetujui."
                  className="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-amber-500 focus:ring-2 focus:ring-amber-500"
                  required
                />
                {form.errors.reason && (
                  <p className="mt-1 text-sm text-red-600">
                    {form.errors.reason}
                  </p>
                )}
              </div>

              <div className="flex gap-3 pt-2">
                <button
                  type="submit"
                  disabled={form.processing}
                  className="flex-1 rounded-lg bg-amber-600 px-4 py-3 font-semibold text-white hover:bg-amber-700 disabled:opacity-50"
                >
                  {form.processing ? 'Mengirim...' : 'Ajukan'}
                </button>
                <button
                  type="button"
                  onClick={() => setOpen(false)}
                  className="flex-1 rounded-lg bg-gray-200 px-4 py-3 font-semibold text-gray-700 hover:bg-gray-300"
                >
                  Batal
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </>
  );
}

/**
 * Pihak yang sudah mengirim melaporkan lawannya yang tidak kunjung mengisi
 * resi setelah tenggat terlampaui. Admin yang memutuskan.
 */
function BarterReportButton({ req }) {
  const [open, setOpen] = useState(false);

  const form = useForm({ reason: '' });

  const submit = (e) => {
    e.preventDefault();
    form.post(`/seller/barter/${req.public_id}/report`, {
      preserveScroll: true,
      onSuccess: () => setOpen(false),
    });
  };

  return (
    <>
      <button
        onClick={() => setOpen(true)}
        className="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
      >
        ⚠️ Laporkan Tidak Mengirim
      </button>

      {open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4 backdrop-blur-sm">
          <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
            <h3 className="mb-1 text-xl font-bold text-gray-900">
              Laporkan Pihak Lawan
            </h3>
            <p className="mb-4 text-sm text-gray-600">Barter {req.public_id}</p>

            <div className="mb-5 rounded-lg bg-red-50 p-4">
              <p className="text-xs text-gray-700">
                Tenggat pengiriman sudah lewat dan pihak lawan belum mengisi
                nomor resi. Admin akan memeriksa catatan pengiriman kedua pihak.
                Bila laporan disetujui, barter dibatalkan, kunci kedua produk
                dilepas
                {Number(req.additional_cash) > 0
                  ? ', dan selisih uang dikembalikan ke pengaju.'
                  : '.'}
              </p>
            </div>

            <form onSubmit={submit} className="space-y-4">
              <div>
                <label className="mb-2 block text-sm font-semibold text-gray-700">
                  Keterangan
                </label>
                <textarea
                  value={form.data.reason}
                  onChange={(e) => form.setData('reason', e.target.value)}
                  rows="4"
                  placeholder="Contoh: Saya sudah mengirim dan mengisi resi sejak awal, tapi pihak lawan belum mengirimkan barangnya sampai sekarang."
                  className="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-red-500 focus:ring-2 focus:ring-red-500"
                  required
                />
                {form.errors.reason && (
                  <p className="mt-1 text-sm text-red-600">
                    {form.errors.reason}
                  </p>
                )}
              </div>

              <div className="flex gap-3 pt-2">
                <button
                  type="submit"
                  disabled={form.processing}
                  className="flex-1 rounded-lg bg-red-600 px-4 py-3 font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                >
                  {form.processing ? 'Mengirim...' : 'Kirim Laporan'}
                </button>
                <button
                  type="button"
                  onClick={() => setOpen(false)}
                  className="flex-1 rounded-lg bg-gray-200 px-4 py-3 font-semibold text-gray-700 hover:bg-gray-300"
                >
                  Batal
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </>
  );
}

function BankField({ label, value, onChange, error }) {
  return (
    <div>
      <label className="mb-2 block text-sm font-semibold text-gray-700">
        {label}
      </label>
      <input
        type="text"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-[#53685B] focus:ring-2 focus:ring-[#53685B]"
      />
      {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
    </div>
  );
}

/**
 * Tombol lapor bila tenggat kirim sudah lewat, atau keterangan kenapa belum
 * bisa melapor (tenggat belum lewat / justru kita yang belum kirim).
 */
function StalledNotice({ req }) {
  if (req.can_report_stalled) {
    return <BarterReportButton req={req} />;
  }

  if (!req.report_block_reason) {
    return null;
  }

  return (
    <p className="max-w-xs text-right text-xs text-gray-500 italic">
      {req.report_block_reason}
    </p>
  );
}

/**
 * Ringkasan status uang barter: pencairan ke responder atau pengembalian ke
 * requester, mana pun yang sedang berjalan.
 */
function MoneyStatusNote({ req }) {
  if (!req.latest_payout && !req.latest_refund) {
    return null;
  }

  return (
    <div className="mt-3 flex flex-wrap gap-2">
      {req.latest_payout && (
        <span
          className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${moneyStatusColors[req.latest_payout.status] || 'bg-gray-100 text-gray-700'}`}
        >
          Pencairan: {req.latest_payout.status}
        </span>
      )}
      {req.latest_refund && (
        <span
          className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${moneyStatusColors[req.latest_refund.status] || 'bg-gray-100 text-gray-700'}`}
        >
          Pengembalian dana: {req.latest_refund.status}
        </span>
      )}
    </div>
  );
}

function RequestCard({
  req,
  counterpartLabel,
  counterpartName,
  theirProduct,
  myProduct,
  theirLabel,
  myLabel,
  children,
}) {
  const additionalCash = Number(req.additional_cash) || 0;
  const paymentStatus = req.payment_status;

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-5 shadow-md">
      <div className="mb-3 flex items-center justify-between">
        <span className="text-sm text-gray-500">
          {counterpartLabel}:{' '}
          <span className="font-semibold text-[#3E2723]">
            {counterpartName ?? 'Toko'}
          </span>
        </span>
        <div className="flex gap-2">
          <StatusBadge status={req.status} />
          {additionalCash > 0 &&
            paymentStatus &&
            paymentStatus !== 'not_required' && (
              <PaymentStatusBadge status={paymentStatus} />
            )}
        </div>
      </div>

      <div className="grid grid-cols-1 items-center gap-4 md:grid-cols-[1fr_auto_1fr]">
        <MiniProduct label={theirLabel} product={theirProduct} />
        <div className="text-center text-2xl text-[#B77C4C]">⇄</div>
        <MiniProduct label={myLabel} product={myProduct} />
      </div>

      {additionalCash > 0 && (
        <div
          className={`mt-4 rounded-lg border p-3 ${
            paymentStatus === 'paid'
              ? 'border-green-300 bg-green-50'
              : paymentStatus === 'pending'
                ? 'border-yellow-300 bg-yellow-50'
                : paymentStatus === 'failed' || paymentStatus === 'expired'
                  ? 'border-red-300 bg-red-50'
                  : 'border-yellow-300 bg-yellow-50'
          }`}
        >
          <div className="flex items-center gap-2">
            <span className="text-xl">
              {paymentStatus === 'paid' ? '✅' : '💰'}
            </span>
            <div>
              <p className="text-sm font-semibold text-gray-900">
                Pembayaran Tambahan: {formatIDR(additionalCash)}
              </p>
              <p className="text-xs text-gray-600">
                {paymentStatus === 'paid'
                  ? '✅ Pembayaran telah selesai'
                  : paymentStatus === 'pending'
                    ? counterpartLabel === 'Dari'
                      ? 'Seller pengaju harus membayar tambahan ini'
                      : '⚠️ Anda harus membayar tambahan ini untuk menyelesaikan barter'
                    : counterpartLabel === 'Dari'
                      ? 'Seller pengaju harus membayar tambahan ini jika Anda setujui'
                      : 'Anda harus membayar tambahan ini jika barter disetujui'}
              </p>
            </div>
          </div>
        </div>
      )}

      {req.note && (
        <div className="mt-3 rounded-lg bg-gray-50 p-3">
          <p className="mb-1 text-xs font-semibold text-gray-500">
            📝 Catatan:
          </p>
          <p className="text-sm text-gray-700">{req.note}</p>
        </div>
      )}

      <MoneyStatusNote req={req} />

      <div className="mt-4 flex justify-end">{children}</div>
    </div>
  );
}

function MiniProduct({ label, product }) {
  return (
    <div className="flex items-center gap-3">
      <img
        src={getProductImage(product ?? {})}
        alt={product?.name}
        className="h-16 w-16 rounded-md object-cover"
      />
      <div>
        <p className="text-xs text-gray-400">{label}</p>
        <p className="text-sm font-semibold text-[#3E2723]">
          {product?.name ?? 'Produk dihapus'}
        </p>
        {product?.price != null && (
          <p className="text-xs text-[#B77C4C]">{formatIDR(product.price)}</p>
        )}
      </div>
    </div>
  );
}

function OfferModal({ target, myProducts, onClose }) {
  const [offeredId, setOfferedId] = useState(myProducts[0]?.public_id ?? '');
  const [note, setNote] = useState('');
  const [processing, setProcessing] = useState(false);

  const offered = myProducts.find((p) => p.public_id === offeredId);
  const priceDiff =
    offered != null ? Number(target.price) - Number(offered.price) : 0;
  const additionalCash = Math.max(0, priceDiff);

  const submit = (e) => {
    e.preventDefault();

    // Konfirmasi jika ada pembayaran tambahan
    if (additionalCash > 0) {
      const confirmMsg = `Anda akan menambah pembayaran sebesar ${formatIDR(additionalCash)} karena produk yang Anda tawarkan lebih murah. Lanjutkan?`;
      if (!confirm(confirmMsg)) {
        return;
      }
    }

    setProcessing(true);
    router.post(
      `/seller/barter/${target.public_id}`,
      {
        offered_product_id: offeredId,
        note,
      },
      {
        preserveScroll: true,
        onFinish: () => setProcessing(false),
        onSuccess: () => onClose(),
      }
    );
  };

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/30 p-4 backdrop-blur-sm">
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-2xl">
        <div className="mb-4 flex items-start justify-between">
          <h2 className="text-xl font-bold text-[#3E2723]">Ajukan Barter</h2>
          <button
            onClick={onClose}
            className="text-2xl leading-none text-gray-400 hover:text-gray-700"
          >
            ×
          </button>
        </div>

        <div className="mb-4 flex items-center gap-3 rounded-lg bg-gray-50 p-3">
          <img
            src={getProductImage(target)}
            alt={target.name}
            className="h-16 w-16 rounded-md object-cover"
          />
          <div>
            <p className="text-xs text-gray-400">Produk yang diinginkan</p>
            <p className="font-semibold text-[#3E2723]">{target.name}</p>
            <p className="text-sm text-[#B77C4C]">{formatIDR(target.price)}</p>
          </div>
        </div>

        <form onSubmit={submit} className="flex flex-col gap-4">
          <div>
            <label className="mb-1 block text-sm font-semibold text-[#2F3E46]">
              Produk Anda yang ditawarkan
            </label>
            <select
              value={offeredId}
              onChange={(e) => setOfferedId(e.target.value)}
              required
              className="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-[#53685B] focus:outline-none"
            >
              {myProducts.map((p) => (
                <option key={p.public_id} value={p.public_id}>
                  {p.name} — {formatIDR(p.price)}
                </option>
              ))}
            </select>
          </div>

          {offered && priceDiff !== 0 && (
            <div
              className={`rounded-lg border p-4 ${
                priceDiff > 0
                  ? 'border-yellow-300 bg-yellow-50'
                  : 'border-green-300 bg-green-50'
              }`}
            >
              <div className="mb-2 flex items-center gap-2">
                <span className="text-2xl">{priceDiff > 0 ? '💰' : '✅'}</span>
                <span className="font-semibold text-gray-900">
                  {priceDiff > 0
                    ? 'Pembayaran Tambahan Diperlukan'
                    : 'Produk Anda Lebih Mahal'}
                </span>
              </div>
              <p className="mb-2 text-sm text-gray-700">
                Selisih harga:{' '}
                <span className="text-lg font-bold">
                  {formatIDR(Math.abs(priceDiff))}
                </span>
              </p>
              {priceDiff > 0 ? (
                <p className="text-xs text-gray-600">
                  ⚠️ Produk Anda lebih murah. Anda harus membayar tambahan
                  sebesar{' '}
                  <span className="font-semibold">
                    {formatIDR(additionalCash)}
                  </span>{' '}
                  jika barter disetujui.
                </p>
              ) : (
                <p className="text-xs text-gray-600">
                  ✨ Produk Anda lebih mahal. Tidak ada pembayaran tambahan
                  diperlukan.
                </p>
              )}
            </div>
          )}

          <div>
            <label className="mb-1 block text-sm font-semibold text-[#2F3E46]">
              Catatan (opsional)
            </label>
            <textarea
              rows="3"
              value={note}
              onChange={(e) => setNote(e.target.value)}
              maxLength={1000}
              className="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-[#53685B] focus:outline-none"
              placeholder="Sampaikan pesan untuk seller..."
            />
          </div>

          <div className="flex justify-end gap-3">
            <button
              type="button"
              onClick={onClose}
              className="rounded-lg bg-gray-200 px-5 py-2 font-semibold text-gray-700 hover:bg-gray-300"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={processing || !offeredId}
              className="rounded-lg bg-[#53685B] px-5 py-2 font-semibold text-white hover:bg-[#3c4a3e] disabled:cursor-not-allowed disabled:opacity-60"
            >
              {processing ? 'Mengirim...' : 'Kirim Pengajuan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

SellerBarterPage.layout = (page) => (
  <MainLayout title="Barter Produk">{page}</MainLayout>
);
