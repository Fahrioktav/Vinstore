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
 * Checkout keranjang: metode pengiriman dan jenis pengemasan dipilih PER TOKO,
 * tetapi pembayarannya tetap SATU transaksi Snap.
 *
 * Satu keranjang bisa memuat guci keramik dari satu toko dan koin dari toko
 * lain. Memaksa keduanya memakai kurir dan pengemasan yang sama membuat pembeli
 * koin membayar peti kayu yang tidak ia butuhkan — atau sebaliknya, guci
 * dikirim tanpa perlindungan yang memadai.
 */
class CartPerStoreOptionsTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Store $storeA;

    private Store $storeB;

    private Product $guci;

    private Product $koin;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.midtrans.server_key', 'SB-Mid-server-testkey');

        Http::fake([
            '*' => Http::response([
                'token' => 'snap-token-palsu',
                'redirect_url' => 'https://example.test/snap',
            ]),
        ]);

        $this->buyer = $this->makeUser('pembelidua');

        $this->storeA = $this->makeStore('sellera', 'Toko Keramik');
        $this->storeB = $this->makeStore('sellerb', 'Toko Koin');

        $this->guci = Product::create([
            'store_id' => $this->storeA->id,
            'name' => 'Guci Keramik',
            'stock' => 5,
            'price' => 1_000_000,
            'weight' => 3000,
            'category' => 'Keramik',
            'description' => 'Guci rapuh',
            'approval_status' => Product::STATUS_APPROVED,
        ]);

        $this->koin = Product::create([
            'store_id' => $this->storeB->id,
            'name' => 'Koin Kuno',
            'stock' => 5,
            'price' => 200_000,
            'weight' => 50,
            'category' => 'Keramik',
            'description' => 'Koin kecil',
            'approval_status' => Product::STATUS_APPROVED,
        ]);

        foreach ([$this->guci, $this->koin] as $product) {
            Cart::create([
                'user_id' => $this->buyer->id,
                'product_id' => $product->id,
                'quantity' => 1,
            ]);
        }
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

    private function makeStore(string $username, string $name): Store
    {
        return Store::create([
            'user_id' => $this->makeUser($username, 'seller')->id,
            'store_name' => $name,
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);
    }

    private function checkout(array $storeOptions)
    {
        return $this->actingAs($this->buyer)->post('/checkout/cart', [
            'shipping_address' => 'Jl. Tujuan No. 7',
            'store_options' => $storeOptions,
            'shipping_latitude' => -7.7828,
            'shipping_longitude' => 110.3671,
        ]);
    }

    public function test_tiap_toko_memakai_pengiriman_dan_pengemasan_sendiri(): void
    {
        $this->checkout([
            // Guci: rapuh dan mahal -> express + peti kayu.
            $this->storeA->public_id => [
                'shipping_method' => 'express',
                'packaging_type' => 'kayu',
            ],
            // Koin: kecil dan tahan banting -> standard + kardus.
            $this->storeB->public_id => [
                'shipping_method' => 'standard',
                'packaging_type' => 'standard',
            ],
        ]);

        $pesananGuci = Order::where('product_id', $this->guci->id)->firstOrFail();
        $pesananKoin = Order::where('product_id', $this->koin->id)->firstOrFail();

        $this->assertSame('express', $pesananGuci->shipping_method);
        $this->assertSame('kayu', $pesananGuci->packaging_type);

        $this->assertSame('standard', $pesananKoin->shipping_method);
        $this->assertSame('standard', $pesananKoin->packaging_type);

        // Peti kayu (25.000 + 5.000) jauh lebih mahal daripada kardus
        // (3.000 + 1.500) — buktinya pilihannya benar-benar dipakai.
        $this->assertSame(30000, $pesananGuci->packaging_fee);
        $this->assertSame(4500, $pesananKoin->packaging_fee);

        // Express memakai pengali 1,6 atas tarif rata; standard tidak.
        $this->assertGreaterThan($pesananKoin->shipping_cost, $pesananGuci->shipping_cost);
    }

    public function test_pembayarannya_tetap_satu_transaksi(): void
    {
        $this->checkout([
            $this->storeA->public_id => ['shipping_method' => 'express', 'packaging_type' => 'kayu'],
            $this->storeB->public_id => ['shipping_method' => 'standard', 'packaging_type' => 'standard'],
        ]);

        $orders = Order::where('user_id', $this->buyer->id)->get();

        $this->assertCount(2, $orders);

        // Dua pesanan, SATU payment_reference: pembeli hanya membayar sekali.
        $this->assertCount(1, $orders->pluck('payment_reference')->unique());
        $this->assertCount(1, $orders->pluck('snap_token')->unique());

        // Dan gross_amount-nya harus sama dengan jumlah kedua pesanan.
        Http::assertSent(function ($request) use ($orders) {
            if (! isset($request['transaction_details'])) {
                return false;
            }

            $this->assertSame(
                (int) $orders->sum('price'),
                (int) $request['transaction_details']['gross_amount']
            );

            return true;
        });
    }

    public function test_rincian_item_midtrans_cocok_dengan_gross_amount(): void
    {
        $this->checkout([
            $this->storeA->public_id => ['shipping_method' => 'express', 'packaging_type' => 'kayu'],
            $this->storeB->public_id => ['shipping_method' => 'standard', 'packaging_type' => 'standard'],
        ]);

        // Midtrans menolak transaksi bila jumlah item_details tidak sama persis
        // dengan gross_amount — inilah yang paling mudah melenceng saat tiap
        // toko punya tarifnya sendiri.
        Http::assertSent(function ($request) {
            if (! isset($request['item_details'])) {
                return false;
            }

            $sum = 0;
            foreach ($request['item_details'] as $item) {
                $sum += $item['price'] * $item['quantity'];
            }

            $this->assertSame((int) $request['transaction_details']['gross_amount'], $sum);

            return true;
        });
    }

    public function test_opsi_toko_wajib_diisi(): void
    {
        $this->actingAs($this->buyer)
            ->post('/checkout/cart', ['shipping_address' => 'Jl. Tujuan No. 7'])
            ->assertSessionHasErrors('store_options');

        $this->assertSame(0, Order::count());
    }

    public function test_jenis_pengemasan_tidak_dikenal_ditolak(): void
    {
        $this->checkout([
            $this->storeA->public_id => [
                'shipping_method' => 'standard',
                'packaging_type' => 'peti-emas',
            ],
            $this->storeB->public_id => [
                'shipping_method' => 'standard',
                'packaging_type' => 'standard',
            ],
        ])->assertSessionHasErrors('store_options.'.$this->storeA->public_id.'.packaging_type');

        $this->assertSame(0, Order::count());
    }

    public function test_toko_yang_opsinya_tidak_dikirim_memakai_tarif_standar(): void
    {
        // Toko B sengaja tidak disertakan. Server tidak boleh gagal, dan tidak
        // boleh menagih pembeli dengan tarif termahal secara diam-diam.
        $this->checkout([
            $this->storeA->public_id => ['shipping_method' => 'express', 'packaging_type' => 'kayu'],
        ]);

        $pesananKoin = Order::where('product_id', $this->koin->id)->firstOrFail();

        $this->assertSame('standard', $pesananKoin->shipping_method);
        $this->assertSame('standard', $pesananKoin->packaging_type);
    }

    public function test_ongkir_ditagih_sekali_per_toko_bukan_per_barang(): void
    {
        // Barang kedua dari toko yang sama masuk paket yang sama.
        Cart::create([
            'user_id' => $this->buyer->id,
            'product_id' => Product::create([
                'store_id' => $this->storeA->id,
                'name' => 'Piring Keramik',
                'stock' => 5,
                'price' => 500_000,
                'weight' => 800,
                'category' => 'Keramik',
                'description' => 'Piring antik',
                'approval_status' => Product::STATUS_APPROVED,
            ])->id,
            'quantity' => 1,
        ]);

        $this->checkout([
            $this->storeA->public_id => ['shipping_method' => 'standard', 'packaging_type' => 'standard'],
            $this->storeB->public_id => ['shipping_method' => 'standard', 'packaging_type' => 'standard'],
        ]);

        $orders = Order::where('user_id', $this->buyer->id)->get();

        $this->assertCount(3, $orders);

        // Dua toko -> dua kali ongkir, bukan tiga.
        $this->assertSame(
            2,
            $orders->where('shipping_cost', '>', 0)->count(),
            'Ongkir seharusnya ditagih sekali per toko.'
        );
    }
}
