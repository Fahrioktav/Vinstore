<?php

namespace App\Services;

use App\Models\PriceGuess;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Mengelola siklus hidup fitur Tebak Harga:
 *   scheduled -> active -> ended -> public
 *
 * Produk ditawarkan dengan harga DISKON (`guess_discount_price`) yang harus
 * ditebak pembeli. Harga normal (`price`) tetap terlihat sebagai patokan.
 *
 * - activateScheduled(): produk terjadwal yang sudah memasuki periode -> active.
 * - finalizeEnded(): periode selesai -> tentukan pemenang (tebakan terdekat yang
 *   masih masuk ambang toleransi), beri hak prioritas 24 jam untuk membeli di
 *   harga diskon. Bila tidak ada tebakan yang cukup dekat -> langsung public
 *   dan dijual di harga normal.
 * - revertExpiredPriority(): hak prioritas pemenang habis & belum dipakai -> public
 *   (dijual biasa di harga normal).
 */
class PriceGuessService
{
    /**
     * Jalankan seluruh transisi siklus hidup. Idempoten & aman dipanggil berulang.
     */
    public function sync(): void
    {
        $this->activateScheduled();
        $this->finalizeEnded();
        $this->revertExpiredPriority();
    }

    /**
     * Inisialisasi status tebak harga ketika admin menyetujui produk.
     * Mengembalikan status awal yang sesuai dengan waktu sekarang.
     */
    public function initialStatusOnApproval(Product $product): string
    {
        $now = now();

        if ($product->guess_ends_at && $now->gte($product->guess_ends_at)) {
            // Periode sudah lewat saat disetujui -> tandai active agar segera difinalisasi.
            return Product::GUESS_ACTIVE;
        }

        if ($product->guess_starts_at && $now->lt($product->guess_starts_at)) {
            return Product::GUESS_SCHEDULED;
        }

        return Product::GUESS_ACTIVE;
    }

    public function activateScheduled(): void
    {
        Product::where('sale_type', Product::SALE_TYPE_TEBAK_HARGA)
            ->where('approval_status', Product::STATUS_APPROVED)
            ->where('guess_status', Product::GUESS_SCHEDULED)
            ->where('guess_starts_at', '<=', now())
            ->where('guess_ends_at', '>', now())
            ->update(['guess_status' => Product::GUESS_ACTIVE]);
    }

    public function finalizeEnded(): void
    {
        Product::where('sale_type', Product::SALE_TYPE_TEBAK_HARGA)
            ->where('approval_status', Product::STATUS_APPROVED)
            ->whereIn('guess_status', [Product::GUESS_SCHEDULED, Product::GUESS_ACTIVE])
            ->where('guess_ends_at', '<=', now())
            ->get()
            ->each(fn (Product $product) => $this->finalize($product));
    }

    /**
     * Tentukan pemenang untuk satu produk yang periodenya sudah berakhir.
     */
    public function finalize(Product $product): void
    {
        DB::transaction(function () use ($product) {
            /** @var Product|null $product */
            $product = Product::whereKey($product->getKey())->lockForUpdate()->first();

            if (! $product || ! $product->isTebakHarga()) {
                return;
            }

            if (! in_array($product->guess_status, [Product::GUESS_SCHEDULED, Product::GUESS_ACTIVE], true)) {
                return;
            }

            $winner = $this->determineWinner($product);

            if (! $winner) {
                // Tidak ada tebakan, atau tidak ada yang cukup dekat dengan
                // harga diskon -> produk dijual biasa di HARGA NORMAL.
                $product->update([
                    'guess_status' => Product::GUESS_PUBLIC,
                    'guess_finished_at' => now(),
                    'guess_winner_id' => null,
                    'guess_winning_amount' => null,
                    'winner_priority_until' => null,
                ]);

                return;
            }

            $product->update([
                'guess_status' => Product::GUESS_ENDED,
                'guess_finished_at' => now(),
                'guess_winner_id' => $winner->user_id,
                'guess_winning_amount' => $winner->amount,
                'winner_priority_until' => now()->addHours(Product::WINNER_PRIORITY_HOURS),
            ]);
        });
    }

    /**
     * Pemenang = tebakan TERDEKAT terhadap harga diskon yang MASIH MASUK AMBANG
     * toleransi (Product::GUESS_TOLERANCE_PERCENT).
     *
     * Sebelumnya tebakan terdekat selalu menang berapa pun melesetnya — sebuah
     * tebakan Rp 750.000 memenangkan produk seharga Rp 800.000. Sekarang bila
     * tidak ada satu pun tebakan yang cukup dekat, tidak ada pemenang dan
     * produknya dijual di harga normal.
     *
     * Tie-break: tebakan yang dikirim lebih dulu (created_at paling awal).
     */
    public function determineWinner(Product $product): ?PriceGuess
    {
        if ($product->guess_discount_price === null) {
            return null;
        }

        $discountPrice = (float) $product->guess_discount_price;

        return PriceGuess::where('product_id', $product->id)
            ->get()
            ->filter(fn (PriceGuess $guess) => $product->isGuessWithinTolerance((float) $guess->amount))
            ->sort(function (PriceGuess $a, PriceGuess $b) use ($discountPrice) {
                $da = abs((float) $a->amount - $discountPrice);
                $db = abs((float) $b->amount - $discountPrice);

                if ($da === $db) {
                    return $a->created_at <=> $b->created_at;
                }

                return $da <=> $db;
            })
            ->first();
    }

    public function revertExpiredPriority(): void
    {
        Product::where('sale_type', Product::SALE_TYPE_TEBAK_HARGA)
            ->where('guess_status', Product::GUESS_ENDED)
            ->whereNotNull('winner_priority_until')
            ->where('winner_priority_until', '<=', now())
            ->update([
                'guess_status' => Product::GUESS_PUBLIC,
            ]);
    }
}
