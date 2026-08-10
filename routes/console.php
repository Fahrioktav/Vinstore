<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Order;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

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

                if (! $auction || $auction->status === 'ended') {
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

                if ($highestBid && ! Order::where('auction_id', $auction->id)->exists()) {
                    $order = Order::create([
                        'user_id' => $highestBid->user_id,
                        'product_id' => null,
                        // Snapshot agar riwayat pesanan lelang tetap terbaca
                        // meski lelang atau tokonya dihapus.
                        'product_name' => $auction->name,
                        'product_price' => $highestBid->amount,
                        'auction_id' => $auction->id,
                        'store_id' => $auction->store_id,
                        'store_name' => $auction->store?->store_name,
                        'quantity' => 1,
                        'price' => $highestBid->amount,
                        'status' => 'Waiting',
                        // Lelang tidak punya form checkout, jadi alamat diambil
                        // dari profil pemenang agar seller punya tujuan kirim.
                        'shipping_address' => $highestBid->user?->address,
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

/**
 * Selesaikan pesanan yang sudah lewat masa sanggah pembeli.
 *
 * Command ini HANYA memindahkan status Delivered -> Completed supaya siklus
 * pesanan tidak menggantung saat pembeli lupa mengonfirmasi. Ia tidak lagi
 * menyentuh uang: pencairan dana kini selalu lewat pengajuan seller yang
 * disetujui admin (PayoutRequest).
 *
 * Pesanan yang punya pengajuan refund berstatus pending sengaja dilewati:
 * sengketanya harus diputus admin lebih dulu.
 */
Artisan::command('orders:auto-complete', function () {
    $deadline = now()->subDays(Order::BUYER_CONFIRMATION_WINDOW_DAYS);
    $completed = 0;

    Order::where('status', 'Delivered')
        ->where('payment_status', 'paid')
        ->whereNull('completed_at')
        ->whereNotNull('delivered_at')
        ->where('delivered_at', '<=', $deadline)
        ->whereDoesntHave('refundRequest', fn ($query) => $query->where('status', 'pending'))
        ->each(function (Order $order) use (&$completed) {
            DB::transaction(function () use ($order, &$completed) {
                $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();

                if (! $locked || $locked->status !== 'Delivered') {
                    return;
                }

                $locked->forceFill([
                    'status' => 'Completed',
                    'completed_at' => now(),
                ])->save();

                $completed++;
            });
        });

    $this->info("Auto-completed {$completed} order(s) past the buyer confirmation window.");
})->purpose('Complete delivered orders whose buyer confirmation window has expired');

Schedule::command('orders:auto-complete')->hourly();

/**
 * Lepaskan reservasi stok pesanan yang ditinggalkan tanpa dibayar.
 *
 * Checkout menahan stok agar dua orang tidak sama-sama membayar guci terakhir.
 * Tetapi pembeli yang menutup popup Snap lalu tidak pernah kembali akan menahan
 * barang itu selamanya — dan untuk barang antik yang stoknya kerap hanya 1,
 * satu orang yang berubah pikiran cukup untuk membuatnya tidak bisa dibeli
 * siapa pun.
 *
 * Tenggatnya disamakan dengan masa berlaku transaksi Midtrans (24 jam). Lewat
 * dari itu pesanannya ditandai kedaluwarsa dan reservasinya dilepas.
 *
 * Webhook Midtrans tetap jalur utamanya; command ini jaring pengaman untuk
 * notifikasi yang tidak pernah sampai — yang SELALU terjadi di lingkungan lokal
 * karena Midtrans tidak bisa menghubungi localhost.
 */
Artisan::command('orders:release-abandoned', function () {
    $deadline = now()->subHours(Order::PAYMENT_WINDOW_HOURS);
    $released = 0;

    Order::whereIn('payment_status', ['pending', 'unpaid'])
        ->whereNull('stock_restored_at')
        ->whereNull('stock_committed_at')
        ->where('created_at', '<=', $deadline)
        ->each(function (Order $order) use (&$released) {
            $order->update([
                'status' => 'Cancelled',
                'payment_status' => 'expired',
            ]);

            $order->restoreReservedStock();
            $released++;
        });

    $this->info("Released {$released} abandoned reservation(s).");
})->purpose('Lepaskan stok pesanan yang tidak dibayar sampai tenggat');

Schedule::command('orders:release-abandoned')->hourly();

// Test command untuk simulasi webhook Midtrans tukar tambah payment
Artisan::command('test:tukar-tambah-webhook {payment_reference} {status=settlement}', function ($paymentReference, $status) {
    $serverKey = config('services.midtrans.server_key');

    if (empty($serverKey)) {
        $this->error('MIDTRANS_SERVER_KEY not configured');

        return 1;
    }

    // Generate signature sesuai format Midtrans
    $statusCode = match ($status) {
        'settlement', 'capture' => '200',
        'pending' => '201',
        'deny' => '400',
        'cancel' => '202',
        'expire' => '407',
        default => '200',
    };

    // Cari tukar tambah berdasarkan payment reference
    $tradeIn = \App\Models\TradeInRequest::where('payment_reference', $paymentReference)->first();

    if (! $tradeIn) {
        $this->error("Trade-in with payment reference '{$paymentReference}' not found");

        return 1;
    }

    $grossAmount = (string) (int) $tradeIn->additional_cash;
    $transactionId = 'TEST-'.time();

    $signature = hash('sha512', $paymentReference.$statusCode.$grossAmount.$serverKey);

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

    $this->info('Sending webhook notification to: /midtrans/barter/notification');
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
            $this->line('Response: '.$response->body());

            // Refresh tukar tambah status
            $tradeIn->refresh();
            $this->line('');
            $this->info('Updated Trade-in Status:');
            $this->line("  Payment Status: {$tradeIn->payment_status}");
            $this->line("  Trade-in Status: {$tradeIn->status}");

            if ($tradeIn->payment_status === 'paid') {
                $this->info('  ✅ Payment completed and products exchanged!');
            }
        } else {
            $this->error('❌ Webhook failed!');
            $this->line('Status: '.$response->status());
            $this->line('Response: '.$response->body());
        }
    } catch (\Exception $e) {
        $this->error('❌ Error: '.$e->getMessage());

        return 1;
    }

    return 0;
})->purpose('Test webhook Midtrans untuk tukar tambah payment (local development only)');
