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

    /**
     * Opsi pengiriman & pengemasan untuk satu toko.
     *
     * Sejak checkout keranjang memilih keduanya PER TOKO, payload-nya berupa
     * peta yang dikunci public_id toko.
     */
    private function storeOptions(string $method = 'standard', string $packaging = 'standard'): array
    {
        return [
            $this->store->public_id => [
                'shipping_method' => $method,
                'packaging_type' => $packaging,
            ],
        ];
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
                'shipping_latitude' => -7.7828,
                'shipping_longitude' => 110.3671,
            ]);

        $order = Order::where('user_id', $this->buyer->id)->firstOrFail();

        $this->assertSame('Jl. Tujuan No. 42, Sleman', $order->shipping_address);
        $this->assertSame('express', $order->shipping_method);
        $this->assertSame('Tolong bubble wrap ekstra.', $order->notes);

        // Toko pada tes ini tidak punya koordinat, jadi ongkir memakai tarif
        // rata (fallback) dan jaraknya tidak tercatat.
        $this->assertSame(25000, $order->shipping_cost);
        $this->assertNull($order->shipping_distance_km);

        // 2 x 1000 gram = 2 kg -> 2 x Rp 3.000.
        $this->assertSame(2000, $order->weight_gram);
        $this->assertSame(6000, $order->weight_fee);

        // Peti Rp 3.000 + pembungkus Rp 1.500 x 2 unit.
        $this->assertSame(6000, $order->packaging_fee);

        // Biaya layanan 2,5% dari Rp 2.000.000.
        $this->assertSame(50000, $order->service_fee);

        // Total = barang + ongkir + berat + pengemasan + layanan.
        $this->assertSame(2087000, (int) $order->price);
    }

    public function test_ongkir_dihitung_dari_jarak_toko_ke_titik_antar(): void
    {
        $this->fakeSnap();

        // Tugu Yogyakarta -> Candi Prambanan, kira-kira 15 km.
        $this->store->update(['latitude' => -7.7828, 'longitude' => 110.3671]);

        $this->actingAs($this->buyer)
            ->post('/checkout/product/'.$this->product->public_id, [
                'quantity' => 1,
                'shipping_address' => 'Prambanan, Sleman',
                'shipping_method' => 'standard',
                'shipping_latitude' => -7.7828,
                'shipping_longitude' => 110.3671,
                'shipping_latitude' => -7.752,
                'shipping_longitude' => 110.4915,
            ]);

        $order = Order::where('user_id', $this->buyer->id)->firstOrFail();

        $this->assertNotNull($order->shipping_distance_km);
        $this->assertEqualsWithDelta(13.9, $order->shipping_distance_km, 1.0);

        // Ongkir = (dasar Rp 5.000 + jarak x Rp 2.000) x pengali standard 1,0.
        $expected = (int) round(5000 + $order->shipping_distance_km * 2000);
        $this->assertSame($expected, $order->shipping_cost);

        // Jauh lebih besar daripada tarif rata Rp 10.000 — inilah bedanya
        // dengan perhitungan lama.
        $this->assertGreaterThan(10000, $order->shipping_cost);
    }

    public function test_ongkir_express_lebih_mahal_daripada_standard(): void
    {
        $this->fakeSnap();

        $this->store->update(['latitude' => -7.7828, 'longitude' => 110.3671]);

        foreach (['standard', 'express'] as $method) {
            $this->actingAs($this->buyer)
                ->post('/checkout/product/'.$this->product->public_id, [
                    'quantity' => 1,
                    'shipping_address' => 'Prambanan, Sleman',
                    'shipping_method' => $method,
                    'shipping_latitude' => -7.7828,
                    'shipping_longitude' => 110.3671,
                    'shipping_latitude' => -7.752,
                    'shipping_longitude' => 110.4915,
                ]);
        }

        $orders = Order::where('user_id', $this->buyer->id)->orderBy('id')->get();

        $this->assertGreaterThan($orders[0]->shipping_cost, $orders[1]->shipping_cost);
    }

    /**
     * Titik antar kini WAJIB (temuan V4-01).
     *
     * Sebelumnya ia opsional dan ongkir jatuh ke tarif rata bila kosong — yang
     * berarti perhitungan berbasis jarak bisa dihindari cukup dengan tidak
     * memilih titik.
     */
    public function test_checkout_tanpa_titik_antar_ditolak(): void
    {
        $this->actingAs($this->buyer)
            ->post('/checkout/product/'.$this->product->public_id, [
                'quantity' => 1,
                'shipping_address' => 'Jl. Tanpa Titik',
                'shipping_method' => 'standard',
            ])
            ->assertSessionHasErrors(['shipping_latitude', 'shipping_longitude']);

        $this->assertSame(0, Order::count());
    }

    public function test_koordinat_tanpa_pasangannya_ditolak(): void
    {
        $this->actingAs($this->buyer)
            ->post('/checkout/product/'.$this->product->public_id, [
                'quantity' => 1,
                'shipping_address' => 'Jl. Tanpa Bujur',
                'shipping_method' => 'standard',
                'shipping_latitude' => -7.752,
            ])
            ->assertSessionHasErrors('shipping_longitude');

        $this->assertSame(0, Order::count());
    }

    public function test_biaya_berat_mengikuti_berat_produk(): void
    {
        $this->fakeSnap();

        // 3,5 kg dibulatkan ke atas menjadi 4 kg.
        $this->product->update(['weight' => 3500]);

        $this->actingAs($this->buyer)
            ->post('/checkout/product/'.$this->product->public_id, [
                'quantity' => 1,
                'shipping_address' => 'Jl. Berat No. 1',
                'shipping_method' => 'standard',
                'shipping_latitude' => -7.7828,
                'shipping_longitude' => 110.3671,
            ]);

        $order = Order::where('user_id', $this->buyer->id)->firstOrFail();

        $this->assertSame(3500, $order->weight_gram);
        $this->assertSame(12000, $order->weight_fee);
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
                'store_options' => $this->storeOptions(),
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
                'store_options' => $this->storeOptions(),
                'shipping_latitude' => -7.7828,
                'shipping_longitude' => 110.3671,
            ]);

        $orders = Order::where('user_id', $this->buyer->id)->get();

        $this->assertCount(2, $orders);

        foreach ($orders as $order) {
            $this->assertSame('Jl. Keranjang No. 7', $order->shipping_address);
            $this->assertSame('standard', $order->shipping_method);
        }

        // Kedua barang berasal dari toko yang sama, jadi ongkir dan biaya peti
        // hanya ditagih sekali — keduanya masuk satu paket.
        $this->assertSame(10000, (int) $orders->sum('shipping_cost'));
        $this->assertSame(3000 + 1500 + 1500, (int) $orders->sum('packaging_fee'));

        // Biaya berat tetap per barang karena beratnya memang nyata masing-masing.
        $this->assertSame(3000 + 3000, (int) $orders->sum('weight_fee'));

        // Biaya layanan 2,5% dari nilai tiap barang.
        $this->assertSame(25000 + 12500, (int) $orders->sum('service_fee'));

        $this->assertSame(1559500, (int) $orders->sum('price'));
    }

    public function test_biaya_layanan_masuk_dompet_admin_setelah_lunas(): void
    {
        $this->fakeSnap();

        $this->actingAs($this->buyer)
            ->post('/checkout/product/'.$this->product->public_id, [
                'quantity' => 1,
                'shipping_address' => 'Jl. Layanan No. 3',
                'shipping_method' => 'standard',
                'shipping_latitude' => -7.7828,
                'shipping_longitude' => 110.3671,
            ]);

        $order = Order::where('user_id', $this->buyer->id)->firstOrFail();

        // Belum lunas: belum ada pendapatan.
        $this->assertSame(0, \App\Models\PlatformRevenue::balance());

        $this->postJson(
            '/midtrans/notification',
            $this->webhookPayload($order->payment_reference, 'settlement', '200', '1000000.00')
        )->assertOk();

        $this->assertSame((int) $order->service_fee, \App\Models\PlatformRevenue::balance());

        // Notifikasi ganda tidak boleh menggandakan saldo dompet admin.
        $this->postJson(
            '/midtrans/notification',
            $this->webhookPayload($order->payment_reference, 'settlement', '200', '1000000.00')
        )->assertOk();

        $this->assertSame((int) $order->service_fee, \App\Models\PlatformRevenue::balance());
        $this->assertSame(1, \App\Models\PlatformRevenue::count());
    }

    public function test_refund_membalikkan_biaya_layanan_di_dompet_admin(): void
    {
        $this->fakeSnap();

        $this->actingAs($this->buyer)
            ->post('/checkout/product/'.$this->product->public_id, [
                'quantity' => 1,
                'shipping_address' => 'Jl. Refund No. 5',
                'shipping_method' => 'standard',
                'shipping_latitude' => -7.7828,
                'shipping_longitude' => 110.3671,
            ]);

        $order = Order::where('user_id', $this->buyer->id)->firstOrFail();
        $reference = $order->payment_reference;

        $this->postJson(
            '/midtrans/notification',
            $this->webhookPayload($reference, 'settlement', '200', '1000000.00')
        )->assertOk();

        $this->assertSame((int) $order->service_fee, \App\Models\PlatformRevenue::balance());

        $this->postJson(
            '/midtrans/notification',
            $this->webhookPayload($reference, 'refund', '200', '1000000.00')
        )->assertOk();

        // Uangnya kembali ke pembeli, jadi tidak ada pendapatan yang tersisa —
        // tetapi baris pendapatannya tidak dihapus, hanya dibalikkan.
        $this->assertSame(0, \App\Models\PlatformRevenue::balance());
        $this->assertSame(2, \App\Models\PlatformRevenue::count());
    }

    /**
     * Nama produk berkarakter multibyte dulu memicu
     * "json_encode error: Malformed UTF-8 characters" karena substr() memotong
     * per byte dan membelah karakter di tengah.
     */
    public function test_checkout_dengan_nama_produk_multibyte_tidak_gagal(): void
    {
        $this->fakeSnap();

        // Panjangnya melewati batas 50 karakter dan karakter ke-50 adalah
        // karakter multibyte, sehingga pemotongan per byte pasti membelahnya.
        $this->product->update([
            'name' => str_repeat('a', 49).'é'.str_repeat('b', 20),
        ]);
        $this->store->update([
            'store_name' => str_repeat('Toko Antik ', 4).'“Café”',
        ]);

        $this->actingAs($this->buyer)
            ->post('/checkout/product/'.$this->product->public_id, [
                'quantity' => 1,
                'shipping_address' => 'Jl. UTF-8 No. 8',
                'shipping_method' => 'standard',
                'shipping_latitude' => -7.7828,
                'shipping_longitude' => 110.3671,
            ]);

        $order = Order::where('user_id', $this->buyer->id)->firstOrFail();

        $this->assertSame('snap-token-palsu', $order->snap_token);

        // Setiap teks yang dikirim ke Midtrans harus UTF-8 valid.
        Http::assertSent(function ($request) {
            foreach ($request['item_details'] ?? [] as $item) {
                $this->assertTrue(
                    mb_check_encoding($item['name'], 'UTF-8'),
                    'Nama item Midtrans bukan UTF-8 valid: '.bin2hex($item['name'])
                );
                $this->assertLessThanOrEqual(50, mb_strlen($item['name']));
            }

            return true;
        });
    }

    public function test_checkout_keranjang_banyak_produk_multibyte_tidak_gagal(): void
    {
        $this->fakeSnap();

        $this->product->update(['name' => str_repeat('Guci ', 9).'Ming™ Émas']);

        $kedua = Product::create([
            'store_id' => $this->store->id,
            'name' => str_repeat('Piring ', 7).'Kuningan – Édisi',
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
                'shipping_address' => 'Jl. Keranjang Multibyte No. 2',
                'store_options' => $this->storeOptions(),
                'shipping_latitude' => -7.7828,
                'shipping_longitude' => 110.3671,
            ])
            ->assertSessionMissing('error');

        $this->assertSame(2, Order::count());

        // Jumlah item_details wajib sama persis dengan gross_amount, kalau
        // tidak Midtrans menolak transaksinya.
        Http::assertSent(function ($request) {
            $sum = 0;

            foreach ($request['item_details'] ?? [] as $item) {
                $sum += $item['price'] * $item['quantity'];
                $this->assertTrue(mb_check_encoding($item['name'], 'UTF-8'));
            }

            $this->assertSame((int) $request['transaction_details']['gross_amount'], $sum);

            return true;
        });
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

        // Checkout menahan stok, tidak memotongnya: stoknya tetap 5 dan
        // produknya tetap tampil di etalase selama pembayaran belum tuntas.
        $this->product->increment('reserved_stock');

        $this->assertSame(5, $this->product->fresh()->stock);
        $this->assertSame(4, $this->product->fresh()->available_stock);

        $this->postJson(
            '/midtrans/notification',
            $this->webhookPayload($order->payment_reference, 'expire', '407', '1000000.00')
        )->assertOk();

        $order->refresh();

        $this->assertSame('expired', $order->payment_status);
        $this->assertSame('Cancelled', $order->status);
        $this->assertNotNull($order->stock_restored_at);

        // Reservasi dilepas; stoknya sendiri tidak pernah berkurang.
        $this->assertSame(5, $this->product->fresh()->stock);
        $this->assertSame(0, $this->product->fresh()->reserved_stock);
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
