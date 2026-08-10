<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\TradeInRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Selisih harga tukar tambah berlaku DUA ARAH.
 *
 * Sebelumnya selisih hanya dihitung dengan max(0, hargaDiminta - hargaDitawarkan),
 * sehingga tukar tambah dengan produk pengaju yang LEBIH MAHAL menghasilkan
 * additional_cash = 0. Akibatnya tidak ada pihak yang ditagih dan tukar tambah
 * langsung melompat ke tahap saling kirim resi — pengaju menyerahkan produk
 * yang lebih mahal secara cuma-cuma.
 */
class TradeInPriceDifferenceTest extends TestCase
{
    use RefreshDatabase;

    private User $requesterUser;

    private User $responderUser;

    private Store $requesterStore;

    private Store $responderStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requesterUser = $this->makeSeller('a');
        $this->responderUser = $this->makeSeller('b');

        $this->requesterStore = $this->makeStore($this->requesterUser, 'Toko A');
        $this->responderStore = $this->makeStore($this->responderUser, 'Toko B');
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

    /**
     * Ajukan tukar tambah lalu setujui, dengan harga yang ditentukan per pihak.
     */
    private function tradeInWith(int $offeredPrice, int $requestedPrice): TradeInRequest
    {
        $offered = $this->makeProduct($this->requesterStore, 'Jam Dinding', $offeredPrice);
        $requested = $this->makeProduct($this->responderStore, 'Sepeda Ontel', $requestedPrice);

        $this->actingAs($this->requesterUser)->post(
            '/seller/tukar-tambah/'.$requested->public_id,
            ['offered_product_id' => $offered->public_id]
        );

        return TradeInRequest::firstOrFail();
    }

    /**
     * Kasus yang dilaporkan: produk pengaju Rp 5jt ditukar produk Rp 2jt.
     * Selisih Rp 3jt harus ditagih ke PENERIMA, bukan diabaikan.
     */
    public function test_penerima_membayar_bila_produk_pengaju_lebih_mahal(): void
    {
        $tradeIn = $this->tradeInWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->assertEquals(3_000_000, $tradeIn->additional_cash);
        $this->assertSame(TradeInRequest::PAYER_RESPONDER, $tradeIn->payer_role);
        $this->assertTrue($tradeIn->requiresPayment());
        $this->assertSame($this->responderStore->id, $tradeIn->payerStoreId());
        $this->assertSame($this->requesterStore->id, $tradeIn->payoutRecipientStoreId());
    }

    public function test_pengaju_membayar_bila_produknya_lebih_murah(): void
    {
        $tradeIn = $this->tradeInWith(offeredPrice: 2_000_000, requestedPrice: 5_000_000);

        $this->assertEquals(3_000_000, $tradeIn->additional_cash);
        $this->assertSame(TradeInRequest::PAYER_REQUESTER, $tradeIn->payer_role);
        $this->assertSame($this->requesterStore->id, $tradeIn->payerStoreId());
        $this->assertSame($this->responderStore->id, $tradeIn->payoutRecipientStoreId());
    }

    public function test_harga_sama_tidak_memerlukan_pembayaran(): void
    {
        $tradeIn = $this->tradeInWith(offeredPrice: 2_500_000, requestedPrice: 2_500_000);

        $this->assertEquals(0, $tradeIn->additional_cash);
        $this->assertNull($tradeIn->payer_role);
        $this->assertFalse($tradeIn->requiresPayment());
        $this->assertNull($tradeIn->payerStoreId());
    }

    /**
     * Inti bug-nya: tukar tambah dengan selisih TIDAK boleh langsung masuk tahap
     * saling kirim resi. Ia harus menunggu pembayaran lebih dulu.
     */
    public function test_tukar_tambah_dengan_selisih_terbalik_tidak_langsung_masuk_pengiriman(): void
    {
        $tradeIn = $this->tradeInWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/accept');

        $tradeIn->refresh();

        $this->assertSame(TradeInRequest::STATUS_ACCEPTED, $tradeIn->status);
        $this->assertSame(TradeInRequest::PAYMENT_PENDING, $tradeIn->payment_status);
        $this->assertNull($tradeIn->shipping_started_at);
    }

    public function test_penerima_boleh_membuka_halaman_pembayaran_selisih(): void
    {
        $tradeIn = $this->tradeInWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/accept');

        // Snap token diisi lebih dulu agar pay() memakai cabang token lama dan
        // test ini tidak perlu membuat transaksi Midtrans baru. Pengecekan
        // status ke Midtrans dipalsukan sebagai "belum dibayar".
        $tradeIn->refresh()->forceFill(['snap_token' => 'dummy-snap-token'])->save();

        Http::fake([
            '*' => Http::response(['transaction_status' => 'pending'], 200),
        ]);

        $this->actingAs($this->responderUser)
            ->get('/seller/tukar-tambah/'.$tradeIn->public_id.'/pay')
            ->assertOk();

        // Pengaju BUKAN pembayar di skenario ini, jadi harus ditolak.
        $this->actingAs($this->requesterUser)
            ->get('/seller/tukar-tambah/'.$tradeIn->public_id.'/pay')
            ->assertForbidden();
    }

