<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionDeposit;
use App\Models\Order;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression test untuk temuan V11-02, V11-03, dan V11-05.
 *
 * V11-02: pesanan lelang yang direfund meninggalkan jaminannya berstatus
 *         `applied` — "sudah dipakai sebagai uang muka" atas pesanan yang sudah
 *         tidak ada, dan pemiliknya kehilangan jalur untuk memintanya.
 * V11-03: pengguna yang lupa password tidak bisa masuk, sehingga chat bantuan
 *         pun tidak terjangkau olehnya. Admin kini bisa membuatkan tautan reset.
 * V11-05: menghapus produk yang sesi tebak harganya berjalan ikut melenyapkan
 *         tebakan peserta, tanpa satu pun pemberitahuan.
 */
class AccountRecoveryAndRefundEdgeTest extends TestCase
{
    use RefreshDatabase;

    private User $pembeli;

    private User $seller;

    private User $admin;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pembeli = $this->makeUser('pembeli', 'user');
        $this->seller = $this->makeUser('seller', 'seller');
        $this->admin = $this->makeUser('admin', 'admin');

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Antik',
            'category' => 'Antik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);
    }

    private function makeUser(string $nama, string $role): User
    {
        return User::create([
            'username' => $nama,
            'first_name' => ucfirst($nama),
            'last_name' => 'Uji',
            'email' => $nama.'@vinstore.test',
            'phone' => '0811'.random_int(1000000, 9999999),
            'address' => 'Jl. Uji',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    // ------------------------------------------------------------------
    // V11-02 — jaminan lelang pada pesanan yang direfund
    // ------------------------------------------------------------------

    private function pesananLelangLunas(): array
    {
        $auction = Auction::create([
            'store_id' => $this->store->id,
            'name' => 'Guci Dinasti Ming',
            'description' => 'Barang antik',
            'weight' => 4000,
            'starting_price' => 5_000_000,
            'min_increment' => 100_000,
            'current_price' => 6_000_000,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
            'status' => 'ended',
            'approval_status' => Auction::STATUS_APPROVED,
            'winner_id' => $this->pembeli->id,
        ]);

        $deposit = AuctionDeposit::create([
            'auction_id' => $auction->id,
            'user_id' => $this->pembeli->id,
            'amount' => 500_000,
            'status' => AuctionDeposit::STATUS_APPLIED,
            'payment_reference' => 'DEP-UJI-1',
            'paid_at' => now()->subDays(2),
        ]);

        $order = Order::create([
            'user_id' => $this->pembeli->id,
            'auction_id' => $auction->id,
            'product_name' => $auction->name,
            'product_price' => 6_000_000,
            'store_id' => $this->store->id,
            'store_name' => $this->store->store_name,
            'quantity' => 1,
            'price' => 6_000_000,
            'deposit_credit' => 500_000,
            'status' => 'Waiting',
            'shipping_address' => 'Jl. Pembeli No. 2',
            'payment_status' => 'paid',
            'payment_method' => 'midtrans',
            'paid_at' => now(),
        ]);

        return [$order, $deposit];
    }

    public function test_refund_pesanan_lelang_ikut_mengembalikan_jaminannya(): void
    {
        [$order, $deposit] = $this->pesananLelangLunas();

        $this->actingAs($this->pembeli)->post('/order/'.$order->public_id.'/refund', [
            'reason' => 'Penjual tidak kunjung mengirim barangnya sampai hari ini.',
        ]);

        $refund = RefundRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/refunds/'.$refund->public_id.'/approve', ['admin_note' => 'Disetujui.'])
            ->assertSessionHas('success');

        $deposit->refresh();

        $this->assertSame(AuctionDeposit::STATUS_REFUNDED, $deposit->status);
        $this->assertNotNull($deposit->refunded_at);
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public function test_pengembalian_jaminan_idempoten(): void
    {
        [$order, $deposit] = $this->pesananLelangLunas();

        AuctionDeposit::returnForRefundedOrder($order);
        $pertama = $deposit->fresh()->refunded_at;

        AuctionDeposit::returnForRefundedOrder($order);

        $this->assertEquals($pertama, $deposit->fresh()->refunded_at);
    }

    /**
     * Test kontrol: pesanan biasa tidak punya jaminan, dan penanganannya tidak
     * boleh menyentuh apa pun.
     */
    public function test_refund_pesanan_biasa_tidak_terpengaruh(): void
    {
        $produk = Product::create([
            'store_id' => $this->store->id,
            'name' => 'Gramofon',
            'stock' => 2,
            'price' => 500_000,
            'category' => 'Antik',
            'description' => 'Barang antik',
            'approval_status' => Product::STATUS_APPROVED,
        ]);

        $order = Order::create([
            'user_id' => $this->pembeli->id,
            'product_id' => $produk->id,
            'product_name' => $produk->name,
            'product_price' => 500_000,
            'store_id' => $this->store->id,
            'store_name' => $this->store->store_name,
            'quantity' => 1,
            'price' => 500_000,
            'status' => 'Waiting',
            'shipping_address' => 'Jl. Pembeli No. 2',
            'payment_status' => 'paid',
            'payment_method' => 'midtrans',
            'paid_at' => now(),
        ]);

        $this->assertNull(AuctionDeposit::returnForRefundedOrder($order));
    }

    // ------------------------------------------------------------------
    // V11-03 — tautan reset password yang dibuatkan admin
    // ------------------------------------------------------------------

    public function test_admin_dapat_membuatkan_tautan_reset_password(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/users/'.$this->pembeli->public_id.'/reset-link')
            ->assertSessionHas('passwordResetLink')
            ->assertSessionHas('passwordResetFor', $this->pembeli->email);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $this->pembeli->email]);
    }

    public function test_tautan_reset_buatan_admin_benar_benar_dapat_dipakai(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/users/'.$this->pembeli->public_id.'/reset-link');

        $link = session('passwordResetLink');
        $token = basename(parse_url($link, PHP_URL_PATH));

        // Sesi admin ditutup dulu: yang memakai tautan ini adalah pemilik akun.
        $this->post('/logout');

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $this->pembeli->email,
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ])->assertRedirect(route('login.form'));

        $this->assertTrue(Hash::check('password-baru', $this->pembeli->fresh()->password));
    }

    public function test_akun_admin_tidak_dapat_dibuatkan_tautan_lewat_jalur_ini(): void
    {
        $adminLain = $this->makeUser('adminlain', 'admin');

        $this->actingAs($this->admin)
            ->post('/admin/users/'.$adminLain->public_id.'/reset-link')
            ->assertNotFound();
    }

    public function test_akun_google_tanpa_password_diberi_tahu_untuk_memakai_tombol_google(): void
    {
        $google = User::create([
            'username' => 'akun-google',
            'first_name' => 'Akun',
            'last_name' => 'Google',
            'email' => 'akungoogle@vinstore.test',
            'phone' => '081100000001',
            'address' => 'Jl. Google',
            'password' => null,
            'google_id' => '99887766',
            'role' => 'user',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/users/'.$google->public_id.'/reset-link')
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $google->email]);
    }

    // ------------------------------------------------------------------
    // V11-05 — produk tebak harga yang sesinya berjalan
    // ------------------------------------------------------------------

    private function produkTebakHarga(string $guessStatus): Product
    {
        return Product::create([
            'store_id' => $this->store->id,
            'name' => 'Arloji Saku Kuno',
            'stock' => 1,
            'price' => 4_000_000,
            'category' => 'Antik',
            'description' => 'Barang antik',
            'approval_status' => Product::STATUS_APPROVED,
            'sale_type' => Product::SALE_TYPE_TEBAK_HARGA,
            'guess_discount_price' => 3_000_000,
            'guess_starts_at' => now()->subDay(),
            'guess_ends_at' => now()->addDay(),
            'guess_status' => $guessStatus,
        ]);
    }

    public function test_produk_dengan_sesi_tebak_harga_berjalan_tidak_dapat_dihapus(): void
    {
        $produk = $this->produkTebakHarga(Product::GUESS_ACTIVE);

        $this->actingAs($this->seller)
            ->delete('/seller/products/'.$produk->public_id)
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['public_id' => $produk->public_id]);
    }

    public function test_produk_yang_pemenangnya_masih_punya_hak_beli_tidak_dapat_dihapus(): void
    {
        $produk = $this->produkTebakHarga(Product::GUESS_ENDED);
        $produk->forceFill([
            'guess_ends_at' => now()->subHour(),
            'guess_winner_id' => $this->pembeli->id,
        ])->save();

        $this->actingAs($this->seller)
            ->delete('/seller/products/'.$produk->public_id)
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['public_id' => $produk->public_id]);
    }

    /**
     * Test kontrol: produk biasa tetap boleh dihapus kapan pun. Tanpa ini,
     * penjagaan di atas mudah melebar menjadi "produk tidak bisa dihapus".
     */
    public function test_produk_biasa_tetap_dapat_dihapus(): void
    {
        $produk = Product::create([
            'store_id' => $this->store->id,
            'name' => 'Gramofon',
            'stock' => 1,
            'price' => 500_000,
            'category' => 'Antik',
            'description' => 'Barang antik',
            'approval_status' => Product::STATUS_APPROVED,
        ]);

        $this->actingAs($this->seller)
            ->delete('/seller/products/'.$produk->public_id)
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('products', ['public_id' => $produk->public_id]);
    }
}
