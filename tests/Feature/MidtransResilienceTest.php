<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionDeposit;
use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Ketahanan aplikasi terhadap Midtrans — jaringan yang lambat, notifikasi yang
 * tidak dikenali, dan referensi pembayaran yang berpindah.
 *
 * Temuan yang dikunci di sini:
 *
 *  V8-03 — notifikasi bertanda tangan sah atas referensi yang tidak dikenal
 *          dijawab 404 tanpa jejak apa pun, padahal uangnya nyata.
 *  V8-04 — penyelarasan status memanggil Midtrans sekali per jaminan, berurutan
 *          dan tanpa batas waktu, di dalam permintaan halaman.
 *  V8-05 — penjadwal penutup lelang kini memanggil jaringan tetapi masih boleh
 *          tumpang tindih dengan dirinya sendiri.
 *  V8-06 — rotasi referensi jaminan dikerjakan tanpa transaksi dan tanpa kunci.
 */
class MidtransResilienceTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-testkey';

    private User $seller;

    private Store $store;

    private User $pembeli;

    private array $jawabanStatus = [
        'transaction_status' => 'pending',
        'fraud_status' => 'accept',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.midtrans.server_key', self::SERVER_KEY);

        Http::fake([
            '*/status' => fn () => Http::response($this->jawabanStatus),
            '*' => Http::response([
                'token' => 'snap-token-palsu',
                'redirect_url' => 'https://example.test/snap',
            ]),
        ]);

        $this->seller = $this->buatUser('sellerketahanan', 'seller');

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Ketahanan',
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
            'latitude' => -7.7828,
            'longitude' => 110.3671,
        ]);

        $this->pembeli = $this->buatUser('pembeliketahanan');
    }

    private function buatUser(string $username, string $role = 'user'): User
    {
        return User::create([
            'username' => $username,
            'first_name' => ucfirst($username),
            'last_name' => 'Uji',
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
            'name' => 'Guci Ketahanan',
            'description' => 'Guci antik',
            'weight' => 4000,
            'starting_price' => 5_000_000,
            'min_increment' => 250_000,
            'current_price' => 5_000_000,
            'approval_status' => Auction::STATUS_APPROVED,
            'status' => 'active',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
        ], $override));
    }

    private function webhookPayload(string $reference, int $gross, string $status = 'settlement'): array
    {
        $grossAmount = number_format($gross, 2, '.', '');

        return [
            'order_id' => $reference,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'transaction_status' => $status,
            'fraud_status' => 'accept',
            'transaction_id' => 'trx-'.$reference,
            'payment_type' => 'bank_transfer',
            'signature_key' => hash('sha512', $reference.'200'.$grossAmount.self::SERVER_KEY),
        ];
    }

    // ------------------------------------------------------------------
    // V8-03 — uang yang tidak dikenali harus meninggalkan jejak
    // ------------------------------------------------------------------

    public function test_notifikasi_atas_referensi_tak_dikenal_dicatat_ke_log(): void
    {
        Log::spy();

        $this->postJson('/midtrans/notification', $this->webhookPayload('ENTAHAPA123', 200_000))
            ->assertStatus(404);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $pesan, array $konteks = []) => str_contains($pesan, 'tidak dikenal')
                && ($konteks['payment_reference'] ?? null) === 'ENTAHAPA123'
                && ($konteks['gross_amount'] ?? null) === '200000.00')
            ->once();
    }

    /**
     * Jaminan yang referensinya sudah dirotasi tetap harus mengenali pembayaran
     * atas referensi lamanya — halaman Snap yang lama masih hidup di peramban
     * pembeli, dan uang yang masuk lewat sana tetap uang yang nyata.
     */
    public function test_pembayaran_atas_referensi_lama_tetap_dikenali_setelah_rotasi(): void
    {
        $auction = $this->lelang();

        $this->actingAs($this->pembeli)->post('/auctions/'.$auction->public_id.'/deposit');

        $deposit = AuctionDeposit::where('auction_id', $auction->id)->firstOrFail();
        $referensiLama = $deposit->payment_reference;

        // Midtrans mengabarkan transaksinya mati -> jaminan dibuka lagi dengan
        // referensi baru saat pembeli menekan bayar untuk kedua kalinya.
        $this->jawabanStatus = ['transaction_status' => 'expire', 'fraud_status' => 'accept'];
        $this->actingAs($this->pembeli)->post('/auctions/'.$auction->public_id.'/deposit');

        $deposit->refresh();
        $this->assertNotSame($referensiLama, $deposit->payment_reference);

        // Pembeli menyelesaikan halaman Snap yang lama.
        $this->postJson('/midtrans/notification', $this->webhookPayload($referensiLama, 500_000))
            ->assertOk();

        $deposit->refresh();

        $this->assertSame(AuctionDeposit::STATUS_PAID, $deposit->status);
        $this->assertNotNull($deposit->paid_at);
    }

    // ------------------------------------------------------------------
    // V8-04 — halaman tidak boleh disandera Midtrans
    // ------------------------------------------------------------------

    public function test_penyelarasan_membatasi_jumlah_panggilan_per_permintaan(): void
    {
        for ($i = 0; $i < 6; $i++) {
            AuctionDeposit::create([
                'auction_id' => $this->lelang(['name' => 'Guci '.$i])->id,
                'user_id' => $this->pembeli->id,
                'amount' => 500_000,
                'status' => AuctionDeposit::STATUS_PENDING,
                'payment_reference' => 'DEP-UJI-'.$i,
                'snap_token' => 'token-'.$i,
            ]);
        }

        $this->actingAs($this->pembeli)->get('/deposit-lelang')->assertOk();

        $this->assertLessThanOrEqual(
            AuctionDeposit::MAX_SYNC_PER_REQUEST,
            count(Http::recorded()),
            'Satu kali muat halaman tidak boleh memanggil Midtrans sebanyak jumlah jaminannya.'
        );
    }

    public function test_klien_midtrans_punya_batas_waktu(): void
    {
        $this->assertIsInt(config('services.midtrans.timeout'));
        $this->assertGreaterThan(0, config('services.midtrans.timeout'));
        $this->assertGreaterThan(0, config('services.midtrans.connect_timeout'));
    }

    /**
     * Midtrans yang tidak menjawab tidak boleh menjatuhkan halaman lelang.
     * Statusnya cukup tidak tersegarkan; itu urusan webhook dan pemuatan
     * berikutnya.
     */
    public function test_halaman_lelang_tetap_tampil_saat_midtrans_tidak_menjawab(): void
    {
        $auction = $this->lelang();

        AuctionDeposit::create([
            'auction_id' => $auction->id,
            'user_id' => $this->pembeli->id,
            'amount' => 500_000,
            'status' => AuctionDeposit::STATUS_PENDING,
            'payment_reference' => 'DEP-MATI-1',
            'snap_token' => 'token-mati',
        ]);

        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->actingAs($this->pembeli)
            ->get('/auctions/'.$auction->public_id)
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // V8-05 — penjadwal tidak boleh tumpang tindih dengan dirinya sendiri
    // ------------------------------------------------------------------

    public function test_penjadwal_penutup_lelang_dijaga_dari_tumpang_tindih(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'auctions:finish'));

        $this->assertNotNull($event, 'Perintah auctions:finish tidak terdaftar di penjadwal.');
        $this->assertTrue($event->withoutOverlapping);
    }

    // ------------------------------------------------------------------
    // V8-06 — rotasi referensi hanya sekali, dan tidak bergantung pada detik
    // ------------------------------------------------------------------

    public function test_rotasi_referensi_hanya_terjadi_sekali(): void
    {
        $auction = $this->lelang();

        $this->actingAs($this->pembeli)->post('/auctions/'.$auction->public_id.'/deposit');
        $deposit = AuctionDeposit::where('auction_id', $auction->id)->firstOrFail();
        $referensiLama = $deposit->payment_reference;

        $this->jawabanStatus = ['transaction_status' => 'expire', 'fraud_status' => 'accept'];
        $this->actingAs($this->pembeli)->post('/auctions/'.$auction->public_id.'/deposit');

        $referensiBaru = $deposit->fresh()->payment_reference;
        $this->assertNotSame($referensiLama, $referensiBaru);

        // Imbuhannya bukan cap waktu berdetik: dua rotasi dalam detik yang sama
        // akan menghasilkan referensi kembar dan ditolak kunci unik.
        $this->assertDoesNotMatchRegularExpression('/-\d{9,}$/', $referensiBaru);

        // Transaksi Snap yang baru dibuat tentu saja belum mati. Permintaan
        // berikutnya memakai transaksi itu apa adanya, tidak merotasi lagi.
        $this->jawabanStatus = ['transaction_status' => 'pending', 'fraud_status' => 'accept'];

        $this->actingAs($this->pembeli)->post('/auctions/'.$auction->public_id.'/deposit');

        $this->assertSame($referensiBaru, $deposit->fresh()->payment_reference);
        $this->assertSame(1, AuctionDeposit::where('auction_id', $auction->id)->count());
    }
}
