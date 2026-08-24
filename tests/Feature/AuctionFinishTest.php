<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penutupan lelang oleh perintah terjadwal `auctions:finish`.
 *
 * Perintah ini mengerjakan dua hal setiap menit: mengaktifkan lelang yang
 * waktunya sudah tiba, dan menutup lelang yang sudah lewat sambil menetapkan
 * pemenang serta membuatkan pesanannya. Karena berjalan berulang tiap menit,
 * sifat idempotennya sama pentingnya dengan hasilnya — itulah yang paling
 * ditekankan di berkas ini.
 */
class AuctionFinishTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = $this->makeUser('seller1', 'seller1@vinstore.test', 'seller');

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Antik',
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);
    }

    private function makeUser(string $username, string $email, string $role, ?string $alamat = null): User
    {
        return User::create([
            'username' => $username,
            'first_name' => 'Uji',
            'last_name' => ucfirst($role),
            'email' => $email,
            'phone' => '0811'.random_int(100000, 999999),
            'address' => $alamat ?? 'Jl. Uji No. 1',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    /** Lelang yang masa berlakunya sudah lewat dan menunggu ditutup. */
    private function lelangKedaluwarsa(array $override = []): Auction
    {
        return Auction::create(array_merge([
            'store_id' => $this->store->id,
            'name' => 'Guci Ming',
            'description' => 'Guci antik',
            'starting_price' => 1000000,
            'min_increment' => 50000,
            'current_price' => 1000000,
            'approval_status' => Auction::STATUS_APPROVED,
            'status' => 'active',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subHour(),
        ], $override));
    }

    private function buatBid(Auction $auction, User $user, $nominal, ?int $detikLalu = null): AuctionBid
    {
        $bid = AuctionBid::create([
            'auction_id' => $auction->id,
            'user_id' => $user->id,
            'amount' => $nominal,
        ]);

        if ($detikLalu !== null) {
            // created_at dipakai sebagai pemecah seri, jadi urutannya perlu
            // bisa diatur secara eksplisit.
            $bid->forceFill(['created_at' => now()->subSeconds($detikLalu)])->save();
        }

        return $bid->fresh();
    }

    private function jalankanPerintah(): void
    {
        $this->artisan('auctions:finish')->assertSuccessful();
    }

    /* ===================== Aktivasi lelang terjadwal ===================== */

    public function test_lelang_terjadwal_yang_waktunya_tiba_menjadi_aktif(): void
    {
        $auction = $this->lelangKedaluwarsa([
            'status' => 'scheduled',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
        ]);

        $this->jalankanPerintah();

        $this->assertSame('active', $auction->fresh()->status);
    }

    public function test_lelang_yang_belum_waktunya_tetap_terjadwal(): void
    {
        $auction = $this->lelangKedaluwarsa([
            'status' => 'scheduled',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addDay(),
        ]);

        $this->jalankanPerintah();

        $this->assertSame('scheduled', $auction->fresh()->status);
    }

    public function test_lelang_yang_belum_disetujui_tidak_ikut_diaktifkan(): void
    {
        $auction = $this->lelangKedaluwarsa([
            'approval_status' => Auction::STATUS_PENDING_ADMIN,
            'status' => 'scheduled',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
        ]);

        $this->jalankanPerintah();

        $this->assertSame('scheduled', $auction->fresh()->status);
    }

    /* ===================== Penutupan dan pemenang ===================== */

    public function test_lelang_kedaluwarsa_ditutup_dan_dicatat_waktu_berakhirnya(): void
    {
        $auction = $this->lelangKedaluwarsa();

        $this->jalankanPerintah();

        $auction->refresh();

        $this->assertSame('ended', $auction->status);
        $this->assertNotNull($auction->ended_at);
    }

    public function test_penawar_tertinggi_menjadi_pemenang(): void
    {
        $auction = $this->lelangKedaluwarsa();

        $kalah = $this->makeUser('penawar1', 'penawar1@vinstore.test', 'user');
        $menang = $this->makeUser('penawar2', 'penawar2@vinstore.test', 'user');

        $this->buatBid($auction, $kalah, 1050000);
        $this->buatBid($auction, $menang, 1200000);

        $this->jalankanPerintah();

        $this->assertSame($menang->id, $auction->fresh()->winner_id);
    }

    public function test_urutan_penawaran_tidak_menentukan_pemenang(): void
    {
        $auction = $this->lelangKedaluwarsa();

        $menang = $this->makeUser('penawar1', 'penawar1@vinstore.test', 'user');
        $kalah = $this->makeUser('penawar2', 'penawar2@vinstore.test', 'user');

        // Nominal tertinggi diajukan lebih dulu; yang belakangan lebih rendah.
        $this->buatBid($auction, $menang, 2000000, 120);
        $this->buatBid($auction, $kalah, 1500000, 60);

        $this->jalankanPerintah();

        $this->assertSame($menang->id, $auction->fresh()->winner_id);
    }

    public function test_nominal_seri_dimenangkan_penawar_yang_lebih_dulu(): void
    {
        $auction = $this->lelangKedaluwarsa();

        $duluan = $this->makeUser('penawar1', 'penawar1@vinstore.test', 'user');
        $belakangan = $this->makeUser('penawar2', 'penawar2@vinstore.test', 'user');

        $this->buatBid($auction, $duluan, 1500000, 120);
        $this->buatBid($auction, $belakangan, 1500000, 60);

        $this->jalankanPerintah();

        $this->assertSame($duluan->id, $auction->fresh()->winner_id);
    }

    public function test_lelang_tanpa_penawaran_ditutup_tanpa_pemenang(): void
    {
        $auction = $this->lelangKedaluwarsa();

        $this->jalankanPerintah();

        $auction->refresh();

        $this->assertSame('ended', $auction->status);
        $this->assertNull($auction->winner_id);
        $this->assertSame(0, Order::where('auction_id', $auction->id)->count());
    }

    public function test_lelang_yang_belum_berakhir_tidak_ikut_ditutup(): void
    {
        $auction = $this->lelangKedaluwarsa(['ends_at' => now()->addHour()]);

        $penawar = $this->makeUser('penawar1', 'penawar1@vinstore.test', 'user');
        $this->buatBid($auction, $penawar, 1050000);

        $this->jalankanPerintah();

        $auction->refresh();

        $this->assertSame('active', $auction->status);
        $this->assertNull($auction->winner_id);
    }

    /* ===================== Pesanan untuk pemenang ===================== */

    public function test_pesanan_dibuat_untuk_pemenang_dengan_nominal_bid_tertinggi(): void
    {
        $auction = $this->lelangKedaluwarsa();

        $pemenang = $this->makeUser('penawar1', 'penawar1@vinstore.test', 'user', 'Jl. Pemenang No. 9');
        $this->buatBid($auction, $pemenang, 1750000);

        $this->jalankanPerintah();

        $order = Order::where('auction_id', $auction->id)->first();

        $this->assertNotNull($order);
        $this->assertSame($pemenang->id, $order->user_id);
        // `price` tidak di-cast decimal seperti `product_price`, jadi
        // dibandingkan sebagai nilai numerik.
        $this->assertEquals(1750000, $order->price);
        $this->assertSame(1, $order->quantity);
        $this->assertNull($order->product_id);
    }

    public function test_pesanan_lelang_menyimpan_snapshot_nama_lelang_dan_toko(): void
    {
        $auction = $this->lelangKedaluwarsa(['name' => 'Keris Pusaka Langka']);

        $pemenang = $this->makeUser('penawar1', 'penawar1@vinstore.test', 'user');
        $this->buatBid($auction, $pemenang, 1500000);

        $this->jalankanPerintah();

        $order = Order::where('auction_id', $auction->id)->first();

        // Snapshot dibutuhkan agar riwayat pesanan tetap terbaca walau lelang
        // atau tokonya kelak dihapus.
        $this->assertSame('Keris Pusaka Langka', $order->product_name);
        $this->assertSame('Toko Antik', $order->store_name);
        $this->assertSame('1500000.00', $order->product_price);
    }

    public function test_pesanan_lelang_dibuat_dalam_keadaan_belum_dibayar(): void
    {
        $auction = $this->lelangKedaluwarsa();

        $pemenang = $this->makeUser('penawar1', 'penawar1@vinstore.test', 'user');
        $this->buatBid($auction, $pemenang, 1500000);

        $this->jalankanPerintah();

        $order = Order::where('auction_id', $auction->id)->first();

        $this->assertSame('Waiting', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('midtrans', $order->payment_method);
        $this->assertSame($order->public_id, $order->payment_reference);
    }

    public function test_alamat_pengiriman_diambil_dari_profil_pemenang(): void
    {
        $auction = $this->lelangKedaluwarsa();

        $pemenang = $this->makeUser('penawar1', 'penawar1@vinstore.test', 'user', 'Jl. Melati No. 45');
        $this->buatBid($auction, $pemenang, 1500000);

        $this->jalankanPerintah();

        // Lelang tidak punya form checkout, jadi alamatnya harus datang dari
        // profil pemenang supaya seller tetap punya tujuan pengiriman.
        $this->assertSame(
            'Jl. Melati No. 45',
            Order::where('auction_id', $auction->id)->first()->shipping_address
        );
    }

    /* ===================== Idempotensi ===================== */

    public function test_perintah_dijalankan_berulang_tidak_membuat_pesanan_ganda(): void
    {
        $auction = $this->lelangKedaluwarsa();

        $pemenang = $this->makeUser('penawar1', 'penawar1@vinstore.test', 'user');
        $this->buatBid($auction, $pemenang, 1500000);

        // Penjadwal memanggil perintah ini setiap menit.
        $this->jalankanPerintah();
        $this->jalankanPerintah();
        $this->jalankanPerintah();

        $this->assertSame(1, Order::where('auction_id', $auction->id)->count());
    }

    public function test_lelang_yang_sudah_ditutup_tidak_diproses_ulang(): void
    {
        $auction = $this->lelangKedaluwarsa();

        $pemenang = $this->makeUser('penawar1', 'penawar1@vinstore.test', 'user');
        $this->buatBid($auction, $pemenang, 1500000);

        $this->jalankanPerintah();

        $waktuBerakhirPertama = $auction->fresh()->ended_at;

        $this->travel(5)->minutes();
        $this->jalankanPerintah();

        $this->assertEquals($waktuBerakhirPertama, $auction->fresh()->ended_at);
    }

    public function test_beberapa_lelang_kedaluwarsa_ditutup_sekaligus(): void
    {
        $pertama = $this->lelangKedaluwarsa(['name' => 'Lelang Satu']);
        $kedua = $this->lelangKedaluwarsa(['name' => 'Lelang Dua']);

        $penawar = $this->makeUser('penawar1', 'penawar1@vinstore.test', 'user');
        $this->buatBid($pertama, $penawar, 1500000);
        $this->buatBid($kedua, $penawar, 1600000);

        $this->jalankanPerintah();

        $this->assertSame('ended', $pertama->fresh()->status);
        $this->assertSame('ended', $kedua->fresh()->status);
        $this->assertSame(2, Order::whereNotNull('auction_id')->count());
    }
}
