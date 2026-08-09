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

        if (! empty($items)) {
            $payload['item_details'] = $items;
        }

        return Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => $this->authorizationHeader(),
        ])->post(config('services.midtrans.snap_url'), self::sanitize($payload))
            ->throw()
            ->json();
    }

    /**
     * Potong teks pada batas KARAKTER, bukan batas byte.
     *
     * substr() memotong per byte. Nama produk atau nama toko yang mengandung
     * karakter non-ASCII (é, ½, emoji, tanda kutip melengkung hasil salin-tempel
     * dari Word) bisa terbelah di tengah karakter, menyisakan byte yatim yang
     * bukan UTF-8 valid. Guzzle lalu gagal meng-encode payload dan melempar
     * "json_encode error: Malformed UTF-8 characters, possibly incorrectly
     * encoded" — yang muncul ke pembeli sebagai gagal membuat pembayaran.
     */
    public static function truncate(?string $value, int $limit): string
    {
        return mb_substr(self::toValidUtf8((string) $value), 0, $limit);
    }

    /**
     * Bersihkan seluruh string di dalam payload agar dijamin UTF-8 valid.
     *
     * Jaring pengaman lapis kedua: sumber datanya tidak hanya pemotongan teks,
     * tetapi juga bisa berupa data lama di database yang tersimpan dengan
     * encoding lain (mis. hasil impor dump latin1). Satu byte rusak di mana pun
     * membuat seluruh transaksi gagal, jadi lebih baik dibersihkan di sini
     * daripada menggagalkan pembayaran.
     */
    public static function sanitize(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::sanitize($value);
            } elseif (is_string($value)) {
                $payload[$key] = self::toValidUtf8($value);
            }
        }

        return $payload;
    }

    private static function toValidUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        // Buang byte yang tidak valid alih-alih menebak encoding asalnya:
        // menebak salah menghasilkan teks kacau yang ikut tercetak di invoice.
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
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
        ])->get(config('services.midtrans.api_url').'/v2/'.rawurlencode($paymentReference).'/status')
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
            ($payload['order_id'] ?? '').
            ($payload['status_code'] ?? '').
            ($payload['gross_amount'] ?? '').
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
        return 'Basic '.base64_encode(config('services.midtrans.server_key').':');
    }
}
