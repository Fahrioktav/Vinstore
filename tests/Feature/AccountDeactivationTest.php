<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Temuan V4-12: akun dinonaktifkan, tidak pernah dihapus.
 *
 * Perilaku lama: AdminUserController::destroy() memanggil
 * `$user->orders()->delete()` lalu menghapus usernya. Satu klik "Hapus"
 * melenyapkan pesanan yang sudah lunas, sudah dikirim, dan sudah dicairkan ke
 * seller — pembukuan marketplace berlubang tanpa jejak.
 *
 * Yang diuji di sini: riwayat selamat, akun nonaktif benar-benar tidak bisa
 * masuk lewat jalur mana pun, dan produk seller nonaktif berhenti dijual.
 */
class AccountDeactivationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $buyer;

    private User $seller;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('adminsatu', 'admin');
        $this->buyer = $this->makeUser('pembelinonaktif');
        $this->seller = $this->makeUser('sellernonaktif', 'seller');

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Nonaktif',
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);

        $this->product = Product::create([
            'store_id' => $this->store->id,
            'name' => 'Guci Uji',
            'stock' => 3,
            'price' => 1_000_000,
            'category' => 'Keramik',
            'description' => 'Guci antik',
            'approval_status' => Product::STATUS_APPROVED,
        ]);
    }

    private function makeUser(string $username, string $role = 'user'): User
    {
        return User::create([
            'username' => $username,
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
            'email' => $username.'@vinstore.test',
            'phone' => '0811'.random_int(1000000, 9999999),
            'address' => 'Jl. Antik',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function paidOrderFor(User $user): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_price' => $this->product->price,
            'store_id' => $this->store->id,
            'store_name' => $this->store->store_name,
            'quantity' => 1,
            'price' => 1_000_000,
            'status' => 'Delivered',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    /* ==================== Inti temuan ==================== */

    public function test_menonaktifkan_pembeli_tidak_menghapus_riwayat_pesanannya(): void
    {
        $order = $this->paidOrderFor($this->buyer);

        $this->actingAs($this->admin)
            ->delete('/admin/users/'.$this->buyer->public_id, ['reason' => 'Melanggar ketentuan']);

        // Akunnya masih ada, hanya ditandai nonaktif.
        $this->buyer->refresh();
        $this->assertTrue($this->buyer->isDeactivated());
        $this->assertSame('Melanggar ketentuan', $this->buyer->deactivation_reason);

        // Dan pesanannya utuh — inilah yang dulu hilang.
        $this->assertTrue(Order::whereKey($order->getKey())->exists());
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_menonaktifkan_seller_tidak_menghapus_produk_maupun_toko(): void
    {
        $order = $this->paidOrderFor($this->buyer);

        $this->actingAs($this->admin)
            ->delete('/admin/sellers/'.$this->seller->public_id, ['reason' => 'Barang tidak sesuai']);

        $this->assertTrue($this->seller->fresh()->isDeactivated());
        $this->assertTrue(Store::whereKey($this->store->getKey())->exists());
        $this->assertTrue(Product::whereKey($this->product->getKey())->exists());
        $this->assertTrue(Order::whereKey($order->getKey())->exists());
    }

    /* ==================== Akses ditutup ==================== */

    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        $this->buyer->deactivate('Melanggar ketentuan');

        $this->post('/login', [
            'login' => $this->buyer->username,
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_akun_aktif_tetap_bisa_login(): void
    {
        $this->post('/login', [
            'login' => $this->buyer->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($this->buyer);
    }

    public function test_sesi_yang_sedang_berjalan_diputus_saat_dinonaktifkan(): void
    {
        $this->actingAs($this->buyer);

        // Masih aktif: halaman terbuka seperti biasa.
        $this->get('/cart')->assertOk();

        $this->buyer->deactivate('Dinonaktifkan di tengah sesi');

        // Klik berikutnya langsung terlempar keluar. Tanpa ini, orang yang
        // sedang masuk saat dinonaktifkan bisa terus berbelanja.
        $this->get('/cart')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_akun_yang_diaktifkan_kembali_bisa_masuk_lagi(): void
    {
        $this->buyer->deactivate('Salah tandai');

        $this->actingAs($this->admin)
            ->delete('/admin/users/'.$this->buyer->public_id);

        $this->buyer->refresh();

        $this->assertFalse($this->buyer->isDeactivated());
        $this->assertNull($this->buyer->deactivation_reason);

        // Sesi admin harus ditutup dulu: route login memakai role:guestOnly,
        // jadi permintaan dari akun yang sudah masuk akan dialihkan begitu saja.
        $this->post('/logout');

        $this->post('/login', [
            'login' => $this->buyer->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($this->buyer);
    }

    /* ==================== Dampak ke etalase ==================== */

    public function test_produk_seller_nonaktif_berhenti_tampil(): void
    {
        $pembeliLain = $this->makeUser('pembelilihat');

        $this->actingAs($pembeliLain)
            ->get('/products')
            ->assertOk()
            ->assertSee($this->product->name);

        $this->seller->deactivate('Dinonaktifkan');

        $this->actingAs($pembeliLain)
            ->get('/products')
            ->assertOk()
            ->assertDontSee($this->product->name);
    }

    public function test_produk_seller_nonaktif_tidak_bisa_dibeli_lewat_url_langsung(): void
    {
        $this->seller->deactivate('Dinonaktifkan');

        $pembeliLain = $this->makeUser('pembelicoba');

        $this->actingAs($pembeliLain)
            ->post('/checkout/product/'.$this->product->public_id, [
                'quantity' => 1,
                'shipping_address' => 'Jl. Coba No. 1',
                'shipping_method' => 'standard',
                'shipping_latitude' => -7.7828,
                'shipping_longitude' => 110.3671,
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, Order::count());
    }

    public function test_produk_seller_nonaktif_tidak_bisa_masuk_keranjang(): void
    {
        $this->seller->deactivate('Dinonaktifkan');

        $pembeliLain = $this->makeUser('pembelikeranjang');

        $this->actingAs($pembeliLain)
            ->post('/cart/add/'.$this->product->public_id, ['quantity' => 1])
            ->assertSessionHas('error');

        $this->assertSame(0, \App\Models\Cart::count());
    }

    public function test_produk_tampil_lagi_setelah_seller_diaktifkan(): void
    {
        $this->seller->deactivate('Sementara');
        $this->seller->reactivate();

        $this->actingAs($this->makeUser('pembelibalik'))
            ->get('/products')
            ->assertOk()
            ->assertSee($this->product->name);
    }

    /* ==================== Penjaga ==================== */

    public function test_admin_terakhir_tidak_dapat_dinonaktifkan(): void
    {
        // Hanya ada satu admin di sistem ini.
        $this->assertFalse($this->admin->canBeDeactivated());

        $adminKedua = $this->makeUser('adminkedua', 'admin');

        // Begitu ada dua, keduanya boleh dinonaktifkan.
        $this->assertTrue($this->admin->fresh()->canBeDeactivated());
        $this->assertTrue($adminKedua->canBeDeactivated());
    }

    public function test_penonaktifan_ganda_tidak_menimpa_waktu_pertama(): void
    {
        $this->buyer->deactivate('Alasan pertama');
        $waktuPertama = $this->buyer->fresh()->deactivated_at;

        $this->travel(1)->hours();

        $this->buyer->fresh()->deactivate('Alasan kedua');

        $this->assertEquals($waktuPertama, $this->buyer->fresh()->deactivated_at);
        $this->assertSame('Alasan pertama', $this->buyer->fresh()->deactivation_reason);
    }
}
