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
