<?php

namespace App\Http\Controllers;

use App\Models\BarterRequest;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BarterPaymentNotificationController extends Controller
{
    /**
     * Handle webhook notifikasi dari Midtrans untuk pembayaran barter
     */
    public function __invoke(Request $request, MidtransService $midtrans)
    {
        $payload = $request->all();

        // Validasi signature Midtrans
        if (! $midtrans->isValidSignature($payload)) {
            Log::warning('Invalid Midtrans signature for barter payment', ['payload' => $payload]);

            return response()->json(['message' => 'Invalid Midtrans signature.'], 403);
        }

        $paymentReference = $payload['order_id'] ?? null;

        // Cari barter request berdasarkan payment reference
        $barter = BarterRequest::where('payment_reference', $paymentReference)->first();

        if (! $barter) {
            Log::warning('Barter not found for payment reference', ['payment_reference' => $paymentReference]);

            return response()->json(['message' => 'Barter tidak ditemukan.'], 404);
        }

        // Map status dari Midtrans
        $transactionStatus = $payload['transaction_status'] ?? null;
        $fraudStatus = $payload['fraud_status'] ?? null;
        $paymentStatus = $midtrans->mapPaymentStatus($transactionStatus, $fraudStatus);

        // Konversi payment status ke format barter
        $barterPaymentStatus = $this->mapToBarterPaymentStatus($paymentStatus);

        try {
            DB::transaction(function () use ($barter, $barterPaymentStatus, $payload) {
                // Kunci baris agar webhook yang dikirim ulang oleh Midtrans
                // tidak diproses dua kali secara bersamaan.
                $barter = BarterRequest::whereKey($barter->getKey())->lockForUpdate()->firstOrFail();

                // Update payment status
                $barter->payment_status = $barterPaymentStatus;
                $barter->midtrans_transaction_id = $payload['transaction_id'] ?? $barter->midtrans_transaction_id;

                if ($barterPaymentStatus === BarterRequest::PAYMENT_PAID) {
                    if ($barter->paid_at === null) {
                        $barter->paid_at = now();
                    }
                    $barter->save();

                    // Pembayaran lunas TIDAK langsung memindahkan kepemilikan.
                    // Kedua seller harus saling mengirim barang lebih dulu, lalu
                    // saling mengonfirmasi penerimaan (temuan T-08). Kepemilikan
                    // berpindah di BarterController::confirmReceipt().
                    if (! in_array($barter->status, [BarterRequest::STATUS_SHIPPING, BarterRequest::STATUS_COMPLETED], true)) {
                        // shipping_started_at menandai awal tenggat pengiriman.
                        $barter->forceFill([
                            'status' => BarterRequest::STATUS_SHIPPING,
                            'shipping_started_at' => now(),
                        ])->save();
                    }

                    Log::info('Barter payment settled, entering shipping stage', [
                        'barter_id' => $barter->public_id,
                        'payment_reference' => $barter->payment_reference,
                    ]);
                } else {
                    // Jika pembayaran gagal/expired/cancelled, update status saja
                    $barter->save();

                    // Jika payment gagal/expired, kembalikan status barter ke pending
                    if (in_array($barterPaymentStatus, [BarterRequest::PAYMENT_FAILED, BarterRequest::PAYMENT_EXPIRED], true)) {
                        // Opsional: bisa auto-cancel barter atau biarkan requester coba lagi
                        // Untuk saat ini biarkan payment_status expired/failed tapi barter tetap accepted
                        Log::info('Barter payment failed/expired', [
                            'barter_id' => $barter->public_id,
                            'payment_status' => $barterPaymentStatus,
                        ]);
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('Error processing barter payment notification', [
                'payment_reference' => $paymentReference,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Error processing notification.'], 500);
        }

        return response()->json(['message' => 'Notification processed successfully.']);
    }

    /**
     * Convert payment status dari MidtransService ke format BarterRequest
     */
    private function mapToBarterPaymentStatus(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            'paid' => BarterRequest::PAYMENT_PAID,
            'pending', 'challenge' => BarterRequest::PAYMENT_PENDING,
            'cancelled', 'denied' => BarterRequest::PAYMENT_FAILED,
            'expired' => BarterRequest::PAYMENT_EXPIRED,
            default => BarterRequest::PAYMENT_PENDING,
        };
    }
}
