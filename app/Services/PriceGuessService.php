<?php

namespace App\Services;

use App\Models\PriceGuess;
use App\Models\Product;
use App\Models\User;
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
     *
     * @param  string|null  $reason  Alasan penutupan. Diisi
     *                               Product::FINISH_EXACT_GUESS ketika sesi
     *                               ditutup lebih cepat karena ada tebakan tepat.
     */
    public function finalize(Product $product, ?string $reason = null): void
    {
        DB::transaction(function () use ($product, $reason) {
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
                    'guess_finished_reason' => Product::FINISH_NO_WINNER,
                    'guess_winner_id' => null,
                    'guess_winning_amount' => null,
                    'winner_priority_until' => null,
                ]);

                return;
            }

            $product->update([
                'guess_status' => Product::GUESS_ENDED,
                'guess_finished_at' => now(),
                'guess_finished_reason' => $reason ?? Product::FINISH_PERIOD_ENDED,
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
     * Aturan lengkapnya, berurutan:
     *   1. Tebakan yang meleset di luar ambang toleransi tidak ikut dinilai.
     *   2. Yang selisihnya PALING KECIL menang.
     *   3. Bila dua orang sama-sama dekat — termasuk saat menebak angka yang
     *      persis sama — yang MENEBAK LEBIH DULU yang menang. Penebak pertama
     *      menanggung risiko lebih besar karena menebak tanpa melihat tebakan
     *      siapa pun, jadi dialah yang berhak atas keunggulan itu.
     */
    public function determineWinner(Product $product): ?PriceGuess
    {
        if ($product->guess_discount_price === null) {
            return null;
        }

        return $this->rankedGuesses($product)
            ->filter(fn (PriceGuess $guess) => $product->isGuessWithinTolerance((float) $guess->amount))
            ->first();
    }

    /**
     * Seluruh tebakan sebuah produk, diurutkan dari yang paling dekat.
     *
     * Tie-break memakai id, bukan created_at: dua tebakan yang masuk pada detik
     * yang sama punya created_at identik, dan urutan yang tidak pasti berarti
     * pemenangnya bisa berubah setiap kali fungsi ini dipanggil. Id auto-increment
     * selalu mencerminkan siapa yang benar-benar lebih dulu.
     *
     * @return \Illuminate\Support\Collection<int, PriceGuess>
     */
    public function rankedGuesses(Product $product)
    {
        if ($product->guess_discount_price === null) {
            return collect();
        }

        $discountPrice = (float) $product->guess_discount_price;

        return PriceGuess::where('product_id', $product->id)
            ->with('user:id,public_id,username,first_name,last_name,photo')
            ->orderBy('id')
            ->get()
            ->sortBy([
                fn (PriceGuess $a, PriceGuess $b) => abs((float) $a->amount - $discountPrice)
                    <=> abs((float) $b->amount - $discountPrice),
                fn (PriceGuess $a, PriceGuess $b) => $a->id <=> $b->id,
            ])
            ->values();
    }

    /**
     * Papan tebakan untuk halaman produk.
     *
     * Aturan tampilnya berbeda menurut status, dan bedanya disengaja:
     *
     * - SELAMA PERIODE BERJALAN: daftar diurutkan waktu, terbaru di atas. Nominal
     *   tebakan tetap terbuka seperti riwayat bid pada lelang, TETAPI peringkat
     *   dan selisihnya tidak ditampilkan — mengurutkan menurut kedekatan sama
     *   saja dengan memberi tahu semua orang di mana kira-kira harga diskonnya.
     *
     * - SETELAH SESI SELESAI: daftar diurutkan menurut kedekatan, lengkap dengan
     *   peringkat. Selisih dalam rupiah hanya ikut ditampilkan bila viewer
     *   memang sudah berhak melihat harga diskonnya.
     */
    public function leaderboard(Product $product, ?User $viewer = null): array
    {
        if (! $product->isTebakHarga()) {
            return [];
        }

        $finished = in_array($product->guess_status, [Product::GUESS_ENDED, Product::GUESS_PUBLIC], true);
        $maySeeDiscount = ! $product->shouldHidePriceFor($viewer);

        $guesses = $finished
            ? $this->rankedGuesses($product)
            : PriceGuess::where('product_id', $product->id)
                ->with('user:id,public_id,username,first_name,last_name,photo')
                ->orderByDesc('id')
                ->get();

        return $guesses->values()->map(function (PriceGuess $guess, int $index) use ($product, $finished, $maySeeDiscount, $viewer) {
            return [
                'public_id' => $guess->public_id,
                'amount' => (float) $guess->amount,
                'created_at' => $guess->created_at,
                'username' => $guess->user?->username ?? 'Pengguna',
                'user_public_id' => $guess->user?->public_id,
                'is_mine' => $viewer !== null && $guess->user_id === $viewer->id,
                'is_winner' => $product->guess_winner_id !== null
                    && $guess->user_id === $product->guess_winner_id,
                // Peringkat baru bermakna setelah sesinya selesai; selama
                // berjalan ia justru membocorkan letak harga diskonnya.
                'rank' => $finished ? $index + 1 : null,
                'difference' => $finished && $maySeeDiscount
                    ? $product->guessDistance((float) $guess->amount)
                    : null,
                'is_exact' => $finished && $maySeeDiscount
                    ? $product->isExactGuess((float) $guess->amount)
                    : null,
            ];
        })->all();
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
