<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\TradeInRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test untuk temuan T-08.
 *
 * Dua masalah yang diperbaiki:
 *  1. Produk yang terikat tukar tambah berjalan masih bisa dibeli pembeli biasa.
 *  2. Kepemilikan berpindah begitu pembayaran lunas, padahal barangnya sendiri
 *     belum tentu pernah dikirim. Sekarang kepemilikan baru berpindah setelah
 *     kedua seller saling mengirim dan saling mengonfirmasi penerimaan.
 */
class TradeInShipmentTest extends TestCase
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
            'is_trade_in_enabled' => true,
        ]);
    }

    /** Ajukan tukar tambah tanpa selisih uang, lalu setujui. */
    private function acceptedTradeIn(): TradeInRequest
    {
        $this->actingAs($this->requesterUser)->post(
            '/seller/tukar-tambah/'.$this->requestedProduct->public_id,
            ['offered_product_id' => $this->offeredProduct->public_id]
        );

        $tradeIn = TradeInRequest::firstOrFail();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/accept');

        return $tradeIn->fresh();
    }

    public function test_tukar_tambah_disetujui_mengunci_kedua_produk(): void
    {
        $tradeIn = $this->acceptedTradeIn();

        $this->assertSame(TradeInRequest::STATUS_SHIPPING, $tradeIn->status);
        $this->assertSame($tradeIn->id, $this->offeredProduct->fresh()->locked_for_trade_in_id);
        $this->assertSame($tradeIn->id, $this->requestedProduct->fresh()->locked_for_trade_in_id);
    }

    public function test_produk_terkunci_tidak_bisa_dimasukkan_keranjang(): void
    {
        $this->acceptedTradeIn();

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
        $tradeIn = $this->acceptedTradeIn();

        // Keduanya mengirim.
        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-A1']);
        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-B1']);

        // Baru satu pihak yang konfirmasi terima.
        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/receive')
            ->assertSessionHas('success');

        $tradeIn->refresh();

        $this->assertSame(TradeInRequest::STATUS_SHIPPING, $tradeIn->status);
        // Kepemilikan masih di tangan masing-masing pemilik lama.
        $this->assertSame($this->requesterStore->id, $this->offeredProduct->fresh()->store_id);
        $this->assertSame($this->responderStore->id, $this->requestedProduct->fresh()->store_id);
    }

    public function test_kepemilikan_berpindah_penuh_setelah_kedua_pihak_konfirmasi(): void
    {
        $tradeIn = $this->acceptedTradeIn();

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-A1']);
        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-B1']);

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/receive');
        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/receive');

        $tradeIn->refresh();

        $this->assertSame(TradeInRequest::STATUS_COMPLETED, $tradeIn->status);
        $this->assertNotNull($tradeIn->completed_at);

        // Kepemilikan bertukar...
        $this->assertSame($this->responderStore->id, $this->offeredProduct->fresh()->store_id);
        $this->assertSame($this->requesterStore->id, $this->requestedProduct->fresh()->store_id);

        // ...dan kuncinya dilepas.
        $this->assertNull($this->offeredProduct->fresh()->locked_for_trade_in_id);
        $this->assertNull($this->requestedProduct->fresh()->locked_for_trade_in_id);
    }

    public function test_tidak_bisa_konfirmasi_terima_sebelum_pihak_lain_mengirim(): void
    {
        $tradeIn = $this->acceptedTradeIn();

        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/receive')
            ->assertSessionHas('error');

        $this->assertNull($tradeIn->fresh()->requester_received_at);
    }

    public function test_seller_luar_tidak_bisa_ikut_campur_pengiriman(): void
    {
        $tradeIn = $this->acceptedTradeIn();

        $orangLuar = $this->makeSeller('c');
        $this->makeStore($orangLuar, 'Toko C');

        $this->actingAs($orangLuar)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'PALSU'])
            ->assertForbidden();
    }
}
