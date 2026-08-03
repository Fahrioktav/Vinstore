<?php

namespace Tests\Feature;

use App\Models\BarterRequest;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test untuk temuan T-08.
 *
 * Dua masalah yang diperbaiki:
 *  1. Produk yang terikat barter berjalan masih bisa dibeli pembeli biasa.
 *  2. Kepemilikan berpindah begitu pembayaran lunas, padahal barangnya sendiri
 *     belum tentu pernah dikirim. Sekarang kepemilikan baru berpindah setelah
 *     kedua seller saling mengirim dan saling mengonfirmasi penerimaan.
 */
class BarterShipmentTest extends TestCase
{
    use RefreshDatabase;

    private User $requesterUser;

    private User $responderUser;

    private Store $requesterStore;

    private Store $responderStore;

    private Product $offeredProduct;

    private Product $requestedProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requesterUser = $this->makeSeller('a');
        $this->responderUser = $this->makeSeller('b');

        $this->requesterStore = $this->makeStore($this->requesterUser, 'Toko A');
        $this->responderStore = $this->makeStore($this->responderUser, 'Toko B');

        $this->offeredProduct = $this->makeProduct($this->requesterStore, 'Keris Jawa');
        $this->requestedProduct = $this->makeProduct($this->responderStore, 'Guci Ming');
    }

    private function makeSeller(string $suffix): User
    {
        return User::create([
            'username' => 'seller'.$suffix,
            'first_name' => 'Seller',
            'last_name' => strtoupper($suffix),
            'email' => "seller{$suffix}@vinstore.test",
            'phone' => '0811000'.$suffix,
            'address' => 'Jl. Antik',
            'password' => 'password',
            'role' => 'seller',
        ]);
    }

    private function makeStore(User $user, string $name): Store
    {
        return Store::create([
            'user_id' => $user->id,
            'store_name' => $name,
            'category' => 'Antik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);
    }

    private function makeProduct(Store $store, string $name): Product
    {
        return Product::create([
            'store_id' => $store->id,
            'name' => $name,
            'stock' => 1,
            'price' => 5000000,
            'category' => 'Antik',
            'description' => 'Barang antik',
            'approval_status' => Product::STATUS_APPROVED,
            'is_barterable' => true,
        ]);
    }

    /** Ajukan barter tanpa selisih uang, lalu setujui. */
    private function acceptedBarter(): BarterRequest
    {
        $this->actingAs($this->requesterUser)->post(
            '/seller/barter/'.$this->requestedProduct->public_id,
            ['offered_product_id' => $this->offeredProduct->public_id]
        );

        $barter = BarterRequest::firstOrFail();

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/accept');

        return $barter->fresh();
    }

    public function test_barter_disetujui_mengunci_kedua_produk(): void
    {
        $barter = $this->acceptedBarter();

        $this->assertSame(BarterRequest::STATUS_SHIPPING, $barter->status);
        $this->assertSame($barter->id, $this->offeredProduct->fresh()->locked_for_barter_id);
        $this->assertSame($barter->id, $this->requestedProduct->fresh()->locked_for_barter_id);
    }

    public function test_produk_terkunci_tidak_bisa_dimasukkan_keranjang(): void
    {
        $this->acceptedBarter();

        $pembeli = User::create([
            'username' => 'pembeli',
            'first_name' => 'Pem',
            'last_name' => 'Beli',
            'email' => 'pembeli@vinstore.test',
            'phone' => '08210001',
            'address' => 'Jl. Pembeli',
            'password' => 'password',
            'role' => 'user',
        ]);

        $this->actingAs($pembeli)
            ->post('/cart/add/'.$this->requestedProduct->public_id, ['quantity' => 1])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('carts', 0);
    }

    public function test_kepemilikan_belum_berpindah_sebelum_kedua_pihak_konfirmasi(): void
    {
        $barter = $this->acceptedBarter();

        // Keduanya mengirim.
        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-A1']);
        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-B1']);

        // Baru satu pihak yang konfirmasi terima.
        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/receive')
            ->assertSessionHas('success');

        $barter->refresh();

        $this->assertSame(BarterRequest::STATUS_SHIPPING, $barter->status);
        // Kepemilikan masih di tangan masing-masing pemilik lama.
        $this->assertSame($this->requesterStore->id, $this->offeredProduct->fresh()->store_id);
        $this->assertSame($this->responderStore->id, $this->requestedProduct->fresh()->store_id);
    }

    public function test_kepemilikan_berpindah_penuh_setelah_kedua_pihak_konfirmasi(): void
    {
        $barter = $this->acceptedBarter();

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-A1']);
        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-B1']);

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/receive');
        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/receive');

        $barter->refresh();

        $this->assertSame(BarterRequest::STATUS_COMPLETED, $barter->status);
        $this->assertNotNull($barter->completed_at);

        // Kepemilikan bertukar...
        $this->assertSame($this->responderStore->id, $this->offeredProduct->fresh()->store_id);
        $this->assertSame($this->requesterStore->id, $this->requestedProduct->fresh()->store_id);

        // ...dan kuncinya dilepas.
        $this->assertNull($this->offeredProduct->fresh()->locked_for_barter_id);
        $this->assertNull($this->requestedProduct->fresh()->locked_for_barter_id);
    }

    public function test_tidak_bisa_konfirmasi_terima_sebelum_pihak_lain_mengirim(): void
    {
        $barter = $this->acceptedBarter();

        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/receive')
            ->assertSessionHas('error');

        $this->assertNull($barter->fresh()->requester_received_at);
    }

    public function test_seller_luar_tidak_bisa_ikut_campur_pengiriman(): void
    {
        $barter = $this->acceptedBarter();

        $orangLuar = $this->makeSeller('c');
        $this->makeStore($orangLuar, 'Toko C');

        $this->actingAs($orangLuar)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'PALSU'])
            ->assertForbidden();
    }
}
