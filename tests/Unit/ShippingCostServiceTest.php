<?php

namespace Tests\Unit;

use App\Models\Auction;
use App\Models\Product;
use App\Models\Store;
use App\Services\ShippingCostService;
use Tests\TestCase;

/**
 * Aturan perhitungan biaya checkout.
 *
 * Angka-angka di sini sengaja tidak diambil dari config supaya tes benar-benar
 * mengunci perilakunya: kalau tarif default berubah tanpa disadari, tes ini
 * yang memberi tahu.
 */
class ShippingCostServiceTest extends TestCase
{
    private ShippingCostService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ShippingCostService;

        config()->set('marketplace.shipping.base_fee', ['jawa' => 10000, 'luar_jawa' => 20000]);
        config()->set('marketplace.shipping.java_bounds', [
            'min_lat' => -8.85,
            'max_lat' => -5.72,
            'min_lng' => 105.00,
            'max_lng' => 114.65,
        ]);
        config()->set('marketplace.shipping.default_region', 'luar_jawa');
        config()->set('marketplace.shipping.method_multiplier', ['standard' => 1.0, 'express' => 1.6]);
        config()->set('marketplace.weight.tiers', [
            ['max_gram' => 10000, 'fee' => 3000],
            ['max_gram' => null, 'fee' => 6000],
        ]);
        config()->set('marketplace.weight.min_billable_gram', 3000);
        config()->set('marketplace.weight.default_gram', 1000);
        config()->set('marketplace.weight.volumetric_divisor', 6000);
        config()->set('marketplace.packaging.default', 'standard');
        config()->set('marketplace.packaging.options', [
            'standard' => ['label' => 'Bubble Wrap + Kardus', 'base_fee' => 3000, 'per_item_fee' => 1500],
            'kayu' => ['label' => 'Peti Kayu', 'base_fee' => 25000, 'per_item_fee' => 5000],
        ]);
        config()->set('marketplace.service_fee', ['percent' => 2.5, 'min' => 1000, 'max' => 100000]);
    }

    /** Titik-titik uji: Yogyakarta (Jawa), Denpasar (Bali), Medan (Sumatera). */
    private const JOGJA = [-7.7828, 110.3671];

    private const DENPASAR = [-8.6705, 115.2126];

    private function toko(array $koordinat = self::JOGJA): Store
    {
        return new Store([
            'store_name' => 'Toko',
            'latitude' => $koordinat[0],
            'longitude' => $koordinat[1],
        ]);
    }

    public function test_titik_di_pulau_jawa_dikenali(): void
    {
        $this->assertTrue($this->service->isInJava(...self::JOGJA));
        // Jakarta.
        $this->assertTrue($this->service->isInJava(-6.2088, 106.8456));
        // Surabaya.
        $this->assertTrue($this->service->isInJava(-7.2575, 112.7521));

        // Denpasar, Medan, dan Makassar jelas di luar kotak.
        $this->assertFalse($this->service->isInJava(...self::DENPASAR));
        $this->assertFalse($this->service->isInJava(3.5952, 98.6722));
        $this->assertFalse($this->service->isInJava(-5.1477, 119.4327));

        $this->assertFalse($this->service->isInJava(null, null));
    }

    public function test_wilayah_jawa_hanya_bila_kedua_titik_di_jawa(): void
    {
        // Jogja -> Semarang: sama-sama di Jawa.
        $this->assertSame('jawa', $this->service->shippingRegion($this->toko(), -6.9667, 110.4167));

        // Jogja -> Denpasar: menyeberang.
        $this->assertSame('luar_jawa', $this->service->shippingRegion($this->toko(), ...self::DENPASAR));

        // Toko di Bali -> pembeli di Jawa: tetap menyeberang.
        $this->assertSame(
            'luar_jawa',
            $this->service->shippingRegion($this->toko(self::DENPASAR), ...self::JOGJA)
        );
    }

    public function test_koordinat_tidak_diketahui_jatuh_ke_wilayah_default(): void
    {
        // Menebak yang lebih murah berarti marketplace menombok ongkirnya.
        $this->assertSame('luar_jawa', $this->service->shippingRegion($this->toko(), null, null));
        $this->assertSame('luar_jawa', $this->service->shippingRegion(null, ...self::JOGJA));
        $this->assertSame(
            'luar_jawa',
            $this->service->shippingRegion(new Store(['store_name' => 'Tanpa Peta']), ...self::JOGJA)
        );
    }

    public function test_ongkir_memakai_tarif_dasar_wilayah(): void
    {
        $this->assertSame(10000, $this->service->shippingCost('jawa', 'standard'));
        $this->assertSame(20000, $this->service->shippingCost('luar_jawa', 'standard'));
    }

    public function test_express_dikenai_pengali(): void
    {
        $this->assertSame(16000, $this->service->shippingCost('jawa', 'express'));
        $this->assertSame(32000, $this->service->shippingCost('luar_jawa', 'express'));
    }

    public function test_metode_tidak_dikenal_diperlakukan_sebagai_standard(): void
    {
        $this->assertSame(
            $this->service->shippingCost('jawa', 'standard'),
            $this->service->shippingCost('jawa', 'kilat-super')
        );
    }

    public function test_jarak_tidak_lagi_memengaruhi_ongkir(): void
    {
        // Dua tujuan di Jawa dengan jarak sangat berbeda tetap membayar sama.
        $dekat = $this->service->shippingRegion($this->toko(), -7.7830, 110.3675);
        $jauh = $this->service->shippingRegion($this->toko(), -6.2088, 106.8456);

        $this->assertSame(
            $this->service->shippingCost($dekat, 'standard'),
            $this->service->shippingCost($jauh, 'standard')
        );
    }

    public function test_biaya_berat_bertingkat_dan_rata_di_dalam_tingkatnya(): void
    {
        // Di bawah 3 kg dibulatkan ke 3 kg — tarif tingkat pertama adalah
        // tarif dasar, jadi tidak ada yang lebih murah dari itu.
        $this->assertSame(3000, $this->service->weightFee(1));
        $this->assertSame(3000, $this->service->weightFee(3000));

        // Rata di dalam satu tingkat: 3 kg dan 10 kg sama harganya.
        $this->assertSame(3000, $this->service->weightFee(10000));

        // Lewat 10 kg naik ke tingkat kedua.
        $this->assertSame(6000, $this->service->weightFee(10001));
        $this->assertSame(6000, $this->service->weightFee(50000));

        // Paket kosong tidak ditagih apa pun.
        $this->assertSame(0, $this->service->weightFee(0));
    }

    public function test_berat_tertagih_dibulatkan_ke_batas_terendah(): void
    {
        $this->assertSame(3000, $this->service->billableWeightGram(500));
        $this->assertSame(3000, $this->service->billableWeightGram(3000));
        $this->assertSame(7500, $this->service->billableWeightGram(7500));
    }

    public function test_biaya_kemasan_dasar_hanya_sekali_per_toko(): void
    {
        // Baris pertama: kemasan dasar 3000 + 2 x 1500.
        $this->assertSame(6000, $this->service->packagingFee(2, true));

        // Baris berikutnya dari toko yang sama: hanya pembungkus per unit.
        $this->assertSame(3000, $this->service->packagingFee(2, false));
    }

    public function test_peti_kayu_lebih_mahal_daripada_pengemasan_standar(): void
    {
        // Peti kayu: 25000 + 2 x 5000.
        $this->assertSame(35000, $this->service->packagingFee(2, true, 'kayu'));
        $this->assertSame(6000, $this->service->packagingFee(2, true, 'standard'));
    }

    public function test_jenis_pengemasan_tidak_dikenal_jatuh_ke_default(): void
    {
        // Pilihan aneh cukup diperlakukan sebagai standar, bukan menggagalkan
        // checkout pembeli.
        $this->assertSame('standard', $this->service->normalizePackagingType('peti-emas'));
        $this->assertSame('standard', $this->service->normalizePackagingType(null));
        $this->assertSame('kayu', $this->service->normalizePackagingType('kayu'));

        $this->assertSame(
            $this->service->packagingFee(1, true, 'standard'),
            $this->service->packagingFee(1, true, 'peti-emas')
        );
    }

    public function test_berat_volumetrik_dihitung_dari_dimensi(): void
    {
        // 30 x 20 x 20 = 12.000 cm3 / 6000 = 2 kg.
        $product = new Product(['length' => 30, 'width' => 20, 'height' => 20]);

        $this->assertSame(2000, $this->service->volumetricWeightGram($product));

        // Dimensi tidak lengkap tidak boleh mendadak menaikkan tagihan.
        $this->assertSame(0, $this->service->volumetricWeightGram(new Product(['length' => 30, 'width' => 20])));
        $this->assertSame(0, $this->service->volumetricWeightGram(new Product));
    }

    public function test_yang_ditagih_adalah_berat_terbesar(): void
    {
        // Barang besar tapi ringan: volumetrik 2 kg mengalahkan berat asli 0,5 kg.
        $besarRingan = new Product(['weight' => 500, 'length' => 30, 'width' => 20, 'height' => 20]);
        $this->assertSame(2000, $this->service->chargeableWeightGram($besarRingan));

        // Barang kecil tapi berat: berat asli yang menang.
        $kecilBerat = new Product(['weight' => 5000, 'length' => 10, 'width' => 10, 'height' => 10]);
        $this->assertSame(5000, $this->service->chargeableWeightGram($kecilBerat));
    }

    public function test_rincian_baris_menagih_berat_volumetrik_bila_lebih_besar(): void
    {
        $store = new Store(['store_name' => 'Toko', 'latitude' => -7.7828, 'longitude' => 110.3671]);

        // Guci besar tapi ringan: 40 x 30 x 30 = 36.000 / 6000 = 6 kg volumetrik,
        // sementara berat aslinya hanya 1 kg.
        $product = new Product([
            'name' => 'Guci Besar',
            'price' => 1000000,
            'weight' => 1000,
            'length' => 40,
            'width' => 30,
            'height' => 30,
        ]);
        $product->setRelation('store', $store);

        $quote = $this->service->quoteLine($product, 1, 1000000, 'standard', -7.7828, 110.3671);

        $this->assertSame(1000, $quote['actual_weight_gram']);
        $this->assertSame(6000, $quote['volumetric_weight_gram']);
        $this->assertSame(6000, $quote['weight_gram']);
        // 6 kg masih di tingkat pertama.
        $this->assertSame(3000, $quote['weight_fee']);
    }

    public function test_rincian_baris_mengikuti_jenis_pengemasan_pilihan_pembeli(): void
    {
        $store = new Store(['store_name' => 'Toko', 'latitude' => -7.7828, 'longitude' => 110.3671]);

        $product = new Product(['name' => 'Koin', 'price' => 200000, 'weight' => 50]);
        $product->setRelation('store', $store);

        $standar = $this->service->quoteLine($product, 1, 200000, 'standard', null, null, true, 'standard');
        $kayu = $this->service->quoteLine($product, 1, 200000, 'standard', null, null, true, 'kayu');

        $this->assertSame('standard', $standar['packaging_type']);
        $this->assertSame(4500, $standar['packaging_fee']);

        $this->assertSame('kayu', $kayu['packaging_type']);
        $this->assertSame(30000, $kayu['packaging_fee']);

        // Selisih pengemasan adalah satu-satunya beda antara keduanya.
        $this->assertSame(
            $kayu['total'] - $standar['total'],
            $kayu['packaging_fee'] - $standar['packaging_fee']
        );
    }

    public function test_biaya_layanan_dibatasi_minimum_dan_maksimum(): void
    {
        $this->assertSame(25000, $this->service->serviceFee(1000000));

        // 2,5% dari Rp 10.000 = Rp 250, dinaikkan ke batas bawah.
        $this->assertSame(1000, $this->service->serviceFee(10000));

        // 2,5% dari Rp 1 miliar dipotong ke batas atas.
        $this->assertSame(100000, $this->service->serviceFee(1000000000));

        $this->assertSame(0, $this->service->serviceFee(0));
    }

    public function test_jarak_null_bila_salah_satu_titik_tidak_diketahui(): void
    {
        $tanpaKoordinat = new Store(['store_name' => 'Toko Tanpa Peta']);
        $berkoordinat = new Store([
            'store_name' => 'Toko Berpeta',
            'latitude' => -7.7828,
            'longitude' => 110.3671,
        ]);

        $this->assertNull($this->service->distanceKm($tanpaKoordinat, -7.75, 110.49));
        $this->assertNull($this->service->distanceKm($berkoordinat, null, null));
        $this->assertNull($this->service->distanceKm(null, -7.75, 110.49));

        // Tugu Yogyakarta -> Prambanan, kira-kira 14 km.
        $this->assertEqualsWithDelta(
            13.9,
            $this->service->distanceKm($berkoordinat, -7.752, 110.4915),
            1.0
        );
    }

    public function test_produk_tanpa_berat_memakai_berat_default(): void
    {
        $this->assertSame(1000, $this->service->productWeightGram(new Product));
        $this->assertSame(1000, $this->service->productWeightGram(new Product(['weight' => 0])));
        $this->assertSame(2500, $this->service->productWeightGram(new Product(['weight' => 2500])));
    }

    public function test_rincian_baris_menjumlahkan_seluruh_komponen(): void
    {
        $store = new Store(['store_name' => 'Toko', 'latitude' => -7.7828, 'longitude' => 110.3671]);

        $product = new Product(['name' => 'Guci', 'price' => 1000000, 'weight' => 2000]);
        $product->setRelation('store', $store);

        $quote = $this->service->quoteLine($product, 2, 1000000, 'standard', -7.7828, 110.3671);

        // Toko dan tujuan sama-sama di Jawa.
        $this->assertSame('jawa', $quote['region']);
        $this->assertSame(10000, $quote['shipping_cost']);
        $this->assertSame(4000, $quote['weight_gram']);
        $this->assertSame(3000, $quote['weight_fee']);
        $this->assertSame(6000, $quote['packaging_fee']);
        $this->assertSame(50000, $quote['service_fee']);
        $this->assertSame(2000000, $quote['subtotal']);

        $this->assertSame(
            $quote['subtotal'] + $quote['shipping_cost'] + $quote['weight_fee']
                + $quote['packaging_fee'] + $quote['service_fee'],
            $quote['total']
        );
    }

    public function test_baris_kedua_dari_toko_sama_tidak_ditagih_ongkir(): void
    {
        $store = new Store(['store_name' => 'Toko', 'latitude' => -7.7828, 'longitude' => 110.3671]);

        $product = new Product(['name' => 'Piring', 'price' => 500000, 'weight' => 1000]);
        $product->setRelation('store', $store);

        $quote = $this->service->quoteLine($product, 1, 500000, 'standard', -7.752, 110.4915, false);

        $this->assertSame(0, $quote['shipping_cost']);
        // Biaya peti juga tidak diulang, hanya pembungkus per unit.
        $this->assertSame(1500, $quote['packaging_fee']);
        // Biaya berat pun tidak diulang: barang kedua masuk paket yang sama,
        // dan beratnya sudah ikut dihitung pada baris pertama.
        $this->assertSame(0, $quote['weight_fee']);
    }

    public function test_biaya_berat_dihitung_atas_berat_seluruh_paket(): void
    {
        $store = $this->toko();

        $product = new Product(['name' => 'Patung', 'price' => 500000, 'weight' => 6000]);
        $product->setRelation('store', $store);

        // Dua patung 6 kg dari toko yang sama: 12 kg dalam satu paket, jadi
        // masuk tingkat kedua meski masing-masing barisnya hanya 6 kg.
        $pertama = $this->service->quoteLine($product, 1, 500000, 'standard', self::JOGJA[0], self::JOGJA[1], true, null, 12000);
        $kedua = $this->service->quoteLine($product, 1, 500000, 'standard', self::JOGJA[0], self::JOGJA[1], false, null, 12000);

        $this->assertSame(6000, $pertama['weight_fee']);
        $this->assertSame(0, $kedua['weight_fee']);
        $this->assertSame(12000, $pertama['package_weight_gram']);
    }

    public function test_lelang_dihitung_dengan_jalur_yang_sama(): void
    {
        // Lelang membawa kolom berat dan dimensi yang sama dengan produk, jadi
        // perhitungannya tidak perlu jalur tersendiri.
        $store = $this->toko();

        $auction = new Auction([
            'name' => 'Guci Ming',
            'weight' => 4000,
            'length' => 30,
            'width' => 20,
            'height' => 20,
        ]);
        $auction->setRelation('store', $store);

        $quote = $this->service->quoteLine($auction, 1, 2000000, 'standard', ...self::JOGJA);

        $this->assertSame('jawa', $quote['region']);
        $this->assertSame(10000, $quote['shipping_cost']);
        // Berat asli 4 kg mengalahkan volumetrik 2 kg.
        $this->assertSame(4000, $quote['weight_gram']);
        $this->assertSame(3000, $quote['weight_fee']);
    }
}
