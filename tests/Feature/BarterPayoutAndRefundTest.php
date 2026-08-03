<?php

namespace Tests\Feature;

use App\Models\BarterRequest;
use App\Models\PayoutRequest;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Nasib selisih uang (additional_cash) pada barter.
 *
 * Uang itu dibayar requester karena produk yang ia tawarkan lebih murah, dan
 * merupakan hak responder. Sebelumnya uang tersebut ditagih lalu berhenti di
 * akun merchant tanpa pernah tercatat sebagai hak siapa pun.
 *
 * Sekarang:
 *  - Barter tuntas  -> responder mengajukan pencairan, admin menyetujui.
 *  - Barter gagal   -> requester mengajukan pengembalian dana, admin menyetujui.
 */
class BarterPayoutAndRefundTest extends TestCase
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
            'username' => 'adminbarter',
            'first_name' => 'Admin',
            'last_name' => 'Barter',
            'email' => 'adminbarter@vinstore.test',
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
            'is_barterable' => true,
        ]);
    }

    /** Barter dengan selisih uang, disetujui responder. */
    private function acceptedBarter(): BarterRequest
    {
        $this->actingAs($this->requesterUser)->post(
            '/seller/barter/'.$this->requestedProduct->public_id,
            ['offered_product_id' => $this->offeredProduct->public_id]
        );

        $barter = BarterRequest::firstOrFail();

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/accept');

        return $barter->fresh();
    }

    /** Barter yang selisihnya sudah lunas dan masuk tahap kirim. */
    private function paidBarter(): BarterRequest
    {
        $barter = $this->acceptedBarter();

        // Meniru apa yang dilakukan webhook Midtrans saat selisih lunas:
        // barter masuk tahap kirim sekaligus memulai hitungan tenggat.
        $barter->forceFill([
            'payment_status' => BarterRequest::PAYMENT_PAID,
            'paid_at' => now(),
            'status' => BarterRequest::STATUS_SHIPPING,
            'shipping_started_at' => now(),
        ])->save();

        return $barter->fresh();
    }

    /** Barter tuntas: keduanya saling kirim dan saling konfirmasi. */
    private function completedBarter(): BarterRequest
    {
        $barter = $this->paidBarter();

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-A']);
        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/receive');
        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/receive');

        return $barter->fresh();
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
        $barter = $this->acceptedBarter();

        $this->assertSame('2000000.00', (string) $barter->additional_cash);
        $this->assertSame($this->responderStore->id, $barter->payoutRecipientStoreId());
    }

    public function test_responder_bisa_mencairkan_selisih_setelah_barter_selesai(): void
    {
        $barter = $this->completedBarter();

        $this->assertSame(BarterRequest::STATUS_COMPLETED, $barter->status);
        $this->assertTrue($barter->canRequestPayout());

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/payout', $this->bankPayload())
            ->assertSessionHas('success');

        $payout = PayoutRequest::firstOrFail();

        $this->assertSame('barter', $payout->source_type);
        $this->assertSame('2000000.00', (string) $payout->amount);
        $this->assertSame($this->responderStore->id, $payout->store_id);
        $this->assertNull($barter->fresh()->payout_released_at);
    }

    public function test_belum_bisa_mencairkan_sebelum_kedua_pihak_konfirmasi(): void
    {
        $barter = $this->paidBarter();

        $this->assertFalse($barter->canRequestPayout());

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/payout', $this->bankPayload())
            ->assertSessionHas('error');

        $this->assertSame(0, PayoutRequest::count());
    }

    public function test_requester_tidak_bisa_mencairkan_selisih_yang_ia_bayar_sendiri(): void
    {
        $barter = $this->completedBarter();

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/payout', $this->bankPayload())
            ->assertForbidden();

        $this->assertSame(0, PayoutRequest::count());
    }

    public function test_admin_menyetujui_pencairan_selisih_barter(): void
    {
        $barter = $this->completedBarter();

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/payout', $this->bankPayload());

        $payout = PayoutRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/payouts/'.$payout->public_id.'/approve', [
                'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertSessionHas('success');

        $barter->refresh();

        $this->assertSame(PayoutRequest::STATUS_APPROVED, $payout->fresh()->status);
        $this->assertNotNull($barter->payout_released_at);
        $this->assertSame('2000000.00', (string) $this->responderStore->fresh()->withdrawn_balance);
        // Requester tidak menerima apa pun dari pencairan ini.
        $this->assertSame('0.00', (string) $this->requesterStore->fresh()->withdrawn_balance);
    }

    public function test_tidak_bisa_mencairkan_dua_kali(): void
    {
        $barter = $this->completedBarter();

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/payout', $this->bankPayload());

        $payout = PayoutRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/payouts/'.$payout->public_id.'/approve', [
                'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
            ]);

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/payout', $this->bankPayload())
            ->assertSessionHas('error');

        $this->assertSame(1, PayoutRequest::count());
    }

    public function test_barter_tanpa_selisih_tidak_punya_pencairan(): void
    {
        // Kedua produk berharga sama -> additional_cash 0.
        $this->requestedProduct->forceFill(['price' => 3000000])->save();

        $barter = $this->completedBarter();

        $this->assertSame('0.00', (string) $barter->additional_cash);
        $this->assertFalse($barter->canRequestPayout());
        $this->assertFalse($barter->canRequestRefund());
    }

    /* ===================== Barter gagal -> refund ===================== */

    public function test_requester_bisa_meminta_pengembalian_dana_saat_barter_belum_tuntas(): void
    {
        $barter = $this->paidBarter();

        $this->assertTrue($barter->canRequestRefund());

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/refund', [
                'reason' => 'Pihak lawan tidak pernah mengirimkan barangnya sejak barter disetujui.',
            ])
            ->assertSessionHas('success');

        $refund = RefundRequest::firstOrFail();

        $this->assertSame('barter', $refund->source_type);
        $this->assertSame($barter->id, $refund->barter_request_id);
        $this->assertSame('pending', $refund->status);
    }

    public function test_responder_tidak_bisa_meminta_pengembalian_dana(): void
    {
        $barter = $this->paidBarter();

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/refund', [
                'reason' => 'Saya ingin uangnya dikembalikan padahal bukan saya yang bayar.',
            ])
            ->assertForbidden();

        $this->assertSame(0, RefundRequest::count());
    }

    public function test_barter_yang_sudah_selesai_tidak_bisa_dimintakan_pengembalian(): void
    {
        $barter = $this->completedBarter();

        $this->assertFalse($barter->canRequestRefund());

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/refund', [
                'reason' => 'Saya berubah pikiran setelah barter tuntas sepenuhnya.',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, RefundRequest::count());
    }

    public function test_admin_menyetujui_pengembalian_membatalkan_barter_dan_membuka_kunci_produk(): void
    {
        $barter = $this->paidBarter();

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/refund', [
                'reason' => 'Pihak lawan tidak pernah mengirimkan barangnya sejak barter disetujui.',
            ]);

        $refund = RefundRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/refunds/'.$refund->public_id.'/approve', [
                'admin_note' => 'Terbukti tidak ada pengiriman dari pihak lawan.',
            ])
            ->assertSessionHas('success');

        $barter->refresh();

        $this->assertSame('approved', $refund->fresh()->status);
        $this->assertSame(BarterRequest::STATUS_CANCELLED, $barter->status);
        $this->assertSame(BarterRequest::PAYMENT_REFUNDED, $barter->payment_status);

        // Kunci barter dilepas agar produk bisa dijual atau dibarter lagi.
        $this->assertNull($this->offeredProduct->fresh()->locked_for_barter_id);
        $this->assertNull($this->requestedProduct->fresh()->locked_for_barter_id);

        // Kepemilikan tidak boleh berpindah lewat jalur ini.
        $this->assertSame($this->requesterStore->id, $this->offeredProduct->fresh()->store_id);
        $this->assertSame($this->responderStore->id, $this->requestedProduct->fresh()->store_id);
    }

    public function test_pengembalian_yang_disetujui_menutup_jalur_pencairan(): void
    {
        $barter = $this->paidBarter();

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/refund', [
                'reason' => 'Pihak lawan tidak pernah mengirimkan barangnya sejak barter disetujui.',
            ]);

        $refund = RefundRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/refunds/'.$refund->public_id.'/approve');

        $this->assertFalse($barter->fresh()->canRequestPayout());
    }

    /* ============ Barter macet: satu pihak tidak mengirim resi ============ */

    /** Barter tahap kirim yang tenggatnya sudah lewat. */
    private function overdueBarter(): BarterRequest
    {
        $barter = $this->paidBarter();

        $barter->forceFill([
            'shipping_started_at' => now()->subDays(BarterRequest::SHIPPING_DEADLINE_DAYS + 1),
        ])->save();

        return $barter->fresh();
    }

    /**
     * Barter tanpa selisih uang langsung masuk tahap kirim begitu disetujui,
     * jadi jalur ini menguji startShipping() yang sebenarnya.
     */
    public function test_tenggat_kirim_dimulai_saat_masuk_tahap_pengiriman(): void
    {
        $this->requestedProduct->forceFill(['price' => 3000000])->save();

        $barter = $this->acceptedBarter();

        $this->assertSame(BarterRequest::STATUS_SHIPPING, $barter->status);
        $this->assertNotNull($barter->shipping_started_at);
        $this->assertFalse($barter->isShippingOverdue());

        $this->assertTrue(
            $barter->shippingDeadlineAt()->equalTo(
                $barter->shipping_started_at->copy()->addDays(BarterRequest::SHIPPING_DEADLINE_DAYS)
            )
        );

        $barter->forceFill([
            'shipping_started_at' => now()->subDays(BarterRequest::SHIPPING_DEADLINE_DAYS + 1),
        ])->save();

        $this->assertTrue($barter->fresh()->isShippingOverdue());
    }

    public function test_belum_bisa_melapor_sebelum_tenggat_lewat(): void
    {
        $barter = $this->paidBarter();

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-A']);

        $this->assertFalse($barter->fresh()->canReportStalledBy($this->requesterStore));

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/report', [
                'reason' => 'Pihak lawan belum mengirimkan barangnya sampai sekarang.',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, RefundRequest::count());
    }

    public function test_pihak_yang_sudah_kirim_bisa_melapor_setelah_tenggat(): void
    {
        $barter = $this->overdueBarter();

        // Requester memenuhi kewajibannya, responder tidak.
        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-A']);

        $this->assertTrue($barter->fresh()->canReportStalledBy($this->requesterStore));

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/report', [
                'reason' => 'Saya sudah mengirim sejak awal tapi pihak lawan belum mengirimkan barangnya.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, RefundRequest::where('barter_request_id', $barter->id)->count());
    }

    /**
     * Inti kasus yang dulu tidak punya jalan keluar: requester menghilang,
     * responder tidak punya tombol apa pun karena bukan dia yang membayar.
     */
    public function test_responder_bisa_melapor_saat_requester_yang_tidak_mengirim(): void
    {
        $barter = $this->overdueBarter();

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $this->assertTrue($barter->fresh()->canReportStalledBy($this->responderStore));

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/report', [
                'reason' => 'Saya sudah mengirim barang saya tapi pengaju tidak pernah mengirimkan miliknya.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, RefundRequest::where('barter_request_id', $barter->id)->count());
    }

    public function test_pihak_yang_belum_kirim_tidak_bisa_melapor(): void
    {
        $barter = $this->overdueBarter();

        // Responder mengirim; requester yang lalai justru mencoba melapor.
        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $this->assertFalse($barter->fresh()->canReportStalledBy($this->requesterStore));

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/report', [
                'reason' => 'Saya ingin membatalkan padahal saya sendiri yang belum mengirim.',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, RefundRequest::count());
    }

    /**
     * Kalau KEDUA pihak belum mengirim, tidak ada yang boleh melapor — keduanya
     * sama-sama lalai.
     *
     * Dulu partyMissingShipment() selalu mengembalikan 'requester' lebih dulu,
     * sehingga responder yang juga belum mengirim tetap lolos pengecekan dan
     * bisa melaporkan requester.
     */
    public function test_tidak_ada_yang_bisa_melapor_bila_keduanya_belum_kirim(): void
    {
        $barter = $this->overdueBarter();

        $this->assertFalse($barter->canReportStalledBy($this->requesterStore));
        $this->assertFalse($barter->canReportStalledBy($this->responderStore));

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/report', [
                'reason' => 'Pengaju belum mengirim, padahal saya juga belum mengirim.',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, RefundRequest::count());
    }

    /**
     * Tenggat harus diberitahukan kepada pihak yang HARUS bertindak, bukan
     * hanya kepada yang menunggu.
     */
    public function test_pihak_yang_belum_kirim_diberi_tahu_tenggatnya(): void
    {
        $barter = $this->paidBarter();

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $barter->refresh();

        $pesanRequester = $barter->reportBlockReasonFor($this->requesterStore);
        $this->assertStringContainsString('belum mengisi nomor resi', $pesanRequester);
        $this->assertStringContainsString('Batas waktunya', $pesanRequester);

        $pesanResponder = $barter->reportBlockReasonFor($this->responderStore);
        $this->assertStringContainsString('dapat melapor ke admin setelah', $pesanResponder);
    }

    public function test_pihak_yang_belum_kirim_didesak_setelah_tenggat_lewat(): void
    {
        $barter = $this->overdueBarter();

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $pesan = $barter->fresh()->reportBlockReasonFor($this->requesterStore);

        $this->assertStringContainsString('Tenggat pengiriman sudah lewat', $pesan);
    }

    public function test_tenggat_dikirim_ke_frontend_sebagai_iso8601(): void
    {
        $barter = $this->paidBarter();

        $this->assertSame(
            $barter->shippingDeadlineAt()->toIso8601String(),
            $barter->shipping_deadline_at
        );
    }

    public function test_seller_luar_tidak_bisa_melapor(): void
    {
        $barter = $this->overdueBarter();

        $luar = $this->makeSeller('c');
        $this->makeStore($luar, 'Toko C');

        $this->actingAs($luar)
            ->post('/seller/barter/'.$barter->public_id.'/report', [
                'reason' => 'Saya bukan pihak dalam barter ini tapi ingin ikut campur.',
            ])
            ->assertForbidden();

        $this->assertSame(0, RefundRequest::count());
    }

    public function test_admin_menyetujui_laporan_membatalkan_barter_dan_membuka_kunci(): void
    {
        $barter = $this->overdueBarter();

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/report', [
                'reason' => 'Saya sudah mengirim barang saya tapi pengaju tidak pernah mengirimkan miliknya.',
            ]);

        $refund = RefundRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/refunds/'.$refund->public_id.'/approve', [
                'admin_note' => 'Terbukti pengaju tidak pernah mengirim.',
            ])
            ->assertSessionHas('success');

        $barter->refresh();

        $this->assertSame(BarterRequest::STATUS_CANCELLED, $barter->status);
        $this->assertSame(BarterRequest::PAYMENT_REFUNDED, $barter->payment_status);
        $this->assertNull($this->offeredProduct->fresh()->locked_for_barter_id);
        $this->assertNull($this->requestedProduct->fresh()->locked_for_barter_id);
    }

    /**
     * Barter tanpa selisih uang dulu sama sekali tidak punya jalan keluar,
     * karena jalur pengembalian dana mensyaratkan adanya pembayaran.
     */
    public function test_barter_tanpa_selisih_uang_yang_macet_tetap_bisa_dilaporkan(): void
    {
        $this->requestedProduct->forceFill(['price' => 3000000])->save();

        $barter = $this->acceptedBarter();

        $this->assertSame('0.00', (string) $barter->additional_cash);
        $this->assertSame(BarterRequest::STATUS_SHIPPING, $barter->status);

        $barter->forceFill([
            'shipping_started_at' => now()->subDays(BarterRequest::SHIPPING_DEADLINE_DAYS + 1),
        ])->save();

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/report', [
                'reason' => 'Pengaju tidak pernah mengirimkan barangnya meski tenggat sudah lewat.',
            ])
            ->assertSessionHas('success');

        $refund = RefundRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/refunds/'.$refund->public_id.'/approve')
            ->assertSessionHas('success');

        $barter->refresh();

        $this->assertSame(BarterRequest::STATUS_CANCELLED, $barter->status);
        // Tidak ada uang yang kembali, jadi status pembayaran tidak diubah.
        $this->assertSame(BarterRequest::PAYMENT_NOT_REQUIRED, $barter->payment_status);
        $this->assertNull($this->offeredProduct->fresh()->locked_for_barter_id);
        $this->assertNull($this->requestedProduct->fresh()->locked_for_barter_id);
    }

    public function test_tidak_bisa_melapor_dua_kali(): void
    {
        $barter = $this->overdueBarter();

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-B']);

        $payload = ['reason' => 'Pengaju tidak pernah mengirimkan barangnya meski tenggat sudah lewat.'];

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/report', $payload)
            ->assertSessionHas('success');

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/report', $payload)
            ->assertSessionHas('error');

        $this->assertSame(1, RefundRequest::count());
    }

    public function test_barter_selesai_tidak_bisa_dilaporkan_macet(): void
    {
        $barter = $this->completedBarter();

        $barter->forceFill([
            'shipping_started_at' => now()->subDays(BarterRequest::SHIPPING_DEADLINE_DAYS + 1),
        ])->save();

        $this->assertFalse($barter->fresh()->canReportStalledBy($this->responderStore));
    }

    public function test_pencairan_yang_sudah_cair_menghalangi_persetujuan_pengembalian(): void
    {
        $barter = $this->completedBarter();

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/payout', $this->bankPayload());

        $payout = PayoutRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/payouts/'.$payout->public_id.'/approve', [
                'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
            ]);

        // Barter sudah completed sehingga refund pun tidak bisa diajukan lagi,
        // tapi penjagaannya diuji langsung di tingkat model.
        $this->assertNotNull($barter->fresh()->payout_released_at);
        $this->assertFalse($barter->fresh()->canRequestRefund());
    }
}
