<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Order;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('auctions:finish', function () {
    Auction::where('approval_status', 'approved')
        ->whereIn('status', ['scheduled', 'active'])
        ->where('starts_at', '<=', now())
        ->where('ends_at', '>', now())
        ->update(['status' => 'active']);

    Auction::where('approval_status', 'approved')
        ->whereIn('status', ['scheduled', 'active'])
        ->where('ends_at', '<=', now())
        ->each(function (Auction $auction) {
            DB::transaction(function () use ($auction) {
                $auction = Auction::whereKey($auction->getKey())->lockForUpdate()->first();

                if (!$auction || $auction->status === 'ended') {
                    return;
                }

                $highestBid = AuctionBid::where('auction_id', $auction->id)
                    ->orderByDesc('amount')
                    ->orderBy('created_at')
                    ->first();

                $updates = [
                    'status' => 'ended',
                    'ended_at' => now(),
                ];

                if ($highestBid) {
                    $updates['winner_id'] = $highestBid->user_id;
                }

                $auction->update($updates);

                if ($highestBid && !Order::where('auction_id', $auction->id)->exists()) {
                    $order = Order::create([
                        'user_id' => $highestBid->user_id,
                        'product_id' => null,
                        'auction_id' => $auction->id,
                        'store_id' => $auction->store_id,
                        'quantity' => 1,
                        'price' => $highestBid->amount,
                        'status' => 'Waiting',
                        'payment_status' => 'pending',
                        'payment_method' => 'midtrans',
                    ]);

                    $order->update(['payment_reference' => $order->public_id]);
                }
            });
        });

    $this->info('Auction statuses synchronized.');
})->purpose('Activate due auctions and finish expired auctions');

Schedule::command('auctions:finish')->everyMinute();

Artisan::command('tebak-harga:finish', function () {
    app(\App\Services\PriceGuessService::class)->sync();
    $this->info('Tebak harga lifecycle synchronized.');
})->purpose('Activate, finalize winners, and revert expired price-guess products');

Schedule::command('tebak-harga:finish')->everyMinute();

// Test command untuk simulasi webhook Midtrans barter payment
Artisan::command('test:barter-webhook {payment_reference} {status=settlement}', function ($paymentReference, $status) {
    $serverKey = config('services.midtrans.server_key');
    
    if (empty($serverKey)) {
        $this->error('MIDTRANS_SERVER_KEY not configured');
        return 1;
    }

    // Generate signature sesuai format Midtrans
    $statusCode = match($status) {
        'settlement', 'capture' => '200',
        'pending' => '201',
        'deny' => '400',
        'cancel' => '202',
        'expire' => '407',
        default => '200',
    };

    // Cari barter berdasarkan payment reference
    $barter = \App\Models\BarterRequest::where('payment_reference', $paymentReference)->first();
    
    if (!$barter) {
        $this->error("Barter with payment reference '{$paymentReference}' not found");
        return 1;
    }

    $grossAmount = (string) (int) $barter->additional_cash;
    $transactionId = 'TEST-' . time();

    $signature = hash('sha512', $paymentReference . $statusCode . $grossAmount . $serverKey);

    $payload = [
        'transaction_time' => now()->toIso8601String(),
        'transaction_status' => $status,
        'transaction_id' => $transactionId,
        'status_message' => 'Success',
        'status_code' => $statusCode,
        'signature_key' => $signature,
        'payment_type' => 'credit_card',
        'order_id' => $paymentReference,
        'merchant_id' => 'TEST-MERCHANT',
        'gross_amount' => $grossAmount,
        'fraud_status' => 'accept',
        'currency' => 'IDR',
    ];

    $this->info("Sending webhook notification to: /midtrans/barter/notification");
    $this->info("Payment Reference: {$paymentReference}");
    $this->info("Status: {$status}");
    $this->line('');

    // Send HTTP request ke webhook endpoint
    try {
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post(url('/midtrans/barter/notification'), $payload);

        if ($response->successful()) {
            $this->info('✅ Webhook sent successfully!');
            $this->line('Response: ' . $response->body());
            
            // Refresh barter status
            $barter->refresh();
            $this->line('');
            $this->info('Updated Barter Status:');
            $this->line("  Payment Status: {$barter->payment_status}");
            $this->line("  Barter Status: {$barter->status}");
            
            if ($barter->payment_status === 'paid') {
                $this->info('  ✅ Payment completed and products exchanged!');
            }
        } else {
            $this->error('❌ Webhook failed!');
            $this->line('Status: ' . $response->status());
            $this->line('Response: ' . $response->body());
        }
    } catch (\Exception $e) {
        $this->error('❌ Error: ' . $e->getMessage());
        return 1;
    }

    return 0;
})->purpose('Test webhook Midtrans untuk barter payment (local development only)');
