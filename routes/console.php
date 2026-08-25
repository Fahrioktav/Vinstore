<?php

use App\Models\Auction;
use App\Models\AuctionDeposit;
use App\Models\Order;
use App\Models\TradeInRequest;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        ->each(fn (Auction $auction) => $auction->finishNow());

    $this->info('Auction statuses synchronized.');
})->purpose('Activate due auctions and finish expired auctions');

// `withoutOverlapping()` karena penutupan lelang kini ikut menanyakan status
// jaminan ke Midtrans. Satu kali jalan yang tertahan jaringan bisa melewati
// satu menit, dan tanpa penjagaan ini jalan berikutnya dimulai sebelum yang
// sebelumnya selesai — penumpukan proses, lalu lelang yang tutup terlambat
// sementara penawaran masih diterima (temuan V8-05).
Schedule::command('auctions:finish')->everyMinute()->withoutOverlapping();

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
    $released = 0;

    Order::whereIn('payment_status', ['pending', 'unpaid'])
        ->whereNull('stock_restored_at')
        ->whereNull('stock_committed_at')
        // Pesanan lelang punya tenggat sendiri yang lebih longgar, jadi
        // penyaringannya tidak bisa memakai satu batas waktu untuk semua.
        ->where(function ($query) {
            $query->where(function ($q) {
                $q->whereNull('auction_id')
                    ->where('created_at', '<=', now()->subHours(Order::PAYMENT_WINDOW_HOURS));
            })->orWhere(function ($q) {
                $q->whereNotNull('auction_id')
                    ->where('created_at', '<=', now()->subHours(Order::AUCTION_PAYMENT_WINDOW_HOURS));
            });
        })
        ->each(function (Order $order) use (&$released) {
            $order->update([
                'status' => 'Cancelled',
                'payment_status' => 'expired',
            ]);

            $order->restoreReservedStock();

            // Pemenang lelang yang tidak kunjung membayar kehilangan depositnya.
            // Inilah sanksi yang menjadi alasan deposit dipungut sejak awal.
            if ($order->auction_id !== null) {
                AuctionDeposit::forfeitForAbandonedOrder($order);
            }

            $released++;
        });

    $this->info("Released {$released} abandoned reservation(s).");
})->purpose('Lepaskan stok pesanan yang tidak dibayar sampai tenggat');

Schedule::command('orders:release-abandoned')->hourly();

/**
 * Batalkan tukar tambah yang selisihnya tidak dibayar sampai tenggat.
 *
 * Sejak disetujui, kedua produk terkunci dari penjualan. Tanpa perintah ini,
 * pembayar yang berubah pikiran cukup diam: tukar tambahnya menggantung
 * selamanya dan dua produk milik dua toko berbeda ikut membeku, tanpa satu pun
 * pihak yang bisa melepaskannya (temuan V2-01/V3-02/V3-10).
 *
 * Sebelum membatalkan, keadaan pembayaran ditanyakan lebih dulu ke Midtrans.
 * Webhook tidak pernah sampai di localhost dan di produksi pun bisa gagal
 * terkirim; tanpa penyelarasan itu, tukar tambah yang sebenarnya sudah lunas
 * ikut dibatalkan dan uangnya tersangkut — persoalan yang sama dengan deposit
 * lelang pada temuan V7-01.
 */
Artisan::command('trade-in:expire', function () {
    $dibatalkan = 0;

    TradeInRequest::where('status', TradeInRequest::STATUS_ACCEPTED)
        ->where('payment_status', TradeInRequest::PAYMENT_PENDING)
        ->whereNotNull('payment_due_at')
        ->where('payment_due_at', '<=', now())
        ->each(function (TradeInRequest $tradeIn) use (&$dibatalkan) {
            try {
                // Sengaja DI LUAR transaksi: panggilan HTTP tidak boleh
                // dilakukan sambil memegang kunci baris.
                $tradeIn->syncPaymentFromMidtrans();
            } catch (\Throwable $e) {
                // Transaksi yang metode bayarnya belum pernah dipilih belum ada
                // di Midtrans dan dijawab 404. Tidak ada yang bisa
                // diselaraskan; lanjutkan ke pembatalan.
                Log::warning('Gagal menyelaraskan pembayaran tukar tambah sebelum kedaluwarsa', [
                    'trade_in_id' => $tradeIn->public_id,
                    'error' => $e->getMessage(),
                ]);
            }

            $tradeIn->refresh();

            if ($tradeIn->cancelUnpaid()) {
                $dibatalkan++;
            }
        });

    $this->info("Expired {$dibatalkan} unpaid trade-in(s).");
})->purpose('Batalkan tukar tambah yang selisihnya tidak dibayar sampai tenggat');

// Sejam sekali, sejalan dengan `orders:release-abandoned`. Tenggatnya 24 jam,
// jadi ketelitian sampai menit tidak menambah apa pun selain panggilan Midtrans
// yang lebih sering. `withoutOverlapping()` karena perintah ini memanggil
// jaringan: satu jalan yang tertahan tidak boleh ditimpa jalan berikutnya.
Schedule::command('trade-in:expire')->hourly()->withoutOverlapping();

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
