<?php

namespace Tests\Feature;

use App\Models\PayoutRequest;
use App\Models\Product;
use App\Models\Store;
use App\Models\TradeInRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TEMUAN V3-01 & V3-04 — test ini MERAH sampai perbaikannya dikerjakan.
 *
 * Foreign key tukar tambah dan tabel keuangannya memakai ON DELETE CASCADE:
 *
 *   trade_in_requests.offered_product_id   -> products         CASCADE
 *   trade_in_requests.requested_product_id -> products         CASCADE
 *   trade_in_requests.requester_store_id   -> stores           CASCADE
 *   trade_in_requests.responder_store_id   -> stores           CASCADE
 *   payout_requests.trade_in_request_id    -> trade_in_requests  CASCADE
 *   payout_requests.store_id             -> stores           CASCADE
 *   refund_requests.trade_in_request_id    -> trade_in_requests  CASCADE
 *
 * Artinya menghapus satu produk menghapus tukar tambahnya, dan menghapus tukar tambahnya
 * menghapus catatan pencairan serta pengembalian dananya. Perbaikan K-04 dulu
 * menyelamatkan riwayat `orders` dengan nullOnDelete + snapshot; tabel tukar tambah
 * dan tabel keuangan yang lahir setelahnya tidak pernah ikut diubah.
 *
 * Yang diharapkan setelah perbaikan: tukar tambah dan catatan pencairan tetap ada
 * sebagai jejak audit, dengan kolom produk/toko menjadi NULL.
 */
class TradeInCascadeIntegrityTest extends TestCase
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
            'is_trade_in_enabled' => true,
        ]);
    }

    /** Tukar tambah yang selisihnya sudah lunas dan barangnya sedang saling dikirim. */
    private function paidTradeIn(): TradeInRequest
    {
        $this->actingAs($this->requesterUser)->post(
            '/seller/tukar-tambah/'.$this->requestedProduct->public_id,
            ['offered_product_id' => $this->offeredProduct->public_id]
        );

        $tradeIn = TradeInRequest::firstOrFail();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/accept');

        $tradeIn->refresh()->forceFill([
            'payment_status' => TradeInRequest::PAYMENT_PAID,
            'paid_at' => now(),
            'status' => TradeInRequest::STATUS_SHIPPING,
            'shipping_started_at' => now(),
        ])->save();

        return $tradeIn->fresh();
    }

    /**
     * Inti V3-01. Penerima menghapus produk yang sudah ia janjikan, setelah
     * pengaju membayar dan mengirimkan barangnya.
     */
    public function test_menghapus_produk_tidak_menghapus_tukar_tambah_yang_sedang_berjalan(): void
    {
        $tradeIn = $this->paidTradeIn();

        // Pengaju sudah mengirimkan barangnya.
        $this->actingAs($this->requesterUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/ship', ['tracking_number' => 'JNE-A']);

        $this->requestedProduct->delete();

        $this->assertTrue(
            TradeInRequest::where('public_id', $tradeIn->public_id)->exists(),
            'Tukar tambah terhapus bersama produknya — pengaju kehilangan barang dan uang tanpa jejak apa pun.'
        );
    }

    /**
     * Inti V3-04. Catatan pencairan adalah bukti uang benar-benar ditransfer.
     */
    public function test_menghapus_produk_tidak_menghapus_catatan_pencairan_tukar_tambah(): void
    {
        $tradeIn = $this->paidTradeIn();

        $payout = PayoutRequest::create([
            'trade_in_request_id' => $tradeIn->id,
            'store_id' => $this->responderStore->id,
            'amount' => $tradeIn->additional_cash,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Seller B',
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $this->offeredProduct->delete();

        $this->assertTrue(
            PayoutRequest::where('public_id', $payout->public_id)->exists(),
            'Catatan pencairan terhapus mengikuti tukar tambahnya — jejak audit uang keluar hilang.'
        );
    }

    /**
     * Inti V3-04 jalur admin: menghapus toko juga memusnahkan riwayat
     * pencairan miliknya, termasuk yang uangnya sudah ditransfer.
     */
    public function test_menghapus_toko_tidak_menghapus_riwayat_pencairan(): void
    {
        $tradeIn = $this->paidTradeIn();

        $payout = PayoutRequest::create([
            'trade_in_request_id' => $tradeIn->id,
            'store_id' => $this->responderStore->id,
            'amount' => $tradeIn->additional_cash,
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
