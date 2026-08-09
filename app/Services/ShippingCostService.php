<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Store;

/**
 * Perhitungan seluruh komponen biaya checkout.
 *
 * Satu-satunya sumber kebenaran untuk ongkir, biaya berat, biaya pengemasan,
 * dan biaya layanan. Frontend menghitung ulang angka yang sama hanya untuk
 * pratinjau (lihat resources/js/lib/shipping.js); yang ditagihkan selalu hasil
 * hitungan kelas ini.
 *
 * Satuan biaya: rupiah bulat. Satuan berat: gram. Satuan jarak: kilometer.
 */
class ShippingCostService
{
    /**
     * Tarif yang perlu diketahui frontend untuk menampilkan pratinjau biaya.
     * Semuanya publik — tidak ada rahasia di sini.
     */
    public function publicRates(): array
    {
        return [
            'shipping' => config('marketplace.shipping'),
            'weight' => config('marketplace.weight'),
            'packaging' => config('marketplace.packaging'),
            'service_fee' => config('marketplace.service_fee'),
        ];
    }

    /**
     * Jarak toko -> titik pengantaran, atau null bila salah satu titik tidak
     * diketahui. null berarti "jarak tidak bisa dihitung", bukan "jarak nol".
     */
    public function distanceKm(?Store $store, ?float $destLat, ?float $destLng): ?float
    {
        if (! $store || $store->latitude === null || $store->longitude === null) {
            return null;
        }

        if ($destLat === null || $destLng === null) {
            return null;
        }

        $distance = $store->calculateDistance($destLat, $destLng);

        // calculateDistance() mengembalikan PHP_FLOAT_MAX bila koordinat toko
        // kosong; sudah dijaga di atas, tapi tetap dijaga di sini agar nilai
        // sentinel itu tidak pernah bocor menjadi tagihan.
        if (! is_finite($distance) || $distance >= PHP_FLOAT_MAX) {
            return null;
        }

        return round($distance, 2);
    }

    /**
     * Ongkir satu paket: tarif dasar + (jarak x tarif per km), lalu dikalikan
     * pengali metode pengiriman.
     *
     * Jarak yang ditagihkan dibatasi bawah dan atas supaya pengantaran sangat
     * dekat tetap berbiaya dan pengantaran sangat jauh tidak meledak.
     */
    public function shippingCost(?float $distanceKm, string $method): int
    {
        $method = $this->normalizeMethod($method);
        $config = config('marketplace.shipping');

        if ($distanceKm === null) {
            return (int) ($config['flat_fallback'][$method] ?? 0);
        }

        $billableKm = $this->billableDistanceKm($distanceKm);
        $multiplier = (float) ($config['method_multiplier'][$method] ?? 1.0);

        return (int) round(
            ((int) $config['base_fee'] + $billableKm * (int) $config['per_km']) * $multiplier
        );
    }

    /**
     * Jarak yang benar-benar ditagihkan setelah dibatasi minimum dan maksimum.
     */
    public function billableDistanceKm(float $distanceKm): float
    {
        $config = config('marketplace.shipping');

        return round(
            min(max($distanceKm, (float) $config['min_distance_km']), (float) $config['max_distance_km']),
            2
        );
    }

    /**
     * Biaya berat: berat total dibulatkan ke atas ke kilogram penuh, lalu
     * dikalikan tarif per kilogram. Ini menyamai cara kurir menghitung.
     */
    public function weightFee(int $totalGram): int
    {
        if ($totalGram <= 0) {
            return 0;
        }

        return (int) ceil($totalGram / 1000) * (int) config('marketplace.weight.per_kg');
    }

    /**
     * Biaya pengemasan menurut jenis yang DIPILIH PEMBELI.
     *
     * Biaya dasar (peti/kardus) hanya dikenakan pada baris pertama sebuah toko —
     * barang lain dari toko yang sama masuk ke kemasan yang sama. Biaya per
     * unit tetap dikenakan tiap barang karena masing-masing dibungkus sendiri.
     */
    public function packagingFee(int $quantity, bool $firstOfStore, ?string $type = null): int
    {
        $option = $this->packagingOption($type);

        return ($firstOfStore ? (int) $option['base_fee'] : 0)
            + (int) $option['per_item_fee'] * max(1, $quantity);
    }

    /**
     * Jenis pengemasan yang valid, atau jenis default bila pilihannya tidak
     * dikenal. Tidak pernah melempar exception: pilihan yang aneh cukup
     * diperlakukan sebagai standar, bukan menggagalkan checkout.
     */
    public function normalizePackagingType(?string $type): string
    {
        $options = config('marketplace.packaging.options');

        return isset($options[$type]) ? $type : (string) config('marketplace.packaging.default');
    }

