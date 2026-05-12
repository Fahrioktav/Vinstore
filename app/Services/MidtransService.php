<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransService
{
    /**
     * @throws RequestException
     */
    public function createSnapTransaction(string $paymentReference, int $grossAmount, User $user, array $items = []): array
    {
        $serverKey = config('services.midtrans.server_key');

        if (empty($serverKey)) {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum diatur di file .env.');
        }

        $payload = [
            'transaction_details' => [
                'order_id' => $paymentReference,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'billing_address' => [
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->address,
                ],
            ],
            'callbacks' => [
                'finish' => route('order'),
            ],
        ];

        if (!empty($items)) {
            $payload['item_details'] = $items;
        }

        return Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => $this->authorizationHeader(),
        ])->post(config('services.midtrans.snap_url'), $payload)
            ->throw()
            ->json();
    }

    /**
     * @throws RequestException
     */
    public function getTransactionStatus(string $paymentReference): array
    {
        if (empty(config('services.midtrans.server_key'))) {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum diatur di file .env.');
        }

        return Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => $this->authorizationHeader(),
        ])->get(config('services.midtrans.api_url') . '/v2/' . rawurlencode($paymentReference) . '/status')
            ->throw()
            ->json();
    }

    public function isValidSignature(array $payload): bool
    {
        $serverKey = config('services.midtrans.server_key');

        if (empty($serverKey) || empty($payload['signature_key'])) {
            return false;
        }

        $signature = hash(
            'sha512',
            ($payload['order_id'] ?? '') .
            ($payload['status_code'] ?? '') .
            ($payload['gross_amount'] ?? '') .
            $serverKey
        );

        return hash_equals($signature, $payload['signature_key']);
    }

    public function mapPaymentStatus(?string $transactionStatus, ?string $fraudStatus): string
    {
        if ($transactionStatus === 'capture') {
            return $fraudStatus === 'accept' ? 'paid' : 'challenge';
        }

        return match ($transactionStatus) {
            'settlement' => 'paid',
            'pending', 'authorize' => 'pending',
            'deny' => 'denied',
            'cancel' => 'cancelled',
            'expire' => 'expired',
            'refund', 'partial_refund' => 'refunded',
            default => 'pending',
        };
    }

    private function authorizationHeader(): string
    {
        return 'Basic ' . base64_encode(config('services.midtrans.server_key') . ':');
    }
}
