<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PlatformRevenue;
use App\Services\MidtransService;
use Illuminate\Http\Request;

class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, MidtransService $midtrans)
    {
        $payload = $request->all();

        if (! $midtrans->isValidSignature($payload)) {
            return response()->json(['message' => 'Invalid Midtrans signature.'], 403);
        }

        $paymentReference = $payload['order_id'] ?? null;
        $orders = Order::where('payment_reference', $paymentReference)->get();

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'Order tidak ditemukan.'], 404);
        }

        $paymentStatus = $midtrans->mapPaymentStatus(
            $payload['transaction_status'] ?? null,
            $payload['fraud_status'] ?? null
        );

        $isFailure = in_array($paymentStatus, ['cancelled', 'denied', 'expired'], true);

        foreach ($orders as $order) {
            $updates = [
                'payment_method' => $payload['payment_type'] ?? $order->payment_method,
                'midtrans_transaction_id' => $payload['transaction_id'] ?? $order->midtrans_transaction_id,
            ];

            // Notifikasi Midtrans bisa datang tidak berurutan atau membawa
            // transaction_status yang tidak dikenal. Pesanan yang sudah lunas
            // tidak boleh dikembalikan ke 'pending' oleh notifikasi semacam itu.
            $applyStatus = $order->canApplyPaymentStatus($paymentStatus);

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
}
