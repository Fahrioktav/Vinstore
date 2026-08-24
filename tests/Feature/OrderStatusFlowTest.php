<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Urutan status pesanan dan nomor resi (temuan V9-01).
 *
 * Sebelum perbaikan, aturannya hanya "salah satu dari lima nilai yang dikenal".
 * Seller bisa memindahkan pesanan dari `Waiting` langsung ke `Delivered` tanpa
 * pernah mengirim apa pun dan tanpa nomor resi — `Delivered` justru satu-satunya
 * status pengiriman yang tidak menuntutnya. Lompatan itu mengisi `delivered_at`,
 * memulai masa sanggah pembeli, dan membuka pengajuan pencairan: uang keluar dari
 * marketplace sebelum barangnya bergerak.
 */
class OrderStatusFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Store $store;

    private User $pembeli;

    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = $this->buatUser('sellerstatus', 'seller');

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Status',
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);

        $this->pembeli = $this->buatUser('pembelistatus');

        $this->produk = Product::create([
            'store_id' => $this->store->id,
            'name' => 'Piring Status',
            'stock' => 5,
            'price' => 500_000,
            'weight' => 1000,
            'category' => 'Keramik',
            'description' => 'Piring antik',
            'approval_status' => Product::STATUS_APPROVED,
        ]);
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

    private function pesanan(string $status = 'Waiting', ?string $resi = null): Order
    {
        return Order::create([
            'user_id' => $this->pembeli->id,
            'product_id' => $this->produk->id,
            'product_name' => $this->produk->name,
            'product_price' => 500_000,
            'store_id' => $this->store->id,
            'store_name' => $this->store->store_name,
            'quantity' => 1,
            'price' => 500_000,
            'status' => $status,
            'tracking_number' => $resi,
            'shipping_address' => 'Jl. Pembeli No. 2',
            'payment_status' => 'paid',
            'payment_method' => 'midtrans',
            'paid_at' => now(),
        ]);
    }

    private function ubahStatus(Order $order, array $data)
    {
        return $this->actingAs($this->seller)
            ->post('/seller/orders/'.$order->public_id.'/status', $data);
    }

    // ------------------------------------------------------------------
    // Inti temuan: lompatan ke Delivered
    // ------------------------------------------------------------------

    public function test_seller_tidak_bisa_melompat_dari_waiting_ke_delivered(): void
    {
        $order = $this->pesanan('Waiting');

        $this->ubahStatus($order, ['status' => 'Delivered', 'tracking_number' => 'JNE123'])
            ->assertSessionHas('error');

        $order->refresh();

        $this->assertSame('Waiting', $order->status);
        $this->assertNull($order->delivered_at);
        $this->assertFalse($order->canRequestPayout());
    }

    public function test_delivered_tanpa_nomor_resi_ditolak(): void
    {
        // Sampai di "On The Way" tanpa resi hanya mungkin pada data lama; yang
        // diuji di sini adalah penjagaan resinya, bukan cara ia sampai ke sana.
        $order = $this->pesanan('On The Way');

        $this->ubahStatus($order, ['status' => 'Delivered'])
            ->assertSessionHas('error');

        $order->refresh();

        $this->assertSame('On The Way', $order->status);
        $this->assertNull($order->delivered_at);
    }

    public function test_alur_pengiriman_yang_berurutan_diterima(): void
    {
        $order = $this->pesanan('Waiting');

        $this->ubahStatus($order, ['status' => 'Processing', 'tracking_number' => 'JNE123456'])
            ->assertSessionHas('success');

        $this->ubahStatus($order->refresh(), ['status' => 'On The Way'])
            ->assertSessionHas('success');

        $this->ubahStatus($order->refresh(), ['status' => 'Delivered'])
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame('Delivered', $order->status);
        $this->assertSame('JNE123456', $order->tracking_number);
        $this->assertNotNull($order->delivered_at);

        // Barulah di sini pencairan boleh diajukan.
        $this->assertTrue($order->canRequestPayout());
    }

    public function test_status_tidak_bisa_ditarik_mundur(): void
    {
        $order = $this->pesanan('Delivered', 'JNE123456');
        $order->forceFill(['delivered_at' => now()])->save();

        $this->ubahStatus($order, ['status' => 'Waiting'])
            ->assertSessionHas('error');

        $this->assertSame('Delivered', $order->fresh()->status);
    }

    public function test_pembatalan_masih_boleh_selama_barang_belum_dinyatakan_sampai(): void
    {
        $order = $this->pesanan('Waiting');

        $this->ubahStatus($order, ['status' => 'Cancelled'])
            ->assertSessionHas('success');

        $this->assertSame('Cancelled', $order->fresh()->status);
    }

    /**
     * Dropdown di dashboard seller mengambil pilihannya dari sini, supaya
     * aturannya tidak ditulis dua kali dan tidak berisiko berbeda.
     */
    public function test_pilihan_status_yang_sah_ikut_terkirim_ke_halaman(): void
    {
        $this->assertSame(['Processing', 'Cancelled'], $this->pesanan('Waiting')->allowed_statuses);
        $this->assertSame(['On The Way', 'Cancelled'], $this->pesanan('Processing')->allowed_statuses);
        $this->assertSame(['Delivered', 'Cancelled'], $this->pesanan('On The Way')->allowed_statuses);
        $this->assertSame([], $this->pesanan('Delivered')->allowed_statuses);
    }
}
