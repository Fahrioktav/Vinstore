<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Order;
use App\Models\PlatformRevenue;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pesanan yang sudah gugur tidak boleh menerima uang lagi (temuan V6-01, V6-03).
 *
 * Alurnya begini. `orders:release-abandoned` membatalkan pesanan yang tidak
 * dibayar sampai tenggat: statusnya Cancelled, pembayarannya expired, dan
 * reservasi stoknya dilepas ke pembeli lain. Tetapi pembeli masih memegang
 * halaman Snap yang masa berlakunya belum tentu sama. Ketika ia akhirnya
 * membayar, notifikasinya datang ke pesanan yang sudah mati.
 *
 * Dulu notifikasi itu diterima: pembayarannya menjadi `paid` tetapi statusnya
 * tetap Cancelled, stoknya tidak pernah dipotong, dan biaya layanannya telanjur
 * diakui sebagai pendapatan. Seller melihat pesanan batal dan tidak mengirim
 * apa pun, sementara unit yang sama sudah tersedia untuk orang lain.
 */
class ExpiredOrderPaymentTest extends TestCase
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

        Http::fake([
            '*' => Http::response([
                'token' => 'snap-token-palsu',
                'redirect_url' => 'https://example.test/snap',
            ]),
        ]);

        $this->seller = $this->makeUser('sellerexp', 'seller');
        $this->buyer = $this->makeUser('pembeliexp');

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Kedaluwarsa',
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
            'latitude' => -7.7828,
            'longitude' => 110.3671,
        ]);

        $this->product = Product::create([
            'store_id' => $this->store->id,
            'name' => 'Guci Terlambat',
            'stock' => 5,
            'price' => 1_000_000,
            'weight' => 1000,
            'category' => 'Keramik',
            'description' => 'Guci antik',
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
            'address' => 'Jl. Antik No. 1',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function webhook(Order $order, string $status = 'settlement', string $statusCode = '200'): array
    {
        $reference = $order->payment_reference;
        $gross = number_format((float) $order->price, 2, '.', '');

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

    private function pesananKedaluwarsa(): Order
    {
        $this->actingAs($this->buyer)->post('/checkout/product/'.$this->product->public_id, [
            'quantity' => 1,
            'shipping_address' => 'Jl. Tujuan No. 1',
            'shipping_method' => 'standard',
            'shipping_latitude' => -7.7828,
            'shipping_longitude' => 110.3671,
        ]);

        $order = Order::where('user_id', $this->buyer->id)->firstOrFail();

        $order->forceFill([
            'created_at' => now()->subHours(Order::PAYMENT_WINDOW_HOURS + 1),
        ])->save();

        Artisan::call('orders:release-abandoned');

        return $order->fresh();
    }

    public function test_penjadwal_membatalkan_pesanan_yang_tidak_dibayar(): void
    {
        $order = $this->pesananKedaluwarsa();

        $this->assertSame('Cancelled', $order->status);
        $this->assertSame('expired', $order->payment_status);
        $this->assertNotNull($order->stock_restored_at);
        $this->assertSame(0, (int) $this->product->fresh()->reserved_stock);
    }

    public function test_pembayaran_terlambat_tidak_menghidupkan_pesanan_yang_sudah_gugur(): void
    {
        $order = $this->pesananKedaluwarsa();

        // Notifikasi settlement yang sah, datang belakangan.
        $this->postJson('/midtrans/notification', $this->webhook($order))->assertOk();

        $order->refresh();

        $this->assertSame('Cancelled', $order->status);
        $this->assertSame('expired', $order->payment_status, 'Pesanan gugur tidak boleh menjadi lunas.');
        $this->assertNull($order->stock_committed_at);
    }

    public function test_pembayaran_terlambat_tidak_mencatat_biaya_layanan(): void
    {
        $order = $this->pesananKedaluwarsa();

        $this->postJson('/midtrans/notification', $this->webhook($order))->assertOk();

        $this->assertSame(
            0,
            PlatformRevenue::where('order_id', $order->id)
                ->where('source', PlatformRevenue::SOURCE_SERVICE_FEE)
                ->count(),
            'Biaya layanan atas pesanan batal tidak boleh diakui sebagai pendapatan.'
        );
    }

    public function test_pembayaran_terlambat_tidak_memotong_stok(): void
    {
        $order = $this->pesananKedaluwarsa();

        $this->postJson('/midtrans/notification', $this->webhook($order))->assertOk();

        // Unitnya sudah dilepas ke etalase; memotongnya sekarang berarti stok
        // hilang tanpa ada pesanan yang akan dikirim.
        $this->assertSame(5, (int) $this->product->fresh()->stock);
    }

    /**
     * Pengembalian dana tetap boleh dicatat: uangnya memang benar-benar masuk
     * ke Midtrans, jadi pembalikannya harus punya tempat untuk direkam.
     */
    public function test_pengembalian_dana_masih_boleh_dicatat_pada_pesanan_gugur(): void
    {
        $order = $this->pesananKedaluwarsa();

        $this->assertTrue($order->canApplyPaymentStatus('refunded'));
        $this->assertFalse($order->canApplyPaymentStatus('paid'));
        $this->assertFalse($order->canApplyPaymentStatus('pending'));
    }

    // ------------------------------------------------------------------
    // Pesanan lelang (V6-03)
    // ------------------------------------------------------------------

    private function pesananLelangKedaluwarsa(): array
    {
        $auction = Auction::create([
            'store_id' => $this->store->id,
            'name' => 'Guci Lelang',
            'description' => 'Guci antik',
            'weight' => 3000,
            'starting_price' => 500_000,
            'min_increment' => 50_000,
            'current_price' => 500_000,
            'approval_status' => Auction::STATUS_APPROVED,
            'status' => 'active',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subMinute(),
        ]);

        AuctionBid::create([
            'auction_id' => $auction->id,
            'user_id' => $this->buyer->id,
            'amount' => 600_000,
        ]);

        Artisan::call('auctions:finish');

        $order = Order::where('auction_id', $auction->id)->firstOrFail();
        $order->forceFill([
            'created_at' => now()->subHours(Order::AUCTION_PAYMENT_WINDOW_HOURS + 1),
        ])->save();

        Artisan::call('orders:release-abandoned');

        return [$auction->fresh(), $order->fresh()];
    }

    public function test_halaman_pembayaran_lelang_ditolak_setelah_pesanannya_gugur(): void
    {
        [$auction, $order] = $this->pesananLelangKedaluwarsa();

        $this->assertSame('Cancelled', $order->status);

        $this->actingAs($this->buyer)
            ->get('/auctions/'.$auction->public_id.'/checkout')
            ->assertRedirect(route('order'));
    }

    public function test_pesanan_lelang_yang_gugur_tidak_dibuatkan_transaksi_snap_baru(): void
    {
        [$auction, $order] = $this->pesananLelangKedaluwarsa();

        $this->actingAs($this->buyer)
            ->post('/auctions/'.$auction->public_id.'/pay', [
                'shipping_address' => 'Jl. Terlambat No. 9',
                'shipping_method' => 'standard',
                'shipping_latitude' => -7.7828,
                'shipping_longitude' => 110.3671,
            ])
            ->assertRedirect(route('order'));

        $this->assertNull($order->fresh()->snap_token);
    }
}