    public function packagingOption(?string $type): array
    {
        return config('marketplace.packaging.options.'.$this->normalizePackagingType($type));
    }

    public function packagingLabel(?string $type): string
    {
        return (string) $this->packagingOption($type)['label'];
    }

    /**
     * Berat volumetrik satu unit produk dalam gram: (P x L x T) / pembagi,
     * dalam kilogram, lalu dikonversi ke gram.
     *
     * Nol bila seller belum mengisi dimensinya — produk lama tidak boleh
     * mendadak ditagih lebih mahal hanya karena kolomnya kosong.
     */
    public function volumetricWeightGram(Product $product): int
    {
        $length = (int) ($product->length ?? 0);
        $width = (int) ($product->width ?? 0);
        $height = (int) ($product->height ?? 0);

        if ($length <= 0 || $width <= 0 || $height <= 0) {
            return 0;
        }

        $divisor = max(1, (int) config('marketplace.weight.volumetric_divisor'));

        return (int) round($length * $width * $height / $divisor * 1000);
    }

    /**
     * Berat yang benar-benar ditagihkan untuk satu unit: yang LEBIH BESAR
     * antara berat asli dan berat volumetrik — cara kurir menghitung.
     */
    public function chargeableWeightGram(Product $product): int
    {
        return max($this->productWeightGram($product), $this->volumetricWeightGram($product));
    }

    /**
     * Biaya layanan marketplace: persentase dari nilai barang, dibatasi
     * minimum dan maksimum. Inilah komponen yang masuk ke dompet admin.
     */
    public function serviceFee(int $subtotal): int
    {
        if ($subtotal <= 0) {
            return 0;
        }

        $config = config('marketplace.service_fee');
        $fee = (int) round($subtotal * (float) $config['percent'] / 100);

        return (int) min(max($fee, (int) $config['min']), (int) $config['max']);
    }

    /**
     * Berat satu unit produk dalam gram; jatuh ke berat default bila seller
     * belum mengisinya (produk lama sebelum kolom berat ada).
     */
    public function productWeightGram(Product $product): int
    {
        $weight = (int) ($product->weight ?? 0);

        return $weight > 0 ? $weight : (int) config('marketplace.weight.default_gram');
    }

    /**
     * Rincian biaya satu baris pesanan.
     *
     * @param  bool  $firstOfStore  Baris pertama dari toko ini dalam satu
     *                              transaksi. Ongkir dan biaya kemasan dasar
     *                              hanya ditagihkan sekali per toko, karena
     *                              barang dari toko yang sama dikirim dalam
     *                              satu paket.
     * @param  string|null  $packagingType  Jenis pengemasan pilihan pembeli.
     * @return array{distance_km: float|null, shipping_cost: int, weight_gram: int, actual_weight_gram: int, volumetric_weight_gram: int, weight_fee: int, packaging_type: string, packaging_fee: int, service_fee: int, subtotal: int, total: int}
     */
    public function quoteLine(
        Product $product,
        int $quantity,
        int $unitPrice,
        string $method,
        ?float $destLat,
        ?float $destLng,
        bool $firstOfStore = true,
        ?string $packagingType = null
    ): array {
        $quantity = max(1, $quantity);
        $subtotal = $unitPrice * $quantity;

        $distanceKm = $this->distanceKm($product->store, $destLat, $destLng);
        $shippingCost = $firstOfStore ? $this->shippingCost($distanceKm, $method) : 0;

        // Yang ditagih adalah berat terbesar antara berat asli dan volumetrik.
        $actualGram = $this->productWeightGram($product) * $quantity;
        $volumetricGram = $this->volumetricWeightGram($product) * $quantity;
        $weightGram = max($actualGram, $volumetricGram);
        $weightFee = $this->weightFee($weightGram);

        $packagingType = $this->normalizePackagingType($packagingType);
        $packagingFee = $this->packagingFee($quantity, $firstOfStore, $packagingType);
        $serviceFee = $this->serviceFee($subtotal);

        return [
            'distance_km' => $distanceKm,
            'shipping_cost' => $shippingCost,
            'weight_gram' => $weightGram,
            'actual_weight_gram' => $actualGram,
            'volumetric_weight_gram' => $volumetricGram,
            'weight_fee' => $weightFee,
            'packaging_type' => $packagingType,
            'packaging_fee' => $packagingFee,
            'service_fee' => $serviceFee,
            'subtotal' => $subtotal,
            'total' => $subtotal + $shippingCost + $weightFee + $packagingFee + $serviceFee,
        ];
    }

    private function normalizeMethod(string $method): string
    {
        return $method === 'express' ? 'express' : 'standard';
    }
}
