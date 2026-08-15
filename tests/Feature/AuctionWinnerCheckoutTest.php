<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionDeposit;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pembayaran pemenang lelang.
 *
 * Dulu pesanan lelang hanya menagih harga menang: tidak ada ongkir, tidak ada
 * biaya berat, tidak ada biaya layanan — bukan karena diputuskan begitu,
 * melainkan karena lelang tidak punya kolom berat dan dimensi sehingga tidak ada
 * yang bisa dihitung. Sekarang lelang membawa kolom yang sama dengan produk, dan
 * pemenang memilih titik antarnya di halaman pembayaran lelang.
 */
class AuctionWinnerCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $pemenang;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.midtrans.server_key', 'SB-Mid-server-testkey');
        config()->set('marketplace.geocoding.enabled', false);

        Http::fake([
            '*' => Http::response([
                'token' => 'snap-token-palsu',
                'redirect_url' => 'https://example.test/snap',
            ]),
        ]);

        $this->seller = $this->makeUser('sellerlelang', 'seller');
        $this->pemenang = $this->makeUser('pemenanglelang');

        // Tugu Yogyakarta — di Pulau Jawa.
        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Lelang',
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
            'latitude' => -7.7828,
            'longitude' => 110.3671,
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

    /** Lelang yang sudah ditutup dengan pemenang. */
    private function lelangSelesai(array $override = []): Auction
    {
        $auction = Auction::create(array_merge([
            'store_id' => $this->store->id,
            'name' => 'Guci Ming',
            'description' => 'Guci antik',
            'weight' => 4000,
            'length' => 30,
            'width' => 20,
            'height' => 20,
            'starting_price' => 500_000,
            'min_increment' => 50_000,
            'current_price' => 500_000,
            'approval_status' => Auction::STATUS_APPROVED,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subMinute(),
        ], $override));

        AuctionBid::create([
            'auction_id' => $auction->id,
            'user_id' => $this->pemenang->id,
            'amount' => 800_000,
        ]);

        Artisan::call('auctions:finish');

        return $auction->fresh();
    }

    private function bayar(Auction $auction, array $override = [])
    {
        return $this->actingAs($this->pemenang)->post('/auctions/'.$auction->public_id.'/pay', array_merge([
            'shipping_address' => 'Jl. Prambanan No. 3',
            'shipping_method' => 'standard',
            'packaging_type' => 'standard',
            // Candi Prambanan — masih di Pulau Jawa.
            'shipping_latitude' => -7.752,
            'shipping_longitude' => 110.4915,
        ], $override));
    }

    public function test_pesanan_lelang_dibuat_tanpa_biaya_sebelum_pemenang_memilih_tujuan(): void
    {
        $auction = $this->lelangSelesai();

        $order = Order::where('auction_id', $auction->id)->firstOrFail();

        $this->assertSame(800_000, (int) round((float) $order->price));
        $this->assertSame(0, $order->shipping_cost);
        $this->assertSame(0, $order->service_fee);
    }

    public function test_halaman_pembayaran_hanya_untuk_pemenang(): void
    {
        $auction = $this->lelangSelesai();

        $orangLain = $this->makeUser('bukanpemenang');

        $this->actingAs($orangLain)
            ->get('/auctions/'.$auction->public_id.'/checkout')
            ->assertForbidden();

        $this->actingAs($this->pemenang)
            ->get('/auctions/'.$auction->public_id.'/checkout')
            ->assertOk();
    }

    public function test_membayar_menambahkan_seluruh_komponen_biaya(): void
    {
        $auction = $this->lelangSelesai();

        $this->bayar($auction)->assertRedirect(route('auctions.show', $auction->public_id));

        $order = Order::where('auction_id', $auction->id)->firstOrFail();

        // Toko dan tujuan sama-sama di Jawa.
        $this->assertSame(10_000, $order->shipping_cost);

        // Berat asli 4 kg mengalahkan volumetrik 30x20x20/6000 = 2 kg, dan 4 kg
        // masuk tingkat pertama.
        $this->assertSame(4000, $order->weight_gram);
        $this->assertSame(3_000, $order->weight_fee);

        // Peti Rp 3.000 + pembungkus Rp 1.500.
        $this->assertSame(4_500, $order->packaging_fee);

        // Biaya layanan 2,5% dari Rp 800.000.
        $this->assertSame(20_000, $order->service_fee);

        $this->assertSame(837_500, (int) round((float) $order->price));
        $this->assertSame('Jl. Prambanan No. 3', $order->shipping_address);
        $this->assertSame('snap-token-palsu', $order->snap_token);
    }

    /**
     * Midtrans menolak order_id yang sudah pernah dipakai, jadi percobaan
     * pembayaran kedua harus memakai referensi baru — bukan menabrak yang lama.
     */
    public function test_mengubah_tujuan_lalu_membayar_lagi_memakai_referensi_baru(): void
    {
        $auction = $this->lelangSelesai();

        $this->bayar($auction);

        $order = Order::where('auction_id', $auction->id)->firstOrFail();
        $referensiPertama = $order->payment_reference;

        $this->assertSame($order->public_id, $referensiPertama);

        // Pemenang berubah pikiran: kirim ke Denpasar, bukan Prambanan.
        $this->bayar($auction, [
            'shipping_latitude' => -8.6705,
            'shipping_longitude' => 115.2126,
        ]);

        $order->refresh();

        $this->assertSame($order->public_id.'-2', $order->payment_reference);
        $this->assertSame(20_000, $order->shipping_cost);

        // Percobaan ketiga menaikkan urutannya, bukan mengulang dari awal.
        $this->bayar($auction);

        $this->assertSame($order->public_id.'-3', $order->fresh()->payment_reference);
    }

    public function test_tujuan_luar_jawa_dikenai_tarif_lebih_mahal(): void
    {
        $auction = $this->lelangSelesai();

        // Denpasar — menyeberang dari Jawa.
        $this->bayar($auction, [
            'shipping_latitude' => -8.6705,
            'shipping_longitude' => 115.2126,
        ]);

        $this->assertSame(
            20_000,
            Order::where('auction_id', $auction->id)->firstOrFail()->shipping_cost
        );
    }

    public function test_titik_antar_wajib_dipilih(): void
    {
        $auction = $this->lelangSelesai();

        $this->actingAs($this->pemenang)
            ->post('/auctions/'.$auction->public_id.'/pay', [
                'shipping_address' => 'Jl. Tanpa Titik',
                'shipping_method' => 'standard',
            ])
            ->assertSessionHasErrors(['shipping_latitude', 'shipping_longitude']);

        $this->assertSame(
            0,
            Order::where('auction_id', $auction->id)->firstOrFail()->shipping_cost
        );
    }

    /**
     * Deposit pemenang dipotong dari yang ditagihkan, tetapi `price` tetap berisi
     * tagihan penuh — dari sanalah hak seller dan biaya layanan dihitung.
     */
    public function test_deposit_hanya_memotong_yang_ditagihkan_bukan_nilai_transaksinya(): void
    {
        $auction = $this->lelangSelesai([
            'starting_price' => 2_000_000,
            'current_price' => 2_000_000,
        ]);

        AuctionDeposit::where('auction_id', $auction->id)->delete();

        // Ulangi dengan jaminan yang sudah lunas.
        $auction2 = Auction::create([
            'store_id' => $this->store->id,
            'name' => 'Guci Berdeposit',
            'description' => 'Guci antik',
            'weight' => 4000,
            'starting_price' => 2_000_000,
            'min_increment' => 100_000,
            'current_price' => 2_000_000,
            'approval_status' => Auction::STATUS_APPROVED,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subMinute(),
        ]);

        AuctionDeposit::create([
            'auction_id' => $auction2->id,
            'user_id' => $this->pemenang->id,
            'amount' => $auction2->depositAmount(),
            'status' => AuctionDeposit::STATUS_PAID,
            'paid_at' => now(),
        ]);

        AuctionBid::create([
            'auction_id' => $auction2->id,
            'user_id' => $this->pemenang->id,
            'amount' => 2_500_000,
        ]);

        Artisan::call('auctions:finish');

        $this->bayar($auction2->fresh());

        $order = Order::where('auction_id', $auction2->id)->firstOrFail();

        $tagihanPenuh = 2_500_000 + 10_000 + 3_000 + 4_500 + 62_500;

        $this->assertSame($tagihanPenuh, (int) round((float) $order->price));
        $this->assertSame(200_000, $order->deposit_credit);
        $this->assertSame($tagihanPenuh - 200_000, $order->amountDue());

        // Hak seller dihitung dari nilai barang, bukan dari sisa yang dibayar.
        $this->assertSame(2_500_000 + 4_500, $order->sellerPayoutAmount());
    }
}
