<?php

namespace App\Http\Controllers;

use App\Models\TradeInRequest;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TradeInPaymentNotificationController extends Controller
{
    /**
     * Handle webhook notifikasi dari Midtrans untuk pembayaran tukar tambah
     */
    public function __invoke(Request $request, MidtransService $midtrans)
    {
        $payload = $request->all();

        // Validasi signature Midtrans
        if (! $midtrans->isValidSignature($payload)) {
            Log::warning('Invalid Midtrans signature for trade-in payment', ['payload' => $payload]);

            return response()->json(['message' => 'Invalid Midtrans signature.'], 403);
        }

        $paymentReference = $payload['order_id'] ?? null;

        // Cari tukar tambah request berdasarkan payment reference
        $tradeIn = TradeInRequest::where('payment_reference', $paymentReference)->first();

        if (! $tradeIn) {
            Log::warning('Trade-in not found for payment reference', ['payment_reference' => $paymentReference]);

            return response()->json(['message' => 'Tukar tambah tidak ditemukan.'], 404);
        }

        // Map status dari Midtrans
        $transactionStatus = $payload['transaction_status'] ?? null;
        $fraudStatus = $payload['fraud_status'] ?? null;
        $paymentStatus = $midtrans->mapPaymentStatus($transactionStatus, $fraudStatus);

        // Konversi payment status ke format tukar tambah
        $tradeInPaymentStatus = $this->mapToTradeInPaymentStatus($paymentStatus);

        try {
            DB::transaction(function () use ($tradeIn, $tradeInPaymentStatus, $payload) {
                // Kunci baris agar webhook yang dikirim ulang oleh Midtrans
                // tidak diproses dua kali secara bersamaan.
                $tradeIn = TradeInRequest::whereKey($tradeIn->getKey())->lockForUpdate()->firstOrFail();

                // Update payment status
                $tradeIn->payment_status = $tradeInPaymentStatus;
                $tradeIn->midtrans_transaction_id = $payload['transaction_id'] ?? $tradeIn->midtrans_transaction_id;

                if ($tradeInPaymentStatus === TradeInRequest::PAYMENT_PAID) {
                    if ($tradeIn->paid_at === null) {
                        $tradeIn->paid_at = now();
                    }
                    $tradeIn->save();

                    // Pembayaran lunas TIDAK langsung memindahkan kepemilikan.
                    // Kedua seller harus saling mengirim barang lebih dulu, lalu
                    // saling mengonfirmasi penerimaan (temuan T-08). Kepemilikan
                    // berpindah di TradeInController::confirmReceipt().
                    if (! in_array($tradeIn->status, [TradeInRequest::STATUS_SHIPPING, TradeInRequest::STATUS_COMPLETED], true)) {
                        // shipping_started_at menandai awal tenggat pengiriman.
                        $tradeIn->forceFill([
                            'status' => TradeInRequest::STATUS_SHIPPING,
                            'shipping_started_at' => now(),
                        ])->save();
                    }

                    Log::info('Trade-in payment settled, entering shipping stage', [
                        'trade_in_id' => $tradeIn->public_id,
                        'payment_reference' => $tradeIn->payment_reference,
                    ]);
                } else {
                    // Jika pembayaran gagal/expired/cancelled, update status saja
                    $tradeIn->save();

                    // Jika payment gagal/expired, kembalikan status tukar tambah ke pending
                    if (in_array($tradeInPaymentStatus, [TradeInRequest::PAYMENT_FAILED, TradeInRequest::PAYMENT_EXPIRED], true)) {
                        // Opsional: bisa auto-cancel tukar tambah atau biarkan requester coba lagi
                        // Untuk saat ini biarkan payment_status expired/failed tapi tukar tambah tetap accepted
                        Log::info('Trade-in payment failed/expired', [
                            'trade_in_id' => $tradeIn->public_id,
                            'payment_status' => $tradeInPaymentStatus,
                        ]);
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('Error processing tukar tambah payment notification', [
                'payment_reference' => $paymentReference,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Error processing notification.'], 500);
        }

        return response()->json(['message' => 'Notification processed successfully.']);
    }

    /**
     * Convert payment status dari MidtransService ke format TradeInRequest
     */
    private function mapToTradeInPaymentStatus(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            'paid' => TradeInRequest::PAYMENT_PAID,
            'pending', 'challenge' => TradeInRequest::PAYMENT_PENDING,
            'cancelled', 'denied' => TradeInRequest::PAYMENT_FAILED,
            'expired' => TradeInRequest::PAYMENT_EXPIRED,
            default => TradeInRequest::PAYMENT_PENDING,
        };
    }
}
