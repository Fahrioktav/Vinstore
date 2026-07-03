<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

/**
 * Menguji aturan inti fitur Tebak Harga pada level model (tanpa database):
 * - Siapa yang boleh membeli (isPurchasableBy)
 * - Kapan harga asli wajib disembunyikan (shouldHidePriceFor)
 */
class TebakHargaLogicTest extends TestCase
{
    private function user(int $id): User
    {
        $user = new User();
        $user->id = $id;

        return $user;
    }

    private function tebakHarga(string $status, array $attrs = []): Product
    {
        $product = new Product(array_merge([
            'sale_type' => Product::SALE_TYPE_TEBAK_HARGA,
            'guess_status' => $status,
        ], $attrs));

        return $product;
    }

    public function test_produk_normal_selalu_bisa_dibeli_siapa_saja(): void
    {
        $product = new Product(['sale_type' => Product::SALE_TYPE_NORMAL]);

        $this->assertTrue($product->isPurchasableBy($this->user(1)));
        $this->assertTrue($product->isPurchasableBy(null));
        $this->assertFalse($product->shouldHidePriceFor($this->user(1)));
    }

    public function test_tebak_harga_scheduled_dan_active_tidak_bisa_dibeli(): void
    {
        $scheduled = $this->tebakHarga(Product::GUESS_SCHEDULED);
        $active = $this->tebakHarga(Product::GUESS_ACTIVE);

        $this->assertFalse($scheduled->isPurchasableBy($this->user(1)));
        $this->assertFalse($active->isPurchasableBy($this->user(1)));

        // Harga wajib disembunyikan dari semua pengguna saat ini.
        $this->assertTrue($scheduled->shouldHidePriceFor($this->user(1)));
        $this->assertTrue($active->shouldHidePriceFor($this->user(99)));
    }

    public function test_saat_ended_hanya_pemenang_dengan_prioritas_aktif_yang_bisa_membeli(): void
    {
        $winner = $this->user(7);

        $product = $this->tebakHarga(Product::GUESS_ENDED, [
            'guess_winner_id' => 7,
            'winner_priority_until' => now()->addHours(5),
        ]);

        // Pemenang dengan prioritas masih berlaku -> boleh beli.
        $this->assertTrue($product->isPurchasableBy($winner));
        // Bukan pemenang -> tidak boleh beli.
        $this->assertFalse($product->isPurchasableBy($this->user(8)));

        // Harga ditampilkan ke pemenang, disembunyikan dari yang lain.
        $this->assertFalse($product->shouldHidePriceFor($winner));
        $this->assertTrue($product->shouldHidePriceFor($this->user(8)));
    }

    public function test_pemenang_tidak_bisa_membeli_setelah_prioritas_kedaluwarsa(): void
    {
        $winner = $this->user(7);

        $product = $this->tebakHarga(Product::GUESS_ENDED, [
            'guess_winner_id' => 7,
            'winner_priority_until' => now()->subMinute(),
        ]);

        $this->assertFalse($product->isWinnerPriorityActive());
        $this->assertFalse($product->isPurchasableBy($winner));
    }

    public function test_status_public_bisa_dibeli_siapa_saja_dan_harga_terlihat(): void
    {
        $product = $this->tebakHarga(Product::GUESS_PUBLIC);

        $this->assertTrue($product->isPurchasableBy($this->user(1)));
        $this->assertTrue($product->isPurchasableBy($this->user(2)));
        $this->assertFalse($product->shouldHidePriceFor($this->user(1)));
    }
}
