<?php

namespace App\Http\Controllers;

use App\Models\PriceGuess;
use App\Models\Product;
use App\Services\PriceGuessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class PriceGuessController extends Controller
{
    /**
     * Pembeli mengirimkan SATU tebakan harga untuk produk tebak harga.
     * Tebakan bersifat final dan tidak dapat diubah.
     */
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        // Pastikan status tebak harga sudah ter-update sebelum diproses.
        app(PriceGuessService::class)->sync();
        $product->refresh();

        $user = Auth::user();

        // Penjual tidak boleh menebak produknya sendiri.
        if ($user->role === 'seller' && $user->store && $product->store_id === $user->store->id) {
            return back()->with('error', 'Anda tidak dapat menebak harga produk dari toko Anda sendiri.');
        }

        if (! $product->isTebakHarga()) {
            return back()->with('error', 'Produk ini bukan produk Tebak Harga.');
        }

        if (! $product->isGuessingOpen()) {
            return back()->with('error', 'Periode tebak harga untuk produk ini sedang tidak berlangsung.');
        }

        try {
            DB::transaction(function () use ($product, $user, $validated) {

                $locked = Product::whereKey($product->getKey())->lockForUpdate()->firstOrFail();

                if (! $locked->isGuessingOpen()) {
                    throw new \RuntimeException('Periode tebak harga untuk produk ini sudah ditutup.');
                }

                $alreadyGuessed = PriceGuess::where('product_id', $product->id)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyGuessed) {
                    throw new \RuntimeException('Anda sudah pernah menebak harga produk ini dan tidak dapat mengubahnya.');
                }

                PriceGuess::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'amount' => $validated['amount'],
                ]);
            });
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($product->isExactGuess((float) $validated['amount'])) {
            app(PriceGuessService::class)->finalize($product, Product::FINISH_EXACT_GUESS);
            $product->refresh();

            if ($product->guess_winner_id === $user->id) {
                return back()->with(
                    'success',
                    '🎯 Tebakan Anda tepat sasaran! Sesi tebak harga ditutup dan Anda keluar sebagai pemenang. '
                    .'Anda punya waktu '.Product::WINNER_PRIORITY_HOURS.' jam untuk membeli produk ini di harga diskon.'
                );
            }
        }

        return back()->with('success', 'Tebakan harga Anda berhasil dikirim. Tebakan tidak dapat diubah lagi.');
    }
}
