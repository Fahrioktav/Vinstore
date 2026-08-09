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
 * Aturan penentuan pemenang tebak harga ketika lebih dari satu orang menebak.
 *
 * Pertanyaan yang dijawab di sini: siapa yang menang kalau dua orang menebak
 * angka yang sama, atau sama-sama dekat? Dan apa yang terjadi kalau ada yang
 * menebak persis tepat?
 *
 * Aturannya, berurutan:
 *   1. Yang meleset di luar ambang toleransi tidak ikut dinilai.
 *   2. Selisih paling kecil menang.
 *   3. Seri -> yang menebak lebih dulu menang.
 *   4. Tebakan persis tepat menutup sesinya seketika.
 */
class TebakHargaPemenangTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        Category::create(['name' => 'Antik']);

        $this->seller = $this->makeUser('penjualpemenang', 'seller');
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
     * Harga normal Rp 1.000.000, harga diskon Rp 800.000.
     * Ambang toleransi 5% dari harga diskon = Rp 40.000.
     */
    private function produk(array $attrs = []): Product
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

    public function test_tebakan_sama_persis_dimenangkan_penebak_pertama(): void
    {
        $produk = $this->produk();

        $duluan = $this->makeUser('duluan');
        $belakangan = $this->makeUser('belakangan');

        // Keduanya menebak angka yang sama; yang pertama menebak 30 menit lalu.
        $this->tebak($produk, $duluan, 790_000, menitLalu: 30);
        $this->tebak($produk, $belakangan, 790_000, menitLalu: 5);

        app(PriceGuessService::class)->finalize($produk);
        $produk->refresh();

        $this->assertSame($duluan->id, $produk->guess_winner_id);
    }

    public function test_yang_lebih_dekat_menang_walau_menebak_belakangan(): void
    {
        $produk = $this->produk();

        $duluanTapiJauh = $this->makeUser('duluanjauh');
        $belakanganTapiDekat = $this->makeUser('belakangandekat');

        // Meleset 30.000, masih di dalam ambang 40.000.
        $this->tebak($produk, $duluanTapiJauh, 770_000, menitLalu: 60);
        // Meleset 5.000.
        $this->tebak($produk, $belakanganTapiDekat, 795_000, menitLalu: 1);

        app(PriceGuessService::class)->finalize($produk);
        $produk->refresh();

        $this->assertSame($belakanganTapiDekat->id, $produk->guess_winner_id);
        $this->assertEquals(795_000, $produk->guess_winning_amount);
    }

    public function test_tebakan_di_atas_dan_di_bawah_dinilai_sama_adilnya(): void
    {
        $produk = $this->produk();

        $diBawah = $this->makeUser('dibawah');
        $diAtas = $this->makeUser('diatas');

        // Sama-sama meleset 10.000, satu ke bawah satu ke atas.
        // Yang menebak lebih dulu menang.
        $this->tebak($produk, $diAtas, 810_000, menitLalu: 40);
        $this->tebak($produk, $diBawah, 790_000, menitLalu: 10);

        app(PriceGuessService::class)->finalize($produk);
        $produk->refresh();

        $this->assertSame($diAtas->id, $produk->guess_winner_id);
    }

    public function test_yang_di_luar_ambang_kalah_dari_yang_di_dalam_ambang(): void
    {
        $produk = $this->produk();

        $jauh = $this->makeUser('jauhsekali');
        $dekat = $this->makeUser('cukupdekat');

        $this->tebak($produk, $jauh, 700_000, menitLalu: 60);   // meleset 100.000
        $this->tebak($produk, $dekat, 830_000, menitLalu: 5);   // meleset 30.000

        app(PriceGuessService::class)->finalize($produk);
        $produk->refresh();

        $this->assertSame($dekat->id, $produk->guess_winner_id);
    }

    public function test_tebakan_tepat_menutup_sesi_seketika(): void
    {
        $produk = $this->produk([
            // Periodenya masih panjang — inilah yang membuktikan sesinya
            // benar-benar ditutup lebih cepat, bukan karena waktunya habis.
            'guess_starts_at' => now()->subHour(),
            'guess_ends_at' => now()->addDays(3),
        ]);

        $penebakLain = $this->makeUser('penebaklain');
        $this->tebak($produk, $penebakLain, 790_000, menitLalu: 30);

        $jitu = $this->makeUser('jitu');

        $this->actingAs($jitu)
            ->post('/products/'.$produk->public_id.'/guess', ['amount' => 800_000])
            ->assertSessionHasNoErrors();

        $produk->refresh();

        $this->assertSame(Product::GUESS_ENDED, $produk->guess_status);
        $this->assertSame(Product::FINISH_EXACT_GUESS, $produk->guess_finished_reason);
        $this->assertSame($jitu->id, $produk->guess_winner_id);
        $this->assertEquals(800_000, $produk->guess_winning_amount);
        $this->assertNotNull($produk->winner_priority_until);
    }

    public function test_sesi_yang_sudah_ditutup_tidak_menerima_tebakan_lagi(): void
    {
        $produk = $this->produk([
            'guess_starts_at' => now()->subHour(),
            'guess_ends_at' => now()->addDays(3),
        ]);

        $jitu = $this->makeUser('jitu');
        $this->actingAs($jitu)->post('/products/'.$produk->public_id.'/guess', ['amount' => 800_000]);

        $terlambat = $this->makeUser('terlambat');
        $this->actingAs($terlambat)
            ->post('/products/'.$produk->public_id.'/guess', ['amount' => 799_000])
            ->assertSessionHas('error');

        $this->assertSame(1, PriceGuess::where('product_id', $produk->id)->count());
        $this->assertSame($jitu->id, $produk->fresh()->guess_winner_id);
    }

    public function test_tebakan_mendekati_biasa_tidak_menutup_sesi(): void
    {
        $produk = $this->produk([
            'guess_starts_at' => now()->subHour(),
            'guess_ends_at' => now()->addDays(3),
        ]);

        $this->actingAs($this->makeUser('hampir'))
            ->post('/products/'.$produk->public_id.'/guess', ['amount' => 799_500]);

        $produk->refresh();

        // Meleset Rp 500 — dekat, tapi bukan tepat. Sesi harus tetap berjalan
        // agar orang lain masih punya kesempatan.
        $this->assertSame(Product::GUESS_ACTIVE, $produk->guess_status);
        $this->assertNull($produk->guess_winner_id);
    }

    public function test_papan_tebakan_tidak_membocorkan_urutan_selama_sesi_berjalan(): void
    {
        $produk = $this->produk([
            'guess_starts_at' => now()->subHour(),
            'guess_ends_at' => now()->addDays(3),
        ]);

        $jauh = $this->makeUser('jauh');
        $dekat = $this->makeUser('dekat');

        $this->tebak($produk, $jauh, 500_000, menitLalu: 60);
        $this->tebak($produk, $dekat, 800_000, menitLalu: 5);

        $papan = app(PriceGuessService::class)->leaderboard($produk->fresh(), $jauh);

        $this->assertCount(2, $papan);

        // Urut waktu, terbaru di atas — BUKAN urut kedekatan.
        $this->assertSame('dekat', $papan[0]['username']);

        foreach ($papan as $baris) {
            $this->assertNull($baris['rank'], 'Peringkat bocor selama sesi berjalan.');
            $this->assertNull($baris['difference'], 'Selisih bocor selama sesi berjalan.');
        }
    }

    public function test_papan_tebakan_menampilkan_peringkat_setelah_sesi_selesai(): void
    {
        $produk = $this->produk();

        $jauh = $this->makeUser('jauh');
        $dekat = $this->makeUser('dekat');

        $this->tebak($produk, $jauh, 770_000, menitLalu: 60);
        $this->tebak($produk, $dekat, 795_000, menitLalu: 5);

        app(PriceGuessService::class)->finalize($produk);
        $produk->refresh();

        // Pemenang berhak melihat harga diskonnya, jadi selisih ikut tampil.
        $papan = app(PriceGuessService::class)->leaderboard($produk, $dekat);

        $this->assertSame(1, $papan[0]['rank']);
        $this->assertSame('dekat', $papan[0]['username']);
        $this->assertTrue($papan[0]['is_winner']);
        $this->assertEqualsWithDelta(5_000, $papan[0]['difference'], 0.01);

        $this->assertSame(2, $papan[1]['rank']);
        $this->assertFalse($papan[1]['is_winner']);
    }

    public function test_selisih_disembunyikan_dari_yang_belum_berhak_melihat_harga_diskon(): void
    {
        $produk = $this->produk();

        $dekat = $this->makeUser('dekat');
        $kalah = $this->makeUser('kalah');

        $this->tebak($produk, $dekat, 795_000, menitLalu: 60);
        $this->tebak($produk, $kalah, 780_000, menitLalu: 5);

        app(PriceGuessService::class)->finalize($produk);
        $produk->refresh();

        // Yang kalah belum boleh melihat harga diskon selama masa prioritas,
        // jadi selisihnya pun tidak boleh ditampilkan — dari selisih, harga
        // diskonnya bisa dihitung mundur.
        $papan = app(PriceGuessService::class)->leaderboard($produk, $kalah);

        foreach ($papan as $baris) {
            $this->assertNull($baris['difference']);
        }

        // Peringkat tetap tampil: itulah gunanya papan hasil.
        $this->assertSame(1, $papan[0]['rank']);
    }
}
