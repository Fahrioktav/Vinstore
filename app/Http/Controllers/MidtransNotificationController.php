<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Http\Request;

class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, MidtransService $midtrans)
    {
        $payload = $request->all();

        if (!$midtrans->isValidSignature($payload)) {
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

        $updates = [
            'payment_status' => $paymentStatus,
            'payment_method' => $payload['payment_type'] ?? $orders->first()->payment_method,
            'midtrans_transaction_id' => $payload['transaction_id'] ?? $orders->first()->midtrans_transaction_id,
        ];

        if ($paymentStatus === 'paid') {
            $updates['paid_at'] = now();
        }

        if (in_array($paymentStatus, ['cancelled', 'denied', 'expired'], true)) {
            $updates['status'] = 'Cancelled';
        }

        foreach ($orders as $order) {
            $order->update($updates);

            if (in_array($paymentStatus, ['cancelled', 'denied', 'expired'], true)) {
                $order->restoreReservedStock();
            }
        }

        return response()->json(['message' => 'Notification processed.']);
    }
}
