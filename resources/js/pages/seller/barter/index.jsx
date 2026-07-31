import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR, getProductImage } from '@/lib/utils';

const STATUS_LABEL = {
  pending: { text: 'Menunggu', className: 'bg-yellow-100 text-yellow-800' },
  accepted: { text: 'Disetujui', className: 'bg-green-100 text-green-800' },
  rejected: { text: 'Ditolak', className: 'bg-red-100 text-red-700' },
  cancelled: { text: 'Dibatalkan', className: 'bg-gray-200 text-gray-600' },
};

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
    not_required: { text: 'Tanpa Bayar', className: 'bg-gray-100 text-gray-600' },
    pending: { text: 'Menunggu Bayar', className: 'bg-orange-100 text-orange-700' },
    paid: { text: 'Sudah Bayar', className: 'bg-green-100 text-green-700' },
    failed: { text: 'Bayar Gagal', className: 'bg-red-100 text-red-700' },
    expired: { text: 'Bayar Kadaluarsa', className: 'bg-gray-100 text-gray-600' },
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
  } = usePage().props;

  const [tab, setTab] = useState('available');
  const [target, setTarget] = useState(null); // produk seller lain yang ingin dibarter

  const pendingIncoming = incomingRequests.filter((r) => r.status === 'pending');

  return (
    <div className="px-6 py-10 md:px-16">
      <h1 className="mb-2 text-3xl font-bold text-[#E9E19E]">Barter Produk</h1>
      <p className="mb-6 max-w-3xl text-sm text-white/80">
        Tukar produk Anda dengan produk seller lain. {' '}
      </p>

      {/* Tabs */}
      <div className="mb-6 flex flex-wrap gap-2">
        <TabButton active={tab === 'available'} onClick={() => setTab('available')}>
          Produk Tersedia ({availableProducts.length})
        </TabButton>
        <TabButton active={tab === 'incoming'} onClick={() => setTab('incoming')}>
          Permintaan Masuk ({pendingIncoming.length})
        </TabButton>
        <TabButton active={tab === 'outgoing'} onClick={() => setTab('outgoing')}>
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

      {tab === 'incoming' && <IncomingTab requests={incomingRequests} />}

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

function IncomingTab({ requests }) {
  if (requests.length === 0) {
    return (
      <p className="rounded-lg bg-white/90 p-6 text-gray-600">
        Belum ada permintaan barter yang masuk.
      </p>
    );
  }

  const accept = (publicId) =>
    router.post(`/seller/barter/${publicId}/accept`, {}, { preserveScroll: true });
  const reject = (publicId) =>
    router.post(`/seller/barter/${publicId}/reject`, {}, { preserveScroll: true });

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
          ) : (
            <StatusBadge status={req.status} />
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
    router.post(`/seller/barter/${publicId}/cancel`, {}, { preserveScroll: true });

  const pay = (publicId) =>
    router.get(`/seller/barter/${publicId}/pay`);

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
              className="rounded-md bg-green-600 px-6 py-3 text-sm font-bold text-white hover:bg-green-700 shadow-lg animate-pulse"
            >
              💳 Bayar Sekarang
            </button>
          ) : (
            <StatusBadge status={req.status} />
          )}
        </RequestCard>
      ))}
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
    <div className="rounded-lg bg-white p-5 shadow-md border border-gray-200">
      <div className="mb-3 flex items-center justify-between">
        <span className="text-sm text-gray-500">
          {counterpartLabel}:{' '}
          <span className="font-semibold text-[#3E2723]">
            {counterpartName ?? 'Toko'}
          </span>
        </span>
        <div className="flex gap-2">
          <StatusBadge status={req.status} />
          {additionalCash > 0 && paymentStatus && paymentStatus !== 'not_required' && (
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
        <div className={`mt-4 rounded-lg border p-3 ${
          paymentStatus === 'paid' 
            ? 'border-green-300 bg-green-50'
            : paymentStatus === 'pending' 
            ? 'border-yellow-300 bg-yellow-50'
            : paymentStatus === 'failed' || paymentStatus === 'expired'
            ? 'border-red-300 bg-red-50'
            : 'border-yellow-300 bg-yellow-50'
        }`}>
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
                  : 'Anda harus membayar tambahan ini jika barter disetujui'
                }
              </p>
            </div>
          </div>
        </div>
      )}
      
      {req.note && (
        <div className="mt-3 rounded-lg bg-gray-50 p-3">
          <p className="text-xs font-semibold text-gray-500 mb-1">📝 Catatan:</p>
          <p className="text-sm text-gray-700">{req.note}</p>
        </div>
      )}

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
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/30 backdrop-blur-sm p-4">
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
            <div className={`rounded-lg border p-4 ${
              priceDiff > 0 
                ? 'border-yellow-300 bg-yellow-50' 
                : 'border-green-300 bg-green-50'
            }`}>
              <div className="flex items-center gap-2 mb-2">
                <span className="text-2xl">{priceDiff > 0 ? '💰' : '✅'}</span>
                <span className="font-semibold text-gray-900">
                  {priceDiff > 0 ? 'Pembayaran Tambahan Diperlukan' : 'Produk Anda Lebih Mahal'}
                </span>
              </div>
              <p className="text-sm text-gray-700 mb-2">
                Selisih harga:{' '}
                <span className="font-bold text-lg">
                  {formatIDR(Math.abs(priceDiff))}
                </span>
              </p>
              {priceDiff > 0 ? (
                <p className="text-xs text-gray-600">
                  ⚠️ Produk Anda lebih murah. Anda harus membayar tambahan sebesar{' '}
                  <span className="font-semibold">{formatIDR(additionalCash)}</span>{' '}
                  jika barter disetujui.
                </p>
              ) : (
                <p className="text-xs text-gray-600">
                  ✨ Produk Anda lebih mahal. Tidak ada pembayaran tambahan diperlukan.
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
