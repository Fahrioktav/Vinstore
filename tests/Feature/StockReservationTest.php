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
 * Stok DITAHAN saat checkout, bukan dipotong.
 *
 * Perilaku lama: checkout langsung memotong `stock`, sehingga produk yang baru
 * diklik checkout — dan belum tentu dibayar — langsung hilang dari halaman
 * produk (daftarnya menyaring `stock > 0`). Untuk barang antik yang stoknya
 * kerap hanya 1, satu orang yang membuka Snap lalu menutupnya sudah cukup untuk
 * melenyapkan barang itu dari etalase.
 *
 * Perilaku sekarang:
 *   - checkout  -> reserved_stock naik; `stock` tidak berubah, produk TETAP tampil
 *   - lunas     -> stock turun, reservasi dilepas
 *   - batal     -> reservasi dilepas, stock tidak pernah berubah
 *
 * Menahan tetap perlu: tanpa itu tiga orang bisa sama-sama membayar guci
 * terakhir dan dua di antaranya harus direfund.
 */
class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-testkey';

    private User $seller;

    private User $buyer;

    private User $buyerLain;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.midtrans.server_key', self::SERVER_KEY);

        Http::fake([
            '*' => Http::response([
                'token' => 'snap-token-palsu',
                'redirect_url' => 'https://example.test/snap',
            ]),
        ]);

        $this->seller = $this->makeUser('sellerstok', 'seller');
        $this->buyer = $this->makeUser('pembelistok');
        $this->buyerLain = $this->makeUser('pembelilain');

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Stok',
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);

        // Stok 1 — kasus paling khas untuk barang antik, dan paling menyakitkan
        // pada perilaku lama.
        $this->product = Product::create([
            'store_id' => $this->store->id,
            'name' => 'Guci Tunggal',
            'stock' => 1,
            'price' => 1_000_000,
            'category' => 'Keramik',
            'description' => 'Guci antik satu-satunya',
            'approval_status' => Product::STATUS_APPROVED,
        ]);
    }

    private function makeUser(string $username, string $role = 'user'): User
    {
        return User::create([
            'username' => $username,
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
            'email' => $username.'@vinstore.test',
            'phone' => '0811'.random_int(1000000, 9999999),
            'address' => 'Jl. Antik',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function checkout(User $user, int $quantity = 1)
    {
        return $this->actingAs($user)->post('/checkout/product/'.$this->product->public_id, [
            'quantity' => $quantity,
            'shipping_address' => 'Jl. Tujuan No. 1',
            'shipping_method' => 'standard',
            'shipping_latitude' => -7.7828,
            'shipping_longitude' => 110.3671,
        ]);
    }

    private function webhookPayload(string $reference, string $status, string $statusCode): array
    {
        $gross = '1000000.00';

        return [
            'order_id' => $reference,
            'status_code' => $statusCode,
            'gross_amount' => $gross,
            'transaction_status' => $status,
            'fraud_status' => 'accept',
            'transaction_id' => 'trx-'.$reference,
            'payment_type' => 'bank_transfer',
            'signature_key' => hash('sha512', $reference.$statusCode.$gross.self::SERVER_KEY),
        ];
    }

    /**
     * Inti keluhannya: barang hilang dari etalase padahal belum dibayar.
     */
    public function test_produk_tetap_tampil_di_daftar_meski_sedang_dipesan(): void
    {
        $this->checkout($this->buyer);

        $this->product->refresh();

        $this->assertSame(1, $this->product->stock, 'Stok tidak boleh berkurang sebelum dibayar.');
        $this->assertSame(1, $this->product->reserved_stock);
        $this->assertSame(0, $this->product->available_stock);
        $this->assertTrue($this->product->is_fully_reserved);

        // Halaman produk menyaring `stock > 0`; produknya harus tetap lolos.
        $this->actingAs($this->buyerLain)
            ->get('/products')
            ->assertOk()
            ->assertSee($this->product->name);
    }

    public function test_pembeli_lain_belum_bisa_membeli_yang_sedang_ditahan(): void
    {
        $this->checkout($this->buyer);

        $this->checkout($this->buyerLain)->assertSessionHas('error');

        // Hanya satu pesanan yang boleh terbentuk atas satu-satunya guci itu.
        $this->assertSame(1, Order::count());
        $this->assertSame(1, $this->product->fresh()->reserved_stock);
    }

    public function test_pesan_menjelaskan_bahwa_barang_sedang_dipesan_bukan_habis(): void
    {
        $this->checkout($this->buyer);

        $response = $this->checkout($this->buyerLain);

        $error = session('error');

        $this->assertStringContainsString('sedang dalam proses pembayaran', $error);
        $this->assertStringNotContainsString('habis', $error);
    }

    public function test_stok_baru_berkurang_setelah_pembayaran_lunas(): void
    {
        $this->checkout($this->buyer);

        $order = Order::firstOrFail();

        $this->postJson(
            '/midtrans/notification',
            $this->webhookPayload($order->payment_reference, 'settlement', '200')
        )->assertOk();

        $this->product->refresh();

        $this->assertSame(0, $this->product->stock, 'Stok harus berkurang setelah lunas.');
        $this->assertSame(0, $this->product->reserved_stock, 'Reservasi harus ikut dilepas.');
        $this->assertNotNull($order->fresh()->stock_committed_at);
    }

    public function test_pemotongan_stok_hanya_terjadi_sekali_walau_webhook_berulang(): void
    {
        $this->product->update(['stock' => 3]);

        $this->checkout($this->buyer);

        $order = Order::firstOrFail();
        $payload = $this->webhookPayload($order->payment_reference, 'settlement', '200');

        $this->postJson('/midtrans/notification', $payload)->assertOk();
        $this->postJson('/midtrans/notification', $payload)->assertOk();
        $this->postJson('/midtrans/notification', $payload)->assertOk();

        // Tiga notifikasi, satu pemotongan.
        $this->assertSame(2, $this->product->fresh()->stock);
    }

    public function test_pembatalan_melepas_reservasi_tanpa_menyentuh_stok(): void
    {
        $this->checkout($this->buyer);

        $order = Order::firstOrFail();

        $this->actingAs($this->buyer)->delete('/order/'.$order->public_id);

        $this->product->refresh();

        $this->assertSame(1, $this->product->stock);
        $this->assertSame(0, $this->product->reserved_stock);
        $this->assertSame(1, $this->product->available_stock);

        // Setelah dilepas, pembeli lain bisa membelinya.
        $this->checkout($this->buyerLain)->assertSessionMissing('error');
        $this->assertSame(2, Order::count());
    }

    public function test_reservasi_yang_sudah_dilepas_tidak_dilepas_dua_kali(): void
    {
        $this->product->update(['stock' => 5]);
        $this->checkout($this->buyer, 2);

        $order = Order::firstOrFail();

        $order->restoreReservedStock();
        $order->restoreReservedStock();
        $order->restoreReservedStock();

        $this->assertSame(0, $this->product->fresh()->reserved_stock);
        $this->assertSame(5, $this->product->fresh()->stock);
    }

    public function test_pesanan_yang_sudah_lunas_tidak_bisa_dilepas_reservasinya(): void
    {
        $this->product->update(['stock' => 3]);
        $this->checkout($this->buyer);

        $order = Order::firstOrFail();
        $order->commitReservedStock();

        $this->assertSame(2, $this->product->fresh()->stock);

        // Pembatalan yang datang belakangan tidak boleh mengembalikan stok
        // barang yang sudah benar-benar terjual.
        $order->restoreReservedStock();

        $this->assertSame(2, $this->product->fresh()->stock);
        $this->assertSame(0, $this->product->fresh()->reserved_stock);
    }

    public function test_command_melepas_reservasi_pesanan_yang_terlantar(): void
    {
        $this->checkout($this->buyer);

        $order = Order::firstOrFail();

        // Belum lewat tenggat: reservasinya harus tetap dipegang.
        $this->artisan('orders:release-abandoned')->assertExitCode(0);
        $this->assertSame(1, $this->product->fresh()->reserved_stock);

        // Digeser melewati tenggat pembayaran.
        $order->forceFill([
            'created_at' => now()->subHours(Order::PAYMENT_WINDOW_HOURS + 1),
        ])->save();

        $this->artisan('orders:release-abandoned')->assertExitCode(0);

        $order->refresh();

        $this->assertSame('expired', $order->payment_status);
        $this->assertSame('Cancelled', $order->status);
        $this->assertSame(0, $this->product->fresh()->reserved_stock);
        $this->assertSame(1, $this->product->fresh()->stock);
    }

    public function test_command_tidak_menyentuh_pesanan_yang_sudah_lunas(): void
    {
        $this->product->update(['stock' => 3]);
        $this->checkout($this->buyer);

        $order = Order::firstOrFail();

        $this->postJson(
            '/midtrans/notification',
            $this->webhookPayload($order->payment_reference, 'settlement', '200')
        )->assertOk();

        $order->forceFill([
            'created_at' => now()->subHours(Order::PAYMENT_WINDOW_HOURS + 1),
        ])->save();

        $this->artisan('orders:release-abandoned')->assertExitCode(0);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(2, $this->product->fresh()->stock);
    }

    public function test_checkout_keranjang_juga_menahan_bukan_memotong(): void
    {
        Cart::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($this->buyer)->post('/checkout/cart', [
            'shipping_address' => 'Jl. Keranjang No. 1',
            'shipping_latitude' => -7.7828,
            'shipping_longitude' => 110.3671,
            'store_options' => [
                $this->store->public_id => [
                    'shipping_method' => 'standard',
                    'packaging_type' => 'standard',
                ],
            ],
        ]);

        $this->product->refresh();

        $this->assertSame(1, $this->product->stock);
        $this->assertSame(1, $this->product->reserved_stock);
    }
}
