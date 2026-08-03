<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dana pesanan tidak boleh cair sendiri — dari jalur mana pun.
 *
 * Tidak saat seller menandai "Delivered", tidak saat pembeli menekan konfirmasi
 * terima, dan tidak lewat penjadwal. Satu-satunya jalur keluarnya uang adalah
 * pengajuan seller yang disetujui admin (PayoutRequest) — lihat
 * PayoutRequestTest.
 */
class SellerEscrowTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $buyer;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::create([
            'username' => 'seller1',
            'first_name' => 'Seller',
            'last_name' => 'Satu',
            'email' => 'seller1@vinstore.test',
            'phone' => '08110001',
            'address' => 'Jl. Antik No. 1',
            'password' => 'password',
            'role' => 'seller',
        ]);

        $this->buyer = User::create([
            'username' => 'pembeli',
            'first_name' => 'Pembeli',
            'last_name' => 'Setia',
            'email' => 'pembeli@vinstore.test',
            'phone' => '08210001',
            'address' => 'Jl. Pembeli No. 2',
            'password' => 'password',
            'role' => 'user',
        ]);

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Antik',
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);

        $this->product = Product::create([
            'store_id' => $this->store->id,
            'name' => 'Guci Ming',
            'stock' => 5,
            'price' => 1000000,
            'category' => 'Keramik',
            'description' => 'Guci antik',
            'approval_status' => Product::STATUS_APPROVED,
        ]);
    }

    private function paidOrder(string $status = 'On The Way'): Order
    {
        return Order::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_price' => $this->product->price,
            'store_id' => $this->store->id,
            'store_name' => $this->store->store_name,
            'quantity' => 1,
            'price' => 1000000,
            'status' => $status,
            'payment_status' => 'paid',
            'payment_method' => 'midtrans',
        ]);
    }

    public function test_seller_menandai_delivered_tidak_mencairkan_dana(): void
    {
        $order = $this->paidOrder('On The Way');

        $this->actingAs($this->seller)
            ->post('/seller/orders/'.$order->public_id.'/status', [
                'status' => 'Delivered',
                'tracking_number' => 'JNE123456',
            ])
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame('Delivered', $order->status);
        $this->assertNotNull($order->delivered_at);
        // Inti temuan K-06: dana TIDAK boleh cair di titik ini.
        $this->assertNull($order->seller_released_at);
        $this->assertSame('0.00', (string) $this->store->fresh()->available_balance);
    }

    public function test_konfirmasi_pembeli_menyelesaikan_pesanan_tanpa_mencairkan_dana(): void
    {
        $order = $this->paidOrder('Delivered');

        $this->actingAs($this->buyer)
            ->post('/order/'.$order->public_id.'/confirm')
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame('Completed', $order->status);
        $this->assertNotNull($order->completed_at);
        // Uang tidak bergerak. Seller harus mengajukan pencairan.
        $this->assertNull($order->seller_released_at);
        $this->assertSame('0.00', (string) $this->store->fresh()->available_balance);
        $this->assertTrue($order->canRequestPayout());
    }

    public function test_pembeli_lain_tidak_bisa_mengonfirmasi_pesanan_orang(): void
    {
        $order = $this->paidOrder('Delivered');

        $penyusup = User::create([
            'username' => 'penyusup',
            'first_name' => 'Peny',
            'last_name' => 'Usup',
            'email' => 'penyusup@vinstore.test',
            'phone' => '08310001',
            'address' => 'Jl. Lain',
            'password' => 'password',
            'role' => 'user',
        ]);

        $this->actingAs($penyusup)
            ->post('/order/'.$order->public_id.'/confirm')
            ->assertNotFound();

        $this->assertNull($order->fresh()->seller_released_at);
    }

    public function test_dana_ditahan_selama_ada_refund_pending(): void
    {
        $order = $this->paidOrder('Delivered');

        RefundRequest::create([
            'order_id' => $order->id,
            'user_id' => $this->buyer->id,
            'reason' => 'Barang tidak sesuai deskripsi sama sekali.',
            'status' => 'pending',
        ]);

        $this->actingAs($this->buyer)->post('/order/'.$order->public_id.'/confirm');

        $order->refresh();

        // Status boleh berpindah ke Completed, tapi dananya tetap ditahan
        // sampai sengketa refund diputus admin.
        $this->assertNull($order->seller_released_at);
        $this->assertSame('0.00', (string) $this->store->fresh()->available_balance);
    }

    /**
     * Sengketa yang ditolak mencabut penahanan: pesanan kembali bisa diajukan
     * pencairannya. Uangnya sendiri tetap tidak bergerak sampai admin
     * menyetujui pengajuan pencairan.
     */
    public function test_refund_ditolak_membuat_pesanan_bisa_diajukan_pencairan(): void
    {
        $order = $this->paidOrder('Delivered');

        $refund = RefundRequest::create([
            'order_id' => $order->id,
            'user_id' => $this->buyer->id,
            'reason' => 'Barang tidak sesuai deskripsi sama sekali.',
            'status' => 'pending',
        ]);

        $this->actingAs($this->buyer)->post('/order/'.$order->public_id.'/confirm');

        // Selama sengketa berjalan, pengajuan pencairan diblokir.
        $this->assertFalse($order->fresh()->canRequestPayout());

        $admin = User::create([
            'username' => 'admin1',
            'first_name' => 'Admin',
            'last_name' => 'Satu',
            'email' => 'admin1@vinstore.test',
            'phone' => '08910001',
            'address' => 'Jl. Admin',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post('/admin/refunds/'.$refund->public_id.'/reject', [
                'admin_note' => 'Bukti tidak mendukung klaim pembeli.',
            ])
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame('rejected', $refund->fresh()->status);
        $this->assertNull($order->seller_released_at);
        $this->assertTrue($order->canRequestPayout());
    }

    public function test_refund_disetujui_tidak_mencairkan_dana_ke_seller(): void
    {
        $order = $this->paidOrder('Delivered');

        $refund = RefundRequest::create([
            'order_id' => $order->id,
            'user_id' => $this->buyer->id,
            'reason' => 'Barang tidak sesuai deskripsi sama sekali.',
            'status' => 'pending',
        ]);

        $this->actingAs($this->buyer)->post('/order/'.$order->public_id.'/confirm');

        $admin = User::create([
            'username' => 'admin2',
            'first_name' => 'Admin',
            'last_name' => 'Dua',
            'email' => 'admin2@vinstore.test',
            'phone' => '08910002',
            'address' => 'Jl. Admin',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post('/admin/refunds/'.$refund->public_id.'/approve')
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame('refunded', $order->payment_status);
        $this->assertNull($order->seller_released_at);
        $this->assertSame('0.00', (string) $this->store->fresh()->available_balance);
    }

    /**
     * Penjadwal masih menutup siklus pesanan yang menggantung, tapi tidak lagi
     * menyentuh uang sepeser pun.
     */
    public function test_auto_complete_menutup_pesanan_tanpa_mencairkan_dana(): void
    {
        $order = $this->paidOrder('Delivered');
        $order->forceFill([
            'delivered_at' => now()->subDays(Order::BUYER_CONFIRMATION_WINDOW_DAYS + 1),
        ])->save();

        $this->artisan('orders:auto-complete')->assertExitCode(0);

        $order->refresh();

        $this->assertSame('Completed', $order->status);
        $this->assertNull($order->seller_released_at);
        $this->assertSame('0.00', (string) $this->store->fresh()->available_balance);
        $this->assertSame('0.00', (string) $this->store->fresh()->withdrawn_balance);
    }

    public function test_pesanan_belum_lewat_masa_sanggah_tidak_dilepas_otomatis(): void
    {
        $order = $this->paidOrder('Delivered');
        $order->forceFill(['delivered_at' => now()->subDay()])->save();

        $this->artisan('orders:auto-complete');

        $order->refresh();

        $this->assertSame('Delivered', $order->status);
        $this->assertNull($order->seller_released_at);
    }
}
