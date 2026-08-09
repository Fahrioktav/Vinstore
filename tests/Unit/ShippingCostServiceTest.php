<?php

namespace Tests\Unit;

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

        config()->set('marketplace.shipping.base_fee', 5000);
        config()->set('marketplace.shipping.per_km', 2000);
        config()->set('marketplace.shipping.min_distance_km', 1.0);
        config()->set('marketplace.shipping.max_distance_km', 100.0);
        config()->set('marketplace.shipping.method_multiplier', ['standard' => 1.0, 'express' => 1.6]);
        config()->set('marketplace.shipping.flat_fallback', ['standard' => 10000, 'express' => 25000]);
        config()->set('marketplace.weight.per_kg', 3000);
        config()->set('marketplace.weight.default_gram', 1000);
        config()->set('marketplace.weight.volumetric_divisor', 6000);
        config()->set('marketplace.packaging.default', 'standard');
        config()->set('marketplace.packaging.options', [
            'standard' => ['label' => 'Bubble Wrap + Kardus', 'base_fee' => 3000, 'per_item_fee' => 1500],
            'kayu' => ['label' => 'Peti Kayu', 'base_fee' => 25000, 'per_item_fee' => 5000],
        ]);
        config()->set('marketplace.service_fee', ['percent' => 2.5, 'min' => 1000, 'max' => 100000]);
    }

    public function test_ongkir_dihitung_dari_tarif_dasar_ditambah_jarak(): void
    {
        // 5000 + 10 km x 2000.
        $this->assertSame(25000, $this->service->shippingCost(10.0, 'standard'));
    }

    public function test_express_dikenai_pengali(): void
    {
        // (5000 + 10 x 2000) x 1,6.
        $this->assertSame(40000, $this->service->shippingCost(10.0, 'express'));
    }

    public function test_jarak_sangat_dekat_tetap_ditagih_jarak_minimum(): void
    {
        // Jarak 0,1 km dinaikkan ke minimum 1 km: 5000 + 1 x 2000.
        $this->assertSame(7000, $this->service->shippingCost(0.1, 'standard'));
    }

    public function test_jarak_sangat_jauh_dibatasi_maksimum(): void
    {
        // Koordinat ngawur di sisi lain bumi tidak boleh menghasilkan ongkir
        // jutaan rupiah.
        $this->assertSame(
            $this->service->shippingCost(100.0, 'standard'),
            $this->service->shippingCost(9000.0, 'standard')
        );
    }

    public function test_tanpa_koordinat_ongkir_memakai_tarif_rata(): void
    {
        $this->assertSame(10000, $this->service->shippingCost(null, 'standard'));
        $this->assertSame(25000, $this->service->shippingCost(null, 'express'));
    }

    public function test_metode_tidak_dikenal_diperlakukan_sebagai_standard(): void
    {
        $this->assertSame(
            $this->service->shippingCost(10.0, 'standard'),
            $this->service->shippingCost(10.0, 'kilat-super')
        );
    }

    public function test_berat_dibulatkan_ke_atas_ke_kilogram_penuh(): void
    {
        $this->assertSame(3000, $this->service->weightFee(1));
        $this->assertSame(3000, $this->service->weightFee(1000));
        $this->assertSame(6000, $this->service->weightFee(1001));
        $this->assertSame(12000, $this->service->weightFee(3500));
        $this->assertSame(0, $this->service->weightFee(0));
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
        $this->assertSame(18000, $quote['weight_fee']);
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

        // Jarak 0 km -> minimum 1 km: 5000 + 2000.
        $this->assertSame(7000, $quote['shipping_cost']);
        $this->assertSame(4000, $quote['weight_gram']);
        $this->assertSame(12000, $quote['weight_fee']);
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
        // Berat tetap ditagih karena barangnya memang punya berat sendiri.
        $this->assertSame(3000, $quote['weight_fee']);
    }
}
