<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Dua hal yang diuji di sini:
 *
 * 1. Detail pengiriman ikut tersimpan pada pesanan. Sebelumnya alamat yang
 *    diisi pembeli di checkout hanya divalidasi lalu dibuang, sehingga seller
 *    tidak tahu ke mana barang harus dikirim.
 *
 * 2. Notifikasi Midtrans tidak boleh menarik mundur pesanan yang sudah lunas.
 *    Midtrans tidak menjamin urutan pengiriman notifikasi, dan
 *    MidtransService::mapPaymentStatus() memetakan status tak dikenal menjadi
 *    'pending' — yang dulu mencabut akses invoice sekaligus membatalkan
 *    pencairan dana atas pesanan yang uangnya sudah diterima.
 */
class OrderShippingAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-testkey';

    private User $seller;

    private User $buyer;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.midtrans.server_key', self::SERVER_KEY);

        $this->seller = User::create([
            'username' => 'sellerkirim',
            'first_name' => 'Seller',
            'last_name' => 'Kirim',
            'email' => 'sellerkirim@vinstore.test',
            'phone' => '08110002',
            'address' => 'Jl. Antik No. 1',
            'password' => 'password',
            'role' => 'seller',
        ]);

        $this->buyer = User::create([
            'username' => 'pembelikirim',
            'first_name' => 'Pembeli',
            'last_name' => 'Kirim',
            'email' => 'pembelikirim@vinstore.test',
            'phone' => '08210002',
            'address' => 'Jl. Profil No. 9',
            'password' => 'password',
            'role' => 'user',
        ]);

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Kirim',
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

    private function fakeSnap(): void
    {
        Http::fake([
            '*' => Http::response([
                'token' => 'snap-token-palsu',
                'redirect_url' => 'https://example.test/snap',
            ]),
        ]);
    }

    public function test_checkout_produk_menyimpan_alamat_pengiriman(): void
    {
        $this->fakeSnap();

        $this->actingAs($this->buyer)
            ->post('/checkout/product/'.$this->product->public_id, [
                'quantity' => 2,
                'shipping_address' => 'Jl. Tujuan No. 42, Sleman',
                'shipping_method' => 'express',
                'notes' => 'Tolong bubble wrap ekstra.',
            ]);

        $order = Order::where('user_id', $this->buyer->id)->firstOrFail();

        $this->assertSame('Jl. Tujuan No. 42, Sleman', $order->shipping_address);
        $this->assertSame('express', $order->shipping_method);
        $this->assertSame(25000, $order->shipping_cost);
        $this->assertSame('Tolong bubble wrap ekstra.', $order->notes);

        // Total = (harga satuan x qty) + ongkir.
        $this->assertSame(2025000, (int) $order->price);
    }

    public function test_checkout_keranjang_wajib_mengisi_alamat(): void
    {
        Cart::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($this->buyer)
            ->post('/checkout/cart', [
                'shipping_method' => 'standard',
            ])
            ->assertSessionHasErrors('shipping_address');

        $this->assertSame(0, Order::count());
        // Stok tidak boleh berkurang saat checkout gagal validasi.
        $this->assertSame(5, $this->product->fresh()->stock);
    }

    public function test_checkout_keranjang_menyimpan_alamat_dan_ongkir_sekali_per_toko(): void
    {
        $this->fakeSnap();

        $kedua = Product::create([
            'store_id' => $this->store->id,
            'name' => 'Piring Antik',
            'stock' => 3,
            'price' => 500000,
            'category' => 'Keramik',
            'description' => 'Piring antik',
            'approval_status' => Product::STATUS_APPROVED,
        ]);

        foreach ([$this->product, $kedua] as $product) {
            Cart::create([
                'user_id' => $this->buyer->id,
                'product_id' => $product->id,
                'quantity' => 1,
            ]);
        }

        $this->actingAs($this->buyer)
            ->post('/checkout/cart', [
                'shipping_address' => 'Jl. Keranjang No. 7',
                'shipping_method' => 'standard',
            ]);

        $orders = Order::where('user_id', $this->buyer->id)->get();

        $this->assertCount(2, $orders);

        foreach ($orders as $order) {
            $this->assertSame('Jl. Keranjang No. 7', $order->shipping_address);
            $this->assertSame('standard', $order->shipping_method);
        }

        // Kedua barang berasal dari toko yang sama, jadi ongkir hanya sekali.
        $this->assertSame(10000, (int) $orders->sum('shipping_cost'));
        $this->assertSame(1510000, (int) $orders->sum('price'));
    }

    public function test_pesanan_lelang_mewarisi_alamat_dari_profil_pemenang(): void
    {
        $auction = \App\Models\Auction::create([
            'store_id' => $this->store->id,
            'name' => 'Lelang Guci',
            'description' => 'Guci langka',
            'starting_price' => 100000,
            'min_increment' => 10000,
            'current_price' => 100000,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subMinute(),
            'approval_status' => 'approved',
            'status' => 'active',
        ]);

        \App\Models\AuctionBid::create([
            'auction_id' => $auction->id,
            'user_id' => $this->buyer->id,
            'amount' => 150000,
        ]);

        $this->artisan('auctions:finish')->assertExitCode(0);

        $order = Order::where('auction_id', $auction->id)->firstOrFail();

        $this->assertSame($this->buyer->address, $order->shipping_address);
    }

    private function webhookPayload(string $reference, string $transactionStatus, string $statusCode, string $grossAmount): array
    {
        return [
            'order_id' => $reference,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => $transactionStatus,
            'fraud_status' => 'accept',
            'transaction_id' => 'trx-123',
            'payment_type' => 'bank_transfer',
            'signature_key' => hash('sha512', $reference.$statusCode.$grossAmount.self::SERVER_KEY),
        ];
    }

    private function pendingOrder(): Order
    {
        $order = Order::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_price' => $this->product->price,
            'store_id' => $this->store->id,
            'store_name' => $this->store->store_name,
            'quantity' => 1,
            'price' => 1000000,
            'status' => 'Waiting',
            'payment_status' => 'pending',
            'payment_method' => 'midtrans',
        ]);

        $order->update(['payment_reference' => $order->public_id]);

        return $order->fresh();
    }

    public function test_webhook_settlement_menandai_pesanan_lunas(): void
    {
        $order = $this->pendingOrder();

        $this->postJson(
            '/midtrans/notification',
            $this->webhookPayload($order->payment_reference, 'settlement', '200', '1000000.00')
        )->assertOk();

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
    }

    public function test_notifikasi_susulan_tidak_menurunkan_pesanan_lunas(): void
    {
        $order = $this->pendingOrder();
        $reference = $order->payment_reference;

        $this->postJson(
            '/midtrans/notification',
            $this->webhookPayload($reference, 'settlement', '200', '1000000.00')
        )->assertOk();

        // Notifikasi 'pending' yang datang terlambat.
        $this->postJson(
            '/midtrans/notification',
            $this->webhookPayload($reference, 'pending', '201', '1000000.00')
        )->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);

        // Status transaksi yang tidak dikenal dipetakan ke 'pending' juga.
        $this->postJson(
            '/midtrans/notification',
            $this->webhookPayload($reference, 'status_aneh', '999', '1000000.00')
        )->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_notifikasi_expire_setelah_lunas_tidak_membatalkan_pesanan(): void
    {
        $order = $this->pendingOrder();
        $reference = $order->payment_reference;

        $this->postJson(
            '/midtrans/notification',
            $this->webhookPayload($reference, 'settlement', '200', '1000000.00')
        )->assertOk();

        $this->postJson(
            '/midtrans/notification',
            $this->webhookPayload($reference, 'expire', '407', '1000000.00')
        )->assertOk();

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertNotSame('Cancelled', $order->status);
        // Stok tidak boleh dikembalikan untuk pesanan yang sudah dibayar.
        $this->assertNull($order->stock_restored_at);
    }

    public function test_webhook_expire_pada_pesanan_belum_lunas_membatalkan_dan_mengembalikan_stok(): void
    {
        $order = $this->pendingOrder();
        $this->product->decrement('stock');

        $this->postJson(
            '/midtrans/notification',
            $this->webhookPayload($order->payment_reference, 'expire', '407', '1000000.00')
        )->assertOk();

        $order->refresh();

        $this->assertSame('expired', $order->payment_status);
        $this->assertSame('Cancelled', $order->status);
        $this->assertNotNull($order->stock_restored_at);
        $this->assertSame(5, $this->product->fresh()->stock);
    }

    public function test_webhook_dengan_signature_salah_ditolak(): void
    {
        $order = $this->pendingOrder();

        $payload = $this->webhookPayload($order->payment_reference, 'settlement', '200', '1000000.00');
        $payload['signature_key'] = 'palsu';

        $this->postJson('/midtrans/notification', $payload)->assertForbidden();

        $this->assertSame('pending', $order->fresh()->payment_status);
    }
}
