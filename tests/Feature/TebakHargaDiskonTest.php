<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PriceGuess;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\PriceGuessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tebak harga: produk ditawarkan dengan harga DISKON yang harus ditebak.
 *
 * Perilaku yang diinginkan:
 *  - Pembeli menebak harga diskonnya; harga normal tetap terlihat sebagai patokan.
 *  - Tebakan terdekat menang HANYA bila masih masuk ambang toleransi.
 *  - Bila tidak ada yang cukup dekat, tidak ada pemenang dan produk dijual
 *    di harga normal.
 *  - Pemenang membayar harga diskon selama masa prioritas 24 jam.
 *
 * Perilaku lama yang diperbaiki: tebakan terdekat selalu menang berapa pun
 * melesetnya — tebakan Rp 750.000 memenangkan produk seharga Rp 800.000.
 */
class TebakHargaDiskonTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        Category::create(['name' => 'Antik']);

        $this->seller = $this->makeUser('penjual', 'seller');
        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Antik',
            'category' => 'Antik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);
    }

    private function makeUser(string $username, string $role = 'user'): User
    {
        return User::create([
            'username' => $username,
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
            'email' => $username.'@vinstore.test',
            'phone' => '0811'.random_int(1000000, 9999999),
            'address' => 'Jl. Antik',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    /**
     * Produk tebak harga: harga normal Rp 1.000.000, harga diskon Rp 800.000.
     * Ambang toleransi 5% dari harga diskon = Rp 40.000.
     */
    private function produkTebakHarga(array $attrs = []): Product
    {
        return Product::create(array_merge([
            'store_id' => $this->store->id,
            'name' => 'Koin Kuno',
            'stock' => 1,
            'price' => 1_000_000,
            'guess_discount_price' => 800_000,
            'category' => 'Antik',
            'description' => 'Koin kuno langka',
            'approval_status' => Product::STATUS_APPROVED,
            'sale_type' => Product::SALE_TYPE_TEBAK_HARGA,
            'guess_status' => Product::GUESS_ACTIVE,
            'guess_starts_at' => now()->subDay(),
            'guess_ends_at' => now()->subMinute(),
        ], $attrs));
    }

    private function tebak(Product $product, User $user, int $amount, int $menitLalu = 5): PriceGuess
    {
        return PriceGuess::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'amount' => $amount,
            'created_at' => now()->subMinutes($menitLalu),
            'updated_at' => now()->subMinutes($menitLalu),
        ]);
    }

    /**
     * Kasus yang dilaporkan: tebakan Rp 750.000 meleset Rp 50.000 dari harga
     * diskon Rp 800.000 — di luar ambang Rp 40.000, jadi TIDAK menang.
     */
    public function test_tebakan_di_luar_ambang_tidak_memenangkan_siapa_pun(): void
    {
        $produk = $this->produkTebakHarga();
        $this->tebak($produk, $this->makeUser('pembeli1'), 750_000);

        app(PriceGuessService::class)->finalize($produk);
        $produk->refresh();

        $this->assertSame(Product::GUESS_PUBLIC, $produk->guess_status);
        $this->assertNull($produk->guess_winner_id, 'Tebakan yang meleset di luar ambang tetap menang.');
        $this->assertEquals(1_000_000, $produk->price, 'Produk harus dijual di harga normal.');
    }

    public function test_tebakan_di_dalam_ambang_memenangkan_hak_beli_harga_diskon(): void
    {
        $produk = $this->produkTebakHarga();
        $pembeli = $this->makeUser('pembeli1');
        $this->tebak($produk, $pembeli, 780_000); // meleset 20.000, ambang 40.000

        app(PriceGuessService::class)->finalize($produk);
        $produk->refresh();

        $this->assertSame(Product::GUESS_ENDED, $produk->guess_status);
        $this->assertSame($pembeli->id, $produk->guess_winner_id);
        $this->assertEquals(780_000, $produk->guess_winning_amount);
        $this->assertNotNull($produk->winner_priority_until);
        $this->assertTrue($produk->isWinnerPriorityActive());
    }

    /**
     * Ambangnya persis di batas: meleset tepat 5% masih dianggap benar.
     */
    public function test_tebakan_tepat_di_batas_ambang_masih_menang(): void
    {
        $produk = $this->produkTebakHarga();
        $pembeli = $this->makeUser('pembeli1');
        $this->tebak($produk, $pembeli, 760_000); // meleset tepat 40.000

        app(PriceGuessService::class)->finalize($produk);

        $this->assertSame($pembeli->id, $produk->refresh()->guess_winner_id);
    }

    /**
     * Di antara yang sama-sama masuk ambang, yang TERDEKAT yang menang.
     */
    public function test_yang_terdekat_di_dalam_ambang_yang_menang(): void
    {
        $produk = $this->produkTebakHarga();
        $jauh = $this->makeUser('pembeli1');
        $dekat = $this->makeUser('pembeli2');

        $this->tebak($produk, $jauh, 770_000, menitLalu: 10);   // meleset 30.000
        $this->tebak($produk, $dekat, 795_000, menitLalu: 5);   // meleset 5.000

        app(PriceGuessService::class)->finalize($produk);

        $this->assertSame($dekat->id, $produk->refresh()->guess_winner_id);
    }

    /**
     * Semua tebakan di luar ambang -> tidak ada pemenang, walau tebakannya banyak.
     */
    public function test_banyak_tebakan_semuanya_meleset_tetap_tanpa_pemenang(): void
    {
        $produk = $this->produkTebakHarga();

        $this->tebak($produk, $this->makeUser('pembeli1'), 500_000);
        $this->tebak($produk, $this->makeUser('pembeli2'), 1_200_000);
        $this->tebak($produk, $this->makeUser('pembeli3'), 700_000);

        app(PriceGuessService::class)->finalize($produk);
        $produk->refresh();

        $this->assertSame(Product::GUESS_PUBLIC, $produk->guess_status);
        $this->assertNull($produk->guess_winner_id);
    }

    public function test_tanpa_tebakan_sama_sekali_produk_dijual_harga_normal(): void
    {
        $produk = $this->produkTebakHarga();

        app(PriceGuessService::class)->finalize($produk);
        $produk->refresh();

        $this->assertSame(Product::GUESS_PUBLIC, $produk->guess_status);
        $this->assertNull($produk->guess_winner_id);
    }

    /**
     * Produk tebak harga yang harga diskonnya belum dikonfigurasi tidak boleh
     * memenangkan siapa pun atas angka yang tidak pernah ditetapkan.
     */
    public function test_tanpa_harga_diskon_tidak_ada_pemenang(): void
    {
        $produk = $this->produkTebakHarga(['guess_discount_price' => null]);
        $this->tebak($produk, $this->makeUser('pembeli1'), 800_000);

        app(PriceGuessService::class)->finalize($produk);
        $produk->refresh();

        $this->assertSame(Product::GUESS_PUBLIC, $produk->guess_status);
        $this->assertNull($produk->guess_winner_id);
    }

    /* ============ Form seller ============ */

    private function payloadProduk(array $ubah = []): array
    {
        return array_merge([
            'name' => 'Guci Tebak',
            'stock' => 1,
            'price' => 1_000_000,
            'category' => 'Antik',
            'description' => 'Guci antik untuk tebak harga',
            'sale_type' => Product::SALE_TYPE_TEBAK_HARGA,
            'guess_discount_price' => 800_000,
            'guess_starts_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'guess_ends_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ], $ubah);
    }

    public function test_seller_dapat_menyimpan_harga_diskon_lewat_form(): void
    {
        $this->actingAs($this->seller)
            ->post('/seller/products', $this->payloadProduk())
            ->assertSessionHasNoErrors();

        $produk = Product::where('name', 'Guci Tebak')->firstOrFail();

        $this->assertEquals(800_000, $produk->guess_discount_price);
        $this->assertEquals(1_000_000, $produk->price);
    }

    /**
     * Diskon yang tidak lebih murah dari harga normal bukan diskon.
     */
    public function test_harga_diskon_harus_lebih_kecil_dari_harga_normal(): void
    {
        $this->actingAs($this->seller)
            ->post('/seller/products', $this->payloadProduk(['guess_discount_price' => 1_000_000]))
            ->assertSessionHasErrors('guess_discount_price');

        $this->assertDatabaseMissing('products', ['name' => 'Guci Tebak']);
    }

    public function test_tebak_harga_wajib_mengisi_harga_diskon(): void
    {
        $this->actingAs($this->seller)
            ->post('/seller/products', $this->payloadProduk(['guess_discount_price' => null]))
            ->assertSessionHasErrors('guess_discount_price');
    }

    /**
     * Produk penjualan biasa tidak boleh terbawa harga diskon.
     */
    public function test_produk_normal_tidak_menyimpan_harga_diskon(): void
    {
        $this->actingAs($this->seller)
            ->post('/seller/products', $this->payloadProduk([
                'sale_type' => Product::SALE_TYPE_NORMAL,
                'guess_starts_at' => null,
                'guess_ends_at' => null,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Product::where('name', 'Guci Tebak')->firstOrFail()->guess_discount_price);
    }

    /* ============ Harga yang benar-benar ditagihkan ============ */

    public function test_pemenang_membayar_harga_diskon(): void
    {
        $pembeli = $this->makeUser('pembeli1');
        $produk = $this->produkTebakHarga([
            'guess_status' => Product::GUESS_ENDED,
            'guess_winner_id' => $pembeli->id,
            'winner_priority_until' => now()->addHours(24),
        ]);

        $this->assertEquals(800_000, $produk->effectivePriceFor($pembeli));
    }

    public function test_selain_pemenang_membayar_harga_normal(): void
    {
        $pembeli = $this->makeUser('pembeli1');
        $orangLain = $this->makeUser('pembeli2');

        $produk = $this->produkTebakHarga([
            'guess_status' => Product::GUESS_ENDED,
            'guess_winner_id' => $pembeli->id,
            'winner_priority_until' => now()->addHours(24),
        ]);

        $this->assertEquals(1_000_000, $produk->effectivePriceFor($orangLain));
        $this->assertEquals(1_000_000, $produk->effectivePriceFor(null));
    }

    /**
     * Prioritas habis -> pemenang kehilangan hak harga diskonnya.
     */
    public function test_pemenang_kehilangan_harga_diskon_setelah_prioritas_habis(): void
    {
        $pembeli = $this->makeUser('pembeli1');
        $produk = $this->produkTebakHarga([
            'guess_status' => Product::GUESS_ENDED,
            'guess_winner_id' => $pembeli->id,
            'winner_priority_until' => now()->subMinute(),
        ]);

        $this->assertEquals(1_000_000, $produk->effectivePriceFor($pembeli));
    }

    /**
     * Harga diskon adalah angka yang sedang ditebak, jadi wajib disembunyikan.
     * Harga normal justru harus tetap terlihat sebagai patokan menebak.
     */
    public function test_harga_diskon_disembunyikan_selama_periode_tebak(): void
    {
        $produk = $this->produkTebakHarga(['guess_status' => Product::GUESS_ACTIVE]);

        $serialisasi = $produk->maskRealPriceFor($this->makeUser('pembeli1'))->toArray();

        $this->assertArrayNotHasKey('guess_discount_price', $serialisasi, 'Harga diskon bocor ke pembeli.');
        $this->assertArrayHasKey('price', $serialisasi, 'Harga normal seharusnya tetap terlihat sebagai patokan.');
    }

    public function test_pemenang_boleh_melihat_harga_diskon(): void
    {
        $pembeli = $this->makeUser('pembeli1');
        $produk = $this->produkTebakHarga([
            'guess_status' => Product::GUESS_ENDED,
            'guess_winner_id' => $pembeli->id,
            'winner_priority_until' => now()->addHours(24),
        ]);

        $this->assertArrayHasKey('guess_discount_price', $produk->maskRealPriceFor($pembeli)->toArray());
    }
}
