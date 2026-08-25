<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\TradeInRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression test untuk temuan V2-01 / V3-02 / V3-10.
 *
 * Sejak tukar tambah disetujui, kedua produk dikunci dari penjualan. Tahap
 * "menunggu pembayaran selisih" adalah satu-satunya tahap yang tidak pernah
 * punya batas waktu maupun tombol keluar: `cancel()` dan `reject()` hanya
 * menerima status `pending` — yaitu sebelum produknya terkunci — sementara
 * `unlockProductsForTradeIn()` tidak pernah dipanggil dari mana pun. Dua produk
 * milik dua toko berbeda bisa membeku selamanya karena satu pihak diam.
 */
class TradeInPaymentDeadlineTest extends TestCase
{
    use RefreshDatabase;

    private User $requesterUser;

    private User $responderUser;

    private Store $requesterStore;

    private Store $responderStore;

    private Product $offeredProduct;

    private Product $requestedProduct;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.midtrans.server_key', 'SB-Mid-server-testkey');

        $this->requesterUser = $this->makeSeller('a');
        $this->responderUser = $this->makeSeller('b');

        $this->requesterStore = $this->makeStore($this->requesterUser, 'Toko A');
        $this->responderStore = $this->makeStore($this->responderUser, 'Toko B');

        // Harga berbeda supaya ada selisih yang harus dibayar. Produk pengaju
        // lebih murah, jadi pengajulah pembayarnya.
        $this->offeredProduct = $this->makeProduct($this->requesterStore, 'Keris Jawa', 3_000_000);
        $this->requestedProduct = $this->makeProduct($this->responderStore, 'Guci Ming', 5_000_000);
    }

    private function makeSeller(string $suffix): User
    {
        return User::create([
            'username' => 'seller'.$suffix,
            'first_name' => 'Seller',
            'last_name' => strtoupper($suffix),
            'email' => "seller{$suffix}@vinstore.test",
            'phone' => '0811000'.$suffix,
            'address' => 'Jl. Antik',
            'password' => 'password',
            'role' => 'seller',
        ]);
    }

    private function makeStore(User $user, string $name): Store
    {
        return Store::create([
            'user_id' => $user->id,
            'store_name' => $name,
            'category' => 'Antik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);
    }

    private function makeProduct(Store $store, string $name, int $price): Product
    {
        return Product::create([
            'store_id' => $store->id,
            'name' => $name,
            'stock' => 1,
            'price' => $price,
            'category' => 'Antik',
            'description' => 'Barang antik',
            'approval_status' => Product::STATUS_APPROVED,
            'is_trade_in_enabled' => true,
        ]);
    }

    /** Ajukan tukar tambah berselisih uang, lalu setujui. */
    private function acceptedTradeIn(): TradeInRequest
    {
        $this->actingAs($this->requesterUser)->post(
            '/seller/tukar-tambah/'.$this->requestedProduct->public_id,
            ['offered_product_id' => $this->offeredProduct->public_id]
        );

        $tradeIn = TradeInRequest::firstOrFail();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/accept');

        return $tradeIn->fresh();
    }

    /**
     * Midtrans tidak pernah dihubungi sungguhan.
     *
     * Dipasang per test, bukan sekali di setUp(): `Http::fake()` menumpuk stub
     * dan yang dipakai adalah stub PERTAMA yang cocok, sehingga stub bawaan
     * dari setUp() akan selalu mengalahkan stub yang dipasang belakangan.
     */
    private function midtransMenjawab(string $transactionStatus): void
    {
        Http::fake([
            '*' => Http::response([
                'transaction_status' => $transactionStatus,
                'transaction_id' => 'trx-uji',
            ], 200),
        ]);
    }

    /**
     * Tukar tambah tanpa selisih uang: harganya disamakan lebih dulu supaya
     * persetujuan langsung masuk tahap saling kirim.
     */
    private function tukarTambahTanpaSelisih(): TradeInRequest
    {
        $this->offeredProduct->forceFill(['price' => 5_000_000])->save();

        return $this->acceptedTradeIn();
    }

    private function assertProdukTerkunci(bool $terkunci): void
    {
        $harapan = $terkunci ? $this->assertNotNull(...) : $this->assertNull(...);

        $harapan($this->offeredProduct->fresh()->locked_for_trade_in_id);
        $harapan($this->requestedProduct->fresh()->locked_for_trade_in_id);
    }

    // ------------------------------------------------------------------
    // Tenggat dipasang saat disetujui
    // ------------------------------------------------------------------

    public function test_persetujuan_memasang_tenggat_pembayaran(): void
    {
        $tradeIn = $this->acceptedTradeIn();

        $this->assertSame(TradeInRequest::STATUS_ACCEPTED, $tradeIn->status);
        $this->assertSame(TradeInRequest::PAYMENT_PENDING, $tradeIn->payment_status);
        $this->assertNotNull($tradeIn->payment_due_at);
        $this->assertEqualsWithDelta(
            now()->addHours(TradeInRequest::PAYMENT_DEADLINE_HOURS)->timestamp,
            $tradeIn->payment_due_at->timestamp,
            60
        );
        $this->assertProdukTerkunci(true);
    }

    // ------------------------------------------------------------------
    // Jalan keluar manual
    // ------------------------------------------------------------------

    public function test_pembayar_dapat_membatalkan_kapan_saja_dan_kunci_produk_terlepas(): void
    {
        $this->midtransMenjawab('expire');

        $tradeIn = $this->acceptedTradeIn();

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/cancel-unpaid')
            ->assertSessionHas('success');

        $tradeIn->refresh();

        $this->assertSame(TradeInRequest::STATUS_CANCELLED, $tradeIn->status);
        $this->assertSame(TradeInRequest::PAYMENT_EXPIRED, $tradeIn->payment_status);
        $this->assertProdukTerkunci(false);
    }

    public function test_pihak_lawan_belum_boleh_membatalkan_sebelum_tenggat(): void
    {
        $this->midtransMenjawab('expire');

        $tradeIn = $this->acceptedTradeIn();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/cancel-unpaid')
            ->assertSessionHas('error');

        $this->assertSame(TradeInRequest::STATUS_ACCEPTED, $tradeIn->fresh()->status);
        $this->assertProdukTerkunci(true);
    }

    public function test_pihak_lawan_boleh_membatalkan_setelah_tenggat_lewat(): void
    {
        $this->midtransMenjawab('expire');

        $tradeIn = $this->acceptedTradeIn();
        $tradeIn->forceFill(['payment_due_at' => now()->subMinute()])->save();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/cancel-unpaid')
            ->assertSessionHas('success');

        $this->assertSame(TradeInRequest::STATUS_CANCELLED, $tradeIn->fresh()->status);
        $this->assertProdukTerkunci(false);
    }

    public function test_pihak_luar_tidak_dapat_membatalkan(): void
    {
        $this->midtransMenjawab('expire');

        $tradeIn = $this->acceptedTradeIn();

        $orangLain = $this->makeSeller('c');
        $this->makeStore($orangLain, 'Toko C');

        $this->actingAs($orangLain)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/cancel-unpaid')
            ->assertForbidden();

        $this->assertSame(TradeInRequest::STATUS_ACCEPTED, $tradeIn->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Penjadwal
    // ------------------------------------------------------------------

    public function test_penjadwal_membatalkan_tukar_tambah_yang_lewat_tenggat(): void
    {
        $this->midtransMenjawab('expire');

        $tradeIn = $this->acceptedTradeIn();
        $tradeIn->forceFill(['payment_due_at' => now()->subHour()])->save();

        Artisan::call('trade-in:expire');

        $tradeIn->refresh();

        $this->assertSame(TradeInRequest::STATUS_CANCELLED, $tradeIn->status);
        $this->assertSame(TradeInRequest::PAYMENT_EXPIRED, $tradeIn->payment_status);
        $this->assertProdukTerkunci(false);
    }

    public function test_penjadwal_tidak_menyentuh_yang_belum_lewat_tenggat(): void
    {
        $this->midtransMenjawab('expire');

        $tradeIn = $this->acceptedTradeIn();

        Artisan::call('trade-in:expire');

        $this->assertSame(TradeInRequest::STATUS_ACCEPTED, $tradeIn->fresh()->status);
        $this->assertProdukTerkunci(true);
    }

    public function test_penjadwal_tidak_membatalkan_yang_ternyata_sudah_lunas(): void
    {
        $tradeIn = $this->acceptedTradeIn();
        $tradeIn->forceFill(['payment_due_at' => now()->subHour()])->save();

        // Midtrans menjawab bahwa uangnya sudah diterima, hanya webhook-nya yang
        // tidak pernah sampai — persoalan yang sama dengan temuan V7-01.
        $this->midtransMenjawab('settlement');

        Artisan::call('trade-in:expire');

        $tradeIn->refresh();

        $this->assertSame(TradeInRequest::PAYMENT_PAID, $tradeIn->payment_status);
        $this->assertSame(TradeInRequest::STATUS_SHIPPING, $tradeIn->status);
        $this->assertProdukTerkunci(true);
    }

    public function test_penjadwal_idempoten(): void
    {
        $this->midtransMenjawab('expire');

        $tradeIn = $this->acceptedTradeIn();
        $tradeIn->forceFill(['payment_due_at' => now()->subHour()])->save();

        Artisan::call('trade-in:expire');
        Artisan::call('trade-in:expire');

        $this->assertSame(TradeInRequest::STATUS_CANCELLED, $tradeIn->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Jalan buntu kedua: tahap saling kirim yang sama-sama pasif
    // ------------------------------------------------------------------

    /**
     * Tukar tambah tanpa selisih uang langsung masuk tahap saling kirim, dan
     * tahap itu punya tenggatnya sendiri. Persoalannya, syarat melapor dulu
     * menuntut pelapor sudah mengirim barangnya — sehingga ketika KEDUA pihak
     * sama-sama diam, tidak ada satu pun yang boleh melapor dan tidak ada
     * penjadwal yang menyentuh tahap ini. Dua produk membeku selamanya.
     */
    public function test_kedua_pihak_sama_sama_tidak_mengirim_tetap_punya_jalan_keluar(): void
    {
        $tradeIn = $this->tukarTambahTanpaSelisih();

        $this->assertSame(TradeInRequest::STATUS_SHIPPING, $tradeIn->status);

        $tradeIn->forceFill([
            'shipping_started_at' => now()->subDays(TradeInRequest::SHIPPING_DEADLINE_DAYS + 1),
        ])->save();

        $this->assertTrue($tradeIn->fresh()->canReportStalledBy($this->requesterStore));
        $this->assertTrue($tradeIn->fresh()->canReportStalledBy($this->responderStore));

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/report', [
                'reason' => 'Kami berdua tidak jadi melanjutkan tukar tambah ini.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('refund_requests', 1);
    }

    /**
     * Test kontrol: yang lalai tetap tidak boleh melaporkan yang taat.
     * Tanpa ini, pelonggaran di atas mudah melebar menjadi "siapa pun boleh
     * melapor kapan pun".
     */
    public function test_yang_belum_mengirim_tidak_boleh_melaporkan_yang_sudah_mengirim(): void
    {
        $tradeIn = $this->tukarTambahTanpaSelisih();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'RESI-123']);

        $tradeIn->forceFill([
            'shipping_started_at' => now()->subDays(TradeInRequest::SHIPPING_DEADLINE_DAYS + 1),
        ])->save();

        $this->assertFalse($tradeIn->fresh()->canReportStalledBy($this->requesterStore));
        $this->assertTrue($tradeIn->fresh()->canReportStalledBy($this->responderStore));
    }

    /**
     * Produk yang kuncinya sudah dilepas harus benar-benar dapat dijual lagi —
     * bukan sekadar berubah nilai kolomnya.
     */
    public function test_produk_dapat_dibeli_lagi_setelah_tukar_tambah_batal(): void
    {
        $this->midtransMenjawab('expire');

        $tradeIn = $this->acceptedTradeIn();
        $tradeIn->forceFill(['payment_due_at' => now()->subHour()])->save();

        Artisan::call('trade-in:expire');

        $pembeli = User::create([
            'username' => 'pembeli',
            'first_name' => 'Pem',
            'last_name' => 'Beli',
            'email' => 'pembeli@vinstore.test',
            'phone' => '08210001',
            'address' => 'Jl. Pembeli',
            'password' => 'password',
            'role' => 'user',
        ]);

        $this->actingAs($pembeli)
            ->post('/cart/add/'.$this->requestedProduct->public_id, ['quantity' => 1])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('carts', 1);
    }
}
