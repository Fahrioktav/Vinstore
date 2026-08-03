<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test untuk temuan K-04.
 *
 * Dulu foreign key orders.product_id memakai cascade delete, sehingga menghapus
 * produk ikut menghapus seluruh riwayat pesanannya — termasuk pesanan berbayar.
 * Seller bisa memakainya untuk menghilangkan bukti pesanan yang bermasalah.
 *
 * Perilaku yang benar: produk boleh dihapus, tetapi riwayat pesanan pembeli
 * WAJIB tetap ada dan tetap terbaca lewat snapshot nama/harga produk.
 */
class ProductDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private function createSeller(string $suffix = '1'): User
    {
        return User::create([
            'username' => 'seller'.$suffix,
            'first_name' => 'Seller',
            'last_name' => 'Satu',
            'email' => "seller{$suffix}@vinstore.test",
            'phone' => '0811000'.$suffix,
            'address' => 'Jl. Antik No. 1',
            'password' => 'password',
            'role' => 'seller',
        ]);
    }

    private function createBuyer(): User
    {
        return User::create([
            'username' => 'pembeli',
            'first_name' => 'Pembeli',
            'last_name' => 'Setia',
            'email' => 'pembeli@vinstore.test',
            'phone' => '082100001',
            'address' => 'Jl. Pembeli No. 2',
            'password' => 'password',
            'role' => 'user',
        ]);
    }

    private function createStoreFor(User $seller): Store
    {
        return Store::create([
            'user_id' => $seller->id,
            'store_name' => 'Toko '.$seller->username,
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);
    }

    private function createProductFor(Store $store): Product
    {
        return Product::create([
            'store_id' => $store->id,
            'name' => 'Guci Ming',
            'stock' => 5,
            'price' => 12000000,
            'category' => 'Keramik',
            'description' => 'Guci antik dinasti Ming',
            'approval_status' => Product::STATUS_APPROVED,
        ]);
    }

    private function createOrder(User $buyer, Product $product, string $status, string $paymentStatus): Order
    {
        return Order::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'store_id' => $product->store_id,
            'store_name' => $product->store->store_name,
            'quantity' => 1,
            'price' => $product->price,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'payment_method' => 'midtrans',
        ]);
    }

    public function test_menghapus_produk_tidak_menghapus_riwayat_pesanan_pembeli(): void
    {
        $seller = $this->createSeller();
        $store = $this->createStoreFor($seller);
        $product = $this->createProductFor($store);
        $order = $this->createOrder($this->createBuyer(), $product, 'Delivered', 'paid');

        $this->actingAs($seller)
            ->delete('/seller/products/'.$product->public_id)
            ->assertSessionHas('success');

        // Produk memang hilang...
        $this->assertDatabaseMissing('products', ['id' => $product->id]);

        // ...tapi pesanannya tetap ada, hanya kehilangan tautan ke produk.
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'product_id' => null,
            'payment_status' => 'paid',
        ]);
    }

    public function test_riwayat_pesanan_tetap_terbaca_setelah_produk_dihapus(): void
    {
        $seller = $this->createSeller();
        $store = $this->createStoreFor($seller);
        $product = $this->createProductFor($store);
        $order = $this->createOrder($this->createBuyer(), $product, 'Delivered', 'paid');

        $this->actingAs($seller)->delete('/seller/products/'.$product->public_id);

        $order->refresh();

        $this->assertNull($order->product);
        $this->assertSame('Guci Ming', $order->display_item_name);
        $this->assertSame('Toko seller1', $order->display_store_name);
        $this->assertSame('12000000.00', (string) $order->product_price);
    }

    public function test_menghapus_toko_tidak_menghapus_riwayat_pesanan(): void
    {
        $admin = User::create([
            'username' => 'admin',
            'first_name' => 'Admin',
            'last_name' => 'Vinstore',
            'email' => 'admin@vinstore.test',
            'phone' => '089900001',
            'address' => 'Kantor Pusat',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $seller = $this->createSeller();
        $store = $this->createStoreFor($seller);
        $product = $this->createProductFor($store);
        $order = $this->createOrder($this->createBuyer(), $product, 'Delivered', 'paid');

        $this->actingAs($admin)
            ->delete('/admin/stores/'.$store->public_id)
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('stores', ['id' => $store->id]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'product_id' => null,
            'store_id' => null,
        ]);

        $order->refresh();
        $this->assertSame('Guci Ming', $order->display_item_name);
        $this->assertSame('Toko seller1', $order->display_store_name);
    }

    public function test_menghapus_toko_menurunkan_role_pemilik_agar_tidak_redirect_loop(): void
    {
        $admin = User::create([
            'username' => 'admin',
            'first_name' => 'Admin',
            'last_name' => 'Vinstore',
            'email' => 'admin@vinstore.test',
            'phone' => '089900001',
            'address' => 'Kantor Pusat',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $seller = $this->createSeller();
        $store = $this->createStoreFor($seller);

        $this->actingAs($admin)->delete('/admin/stores/'.$store->public_id);

        $this->assertSame('user', $seller->fresh()->role);
    }

    public function test_seller_lain_tetap_tidak_bisa_menghapus_produk_bukan_miliknya(): void
    {
        $pemilik = $this->createSeller('1');
        $penyusup = $this->createSeller('2');
        $this->createStoreFor($penyusup);

        $product = $this->createProductFor($this->createStoreFor($pemilik));

        $this->actingAs($penyusup)
            ->delete('/seller/products/'.$product->public_id)
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_seller_tidak_bisa_menghapus_pesanan_toko_lain(): void
    {
        $pemilik = $this->createSeller('1');
        $penyusup = $this->createSeller('2');
        $this->createStoreFor($penyusup);

        $product = $this->createProductFor($this->createStoreFor($pemilik));
        $order = $this->createOrder($this->createBuyer(), $product, 'Waiting', 'pending');

        $this->actingAs($penyusup)
            ->delete('/seller/orders/'.$order->public_id)
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_seller_tidak_bisa_menghapus_pesanan_yang_sudah_dibayar(): void
    {
        $seller = $this->createSeller();
        $store = $this->createStoreFor($seller);
        $product = $this->createProductFor($store);
        $order = $this->createOrder($this->createBuyer(), $product, 'Delivered', 'paid');

        $this->actingAs($seller)
            ->delete('/seller/orders/'.$order->public_id)
            ->assertSessionHas('error');

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }
}
