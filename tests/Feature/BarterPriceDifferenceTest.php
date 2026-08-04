<?php

namespace Tests\Feature;

use App\Models\BarterRequest;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Selisih harga barter berlaku DUA ARAH.
 *
 * Sebelumnya selisih hanya dihitung dengan max(0, hargaDiminta - hargaDitawarkan),
 * sehingga barter dengan produk pengaju yang LEBIH MAHAL menghasilkan
 * additional_cash = 0. Akibatnya tidak ada pihak yang ditagih dan barter
 * langsung melompat ke tahap saling kirim resi — pengaju menyerahkan produk
 * yang lebih mahal secara cuma-cuma.
 */
class BarterPriceDifferenceTest extends TestCase
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
            'is_barterable' => true,
        ]);
    }

    /**
     * Ajukan barter lalu setujui, dengan harga yang ditentukan per pihak.
     */
    private function barterWith(int $offeredPrice, int $requestedPrice): BarterRequest
    {
        $offered = $this->makeProduct($this->requesterStore, 'Jam Dinding', $offeredPrice);
        $requested = $this->makeProduct($this->responderStore, 'Sepeda Ontel', $requestedPrice);

        $this->actingAs($this->requesterUser)->post(
            '/seller/barter/'.$requested->public_id,
            ['offered_product_id' => $offered->public_id]
        );

        return BarterRequest::firstOrFail();
    }

    /**
     * Kasus yang dilaporkan: produk pengaju Rp 5jt ditukar produk Rp 2jt.
     * Selisih Rp 3jt harus ditagih ke PENERIMA, bukan diabaikan.
     */
    public function test_penerima_membayar_bila_produk_pengaju_lebih_mahal(): void
    {
        $barter = $this->barterWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->assertEquals(3_000_000, $barter->additional_cash);
        $this->assertSame(BarterRequest::PAYER_RESPONDER, $barter->payer_role);
        $this->assertTrue($barter->requiresPayment());
        $this->assertSame($this->responderStore->id, $barter->payerStoreId());
        $this->assertSame($this->requesterStore->id, $barter->payoutRecipientStoreId());
    }

    public function test_pengaju_membayar_bila_produknya_lebih_murah(): void
    {
        $barter = $this->barterWith(offeredPrice: 2_000_000, requestedPrice: 5_000_000);

        $this->assertEquals(3_000_000, $barter->additional_cash);
        $this->assertSame(BarterRequest::PAYER_REQUESTER, $barter->payer_role);
        $this->assertSame($this->requesterStore->id, $barter->payerStoreId());
        $this->assertSame($this->responderStore->id, $barter->payoutRecipientStoreId());
    }

    public function test_harga_sama_tidak_memerlukan_pembayaran(): void
    {
        $barter = $this->barterWith(offeredPrice: 2_500_000, requestedPrice: 2_500_000);

        $this->assertEquals(0, $barter->additional_cash);
        $this->assertNull($barter->payer_role);
        $this->assertFalse($barter->requiresPayment());
        $this->assertNull($barter->payerStoreId());
    }

    /**
     * Inti bug-nya: barter dengan selisih TIDAK boleh langsung masuk tahap
     * saling kirim resi. Ia harus menunggu pembayaran lebih dulu.
     */
    public function test_barter_dengan_selisih_terbalik_tidak_langsung_masuk_pengiriman(): void
    {
        $barter = $this->barterWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/accept');

        $barter->refresh();

        $this->assertSame(BarterRequest::STATUS_ACCEPTED, $barter->status);
        $this->assertSame(BarterRequest::PAYMENT_PENDING, $barter->payment_status);
        $this->assertNull($barter->shipping_started_at);
    }

    public function test_penerima_boleh_membuka_halaman_pembayaran_selisih(): void
    {
        $barter = $this->barterWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/accept');

        // Snap token diisi lebih dulu agar pay() memakai cabang token lama dan
        // test ini tidak perlu membuat transaksi Midtrans baru. Pengecekan
        // status ke Midtrans dipalsukan sebagai "belum dibayar".
        $barter->refresh()->forceFill(['snap_token' => 'dummy-snap-token'])->save();

        Http::fake([
            '*' => Http::response(['transaction_status' => 'pending'], 200),
        ]);

        $this->actingAs($this->responderUser)
            ->get('/seller/barter/'.$barter->public_id.'/pay')
            ->assertOk();

        // Pengaju BUKAN pembayar di skenario ini, jadi harus ditolak.
        $this->actingAs($this->requesterUser)
            ->get('/seller/barter/'.$barter->public_id.'/pay')
            ->assertForbidden();
    }

    /**
     * Halaman barter menentukan tombol "Bayar Sekarang" dari prop is_payer /
     * can_pay. Tanpa prop ini tombolnya tidak pernah muncul untuk penerima.
     */
    public function test_halaman_barter_mengirim_peran_pembayar_ke_frontend(): void
    {
        $barter = $this->barterWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/accept');

        // Penerima adalah pembayarnya: tombol bayar harus aktif di tab masuk.
        $this->actingAs($this->responderUser)
            ->get('/seller/barter')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('incomingRequests.0.is_payer', true)
                    ->where('incomingRequests.0.can_pay', true)
                    ->where('incomingRequests.0.payer_label', 'penerima barter')
            );

        // Pengaju bukan pembayar: tombolnya harus mati di tab keluar.
        $this->actingAs($this->requesterUser)
            ->get('/seller/barter')
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
     * polling selalu 403: status mentok "menunggu bayar" dan barter tidak
     * pernah masuk tahap resi.
     */
    public function test_penerima_pembayar_boleh_memeriksa_status_pembayaran(): void
    {
        $barter = $this->barterWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/accept');

        // payment_reference dikosongkan agar syncPaymentFromMidtrans() berhenti
        // lebih awal — test ini menguji otorisasinya, bukan panggilan Midtrans.
        $barter->refresh()->forceFill(['payment_reference' => null])->save();

        $this->actingAs($this->responderUser)
            ->getJson('/seller/barter/'.$barter->public_id.'/payment-status')
            ->assertOk()
            ->assertJson(['is_paid' => false, 'payment_status' => BarterRequest::PAYMENT_PENDING]);

        // Pengaju bukan pembayarnya di skenario ini.
        $this->actingAs($this->requesterUser)
            ->getJson('/seller/barter/'.$barter->public_id.'/payment-status')
            ->assertForbidden();
    }

    /**
     * Jalur lengkap yang gagal di localhost: webhook tidak sampai, jadi polling
     * dari halaman pembayaran yang harus menandai lunas dan membuka tahap resi.
     */
    public function test_polling_menandai_lunas_dan_membuka_tahap_pengiriman(): void
    {
        config(['services.midtrans.server_key' => 'dummy-server-key']);

        $barter = $this->barterWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/accept');

        Http::fake([
            '*' => Http::response([
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'transaction_id' => 'TRX-123',
            ], 200),
        ]);

        $this->actingAs($this->responderUser)
            ->getJson('/seller/barter/'.$barter->public_id.'/payment-status')
            ->assertOk()
            ->assertJson(['is_paid' => true]);

        $barter->refresh();

        $this->assertSame(BarterRequest::PAYMENT_PAID, $barter->payment_status);
        $this->assertSame(BarterRequest::STATUS_SHIPPING, $barter->status);
        $this->assertNotNull($barter->shipping_started_at);
        $this->assertNotNull($barter->paid_at);
    }

    /**
     * Setelah pembayaran lunas, barter WAJIB masuk tahap saling kirim resi.
     * Tanpa ini form resinya tidak pernah muncul untuk kedua seller.
     */
    public function test_barter_lunas_masuk_tahap_pengiriman_dan_menampilkan_form_resi(): void
    {
        $barter = $this->barterWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/accept');

        // Simulasikan webhook Midtrans yang menandai lunas.
        $barter->refresh()->forceFill([
            'payment_status' => BarterRequest::PAYMENT_PAID,
            'paid_at' => now(),
            'status' => BarterRequest::STATUS_SHIPPING,
            'shipping_started_at' => now(),
        ])->save();

        // Kedua seller harus bisa mengisi resi.
        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-B-1'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-A-1'])
            ->assertSessionHasNoErrors();

        $barter->refresh();

        $this->assertSame('JNE-A-1', $barter->requester_tracking_number);
        $this->assertSame('JNE-B-1', $barter->responder_tracking_number);
    }

    /**
     * Pengembalian dana adalah hak pembayarnya. Ketika penerima yang membayar,
     * pengaju tidak boleh bisa menariknya.
     */
    public function test_pengaju_tidak_dapat_meminta_refund_selisih_yang_dibayar_penerima(): void
    {
        $barter = $this->barterWith(offeredPrice: 5_000_000, requestedPrice: 2_000_000);

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/accept');

        $barter->refresh()->forceFill([
            'payment_status' => BarterRequest::PAYMENT_PAID,
            'status' => BarterRequest::STATUS_SHIPPING,
            'paid_at' => now(),
        ])->save();

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/refund', [
                'reason' => 'Saya ingin uang saya kembali karena barternya macet.',
            ])
            ->assertForbidden();

        $this->assertTrue($barter->fresh()->canRequestRefundBy($this->responderStore));
    }
}
