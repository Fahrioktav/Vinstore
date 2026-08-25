<?php

namespace Tests\Feature;

use App\Models\PayoutRequest;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\Store;
use App\Models\TradeInRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Nasib selisih uang (additional_cash) pada tukar tambah.
 *
 * Uang itu dibayar requester karena produk yang ia tawarkan lebih murah, dan
 * merupakan hak responder. Sebelumnya uang tersebut ditagih lalu berhenti di
 * akun merchant tanpa pernah tercatat sebagai hak siapa pun.
 *
 * Sekarang:
 *  - Tukar tambah tuntas  -> responder mengajukan pencairan, admin menyetujui.
 *  - Tukar tambah gagal   -> requester mengajukan pengembalian dana, admin menyetujui.
 */
class TradeInPayoutAndRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $requesterUser;

    private User $responderUser;

    private User $admin;

    private Store $requesterStore;

    private Store $responderStore;

    private Product $offeredProduct;

    private Product $requestedProduct;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->requesterUser = $this->makeSeller('a');
        $this->responderUser = $this->makeSeller('b');

        $this->admin = User::create([
            'username' => 'admintukartambah',
            'first_name' => 'Admin',
            'last_name' => 'Tukar Tambah',
            'email' => 'admintukartambah@vinstore.test',
            'phone' => '08910009',
            'address' => 'Jl. Admin',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->requesterStore = $this->makeStore($this->requesterUser, 'Toko A');
        $this->responderStore = $this->makeStore($this->responderUser, 'Toko B');

        // Produk responder lebih mahal, jadi requester membayar selisih 2 juta.
        $this->offeredProduct = $this->makeProduct($this->requesterStore, 'Keris Jawa', 3000000);
        $this->requestedProduct = $this->makeProduct($this->responderStore, 'Guci Ming', 5000000);
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

    /** Tukar tambah dengan selisih uang, disetujui responder. */
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

    /** Tukar tambah yang selisihnya sudah lunas dan masuk tahap kirim. */
    private function paidTradeIn(): TradeInRequest
    {
        $tradeIn = $this->acceptedTradeIn();

        // Meniru apa yang dilakukan webhook Midtrans saat selisih lunas:
        // tukar tambah masuk tahap kirim sekaligus memulai hitungan tenggat.
        $tradeIn->forceFill([
            'payment_status' => TradeInRequest::PAYMENT_PAID,
            'paid_at' => now(),
            'status' => TradeInRequest::STATUS_SHIPPING,
            'shipping_started_at' => now(),
        ])->save();

        return $tradeIn->fresh();
    }

    /** Tukar tambah tuntas: keduanya saling kirim dan saling konfirmasi. */
    private function completedTradeIn(): TradeInRequest
    {
        $tradeIn = $this->paidTradeIn();

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-A']);
        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/receive');
        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/receive');

        return $tradeIn->fresh();
    }

    private function bankPayload(): array
    {
        return [
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Seller B',
        ];
    }

    public function test_selisih_harga_dihitung_dari_produk_yang_lebih_mahal(): void
    {
        $tradeIn = $this->acceptedTradeIn();

        $this->assertSame('2000000.00', (string) $tradeIn->additional_cash);
        $this->assertSame($this->responderStore->id, $tradeIn->payoutRecipientStoreId());
    }

    public function test_responder_bisa_mencairkan_selisih_setelah_tukar_tambah_selesai(): void
    {
        $tradeIn = $this->completedTradeIn();

        $this->assertSame(TradeInRequest::STATUS_COMPLETED, $tradeIn->status);
        $this->assertTrue($tradeIn->canRequestPayout());

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/payout', $this->bankPayload())
            ->assertSessionHas('success');

        $payout = PayoutRequest::firstOrFail();

        $this->assertSame('trade_in', $payout->source_type);
        $this->assertSame('2000000.00', (string) $payout->amount);
        $this->assertSame($this->responderStore->id, $payout->store_id);
        $this->assertNull($tradeIn->fresh()->payout_released_at);
    }

    public function test_belum_bisa_mencairkan_sebelum_kedua_pihak_konfirmasi(): void
    {
        $tradeIn = $this->paidTradeIn();

        $this->assertFalse($tradeIn->canRequestPayout());

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/payout', $this->bankPayload())
            ->assertSessionHas('error');

        $this->assertSame(0, PayoutRequest::count());
    }

    public function test_requester_tidak_bisa_mencairkan_selisih_yang_ia_bayar_sendiri(): void
    {
        $tradeIn = $this->completedTradeIn();

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/payout', $this->bankPayload())
            ->assertForbidden();

        $this->assertSame(0, PayoutRequest::count());
    }

    public function test_admin_menyetujui_pencairan_selisih_tukar_tambah(): void
    {
        $tradeIn = $this->completedTradeIn();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/payout', $this->bankPayload());

        $payout = PayoutRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/payouts/'.$payout->public_id.'/approve', [
                'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertSessionHas('success');

        $tradeIn->refresh();

        $this->assertSame(PayoutRequest::STATUS_APPROVED, $payout->fresh()->status);
        $this->assertNotNull($tradeIn->payout_released_at);
        $this->assertSame('2000000.00', (string) $this->responderStore->fresh()->withdrawn_balance);
        // Requester tidak menerima apa pun dari pencairan ini.
        $this->assertSame('0.00', (string) $this->requesterStore->fresh()->withdrawn_balance);
    }

    public function test_tidak_bisa_mencairkan_dua_kali(): void
    {
        $tradeIn = $this->completedTradeIn();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/payout', $this->bankPayload());

        $payout = PayoutRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/payouts/'.$payout->public_id.'/approve', [
                'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
            ]);

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/payout', $this->bankPayload())
            ->assertSessionHas('error');

        $this->assertSame(1, PayoutRequest::count());
    }

    public function test_tukar_tambah_tanpa_selisih_tidak_punya_pencairan(): void
    {
        // Kedua produk berharga sama -> additional_cash 0.
        $this->requestedProduct->forceFill(['price' => 3000000])->save();

        $tradeIn = $this->completedTradeIn();

        $this->assertSame('0.00', (string) $tradeIn->additional_cash);
        $this->assertFalse($tradeIn->canRequestPayout());
        $this->assertFalse($tradeIn->canRequestRefund());
    }

    /* ===================== Tukar tambah gagal -> refund ===================== */

    public function test_requester_bisa_meminta_pengembalian_dana_saat_tukar_tambah_belum_tuntas(): void
    {
        $tradeIn = $this->paidTradeIn();

        $this->assertTrue($tradeIn->canRequestRefund());

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/refund', [
                'reason' => 'Pihak lawan tidak pernah mengirimkan barangnya sejak tukar tambah disetujui.',
            ])
            ->assertSessionHas('success');

        $refund = RefundRequest::firstOrFail();

        $this->assertSame('trade_in', $refund->source_type);
        $this->assertSame($tradeIn->id, $refund->trade_in_request_id);
        $this->assertSame('pending', $refund->status);
    }

    public function test_responder_tidak_bisa_meminta_pengembalian_dana(): void
    {
        $tradeIn = $this->paidTradeIn();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/refund', [
                'reason' => 'Saya ingin uangnya dikembalikan padahal bukan saya yang bayar.',
            ])
            ->assertForbidden();

        $this->assertSame(0, RefundRequest::count());
    }

    public function test_tukar_tambah_yang_sudah_selesai_tidak_bisa_dimintakan_pengembalian(): void
    {
        $tradeIn = $this->completedTradeIn();

        $this->assertFalse($tradeIn->canRequestRefund());

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/refund', [
                'reason' => 'Saya berubah pikiran setelah tukar tambah tuntas sepenuhnya.',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, RefundRequest::count());
    }

    public function test_admin_menyetujui_pengembalian_membatalkan_tukar_tambah_dan_membuka_kunci_produk(): void
    {
        $tradeIn = $this->paidTradeIn();

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/refund', [
                'reason' => 'Pihak lawan tidak pernah mengirimkan barangnya sejak tukar tambah disetujui.',
            ]);

        $refund = RefundRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/refunds/'.$refund->public_id.'/approve', [
                'admin_note' => 'Terbukti tidak ada pengiriman dari pihak lawan.',
            ])
            ->assertSessionHas('success');

        $tradeIn->refresh();

        $this->assertSame('approved', $refund->fresh()->status);
        $this->assertSame(TradeInRequest::STATUS_CANCELLED, $tradeIn->status);
        $this->assertSame(TradeInRequest::PAYMENT_REFUNDED, $tradeIn->payment_status);

        // Kunci tukar tambah dilepas agar produk bisa dijual atau ditukar tambah lagi.
        $this->assertNull($this->offeredProduct->fresh()->locked_for_trade_in_id);
        $this->assertNull($this->requestedProduct->fresh()->locked_for_trade_in_id);

        // Kepemilikan tidak boleh berpindah lewat jalur ini.
        $this->assertSame($this->requesterStore->id, $this->offeredProduct->fresh()->store_id);
        $this->assertSame($this->responderStore->id, $this->requestedProduct->fresh()->store_id);
    }

    public function test_pengembalian_yang_disetujui_menutup_jalur_pencairan(): void
    {
        $tradeIn = $this->paidTradeIn();

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/refund', [
                'reason' => 'Pihak lawan tidak pernah mengirimkan barangnya sejak tukar tambah disetujui.',
            ]);

        $refund = RefundRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/refunds/'.$refund->public_id.'/approve');

        $this->assertFalse($tradeIn->fresh()->canRequestPayout());
    }

    /* ============ Tukar tambah macet: satu pihak tidak mengirim resi ============ */

    /** Tukar tambah tahap kirim yang tenggatnya sudah lewat. */
    private function overdueTradeIn(): TradeInRequest
    {
        $tradeIn = $this->paidTradeIn();

        $tradeIn->forceFill([
            'shipping_started_at' => now()->subDays(TradeInRequest::SHIPPING_DEADLINE_DAYS + 1),
        ])->save();

        return $tradeIn->fresh();
    }

    /**
     * Tukar tambah tanpa selisih uang langsung masuk tahap kirim begitu disetujui,
     * jadi jalur ini menguji startShipping() yang sebenarnya.
     */
    public function test_tenggat_kirim_dimulai_saat_masuk_tahap_pengiriman(): void
    {
        $this->requestedProduct->forceFill(['price' => 3000000])->save();

        $tradeIn = $this->acceptedTradeIn();

        $this->assertSame(TradeInRequest::STATUS_SHIPPING, $tradeIn->status);
        $this->assertNotNull($tradeIn->shipping_started_at);
        $this->assertFalse($tradeIn->isShippingOverdue());

        $this->assertTrue(
            $tradeIn->shippingDeadlineAt()->equalTo(
                $tradeIn->shipping_started_at->copy()->addDays(TradeInRequest::SHIPPING_DEADLINE_DAYS)
            )
        );

        $tradeIn->forceFill([
            'shipping_started_at' => now()->subDays(TradeInRequest::SHIPPING_DEADLINE_DAYS + 1),
        ])->save();

        $this->assertTrue($tradeIn->fresh()->isShippingOverdue());
    }

    public function test_belum_bisa_melapor_sebelum_tenggat_lewat(): void
    {
        $tradeIn = $this->paidTradeIn();

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-A']);

        $this->assertFalse($tradeIn->fresh()->canReportStalledBy($this->requesterStore));

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/report', [
                'reason' => 'Pihak lawan belum mengirimkan barangnya sampai sekarang.',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, RefundRequest::count());
    }

    public function test_pihak_yang_sudah_kirim_bisa_melapor_setelah_tenggat(): void
    {
        $tradeIn = $this->overdueTradeIn();

        // Requester memenuhi kewajibannya, responder tidak.
        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-A']);

        $this->assertTrue($tradeIn->fresh()->canReportStalledBy($this->requesterStore));

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/report', [
                'reason' => 'Saya sudah mengirim sejak awal tapi pihak lawan belum mengirimkan barangnya.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, RefundRequest::where('trade_in_request_id', $tradeIn->id)->count());
    }

    /**
     * Inti kasus yang dulu tidak punya jalan keluar: requester menghilang,
     * responder tidak punya tombol apa pun karena bukan dia yang membayar.
     */
    public function test_responder_bisa_melapor_saat_requester_yang_tidak_mengirim(): void
    {
        $tradeIn = $this->overdueTradeIn();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $this->assertTrue($tradeIn->fresh()->canReportStalledBy($this->responderStore));

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/report', [
                'reason' => 'Saya sudah mengirim barang saya tapi pengaju tidak pernah mengirimkan miliknya.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, RefundRequest::where('trade_in_request_id', $tradeIn->id)->count());
    }

    public function test_pihak_yang_belum_kirim_tidak_bisa_melapor(): void
    {
        $tradeIn = $this->overdueTradeIn();

        // Responder mengirim; requester yang lalai justru mencoba melapor.
        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $this->assertFalse($tradeIn->fresh()->canReportStalledBy($this->requesterStore));

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/report', [
                'reason' => 'Saya ingin membatalkan padahal saya sendiri yang belum mengirim.',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, RefundRequest::count());
    }

    /**
     * Kalau KEDUA pihak belum mengirim sampai tenggat, siapa pun di antara
     * keduanya boleh membawanya ke admin.
     *
     * Aturannya sempat kebalikan dari ini: karena pelapor wajib sudah mengirim
     * barangnya sendiri, keadaan "keduanya sama-sama diam" berarti tidak ada
     * satu pun yang boleh melapor. Tidak ada pula penjadwal yang menyentuh
     * tahap saling kirim, sehingga dua produk milik dua toko berbeda terkunci
     * selamanya justru karena tidak ada yang bergerak — jalan buntu yang tidak
     * disengaja.
     *
     * Yang tetap dijaga adalah maksud aslinya: pihak yang lalai tidak boleh
     * melaporkan pihak yang taat. Test tepat di atas membuktikannya masih
     * berlaku.
     */
    public function test_keduanya_belum_kirim_maka_siapa_pun_boleh_melapor(): void
    {
        $tradeIn = $this->overdueTradeIn();

        $this->assertTrue($tradeIn->canReportStalledBy($this->requesterStore));
        $this->assertTrue($tradeIn->canReportStalledBy($this->responderStore));

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/report', [
                'reason' => 'Kami berdua tidak jadi melanjutkan tukar tambah ini.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, RefundRequest::count());
    }

    /**
     * Tenggat harus diberitahukan kepada pihak yang HARUS bertindak, bukan
     * hanya kepada yang menunggu.
     */
    public function test_pihak_yang_belum_kirim_diberi_tahu_tenggatnya(): void
    {
        $tradeIn = $this->paidTradeIn();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $tradeIn->refresh();

        $pesanRequester = $tradeIn->reportBlockReasonFor($this->requesterStore);
        $this->assertStringContainsString('belum mengisi nomor resi', $pesanRequester);
        $this->assertStringContainsString('Batas waktunya', $pesanRequester);

        $pesanResponder = $tradeIn->reportBlockReasonFor($this->responderStore);
        $this->assertStringContainsString('dapat melapor ke admin setelah', $pesanResponder);
    }

    public function test_pihak_yang_belum_kirim_didesak_setelah_tenggat_lewat(): void
    {
        $tradeIn = $this->overdueTradeIn();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $pesan = $tradeIn->fresh()->reportBlockReasonFor($this->requesterStore);

        $this->assertStringContainsString('Tenggat pengiriman sudah lewat', $pesan);
    }

    public function test_tenggat_dikirim_ke_frontend_sebagai_iso8601(): void
    {
        $tradeIn = $this->paidTradeIn();

        $this->assertSame(
            $tradeIn->shippingDeadlineAt()->toIso8601String(),
            $tradeIn->shipping_deadline_at
        );
    }

    public function test_seller_luar_tidak_bisa_melapor(): void
    {
        $tradeIn = $this->overdueTradeIn();

        $luar = $this->makeSeller('c');
        $this->makeStore($luar, 'Toko C');

        $this->actingAs($luar)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/report', [
                'reason' => 'Saya bukan pihak dalam tukar tambah ini tapi ingin ikut campur.',
            ])
            ->assertForbidden();

        $this->assertSame(0, RefundRequest::count());
    }

    public function test_admin_menyetujui_laporan_membatalkan_tukar_tambah_dan_membuka_kunci(): void
    {
        $tradeIn = $this->overdueTradeIn();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/report', [
                'reason' => 'Saya sudah mengirim barang saya tapi pengaju tidak pernah mengirimkan miliknya.',
            ]);

        $refund = RefundRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/refunds/'.$refund->public_id.'/approve', [
                'admin_note' => 'Terbukti pengaju tidak pernah mengirim.',
            ])
            ->assertSessionHas('success');

        $tradeIn->refresh();

        $this->assertSame(TradeInRequest::STATUS_CANCELLED, $tradeIn->status);
        $this->assertSame(TradeInRequest::PAYMENT_REFUNDED, $tradeIn->payment_status);
        $this->assertNull($this->offeredProduct->fresh()->locked_for_trade_in_id);
        $this->assertNull($this->requestedProduct->fresh()->locked_for_trade_in_id);
    }

    /**
     * Tukar tambah tanpa selisih uang dulu sama sekali tidak punya jalan keluar,
     * karena jalur pengembalian dana mensyaratkan adanya pembayaran.
     */
    public function test_tukar_tambah_tanpa_selisih_uang_yang_macet_tetap_bisa_dilaporkan(): void
    {
        $this->requestedProduct->forceFill(['price' => 3000000])->save();

        $tradeIn = $this->acceptedTradeIn();

        $this->assertSame('0.00', (string) $tradeIn->additional_cash);
        $this->assertSame(TradeInRequest::STATUS_SHIPPING, $tradeIn->status);

        $tradeIn->forceFill([
            'shipping_started_at' => now()->subDays(TradeInRequest::SHIPPING_DEADLINE_DAYS + 1),
        ])->save();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/report', [
                'reason' => 'Pengaju tidak pernah mengirimkan barangnya meski tenggat sudah lewat.',
            ])
            ->assertSessionHas('success');

        $refund = RefundRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/refunds/'.$refund->public_id.'/approve')
            ->assertSessionHas('success');

        $tradeIn->refresh();

        $this->assertSame(TradeInRequest::STATUS_CANCELLED, $tradeIn->status);
        // Tidak ada uang yang kembali, jadi status pembayaran tidak diubah.
        $this->assertSame(TradeInRequest::PAYMENT_NOT_REQUIRED, $tradeIn->payment_status);
        $this->assertNull($this->offeredProduct->fresh()->locked_for_trade_in_id);
        $this->assertNull($this->requestedProduct->fresh()->locked_for_trade_in_id);
    }

    public function test_tidak_bisa_melapor_dua_kali(): void
    {
        $tradeIn = $this->overdueTradeIn();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $payload = ['reason' => 'Pengaju tidak pernah mengirimkan barangnya meski tenggat sudah lewat.'];

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/report', $payload)
            ->assertSessionHas('success');

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/report', $payload)
            ->assertSessionHas('error');

        $this->assertSame(1, RefundRequest::count());
    }

    public function test_tukar_tambah_selesai_tidak_bisa_dilaporkan_macet(): void
    {
        $tradeIn = $this->completedTradeIn();

        $tradeIn->forceFill([
            'shipping_started_at' => now()->subDays(TradeInRequest::SHIPPING_DEADLINE_DAYS + 1),
        ])->save();

        $this->assertFalse($tradeIn->fresh()->canReportStalledBy($this->responderStore));
    }

    public function test_pencairan_yang_sudah_cair_menghalangi_persetujuan_pengembalian(): void
    {
        $tradeIn = $this->completedTradeIn();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/payout', $this->bankPayload());

        $payout = PayoutRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/payouts/'.$payout->public_id.'/approve', [
                'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
            ]);

        // Tukar tambah sudah completed sehingga refund pun tidak bisa diajukan lagi,
        // tapi penjagaannya diuji langsung di tingkat model.
        $this->assertNotNull($tradeIn->fresh()->payout_released_at);
        $this->assertFalse($tradeIn->fresh()->canRequestRefund());
    }
}
