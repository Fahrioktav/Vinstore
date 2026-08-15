<?php

namespace App\Http\Controllers;

use App\Models\AuctionDeposit;
use App\Models\Order;
use App\Models\PlatformRevenue;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, MidtransService $midtrans)
    {
        $payload = $request->all();

        if (! $midtrans->isValidSignature($payload)) {
            return response()->json(['message' => 'Invalid Midtrans signature.'], 403);
        }

        $paymentReference = $payload['order_id'] ?? null;

        $paymentStatus = $midtrans->mapPaymentStatus(
            $payload['transaction_status'] ?? null,
            $payload['fraud_status'] ?? null
        );

        $isFailure = in_array($paymentStatus, ['cancelled', 'denied', 'expired'], true);

        $orders = Order::where('payment_reference', $paymentReference)->get();

        if ($orders->isEmpty()) {
            // Referensi yang tidak cocok dengan pesanan mana pun masih mungkin
            // milik deposit lelang. Depositnya memakai endpoint webhook yang
            // sama supaya hanya ada satu Payment Notification URL yang perlu
            // didaftarkan di dashboard Midtrans.
            return $this->handleDepositNotification($paymentReference, $paymentStatus, $isFailure, $payload);
        }

        foreach ($orders as $order) {
            $updates = [
                'payment_method' => $payload['payment_type'] ?? $order->payment_method,
                'midtrans_transaction_id' => $payload['transaction_id'] ?? $order->midtrans_transaction_id,
            ];

            // Notifikasi Midtrans bisa datang tidak berurutan atau membawa
            // transaction_status yang tidak dikenal. Pesanan yang sudah lunas
            // tidak boleh dikembalikan ke 'pending' oleh notifikasi semacam itu.
            $applyStatus = $order->canApplyPaymentStatus($paymentStatus);

            // Pesanan yang sudah gugur tidak boleh dihidupkan lagi oleh uang
            // yang datang terlambat (temuan V6-01). Uangnya sendiri tetap
            // diterima Midtrans, jadi kejadiannya dicatat agar admin tahu ada
            // dana yang harus dikembalikan secara manual — diam-diam menolaknya
            // justru membuat uang pembeli hilang tanpa jejak.
            if (! $applyStatus && $paymentStatus === 'paid' && $order->isPaymentDead()) {
                Log::warning('Pembayaran diterima atas pesanan yang sudah gugur; dana perlu dikembalikan manual.', [
                    'order_public_id' => $order->public_id,
                    'payment_reference' => $order->payment_reference,
                    'payment_status' => $order->payment_status,
                    'midtrans_transaction_id' => $payload['transaction_id'] ?? null,
                    'gross_amount' => $payload['gross_amount'] ?? null,
                ]);
            }

            if ($applyStatus) {
                $updates['payment_status'] = $paymentStatus;

                if ($paymentStatus === 'paid') {
                    $updates['paid_at'] = now();
                }

                if ($isFailure) {
                    $updates['status'] = 'Cancelled';
                }
            }

            $order->update($updates);

            // Biaya layanan baru menjadi pendapatan setelah uangnya benar-benar
            // diterima. Pencatatannya idempoten, jadi notifikasi ganda dari
            // Midtrans tidak menggandakan saldo dompet admin.
            if ($applyStatus && $paymentStatus === 'paid') {
                // Reservasi menjadi pengurangan stok yang sesungguhnya. Sampai
                // titik ini barangnya masih tercatat di stok seller dan tetap
                // tampil di etalase.
                $order->commitReservedStock();

                PlatformRevenue::recordServiceFee($order);
            }

            // Midtrans juga bisa mengabarkan refund yang diproses di luar
            // aplikasi (langsung dari dashboard Midtrans). Tanpa cabang ini,
            // biaya layanan atas uang yang sudah dikembalikan tetap terhitung
            // sebagai pendapatan marketplace.
            if ($applyStatus && $paymentStatus === 'refunded') {
                PlatformRevenue::reverseServiceFee(
                    $order,
                    'Pembalikan biaya layanan atas refund Midtrans pesanan '.$order->public_id
                );
            }

            if ($applyStatus && $isFailure) {
                $order->restoreReservedStock();
            }
        }

        return response()->json(['message' => 'Notification processed.']);
    }

    /**
     * Notifikasi pembayaran uang jaminan lelang.
     *
     * Yang boleh menerima kabar pelunasan bukan hanya jaminan `pending`, tetapi
     * juga yang sudah telanjur ditandai `expired`.
     *
     * Alasannya: `expired` dipasang oleh penutupan lelang atas jaminan yang saat
     * itu belum dibayar — dan "belum dibayar pada detik itu" tidak sama dengan
     * "tidak akan pernah dibayar". Pembeli yang memilih virtual account bisa saja
     * sudah menekan bayar semenit sebelum lelangnya tutup. Menolak uangnya
     * membuat jaminan bernilai `expired` padahal dananya benar-benar diterima,
     * dan pemiliknya kehilangan jalur pengembalian sama sekali (temuan V7-01).
     *
     * Pemilik jaminan semacam itu pasti kalah — untuk menawar ia harus punya
     * jaminan yang sudah aktif — jadi menaikkannya ke `paid` selalu berujung ke
     * jalur pengembalian, tidak pernah ke uang muka.
     *
     * Status selebihnya (`applied`, `refunded`, `forfeited`) sudah milik alur
     * lelang dan tidak boleh ditarik mundur oleh notifikasi yang terlambat.
     */
    private function handleDepositNotification(
        ?string $paymentReference,
        ?string $paymentStatus,
        bool $isFailure,
        array $payload
    ) {
        $deposit = AuctionDeposit::where('payment_reference', $paymentReference)->first();

        if (! $deposit) {
            return response()->json(['message' => 'Order tidak ditemukan.'], 404);
        }

        $deposit->midtrans_transaction_id = $payload['transaction_id'] ?? $deposit->midtrans_transaction_id;

        $menungguPelunasan = in_array($deposit->status, [
            AuctionDeposit::STATUS_PENDING,
            AuctionDeposit::STATUS_EXPIRED,
        ], true);

        if ($paymentStatus === 'paid' && $menungguPelunasan) {
            $terlambat = $deposit->status === AuctionDeposit::STATUS_EXPIRED;

            $deposit->status = AuctionDeposit::STATUS_PAID;
            $deposit->paid_at = now();

            if ($terlambat) {
                Log::info('Deposit lelang lunas setelah lelangnya tutup; masuk jalur pengembalian.', [
                    'deposit_public_id' => $deposit->public_id,
                    'gross_amount' => $payload['gross_amount'] ?? null,
                ]);
            }
        } elseif ($isFailure && $deposit->status === AuctionDeposit::STATUS_PENDING) {
            $deposit->status = AuctionDeposit::STATUS_EXPIRED;
        } elseif ($paymentStatus === 'paid') {
            Log::warning('Pembayaran deposit lelang datang atas jaminan yang sudah tidak menunggu bayaran.', [
                'deposit_public_id' => $deposit->public_id,
                'status' => $deposit->status,
                'gross_amount' => $payload['gross_amount'] ?? null,
            ]);
        }

        $deposit->save();

        return response()->json(['message' => 'Notification processed.']);
    }
}
