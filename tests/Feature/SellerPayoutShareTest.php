<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\PlatformRevenue;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Mengunci pembagian tagihan pembeli.
 *
 * Aturannya satu kalimat: uang mengikuti pekerjaannya.
 *
 *   nilai barang + pengemasan + ongkir + biaya berat -> seller
 *   biaya layanan                                    -> marketplace
 *
 * Sellerlah yang mengemas dan mengantar paketnya ke gerai kurir — aplikasi ini
 * tidak punya integrasi kurir maupun penjemputan, dan nomor resi diisi seller
 * dengan tangan. Sebelum aturan ini ditegakkan, ongkir dikurangkan dari hak
 * seller tetapi juga tidak pernah dicatat sebagai pendapatan platform: uangnya
 * mengendap tanpa asal-usul, sementara seller menalanginya di gerai kurir.
 */
class SellerPayoutShareTest extends TestCase
{
    use RefreshDatabase;

    private User $pembeli;

    private User $seller;

    private User $admin;

    private Store $store;

    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pembeli = $this->makeUser('pembeli', 'user');
        $this->seller = $this->makeUser('seller', 'seller');
        $this->admin = $this->makeUser('admin', 'admin');

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Antik',
            'category' => 'Antik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);

        $this->produk = Product::create([
            'store_id' => $this->store->id,
            'name' => 'Gramofon',
            'stock' => 3,
            'price' => 1_000_000,
            'category' => 'Antik',
            'description' => 'Barang antik',
            'approval_status' => Product::STATUS_APPROVED,
        ]);
    }

    private function makeUser(string $nama, string $role): User
    {
        return User::create([
            'username' => $nama,
            'first_name' => ucfirst($nama),
            'last_name' => 'Uji',
            'email' => $nama.'@vinstore.test',
            'phone' => '0811'.random_int(1000000, 9999999),
            'address' => 'Jl. Uji',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    /**
     * Satu pesanan dengan seluruh komponen biaya terisi, supaya tiap rupiah
     * bisa ditelusuri ke pemiliknya.
     */
    private function pesananLengkap(string $status = 'Completed'): Order
    {
        $barang = 1_000_000;
        $ongkir = 20_000;
        $pengemasan = 4_500;
        $berat = 3_000;
        $layanan = 25_000;

        $order = Order::create([
            'user_id' => $this->pembeli->id,
            'product_id' => $this->produk->id,
            'product_name' => $this->produk->name,
            'product_price' => $barang,
            'store_id' => $this->store->id,
            'store_name' => $this->store->store_name,
            'quantity' => 1,
            'price' => $barang + $ongkir + $pengemasan + $berat + $layanan,
            'shipping_cost' => $ongkir,
            'packaging_fee' => $pengemasan,
            'weight_fee' => $berat,
            'service_fee' => $layanan,
            'status' => $status,
            'shipping_address' => 'Jl. Pembeli No. 2',
            'payment_status' => 'paid',
            'payment_method' => 'midtrans',
            'paid_at' => now(),
            'delivered_at' => now()->subDay(),
            'completed_at' => $status === 'Completed' ? now() : null,
        ]);

        return $order->fresh();
    }

    public function test_ongkir_pengemasan_dan_biaya_berat_menjadi_hak_seller(): void
    {
        $order = $this->pesananLengkap();

        $this->assertSame(
            1_000_000 + 20_000 + 4_500 + 3_000,
            $order->sellerPayoutAmount()
        );
    }

    public function test_biaya_layanan_satu_satunya_yang_tidak_ikut_cair(): void
    {
        $order = $this->pesananLengkap();

        $this->assertSame(
            (int) round((float) $order->price) - 25_000,
            $order->sellerPayoutAmount()
        );
    }

    /**
     * Yang paling penting: tidak ada rupiah yang menguap. Tagihan pembeli harus
     * habis terbagi menjadi hak seller dan pendapatan marketplace.
     */
    public function test_tagihan_pembeli_habis_terbagi_tanpa_sisa(): void
    {
        $order = $this->pesananLengkap();

        PlatformRevenue::recordServiceFee($order);

        $hakSeller = $order->sellerPayoutAmount();
        $pendapatanPlatform = PlatformRevenue::balance();

        $this->assertSame(
            (int) round((float) $order->price),
            $hakSeller + $pendapatanPlatform
        );
    }

    public function test_pengajuan_pencairan_memakai_nominal_yang_sama(): void
    {
        $order = $this->pesananLengkap();

        $this->actingAs($this->seller)
            ->post('/seller/orders/'.$order->public_id.'/payout', [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder' => 'Seller Uji',
            ])
            ->assertSessionHas('success');

        $payout = PayoutRequest::firstOrFail();

        $this->assertSame($order->sellerPayoutAmount(), (int) $payout->amount);
        $this->assertSame(1_027_500, (int) $payout->amount);
    }

    /**
     * Test kontrol: pesanan lelang tidak punya komponen biaya sama sekali, jadi
     * nilainya harus tetap sama persis dengan harga menang.
     */
    public function test_pesanan_tanpa_komponen_biaya_tidak_berubah(): void
    {
        $order = Order::create([
            'user_id' => $this->pembeli->id,
            'product_id' => $this->produk->id,
            'product_name' => $this->produk->name,
            'product_price' => 1_000_000,
            'store_id' => $this->store->id,
            'store_name' => $this->store->store_name,
            'quantity' => 1,
            'price' => 1_000_000,
            'status' => 'Completed',
            'shipping_address' => 'Jl. Pembeli No. 2',
            'payment_status' => 'paid',
            'payment_method' => 'midtrans',
            'paid_at' => now(),
        ]);

        $this->assertSame(1_000_000, $order->fresh()->sellerPayoutAmount());
    }
}
