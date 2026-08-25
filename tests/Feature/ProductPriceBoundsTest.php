<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test untuk temuan V10-06 (dan S-03 dari audit pertama).
 *
 * Validasi harga dan stok hanya `numeric` / `integer`, tanpa batas bawah.
 * Angka negatif lolos sampai ke keranjang, ikut dijumlahkan ke `gross_amount`,
 * dan Midtrans menolak seluruh transaksinya — pembeli hanya melihat checkout
 * yang gagal tanpa sebab yang bisa ia mengerti.
 */
class ProductPriceBoundsTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::create([
            'username' => 'seller',
            'first_name' => 'Seller',
            'last_name' => 'Uji',
            'email' => 'seller@vinstore.test',
            'phone' => '081100011122',
            'address' => 'Jl. Antik',
            'password' => 'password',
            'role' => 'seller',
        ]);

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Antik',
            'category' => 'Antik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);

        Category::create(['name' => 'Antik']);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Gramofon',
            'stock' => 1,
            'price' => 500_000,
            'category' => 'Antik',
            'description' => 'Barang antik peninggalan',
        ], $override);
    }

    public function test_harga_negatif_ditolak(): void
    {
        $this->actingAs($this->seller)
            ->post('/seller/products', $this->payload(['price' => -50_000]))
            ->assertSessionHasErrors('price');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_harga_nol_ditolak(): void
    {
        $this->actingAs($this->seller)
            ->post('/seller/products', $this->payload(['price' => 0]))
            ->assertSessionHasErrors('price');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_stok_negatif_ditolak(): void
    {
        $this->actingAs($this->seller)
            ->post('/seller/products', $this->payload(['stock' => -3]))
            ->assertSessionHasErrors('stock');

        $this->assertDatabaseCount('products', 0);
    }

    /**
     * Test kontrol: penjagaannya tidak boleh menolak produk yang wajar.
     * Tanpa ini, "harga negatif ditolak" bisa lulus karena alasan yang salah —
     * misalnya seluruh POST produk gagal validasi karena hal lain.
     */
    public function test_produk_wajar_tetap_tersimpan(): void
    {
        $this->actingAs($this->seller)
            ->post('/seller/products', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('products', 1);
        $this->assertSame(500_000.0, (float) Product::firstOrFail()->price);
    }

    public function test_stok_nol_masih_boleh_karena_artinya_barang_habis(): void
    {
        $this->actingAs($this->seller)
            ->post('/seller/products', $this->payload(['stock' => 0]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('products', 1);
    }

    public function test_perubahan_stok_negatif_lewat_form_cepat_ditolak(): void
    {
        $produk = Product::create([
            'store_id' => $this->store->id,
            'name' => 'Keris',
            'stock' => 2,
            'price' => 750_000,
            'category' => 'Antik',
            'description' => 'Barang antik',
            'approval_status' => Product::STATUS_APPROVED,
        ]);

        $this->actingAs($this->seller)
            ->patch('/seller/products/'.$produk->public_id, ['stock' => -1])
            ->assertSessionHas('error');

        $this->assertSame(2, $produk->fresh()->stock);
    }
}
