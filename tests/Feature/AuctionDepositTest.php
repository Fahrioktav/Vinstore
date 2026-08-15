<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionDeposit;
use App\Models\Order;
use App\Models\PlatformRevenue;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uang jaminan peserta lelang.
 *
 * Yang diuji di sini adalah seluruh jalur uangnya, bukan hanya tampilannya:
 * siapa yang boleh menawar, ke mana jaminan pemenang pergi, bagaimana yang
 * kalah memintanya kembali, dan apa yang terjadi bila pemenang menghilang.
 */
class AuctionDepositTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-testkey';

    private User $seller;

    private Store $store;

    private User $penawar;

    private User $penawarLain;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.midtrans.server_key', self::SERVER_KEY);

        Http::fake([
            '*' => Http::response([
                'token' => 'snap-token-palsu',
                'redirect_url' => 'https://example.test/snap',
            ]),
        ]);

        $this->seller = $this->makeUser('sellerdep', 'seller');

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Deposit',
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
            'latitude' => -7.7828,
            'longitude' => 110.3671,
        ]);

        $this->penawar = $this->makeUser('penawardep');
        $this->penawarLain = $this->makeUser('penawarlaindep');
    }

    private function makeUser(string $username, string $role = 'user'): User
    {
        return User::create([
            'username' => $username,
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
            'email' => $username.'@vinstore.test',
            'phone' => '0811'.random_int(1000000, 9999999),
            'address' => 'Jl. Antik No. 1',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function lelang(array $override = []): Auction
    {
        return Auction::create(array_merge([
            'store_id' => $this->store->id,
            'name' => 'Guci Ming',
            'description' => 'Guci antik',
            'weight' => 4000,
            'starting_price' => 2_000_000,
            'min_increment' => 100_000,
            'current_price' => 2_000_000,
            'approval_status' => Auction::STATUS_APPROVED,
            'status' => 'active',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
        ], $override));
    }

    private function depositLunas(Auction $auction, User $user): AuctionDeposit
    {
        return AuctionDeposit::create([
            'auction_id' => $auction->id,
            'user_id' => $user->id,
            'amount' => $auction->depositAmount(),
            'status' => AuctionDeposit::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    private function webhookPayload(string $reference, int $gross, string $status = 'settlement', string $statusCode = '200'): array
    {
        $grossAmount = number_format($gross, 2, '.', '');

        return [
            'order_id' => $reference,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => $status,
            'fraud_status' => 'accept',
            'transaction_id' => 'trx-'.$reference,
            'payment_type' => 'bank_transfer',
            'signature_key' => hash('sha512', $reference.$statusCode.$grossAmount.self::SERVER_KEY),
        ];
    }

    // ------------------------------------------------------------------
    // Besaran jaminan
    // ------------------------------------------------------------------

    public function test_lelang_di_bawah_ambang_tidak_memungut_deposit(): void
    {
        $auction = $this->lelang(['starting_price' => 999_999, 'current_price' => 999_999]);

        $this->assertSame(0, $auction->depositAmount());
        $this->assertFalse($auction->requiresDeposit());
    }

    public function test_deposit_sepuluh_persen_dari_harga_awal(): void
    {
        $this->assertSame(100_000, $this->lelang([
            'starting_price' => 1_000_000,
            'current_price' => 1_000_000,
        ])->depositAmount());

        $this->assertSame(200_000, $this->lelang(['name' => 'Lain'])->depositAmount());
    }

    /**
     * Besarannya dikunci pada HARGA AWAL, bukan harga berjalan.
     *
     * Nominal yang ikut naik tiap kali ada penawaran akan menagih peserta lama
     * atas kekurangan yang tidak pernah ia setujui.
     */
    public function test_deposit_tidak_ikut_naik_saat_harga_berjalan_naik(): void
    {
        $auction = $this->lelang();

        $auction->update(['current_price' => 9_000_000]);

        $this->assertSame(200_000, $auction->fresh()->depositAmount());
    }

    // ------------------------------------------------------------------
    // Hak menawar
    // ------------------------------------------------------------------

    public function test_tanpa_deposit_penawaran_ditolak(): void
    {
        $auction = $this->lelang();

        $this->actingAs($this->penawar)
            ->post('/auctions/'.$auction->public_id.'/bid', ['amount' => 2_100_000])
            ->assertSessionHas('error');

        $this->assertSame(0, AuctionBid::where('auction_id', $auction->id)->count());
        $this->assertSame('2000000.00', $auction->fresh()->current_price);
    }

    public function test_deposit_yang_belum_dibayar_belum_memberi_hak_menawar(): void
    {
        $auction = $this->lelang();

        AuctionDeposit::create([
            'auction_id' => $auction->id,
            'user_id' => $this->penawar->id,
            'amount' => $auction->depositAmount(),
            'status' => AuctionDeposit::STATUS_PENDING,
        ]);

        $this->actingAs($this->penawar)
            ->post('/auctions/'.$auction->public_id.'/bid', ['amount' => 2_100_000])
            ->assertSessionHas('error');

        $this->assertSame(0, AuctionBid::where('auction_id', $auction->id)->count());
    }

    public function test_deposit_lunas_memberi_hak_menawar(): void
    {
        $auction = $this->lelang();
        $this->depositLunas($auction, $this->penawar);

        $this->actingAs($this->penawar)
            ->post('/auctions/'.$auction->public_id.'/bid', ['amount' => 2_100_000])
            ->assertSessionHas('success');

        $this->assertSame('2100000.00', $auction->fresh()->current_price);
    }

    public function test_lelang_murah_tidak_memerlukan_deposit_untuk_menawar(): void
    {
        $auction = $this->lelang(['starting_price' => 500_000, 'current_price' => 500_000]);

        $this->actingAs($this->penawar)
            ->post('/auctions/'.$auction->public_id.'/bid', ['amount' => 600_000])
            ->assertSessionHas('success');
    }

    // ------------------------------------------------------------------
    // Pembayaran deposit
    // ------------------------------------------------------------------

    public function test_membayar_deposit_membuat_transaksi_snap(): void
    {
        $auction = $this->lelang();

        $this->actingAs($this->penawar)
            ->post('/auctions/'.$auction->public_id.'/deposit')
            ->assertSessionHas('snap_token', 'snap-token-palsu');

        $deposit = AuctionDeposit::where('auction_id', $auction->id)->firstOrFail();

        $this->assertSame(AuctionDeposit::STATUS_PENDING, $deposit->status);
        $this->assertSame(200_000, $deposit->amount);
        $this->assertSame($deposit->public_id, $deposit->payment_reference);
    }

    public function test_membayar_deposit_dua_kali_tidak_menggandakan_jaminan(): void
    {
        $auction = $this->lelang();

        $this->actingAs($this->penawar)->post('/auctions/'.$auction->public_id.'/deposit');
        $this->actingAs($this->penawar)->post('/auctions/'.$auction->public_id.'/deposit');

        $this->assertSame(1, AuctionDeposit::where('auction_id', $auction->id)->count());
    }

    public function test_seller_tidak_boleh_membayar_deposit_lelang_tokonya_sendiri(): void
    {
        $auction = $this->lelang();

        $this->actingAs($this->seller)
            ->post('/auctions/'.$auction->public_id.'/deposit')
            ->assertSessionHas('error');

        $this->assertSame(0, AuctionDeposit::count());
    }

    public function test_webhook_menandai_deposit_lunas(): void
    {
        $auction = $this->lelang();

        $this->actingAs($this->penawar)->post('/auctions/'.$auction->public_id.'/deposit');

        $deposit = AuctionDeposit::where('auction_id', $auction->id)->firstOrFail();

        $this->postJson('/midtrans/notification', $this->webhookPayload($deposit->payment_reference, 200_000))
            ->assertOk();

        $deposit->refresh();

        $this->assertSame(AuctionDeposit::STATUS_PAID, $deposit->status);
        $this->assertNotNull($deposit->paid_at);
    }

    public function test_webhook_dengan_tanda_tangan_palsu_ditolak(): void
    {
        $auction = $this->lelang();

        $this->actingAs($this->penawar)->post('/auctions/'.$auction->public_id.'/deposit');

        $deposit = AuctionDeposit::where('auction_id', $auction->id)->firstOrFail();

        $payload = $this->webhookPayload($deposit->payment_reference, 200_000);
        $payload['signature_key'] = 'palsu';

        $this->postJson('/midtrans/notification', $payload)->assertStatus(403);

        $this->assertSame(AuctionDeposit::STATUS_PENDING, $deposit->fresh()->status);
    }

    /**
     * Notifikasi yang datang setelah jaminannya berpindah status tidak boleh
     * menariknya mundur — misalnya jaminan yang sudah menjadi uang muka.
     */
    public function test_webhook_terlambat_tidak_menarik_mundur_status_deposit(): void
    {
        $auction = $this->lelang();
        $deposit = $this->depositLunas($auction, $this->penawar);

        $deposit->update([
            'payment_reference' => $deposit->public_id,
            'status' => AuctionDeposit::STATUS_APPLIED,
        ]);

        $this->postJson('/midtrans/notification', $this->webhookPayload($deposit->payment_reference, 200_000))
            ->assertOk();

        $this->assertSame(AuctionDeposit::STATUS_APPLIED, $deposit->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Penutupan lelang
    // ------------------------------------------------------------------

    public function test_deposit_pemenang_menjadi_uang_muka_pesanannya(): void
    {
        $auction = $this->lelang();
        $depositMenang = $this->depositLunas($auction, $this->penawar);
        $depositKalah = $this->depositLunas($auction, $this->penawarLain);

        AuctionBid::create([
            'auction_id' => $auction->id,
            'user_id' => $this->penawarLain->id,
            'amount' => 2_100_000,
        ]);

        AuctionBid::create([
            'auction_id' => $auction->id,
            'user_id' => $this->penawar->id,
            'amount' => 2_500_000,
        ]);

        $auction->update(['ends_at' => now()->subMinute()]);

        Artisan::call('auctions:finish');

        $order = Order::where('auction_id', $auction->id)->firstOrFail();

        $this->assertSame($this->penawar->id, $order->user_id);
        $this->assertSame(200_000, $order->deposit_credit);

        // Tagihan penuh tetap harga menang; yang dipotong hanya yang dibayar.
        $this->assertSame(2_500_000, (int) round((float) $order->price));
        $this->assertSame(2_300_000, $order->amountDue());

        $this->assertSame(AuctionDeposit::STATUS_APPLIED, $depositMenang->fresh()->status);
        $this->assertSame(AuctionDeposit::STATUS_PAID, $depositKalah->fresh()->status);
    }

    public function test_deposit_yang_tidak_pernah_dibayar_kedaluwarsa_saat_lelang_tutup(): void
    {
        $auction = $this->lelang();

        AuctionDeposit::create([
            'auction_id' => $auction->id,
            'user_id' => $this->penawar->id,
            'amount' => $auction->depositAmount(),
            'status' => AuctionDeposit::STATUS_PENDING,
        ]);

        $auction->update(['ends_at' => now()->subMinute()]);

        Artisan::call('auctions:finish');

        $this->assertSame(
            AuctionDeposit::STATUS_EXPIRED,
            AuctionDeposit::where('auction_id', $auction->id)->firstOrFail()->status
        );
    }

    // ------------------------------------------------------------------
    // Pengembalian ke peserta yang kalah
    // ------------------------------------------------------------------

    private function lelangSelesai(): Auction
    {
        $auction = $this->lelang();

        $this->depositLunas($auction, $this->penawar);
        $this->depositLunas($auction, $this->penawarLain);

        AuctionBid::create([
            'auction_id' => $auction->id,
            'user_id' => $this->penawar->id,
            'amount' => 2_500_000,
        ]);

        $auction->update(['ends_at' => now()->subMinute()]);

        Artisan::call('auctions:finish');

        return $auction->fresh();
    }

    public function test_halaman_deposit_pembeli_dapat_dibuka(): void
    {
        $this->lelangSelesai();

        $this->actingAs($this->penawarLain)
            ->get('/deposit-lelang')
            ->assertOk();
    }

    public function test_halaman_deposit_admin_dapat_dibuka(): void
    {
        $this->lelangSelesai();

        $this->actingAs($this->makeUser('adminhal', 'admin'))
            ->get('/admin/deposit-lelang')
            ->assertOk();
    }

    public function test_peserta_kalah_dapat_mengajukan_pengembalian(): void
    {
        $auction = $this->lelangSelesai();
        $deposit = AuctionDeposit::where('user_id', $this->penawarLain->id)->firstOrFail();

        $this->actingAs($this->penawarLain)
            ->post('/deposit-lelang/'.$deposit->public_id.'/refund', [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder' => 'Penawar Lain',
            ])
            ->assertSessionHas('success');

        $deposit->refresh();

        $this->assertSame(AuctionDeposit::STATUS_REFUND_REQUESTED, $deposit->status);
        $this->assertSame('BCA', $deposit->bank_name);
        $this->assertNotNull($deposit->refund_requested_at);
    }

    public function test_pemenang_tidak_dapat_mengajukan_pengembalian(): void
    {
        $auction = $this->lelangSelesai();
        $deposit = AuctionDeposit::where('user_id', $this->penawar->id)->firstOrFail();

        $this->assertSame(AuctionDeposit::STATUS_APPLIED, $deposit->status);

        $this->actingAs($this->penawar)
            ->post('/deposit-lelang/'.$deposit->public_id.'/refund', [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder' => 'Penawar',
            ])
            ->assertSessionHas('error');

        $this->assertSame(AuctionDeposit::STATUS_APPLIED, $deposit->fresh()->status);
    }

    public function test_pengembalian_belum_bisa_diajukan_selama_lelang_berjalan(): void
    {
        $auction = $this->lelang();
        $deposit = $this->depositLunas($auction, $this->penawar);

        $this->actingAs($this->penawar)
            ->post('/deposit-lelang/'.$deposit->public_id.'/refund', [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder' => 'Penawar',
            ])
            ->assertSessionHas('error');

        $this->assertSame(AuctionDeposit::STATUS_PAID, $deposit->fresh()->status);
    }

    public function test_deposit_orang_lain_tidak_bisa_diajukan(): void
    {
        $this->lelangSelesai();
        $deposit = AuctionDeposit::where('user_id', $this->penawarLain->id)->firstOrFail();

        $this->actingAs($this->penawar)
            ->post('/deposit-lelang/'.$deposit->public_id.'/refund', [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder' => 'Bukan Pemilik',
            ])
            ->assertForbidden();
    }

    public function test_admin_menyetujui_pengembalian_dengan_bukti_transfer(): void
    {
        Storage::fake('public');

        $this->lelangSelesai();
        $deposit = AuctionDeposit::where('user_id', $this->penawarLain->id)->firstOrFail();

        $deposit->update([
            'status' => AuctionDeposit::STATUS_REFUND_REQUESTED,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Penawar Lain',
            'refund_requested_at' => now(),
        ]);

        $admin = $this->makeUser('admindep', 'admin');

        $this->actingAs($admin)
            ->post('/admin/deposit-lelang/'.$deposit->public_id.'/approve', [
                'admin_note' => 'Sudah ditransfer',
                'transfer_proof' => \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertSessionHas('success');

        $deposit->refresh();

        $this->assertSame(AuctionDeposit::STATUS_REFUNDED, $deposit->status);
        $this->assertNotNull($deposit->transfer_proof);
        $this->assertNotNull($deposit->refunded_at);
        $this->assertSame($admin->id, $deposit->reviewed_by);
    }

    public function test_persetujuan_tanpa_bukti_transfer_ditolak(): void
    {
        $this->lelangSelesai();
        $deposit = AuctionDeposit::where('user_id', $this->penawarLain->id)->firstOrFail();

        $deposit->update(['status' => AuctionDeposit::STATUS_REFUND_REQUESTED]);

        $admin = $this->makeUser('admindep2', 'admin');

        $this->actingAs($admin)
            ->post('/admin/deposit-lelang/'.$deposit->public_id.'/approve', [
                'admin_note' => 'Tanpa bukti',
            ])
            ->assertSessionHasErrors('transfer_proof');

        $this->assertSame(AuctionDeposit::STATUS_REFUND_REQUESTED, $deposit->fresh()->status);
    }

    public function test_penolakan_mengembalikan_deposit_ke_status_aktif(): void
    {
        $this->lelangSelesai();
        $deposit = AuctionDeposit::where('user_id', $this->penawarLain->id)->firstOrFail();

        $deposit->update([
            'status' => AuctionDeposit::STATUS_REFUND_REQUESTED,
            'refund_requested_at' => now(),
        ]);

        $admin = $this->makeUser('admindep3', 'admin');

        $this->actingAs($admin)
            ->post('/admin/deposit-lelang/'.$deposit->public_id.'/reject', [
                'admin_note' => 'Nomor rekening tidak sesuai nama akun.',
            ])
            ->assertSessionHas('success');

        $deposit->refresh();

        // Kembali aktif supaya pembeli bisa memperbaiki datanya lalu mengajukan
        // ulang — bukan hangus hanya karena salah ketik nomor rekening.
        $this->assertSame(AuctionDeposit::STATUS_PAID, $deposit->status);
        $this->assertNull($deposit->refund_requested_at);
        $this->assertTrue($deposit->fresh()->canRequestRefund());
    }

    // ------------------------------------------------------------------
    // Pemenang yang tidak membayar
    // ------------------------------------------------------------------

    public function test_deposit_pemenang_hangus_bila_pesanan_tidak_dibayar_sampai_tenggat(): void
    {
        $auction = $this->lelangSelesai();

        $order = Order::where('auction_id', $auction->id)->firstOrFail();
        $order->forceFill([
            'created_at' => now()->subHours(Order::AUCTION_PAYMENT_WINDOW_HOURS + 1),
        ])->save();

        Artisan::call('orders:release-abandoned');

        $deposit = AuctionDeposit::where('user_id', $this->penawar->id)->firstOrFail();

        $this->assertSame(AuctionDeposit::STATUS_FORFEITED, $deposit->status);
        $this->assertNotNull($deposit->forfeited_at);

        // Uangnya berpindah ke dompet marketplace, bukan menguap.
        $this->assertSame(
            200_000,
            (int) PlatformRevenue::where('order_id', $order->id)
                ->where('source', PlatformRevenue::SOURCE_AUCTION_DEPOSIT_FORFEIT)
                ->value('amount')
        );
    }

    public function test_penghangusan_tidak_menggandakan_pendapatan_bila_dijalankan_ulang(): void
    {
        $auction = $this->lelangSelesai();

        $order = Order::where('auction_id', $auction->id)->firstOrFail();
        $order->forceFill([
            'created_at' => now()->subHours(Order::AUCTION_PAYMENT_WINDOW_HOURS + 1),
        ])->save();

        Artisan::call('orders:release-abandoned');
        Artisan::call('orders:release-abandoned');

        $this->assertSame(
            1,
            PlatformRevenue::where('order_id', $order->id)
                ->where('source', PlatformRevenue::SOURCE_AUCTION_DEPOSIT_FORFEIT)
                ->count()
        );
    }

    /**
     * Pesanan lelang punya tenggat sendiri yang lebih longgar: pemenang tidak
     * melewati checkout, jadi ia bisa saja baru menyadari kemenangannya
     * beberapa jam kemudian (temuan V6-03).
     */
    public function test_pesanan_lelang_belum_gugur_pada_tenggat_pesanan_biasa(): void
    {
        $auction = $this->lelangSelesai();

        $order = Order::where('auction_id', $auction->id)->firstOrFail();
        $order->forceFill([
            'created_at' => now()->subHours(Order::PAYMENT_WINDOW_HOURS + 1),
        ])->save();

        Artisan::call('orders:release-abandoned');

        $this->assertSame('Waiting', $order->fresh()->status);
        $this->assertSame(
            AuctionDeposit::STATUS_APPLIED,
            AuctionDeposit::where('user_id', $this->penawar->id)->firstOrFail()->status
        );
    }
}
