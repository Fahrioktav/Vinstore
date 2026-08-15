<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionDeposit;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aturan penawaran lelang (AuctionController@bid).
 *
 * Penawaran memindahkan nilai: nominalnya menjadi current_price dan menentukan
 * siapa yang menang saat lelang ditutup. Seluruh aturan penolakannya dijalankan
 * di dalam DB::transaction + lockForUpdate, jadi yang diuji di sini bukan hanya
 * pesan errornya melainkan juga bahwa keadaan lelang benar-benar tidak berubah
 * ketika penawaran ditolak.
 */
class AuctionBidTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Store $store;

    private User $penawar;

    private User $penawarLain;

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

        $this->penawar = $this->makeUser('penawar1', 'penawar1@vinstore.test', 'user');
        $this->penawarLain = $this->makeUser('penawar2', 'penawar2@vinstore.test', 'user');
    }

    private function makeUser(string $username, string $email, string $role): User
    {
        return User::create([
            'username' => $username,
            'first_name' => 'Uji',
            'last_name' => ucfirst($role),
            'email' => $email,
            'phone' => '0811'.random_int(100000, 999999),
            'address' => 'Jl. Uji No. 1',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    /**
     * Lelang yang sedang berjalan dan siap menerima penawaran.
     *
     * Harga awalnya Rp 1.000.000, jadi lelang ini memungut deposit. Kedua
     * penawar langsung dibuatkan jaminan yang sudah lunas supaya berkas ini
     * tetap menguji aturan PENAWARAN saja; aturan depositnya sendiri diuji
     * terpisah di AuctionDepositTest.
     */
    private function lelangAktif(array $override = []): Auction
    {
        $auction = Auction::create(array_merge([
            'store_id' => $this->store->id,
            'name' => 'Guci Ming',
            'description' => 'Guci antik',
            'starting_price' => 1000000,
            'min_increment' => 50000,
            'current_price' => 1000000,
            'approval_status' => Auction::STATUS_APPROVED,
            'status' => 'active',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
        ], $override));

        $this->berikanDeposit($auction, $this->penawar);
        $this->berikanDeposit($auction, $this->penawarLain);

        return $auction;
    }

    private function berikanDeposit(Auction $auction, User $user): AuctionDeposit
    {
        return AuctionDeposit::create([
            'auction_id' => $auction->id,
            'user_id' => $user->id,
            'amount' => $auction->depositAmount(),
            'status' => AuctionDeposit::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    private function ajukanBid(User $user, Auction $auction, $nominal)
    {
        return $this->actingAs($user)
            ->post('/auctions/'.$auction->public_id.'/bid', ['amount' => $nominal]);
    }

    public function test_penawaran_tepat_di_kelipatan_minimum_diterima(): void
    {
        $auction = $this->lelangAktif();

        // 1.000.000 + 50.000 = 1.050.000, nominal terkecil yang sah.
        $this->ajukanBid($this->penawar, $auction, 1050000)
            ->assertSessionHas('success');

        $auction->refresh();

        $this->assertSame('1050000.00', $auction->current_price);
        $this->assertSame(1, $auction->bids_count);
        $this->assertSame(1, AuctionBid::where('auction_id', $auction->id)->count());
    }

    public function test_penawaran_di_atas_kelipatan_minimum_diterima(): void
    {
        $auction = $this->lelangAktif();

        $this->ajukanBid($this->penawar, $auction, 2000000)
            ->assertSessionHas('success');

        $this->assertSame('2000000.00', $auction->fresh()->current_price);
    }

    public function test_penawaran_di_bawah_kelipatan_minimum_ditolak(): void
    {
        $auction = $this->lelangAktif();

        // 1.040.000 masih di bawah minimum 1.050.000.
        $this->ajukanBid($this->penawar, $auction, 1040000)
            ->assertSessionHas('error');

        $auction->refresh();

        $this->assertSame('1000000.00', $auction->current_price);
        $this->assertSame(0, AuctionBid::where('auction_id', $auction->id)->count());
    }

    public function test_penawaran_sama_dengan_harga_saat_ini_ditolak(): void
    {
        $auction = $this->lelangAktif();

        $this->ajukanBid($this->penawar, $auction, 1000000)
            ->assertSessionHas('error');

        $this->assertSame(0, AuctionBid::where('auction_id', $auction->id)->count());
    }

    public function test_kelipatan_minimum_dihitung_ulang_setelah_ada_penawaran(): void
    {
        $auction = $this->lelangAktif();

        $this->ajukanBid($this->penawar, $auction, 1050000);

        // Minimum berikutnya 1.050.000 + 50.000 = 1.100.000, bukan lagi dari
        // harga awal.
        $this->ajukanBid($this->penawarLain, $auction, 1080000)
            ->assertSessionHas('error');

        $this->assertSame('1050000.00', $auction->fresh()->current_price);

        $this->ajukanBid($this->penawarLain, $auction, 1100000)
            ->assertSessionHas('success');

        $this->assertSame('1100000.00', $auction->fresh()->current_price);
    }

    public function test_penawar_terakhir_tidak_boleh_menawar_dua_kali_berturut_turut(): void
    {
        $auction = $this->lelangAktif();

        $this->ajukanBid($this->penawar, $auction, 1050000)
            ->assertSessionHas('success');

        $this->ajukanBid($this->penawar, $auction, 1200000)
            ->assertSessionHas('error');

        $auction->refresh();

        $this->assertSame('1050000.00', $auction->current_price);
        $this->assertSame(1, $auction->bids_count);
    }

    public function test_penawar_boleh_menawar_lagi_setelah_diselingi_penawar_lain(): void
    {
        $auction = $this->lelangAktif();

        // Waktu sengaja dimajukan antar penawaran. Pengecekan "penawar
        // terakhir" di AuctionController@bid memakai latest(), yaitu urut
        // created_at yang presisinya hanya sampai detik; bila beberapa
        // penawaran jatuh pada detik yang sama, baris mana yang terambil tidak
        // menentu dan penawar yang sah bisa ikut tertolak. Lihat catatan pada
        // dokumen hasil pengujian.
        $this->ajukanBid($this->penawar, $auction, 1050000);

        $this->travel(1)->seconds();
        $this->ajukanBid($this->penawarLain, $auction, 1100000);

        $this->travel(1)->seconds();
        $this->ajukanBid($this->penawar, $auction, 1200000)
            ->assertSessionHas('success');

        $auction->refresh();

        $this->assertSame('1200000.00', $auction->current_price);
        $this->assertSame(3, $auction->bids_count);
    }

    /**
     * Lelang hanya untuk pembeli.
     *
     * Sebelumnya seller ditahan hanya pada lelang TOKONYA SENDIRI, sehingga ia
     * bebas menawar di lelang pesaing. Sekarang gerbangnya di tingkat rute:
     * seluruh keikutsertaan lelang berada di grup `role:user`, dan seller yang
     * mencobanya diarahkan pulang ke dashboard-nya tanpa sempat menyentuh
     * controller.
     */
    public function test_seller_tidak_boleh_menawar_lelang_tokonya_sendiri(): void
    {
        $auction = $this->lelangAktif();

        $this->ajukanBid($this->seller, $auction, 2000000)
            ->assertRedirect('/seller/dashboard');

        $this->assertSame(0, AuctionBid::where('auction_id', $auction->id)->count());
    }

    public function test_seller_lain_juga_tidak_boleh_menawar(): void
    {
        $auction = $this->lelangAktif();

        $sellerLain = $this->makeUser('seller2', 'seller2@vinstore.test', 'seller');

        Store::create([
            'user_id' => $sellerLain->id,
            'store_name' => 'Toko Lain',
            'category' => 'Logam',
            'description' => 'Toko lain',
            'location' => 'Solo',
        ]);

        $this->berikanDeposit($auction, $sellerLain);

        $this->ajukanBid($sellerLain, $auction, 1050000)
            ->assertRedirect('/seller/dashboard');

        $this->assertSame(0, AuctionBid::where('auction_id', $auction->id)->count());
        $this->assertSame('1000000.00', $auction->fresh()->current_price);
    }

    public function test_validator_dan_admin_juga_tidak_boleh_menawar(): void
    {
        $auction = $this->lelangAktif();

        $this->ajukanBid($this->makeUser('validator1', 'validator1@vinstore.test', 'validator'), $auction, 1050000)
            ->assertRedirect('/validator/dashboard');

        $this->ajukanBid($this->makeUser('admin1', 'admin1@vinstore.test', 'admin'), $auction, 1050000)
            ->assertRedirect('/admin/dashboard');

        $this->assertSame(0, AuctionBid::where('auction_id', $auction->id)->count());
    }

    public function test_lelang_yang_belum_mulai_tidak_menerima_penawaran(): void
    {
        $auction = $this->lelangAktif([
            'status' => 'scheduled',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addDay(),
        ]);

        $this->ajukanBid($this->penawar, $auction, 2000000)
            ->assertSessionHas('error');

        $this->assertSame(0, AuctionBid::where('auction_id', $auction->id)->count());
    }

    public function test_lelang_yang_sudah_berakhir_tidak_menerima_penawaran(): void
    {
        $auction = $this->lelangAktif([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subHour(),
        ]);

        $this->ajukanBid($this->penawar, $auction, 2000000)
            ->assertSessionHas('error');

        $this->assertSame(0, AuctionBid::where('auction_id', $auction->id)->count());

        // Catatan perilaku: bid() memanggil refreshAuctionStatus() yang menutup
        // lelang kedaluwarsa, tetapi itu terjadi di dalam transaksi yang sama
        // dengan penolakan bid — begitu RuntimeException dilempar, penutupannya
        // ikut ter-rollback. Lelang baru benar-benar ditutup oleh perintah
        // terjadwal `auctions:finish` (lihat AuctionFinishTest). Status di sini
        // sengaja tidak diassert supaya perilaku itu tidak ikut dikunci.
    }

    public function test_lelang_yang_belum_disetujui_tidak_menerima_penawaran(): void
    {
        $auction = $this->lelangAktif([
            'approval_status' => Auction::STATUS_PENDING_ADMIN,
            'status' => 'pending',
        ]);

        $this->ajukanBid($this->penawar, $auction, 2000000)
            ->assertSessionHas('error');

        $this->assertSame(0, AuctionBid::where('auction_id', $auction->id)->count());
    }

    public function test_nominal_penawaran_wajib_berupa_angka(): void
    {
        $auction = $this->lelangAktif();

        $this->ajukanBid($this->penawar, $auction, 'bukan angka')
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, AuctionBid::where('auction_id', $auction->id)->count());
    }

    public function test_pengunjung_yang_belum_login_tidak_bisa_menawar(): void
    {
        $auction = $this->lelangAktif();

        $this->post('/auctions/'.$auction->public_id.'/bid', ['amount' => 2000000])
            ->assertRedirect('/login');

        $this->assertSame(0, AuctionBid::where('auction_id', $auction->id)->count());
    }
}
