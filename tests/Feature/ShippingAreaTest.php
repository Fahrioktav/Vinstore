<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Temuan V4-01: titik peta tidak pernah dicocokkan dengan alamat teksnya.
 *
 * Ongkir dihitung dari pin yang digeser sendiri pembeli, sementara seller
 * mengirim ke alamat teks yang diketik terpisah — pembeli bisa menulis alamat
 * Jayapura, meletakkan pin di depan toko, dan membayar ongkir jarak minimum.
 *
 * Perbaikannya: pin jadi WAJIB dan menjadi sumber tunggal lokasi. Koordinatnya
 * dibaca balik menjadi nama wilayah yang dilihat seller, sehingga tidak ada
 * lagi dua sumber lokasi yang bisa saling bertentangan.
 */
class ShippingAreaTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.midtrans.server_key', 'SB-Mid-server-testkey');
        config()->set('marketplace.geocoding.enabled', true);
        config()->set('marketplace.geocoding.url', 'https://nominatim.test/reverse');

        Cache::flush();

        $seller = $this->makeUser('sellerarea', 'seller');
        $this->buyer = $this->makeUser('pembeliarea');

        $this->store = Store::create([
            'user_id' => $seller->id,
            'store_name' => 'Toko Area',
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
            'latitude' => -7.7828,
            'longitude' => 110.3671,
        ]);

        $this->product = Product::create([
            'store_id' => $this->store->id,
            'name' => 'Guci Area',
            'stock' => 5,
            'price' => 1_000_000,
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
            'address' => 'Jl. Antik',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    /**
     * Balasan Nominatim + balasan Midtrans dalam satu fake.
     */
    private function fakeServices(?array $geocodeResponse = null): void
    {
        Http::fake([
            'nominatim.test/*' => $geocodeResponse === null
                ? Http::response([], 500)
                : Http::response($geocodeResponse),
            '*' => Http::response([
                'token' => 'snap-token-palsu',
                'redirect_url' => 'https://example.test/snap',
            ]),
        ]);
    }

    private function checkout(array $override = [])
    {
        return $this->actingAs($this->buyer)->post(
            '/checkout/product/'.$this->product->public_id,
            array_merge([
                'quantity' => 1,
                'shipping_address' => 'Jl. Kaliurang No. 10, dekat masjid',
                'shipping_method' => 'standard',
                'shipping_latitude' => -7.752,
                'shipping_longitude' => 110.4915,
            ], $override)
        );
    }

    public function test_titik_antar_wajib_dipilih(): void
    {
        $this->fakeServices();

        $this->checkout(['shipping_latitude' => null, 'shipping_longitude' => null])
            ->assertSessionHasErrors(['shipping_latitude', 'shipping_longitude']);

        $this->assertSame(0, Order::count());
    }

    public function test_nama_wilayah_disimpan_dari_koordinat(): void
    {
        $this->fakeServices([
            'address' => [
                'village' => 'Caturtunggal',
                'city_district' => 'Depok',
                'county' => 'Sleman',
                'state' => 'Daerah Istimewa Yogyakarta',
            ],
        ]);

        $this->checkout();

        $order = Order::firstOrFail();

        // Inilah tujuan sebenarnya yang dilihat seller — bukan lagi semata-mata
        // teks bebas dari pembeli.
        $this->assertSame(
            'Caturtunggal, Depok, Sleman, Daerah Istimewa Yogyakarta',
            $order->shipping_area
        );

        // Detail alamatnya tetap tersimpan sebagai pelengkap.
        $this->assertSame('Jl. Kaliurang No. 10, dekat masjid', $order->shipping_address);
    }

    public function test_nama_wilayah_tidak_mengulang_bagian_yang_sama(): void
    {
        $this->fakeServices([
            'address' => [
                'village' => 'Sleman',
                'county' => 'Sleman',
                'state' => 'DI Yogyakarta',
            ],
        ]);

        $this->checkout();

        // "Sleman, Sleman, DI Yogyakarta" membingungkan; yang berulang dibuang.
        $this->assertSame('Sleman, DI Yogyakarta', Order::firstOrFail()->shipping_area);
    }

    /**
     * Yang paling penting: layanan luar tidak boleh menggagalkan checkout.
     */
    public function test_checkout_tetap_berhasil_saat_geocoding_gagal(): void
    {
        $this->fakeServices(); // Nominatim membalas 500.

        $this->checkout();

        $order = Order::firstOrFail();

        $this->assertNull($order->shipping_area);

        // Ongkirnya tetap benar: ia dihitung dari koordinat, bukan dari nama
        // wilayah.
        $this->assertNotNull($order->shipping_distance_km);
        $this->assertGreaterThan(0, $order->shipping_cost);
    }

    public function test_geocoding_yang_dimatikan_tidak_memanggil_layanan_luar(): void
    {
        config()->set('marketplace.geocoding.enabled', false);

        $this->fakeServices(['address' => ['village' => 'Tidak Dipakai']]);

        $this->checkout();

        $this->assertNull(Order::firstOrFail()->shipping_area);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'nominatim.test'));
    }

    public function test_hasil_geocoding_dipakai_ulang_dari_cache(): void
    {
        $this->fakeServices([
            'address' => ['village' => 'Caturtunggal', 'county' => 'Sleman'],
        ]);

        $service = app(GeocodingService::class);

        $pertama = $service->areaName(-7.752, 110.4915);
        // Titik yang beda beberapa meter harus jatuh ke kunci cache yang sama.
        $kedua = $service->areaName(-7.75201, 110.49151);

        $this->assertSame('Caturtunggal, Sleman', $pertama);
        $this->assertSame($pertama, $kedua);

        // Nominatim hanya membatasi 1 permintaan per detik; titik yang sama
        // tidak boleh ditanyakan berulang.
        Http::assertSentCount(1);
    }

    public function test_alamat_kosong_dari_nominatim_menghasilkan_null(): void
    {
        $this->fakeServices(['address' => []]);

        $this->assertNull(app(GeocodingService::class)->areaName(-7.752, 110.4915));
    }

    public function test_koordinat_null_tidak_memanggil_layanan(): void
    {
        $this->fakeServices(['address' => ['village' => 'Tidak Dipakai']]);

        $this->assertNull(app(GeocodingService::class)->areaName(null, 110.4915));
        $this->assertNull(app(GeocodingService::class)->areaName(-7.752, null));

        Http::assertNothingSent();
    }
}
