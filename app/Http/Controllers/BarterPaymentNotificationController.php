<?php

namespace App\Http\Controllers;

use App\Models\BarterRequest;
use App\Models\Product;
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
        if (!$midtrans->isValidSignature($payload)) {
            Log::warning('Invalid Midtrans signature for barter payment', ['payload' => $payload]);
            return response()->json(['message' => 'Invalid Midtrans signature.'], 403);
        }

        $paymentReference = $payload['order_id'] ?? null;
        
        // Cari barter request berdasarkan payment reference
        $barter = BarterRequest::where('payment_reference', $paymentReference)->first();

        if (!$barter) {
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
                // Update payment status
                $barter->payment_status = $barterPaymentStatus;
                $barter->midtrans_transaction_id = $payload['transaction_id'] ?? $barter->midtrans_transaction_id;

                // Jika pembayaran berhasil, tukar kepemilikan produk
                if ($barterPaymentStatus === BarterRequest::PAYMENT_PAID) {
                    $barter->paid_at = now();
                    $barter->save();

                    // Lock dan tukar kepemilikan produk
                    $offered = Product::where('id', $barter->offered_product_id)->lockForUpdate()->first();
                    $requested = Product::where('id', $barter->requested_product_id)->lockForUpdate()->first();

                    if ($offered && $requested) {
                        // Tukar kepemilikan
                        $requesterStoreId = $barter->requester_store_id;
                        $responderStoreId = $barter->responder_store_id;

                        $offered->store_id = $responderStoreId;
                        $requested->store_id = $requesterStoreId;

                        // Matikan flag barter
                        $offered->is_barterable = false;
                        $requested->is_barterable = false;

                        $offered->save();
                        $requested->save();

                        // Batalkan pengajuan pending lain
                        BarterRequest::where('status', BarterRequest::STATUS_PENDING)
                            ->where('id', '!=', $barter->id)
                            ->where(function ($query) use ($barter) {
                                $query->whereIn('offered_product_id', [$barter->offered_product_id, $barter->requested_product_id])
                                    ->orWhereIn('requested_product_id', [$barter->offered_product_id, $barter->requested_product_id]);
                            })
                            ->update([
                                'status' => BarterRequest::STATUS_CANCELLED,
                                'responded_at' => now(),
                            ]);

                        Log::info('Barter products exchanged after payment', [
                            'barter_id' => $barter->public_id,
                            'payment_reference' => $barter->payment_reference,
                        ]);
                    }
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
