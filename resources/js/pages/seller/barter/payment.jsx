import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR, getProductImage } from '@/lib/utils';
import { openSnapPayment } from '@/lib/midtrans';
import { BarterIcon, MoneyIcon, SuccessIcon } from '@/components/icons';
import { toast } from 'sonner';

export default function BarterPaymentPage() {
  const { barter, snapToken, viewerRole } = usePage().props;
  // Pembayar selisih bisa pengaju maupun penerima, jadi label "produk Anda"
  // tidak lagi selalu berarti produk yang ditawarkan.
  const isRequester = viewerRole !== 'responder';
  const myProduct = isRequester
    ? barter.offered_product
    : barter.requested_product;
  const theirProduct = isRequester
    ? barter.requested_product
    : barter.offered_product;
  const myStore = isRequester ? barter.requester_store : barter.responder_store;
  const theirStore = isRequester
    ? barter.responder_store
    : barter.requester_store;
  const [processing, setProcessing] = useState(false);
  const [paymentCompleted, setPaymentCompleted] = useState(false);
  const [pollingStatus, setPollingStatus] = useState(false);

  useEffect(() => {
    if (!snapToken) {
      return;
    }

    // Auto-trigger Snap popup setelah halaman load. openSnapPayment menunggu
    // library Snap siap, jadi tidak perlu lagi menebak lewat setTimeout.
    const timer = setTimeout(() => {
      handlePay();
    }, 500);

    return () => clearTimeout(timer);
  }, [snapToken]);

  // Polling status payment jika webhook lambat
  useEffect(() => {
    if (!pollingStatus) return;

    const interval = setInterval(async () => {
      try {
        const response = await fetch(
          `/seller/barter/${barter.public_id}/payment-status`
        );
        const data = await response.json();

        if (data.is_paid) {
          setPollingStatus(false);
          setProcessing(false);
          toast.success(
            'Pembayaran berhasil! Silakan saling mengirim barang dan isi nomor resinya.'
          );
          router.visit('/seller/barter');
        }
      } catch (error) {
        console.error('Error checking payment status:', error);
      }
    }, 3000); // Check setiap 3 detik

    // Stop polling setelah 30 detik
    const timeout = setTimeout(() => {
      setPollingStatus(false);
      setProcessing(false);
      toast.info(
        'Pembayaran sedang diproses. Silakan cek halaman barter dalam beberapa menit.'
      );
      router.visit('/seller/barter');
    }, 30000);

    return () => {
      clearInterval(interval);
      clearTimeout(timeout);
    };
  }, [pollingStatus, barter.public_id]);

  const handlePay = () => {
    if (!snapToken) {
      toast.error('Sistem pembayaran belum siap. Silakan refresh halaman.');
      return;
    }

    setProcessing(true);

    openSnapPayment(snapToken, {
      onSuccess: () => {
        setPaymentCompleted(true);
        setPollingStatus(true); // Mulai polling status
      },
      onPending: () => {
        setProcessing(false);
        toast.info(
          'Pembayaran tertunda. Silakan selesaikan pembayaran Anda terlebih dahulu.'
        );
      },
      onError: () => {
        setProcessing(false);
        toast.error('Pembayaran gagal. Silakan coba lagi.');
      },
      onClose: () => {
        if (!paymentCompleted) {
          setProcessing(false);
        }
      },
    }).catch((error) => {
      setProcessing(false);
      toast.error(error.message);
    });
  };

  return (
    <div className="px-4 py-8 sm:px-6 sm:py-10 md:px-16">
      <div className="mx-auto max-w-3xl">
        <h1 className="mb-2 text-3xl font-bold text-[#E9E19E]">
          Pembayaran Selisih Barter
        </h1>
        <p className="mb-8 text-sm text-white/80">
          Silakan selesaikan pembayaran untuk menyelesaikan proses barter.
        </p>

        <div className="rounded-xl bg-white p-6 shadow-lg">
          {/* Barter Info */}
          <div className="mb-6">
            <div className="mb-4 flex items-center justify-between border-b pb-3">
              <h2 className="text-lg font-bold text-[#3E2723]">
                Detail Barter #{barter.public_id}
              </h2>
              <span className="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                Disetujui
              </span>
            </div>

            {/* Products */}
            <div className="grid grid-cols-1 items-center gap-4 md:grid-cols-[1fr_auto_1fr]">
              <ProductCard
                product={myProduct}
                label="Produk Anda"
                store={myStore}
              />
              <div className="flex justify-center text-[#B77C4C]">
                <BarterIcon className="h-9 w-9" />
              </div>
              <ProductCard
                product={theirProduct}
                label="Produk Mereka"
                store={theirStore}
              />
            </div>
          </div>

          {/* Payment Info */}
          <div className="rounded-lg border-2 border-yellow-300 bg-yellow-50 p-5">
            <div className="mb-3 flex items-center gap-2">
              <MoneyIcon className="h-8 w-8 text-[#B77C4C]" />
              <h3 className="text-lg font-bold text-gray-900">
                Pembayaran Tambahan Diperlukan
              </h3>
            </div>

            <div className="mb-4 space-y-2 text-sm text-gray-700">
              <div className="flex justify-between">
                <span>Harga Produk Mereka:</span>
                <span className="font-semibold">
                  {formatIDR(theirProduct?.price)}
                </span>
              </div>
              <div className="flex justify-between">
                <span>Harga Produk Anda:</span>
                <span className="font-semibold">
                  {formatIDR(myProduct?.price)}
                </span>
              </div>
              <div className="flex justify-between border-t pt-2 text-base font-bold text-[#B77C4C]">
                <span>Selisih (Tambahan):</span>
                <span>{formatIDR(barter.additional_cash)}</span>
              </div>
            </div>

            <p className="mb-4 text-xs text-gray-600">
              Karena produk Anda lebih murah, Anda perlu membayar selisih harga
              untuk melanjutkan barter. Setelah pembayaran berhasil, kedua
              seller saling mengirim barang dan mengisi nomor resi. Kepemilikan
              baru berpindah setelah keduanya mengonfirmasi barang diterima.
            </p>

            {paymentCompleted ? (
              <div className="rounded-lg bg-green-100 p-4 text-center">
                <SuccessIcon className="mx-auto mb-2 h-9 w-9 animate-bounce text-green-600" />
                <p className="font-bold text-green-800">Pembayaran Berhasil!</p>
                <p className="mb-2 text-sm text-green-700">
                  Sedang menyiapkan tahap pengiriman...
                </p>
                {pollingStatus && (
                  <div className="flex items-center justify-center gap-2 text-xs text-green-600">
                    <div className="h-2 w-2 animate-pulse rounded-full bg-green-600"></div>
                    Mengecek status pembayaran...
                  </div>
                )}
              </div>
            ) : (
              <button
                onClick={handlePay}
                disabled={processing}
                className="w-full rounded-lg bg-green-600 px-6 py-3 font-bold text-white shadow-lg transition hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {processing
                  ? '⏳ Memproses...'
                  : `Bayar ${formatIDR(barter.additional_cash)}`}
              </button>
            )}

            <p className="mt-3 text-center text-xs text-gray-500">
              Pembayaran aman melalui Midtrans
            </p>
          </div>
        </div>

        {/* Back Button */}
        {!processing && !paymentCompleted && (
          <div className="mt-6 text-center">
            <a
              href="/seller/barter"
              className="inline-block rounded-lg bg-white/10 px-6 py-2 font-semibold text-white hover:bg-white/20"
            >
              ← Kembali ke Barter
            </a>
          </div>
        )}
      </div>
    </div>
  );
}

function ProductCard({ product, label, store }) {
  return (
    <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
      <p className="mb-2 text-xs font-semibold text-gray-500">{label}</p>
      <div className="flex items-center gap-3">
        <img
          src={getProductImage(product ?? {})}
          alt={product?.name}
          className="h-20 w-20 rounded-md object-cover"
        />
        <div className="flex-1">
          <p className="text-xs text-gray-400">{store?.store_name ?? 'Toko'}</p>
          <p className="font-semibold text-[#3E2723]">
            {product?.name ?? 'Produk'}
          </p>
          <p className="text-sm font-bold text-[#B77C4C]">
            {formatIDR(product?.price)}
          </p>
        </div>
      </div>
    </div>
  );
}

BarterPaymentPage.layout = (page) => (
  <MainLayout title="Pembayaran Barter">{page}</MainLayout>
);
