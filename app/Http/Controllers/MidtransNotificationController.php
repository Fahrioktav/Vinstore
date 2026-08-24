<?php

namespace App\Http\Controllers;

use App\Models\AuctionDeposit;
use App\Models\Order;
use App\Models\PlatformRevenue;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            return $this->handleDepositNotification($paymentReference, $paymentStatus, $payload);
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
     * Aturan perpindahan statusnya sendiri ada di
     * AuctionDeposit::applyPaymentStatus(), dipakai bersama dengan
     * penyelarasan langsung ke Midtrans.
     */
    private function handleDepositNotification(
        ?string $paymentReference,
        ?string $paymentStatus,
        array $payload
    ) {
        $deposit = AuctionDeposit::where('payment_reference', $paymentReference)->first();

        // Jaminan yang transaksinya pernah mati dibuka kembali dengan referensi
        // baru berimbuhan (`DEP12345678-a1b2c3`), sehingga notifikasi atas
        // referensi LAMA tidak lagi cocok dengan kolomnya. Referensi lama itu
        // sama dengan public_id jaminannya, jadi masih bisa ditemukan — dan
        // harus, karena uangnya nyata (temuan V8-03).
        if (! $deposit && $paymentReference) {
            $deposit = AuctionDeposit::where('public_id', Str::before($paymentReference, '-'))->first();
        }

        if (! $deposit) {
            return $this->unknownReference($paymentReference, $payload);
        }

        $deposit->applyPaymentStatus($paymentStatus, $payload);

        return response()->json(['message' => 'Notification processed.']);
    }

    /**
     * Notifikasi bertanda tangan sah atas referensi yang tidak dikenali siapa pun.
     *
     * Tanda tangannya sudah diverifikasi sebelum sampai ke sini — artinya
     * notifikasi ini benar-benar dari Midtrans dan uangnya benar-benar bergerak.
     * Menjawab 404 lalu melupakannya berarti dana yang tidak dikenali tidak
     * meninggalkan jejak apa pun di sisi aplikasi, dan satu-satunya cara
     * mengetahuinya adalah mencocokkan manual di dashboard Midtrans
     * (temuan V8-03).
     */
    private function unknownReference(?string $paymentReference, array $payload)
    {
        Log::warning('Notifikasi Midtrans atas referensi yang tidak dikenal; dana perlu dicek manual.', [
            'payment_reference' => $paymentReference,
            'transaction_id' => $payload['transaction_id'] ?? null,
            'transaction_status' => $payload['transaction_status'] ?? null,
            'gross_amount' => $payload['gross_amount'] ?? null,
            'payment_type' => $payload['payment_type'] ?? null,
        ]);

        return response()->json(['message' => 'Order tidak ditemukan.'], 404);
    }
}