    /**
     * Halaman tukar tambah menentukan tombol "Bayar Sekarang" dari prop is_payer /
     * can_pay. Tanpa prop ini tombolnya tidak pernah muncul untuk penerima.
     */
    public function test_halaman_tukar_tambah_mengirim_peran_pembayar_ke_frontend(): void
    {
        $tradeIn = $this->tradeInWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/accept');

        // Penerima adalah pembayarnya: tombol bayar harus aktif di tab masuk.
        $this->actingAs($this->responderUser)
            ->get('/seller/tukar-tambah')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('incomingRequests.0.is_payer', true)
                    ->where('incomingRequests.0.can_pay', true)
                    ->where('incomingRequests.0.payer_label', 'penerima tukar tambah')
            );

        // Pengaju bukan pembayar: tombolnya harus mati di tab keluar.
        $this->actingAs($this->requesterUser)
            ->get('/seller/tukar-tambah')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('outgoingRequests.0.is_payer', false)
                    ->where('outgoingRequests.0.can_pay', false)
            );
    }

    /**
     * Halaman pembayaran mem-polling endpoint ini untuk menandai lunas ketika
     * webhook Midtrans tidak sampai (kondisi normal di localhost). Endpoint-nya
     * sempat terkunci ke pengaju, sehingga saat penerima yang membayar,
     * polling selalu 403: status mentok "menunggu bayar" dan tukar tambah tidak
     * pernah masuk tahap resi.
     */
    public function test_penerima_pembayar_boleh_memeriksa_status_pembayaran(): void
    {
        $tradeIn = $this->tradeInWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/accept');

        // payment_reference dikosongkan agar syncPaymentFromMidtrans() berhenti
        // lebih awal — test ini menguji otorisasinya, bukan panggilan Midtrans.
        $tradeIn->refresh()->forceFill(['payment_reference' => null])->save();

        $this->actingAs($this->responderUser)
            ->getJson('/seller/tukar-tambah/'.$tradeIn->public_id.'/payment-status')
            ->assertOk()
            ->assertJson(['is_paid' => false, 'payment_status' => TradeInRequest::PAYMENT_PENDING]);

        // Pengaju bukan pembayarnya di skenario ini.
        $this->actingAs($this->requesterUser)
            ->getJson('/seller/tukar-tambah/'.$tradeIn->public_id.'/payment-status')
            ->assertForbidden();
    }

    /**
     * Jalur lengkap yang gagal di localhost: webhook tidak sampai, jadi polling
     * dari halaman pembayaran yang harus menandai lunas dan membuka tahap resi.
     */
    public function test_polling_menandai_lunas_dan_membuka_tahap_pengiriman(): void
    {
        config(['services.midtrans.server_key' => 'dummy-server-key']);

        $tradeIn = $this->tradeInWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/accept');

        Http::fake([
            '*' => Http::response([
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'transaction_id' => 'TRX-123',
            ], 200),
        ]);

        $this->actingAs($this->responderUser)
            ->getJson('/seller/tukar-tambah/'.$tradeIn->public_id.'/payment-status')
            ->assertOk()
            ->assertJson(['is_paid' => true]);

        $tradeIn->refresh();

        $this->assertSame(TradeInRequest::PAYMENT_PAID, $tradeIn->payment_status);
        $this->assertSame(TradeInRequest::STATUS_SHIPPING, $tradeIn->status);
        $this->assertNotNull($tradeIn->shipping_started_at);
        $this->assertNotNull($tradeIn->paid_at);
    }

    /**
     * Setelah pembayaran lunas, tukar tambah WAJIB masuk tahap saling kirim resi.
     * Tanpa ini form resinya tidak pernah muncul untuk kedua seller.
     */
    public function test_tukar_tambah_lunas_masuk_tahap_pengiriman_dan_menampilkan_form_resi(): void
    {
        $tradeIn = $this->tradeInWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/accept');

        // Simulasikan webhook Midtrans yang menandai lunas.
        $tradeIn->refresh()->forceFill([
            'payment_status' => TradeInRequest::PAYMENT_PAID,
            'paid_at' => now(),
            'status' => TradeInRequest::STATUS_SHIPPING,
            'shipping_started_at' => now(),
        ])->save();

        // Kedua seller harus bisa mengisi resi.
        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-B-1'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-A-1'])
            ->assertSessionHasNoErrors();

        $tradeIn->refresh();

        $this->assertSame('JNE-A-1', $tradeIn->requester_tracking_number);
        $this->assertSame('JNE-B-1', $tradeIn->responder_tracking_number);
    }

    /**
     * Pengembalian dana adalah hak pembayarnya. Ketika penerima yang membayar,
     * pengaju tidak boleh bisa menariknya.
     */
    public function test_pengaju_tidak_dapat_meminta_refund_selisih_yang_dibayar_penerima(): void
    {
        $tradeIn = $this->tradeInWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/accept');

        $tradeIn->refresh()->forceFill([
            'payment_status' => TradeInRequest::PAYMENT_PAID,
            'status' => TradeInRequest::STATUS_SHIPPING,
            'paid_at' => now(),
        ])->save();

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/refund', [
                'reason' => 'Saya ingin uang saya kembali karena tukar tambahnya macet.',
            ])
            ->assertForbidden();

        $this->assertTrue($tradeIn->fresh()->canRequestRefundBy($this->responderStore));
    }
}
