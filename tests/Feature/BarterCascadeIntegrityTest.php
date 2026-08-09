<?php

namespace Tests\Feature;

use App\Models\BarterRequest;
use App\Models\PayoutRequest;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TEMUAN V3-01 & V3-04 — test ini MERAH sampai perbaikannya dikerjakan.
 *
 * Foreign key barter dan tabel keuangannya memakai ON DELETE CASCADE:
 *
 *   barter_requests.offered_product_id   -> products         CASCADE
 *   barter_requests.requested_product_id -> products         CASCADE
 *   barter_requests.requester_store_id   -> stores           CASCADE
 *   barter_requests.responder_store_id   -> stores           CASCADE
 *   payout_requests.barter_request_id    -> barter_requests  CASCADE
 *   payout_requests.store_id             -> stores           CASCADE
 *   refund_requests.barter_request_id    -> barter_requests  CASCADE
 *
 * Artinya menghapus satu produk menghapus barternya, dan menghapus barternya
 * menghapus catatan pencairan serta pengembalian dananya. Perbaikan K-04 dulu
 * menyelamatkan riwayat `orders` dengan nullOnDelete + snapshot; tabel barter
 * dan tabel keuangan yang lahir setelahnya tidak pernah ikut diubah.
 *
 * Yang diharapkan setelah perbaikan: barter dan catatan pencairan tetap ada
 * sebagai jejak audit, dengan kolom produk/toko menjadi NULL.
 */
class BarterCascadeIntegrityTest extends TestCase
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

        // Produk pengaju lebih murah -> pengaju yang membayar selisih.
        $this->offeredProduct = $this->makeProduct($this->requesterStore, 'Mesin Tik', 1_500_000);
        $this->requestedProduct = $this->makeProduct($this->responderStore, 'Guci Antik', 5_000_000);
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

    private function makeProduct(Store $store, string $name, int $price): Product
    {
        return Product::create([
            'store_id' => $store->id,
            'name' => $name,
            'stock' => 1,
            'price' => $price,
            'category' => 'Antik',
            'description' => 'Barang antik',
            'approval_status' => Product::STATUS_APPROVED,
            'is_barterable' => true,
        ]);
    }

    /** Barter yang selisihnya sudah lunas dan barangnya sedang saling dikirim. */
    private function paidBarter(): BarterRequest
    {
        $this->actingAs($this->requesterUser)->post(
            '/seller/barter/'.$this->requestedProduct->public_id,
            ['offered_product_id' => $this->offeredProduct->public_id]
        );

        $barter = BarterRequest::firstOrFail();

        $this->actingAs($this->responderUser)
            ->post('/seller/barter/'.$barter->public_id.'/accept');

        $barter->refresh()->forceFill([
            'payment_status' => BarterRequest::PAYMENT_PAID,
            'paid_at' => now(),
            'status' => BarterRequest::STATUS_SHIPPING,
            'shipping_started_at' => now(),
        ])->save();

        return $barter->fresh();
    }

    /**
     * Inti V3-01. Penerima menghapus produk yang sudah ia janjikan, setelah
     * pengaju membayar dan mengirimkan barangnya.
     */
    public function test_menghapus_produk_tidak_menghapus_barter_yang_sedang_berjalan(): void
    {
        $barter = $this->paidBarter();

        // Pengaju sudah mengirimkan barangnya.
        $this->actingAs($this->requesterUser)
            ->post('/seller/barter/'.$barter->public_id.'/ship', ['tracking_number' => 'JNE-A']);

        $this->requestedProduct->delete();

        $this->assertTrue(
            BarterRequest::where('public_id', $barter->public_id)->exists(),
            'Barter terhapus bersama produknya — pengaju kehilangan barang dan uang tanpa jejak apa pun.'
        );
    }

    /**
     * Inti V3-04. Catatan pencairan adalah bukti uang benar-benar ditransfer.
     */
    public function test_menghapus_produk_tidak_menghapus_catatan_pencairan_barter(): void
    {
        $barter = $this->paidBarter();

        $payout = PayoutRequest::create([
            'barter_request_id' => $barter->id,
            'store_id' => $this->responderStore->id,
            'amount' => $barter->additional_cash,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Seller B',
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $this->offeredProduct->delete();

        $this->assertTrue(
            PayoutRequest::where('public_id', $payout->public_id)->exists(),
            'Catatan pencairan terhapus mengikuti barternya — jejak audit uang keluar hilang.'
        );
    }

    /**
     * Inti V3-04 jalur admin: menghapus toko juga memusnahkan riwayat
     * pencairan miliknya, termasuk yang uangnya sudah ditransfer.
     */
    public function test_menghapus_toko_tidak_menghapus_riwayat_pencairan(): void
    {
        $barter = $this->paidBarter();

        $payout = PayoutRequest::create([
            'barter_request_id' => $barter->id,
            'store_id' => $this->responderStore->id,
            'amount' => $barter->additional_cash,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Seller B',
            'status' => PayoutRequest::STATUS_APPROVED,
            'transferred_at' => now(),
        ]);

        $this->responderStore->delete();

        $this->assertTrue(
            PayoutRequest::where('public_id', $payout->public_id)->exists(),
            'Riwayat pencairan yang sudah ditransfer ikut terhapus bersama tokonya.'
        );
    }
}
