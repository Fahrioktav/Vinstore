<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Database\Eloquent\Model;

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
     * Apakah sebuah titik berada di Pulau Jawa (termasuk Madura).
     *
     * Dinilai dari kotak pembatas di config; batasannya dijelaskan di sana.
     */
    public function isInJava(?float $lat, ?float $lng): bool
    {
        if ($lat === null || $lng === null) {
            return false;
        }

        $bounds = config('marketplace.shipping.java_bounds');

        return $lat >= (float) $bounds['min_lat']
            && $lat <= (float) $bounds['max_lat']
            && $lng >= (float) $bounds['min_lng']
            && $lng <= (float) $bounds['max_lng'];
    }

    /**
     * Wilayah tarif sebuah pengiriman: 'jawa' bila toko DAN tujuan sama-sama di
     * Pulau Jawa, selain itu 'luar_jawa'.
     *
     * Koordinat yang tidak diketahui jatuh ke wilayah default (luar Jawa),
     * bukan ke tarif termurah.
     */
    public function shippingRegion(?Store $store, ?float $destLat, ?float $destLng): string
    {
        if (! $store || $store->latitude === null || $store->longitude === null) {
            return (string) config('marketplace.shipping.default_region');
        }

        if ($destLat === null || $destLng === null) {
            return (string) config('marketplace.shipping.default_region');
        }

        $sameIsland = $this->isInJava((float) $store->latitude, (float) $store->longitude)
            && $this->isInJava($destLat, $destLng);

        return $sameIsland ? 'jawa' : 'luar_jawa';
    }

    public function regionLabel(string $region): string
    {
        return $region === 'jawa' ? 'Pulau Jawa' : 'Luar Pulau Jawa';
    }

    /**
     * Ongkir satu paket: tarif dasar wilayah dikalikan pengali metode
     * pengiriman. Jaraknya tidak lagi ikut dihitung — lihat catatan di
     * config/marketplace.php.
     */
    public function shippingCost(string $region, string $method): int
    {
        $method = $this->normalizeMethod($method);
        $config = config('marketplace.shipping');

        $baseFee = (int) ($config['base_fee'][$region] ?? $config['base_fee'][$config['default_region']]);
        $multiplier = (float) ($config['method_multiplier'][$method] ?? 1.0);

        return (int) round($baseFee * $multiplier);
    }

    /**
     * Berat yang benar-benar ditagihkan untuk satu paket.
     *
     * Tingkat pertama adalah tarif dasar, jadi paket di bawah batas terendah
     * (3 kg) dibulatkan naik ke batas itu — tidak ada tagihan berat yang lebih
     * murah dari tarif dasar.
     */
    public function billableWeightGram(int $totalGram): int
    {
        return max($totalGram, (int) config('marketplace.weight.min_billable_gram'));
    }

    /**
     * Biaya berat satu paket menurut tingkatan berat.
     *
     * Rata di dalam satu tingkat: 3 kg dan 10 kg sama-sama dikenai tarif tingkat
     * pertama. Paket kosong (berat 0, yang seharusnya tidak terjadi) tidak
     * ditagih apa pun.
     */
    public function weightFee(int $totalGram): int
    {
        if ($totalGram <= 0) {
            return 0;
        }

        $billable = $this->billableWeightGram($totalGram);
        $tiers = config('marketplace.weight.tiers');

        foreach ($tiers as $tier) {
            if ($tier['max_gram'] === null || $billable <= (int) $tier['max_gram']) {
                return (int) $tier['fee'];
            }
        }

        return (int) end($tiers)['fee'];
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
     * Berat volumetrik satu unit barang dalam gram: (P x L x T) / pembagi,
     * dalam kilogram, lalu dikonversi ke gram.
     *
     * Nol bila seller belum mengisi dimensinya — produk lama tidak boleh
     * mendadak ditagih lebih mahal hanya karena kolomnya kosong.
     */
    public function volumetricWeightGram(Model $item): int
    {
        $length = (int) ($item->length ?? 0);
        $width = (int) ($item->width ?? 0);
        $height = (int) ($item->height ?? 0);

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
    public function chargeableWeightGram(Model $item): int
    {
        return max($this->productWeightGram($item), $this->volumetricWeightGram($item));
    }

    /**
     * Berat tertagih satu baris: berat satu unit dikalikan jumlahnya.
     *
     * Dipakai untuk menjumlahkan berat seluruh barang dalam satu paket sebelum
     * memanggil weightFee(), karena biaya berat dihitung per paket.
     */
    public function lineWeightGram(Model $item, int $quantity): int
    {
        return $this->chargeableWeightGram($item) * max(1, $quantity);
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
     * Berat satu unit barang dalam gram; jatuh ke berat default bila seller
     * belum mengisinya (produk lama sebelum kolom berat ada).
     */
    public function productWeightGram(Model $item): int
    {
        $weight = (int) ($item->weight ?? 0);

        return $weight > 0 ? $weight : (int) config('marketplace.weight.default_gram');
    }

    /**
     * Rincian biaya satu baris pesanan.
     *
     * @param  Model  $item  Barang yang dikirim: Product, atau Auction untuk
     *                       pesanan lelang. Keduanya membawa kolom berat,
     *                       dimensi, dan relasi `store` yang sama.
     * @param  bool  $firstOfStore  Baris pertama dari toko ini dalam satu
     *                              transaksi. Ongkir, biaya berat, dan biaya
     *                              kemasan dasar hanya ditagihkan sekali per
     *                              toko, karena barang dari toko yang sama
     *                              dikirim dalam satu paket.
     * @param  string|null  $packagingType  Jenis pengemasan pilihan pembeli.
     * @param  int|null  $packageWeightGram  Berat seluruh isi paket toko ini.
     *                                       Diisi pemanggil bila satu toko
     *                                       menyumbang lebih dari satu baris;
     *                                       bila null, berat baris ini sendiri
     *                                       yang dipakai.
     * @return array{region: string, distance_km: float|null, shipping_cost: int, weight_gram: int, actual_weight_gram: int, volumetric_weight_gram: int, package_weight_gram: int, billable_weight_gram: int, weight_fee: int, packaging_type: string, packaging_fee: int, service_fee: int, subtotal: int, total: int}
     */
    public function quoteLine(
        Model $item,
        int $quantity,
        int $unitPrice,
        string $method,
        ?float $destLat,
        ?float $destLng,
        bool $firstOfStore = true,
        ?string $packagingType = null,
        ?int $packageWeightGram = null
    ): array {
        $quantity = max(1, $quantity);
        $subtotal = $unitPrice * $quantity;

        $distanceKm = $this->distanceKm($item->store, $destLat, $destLng);
        $region = $this->shippingRegion($item->store, $destLat, $destLng);
        $shippingCost = $firstOfStore ? $this->shippingCost($region, $method) : 0;

        // Yang ditagih adalah berat terbesar antara berat asli dan volumetrik.
        $actualGram = $this->productWeightGram($item) * $quantity;
        $volumetricGram = $this->volumetricWeightGram($item) * $quantity;
        $weightGram = max($actualGram, $volumetricGram);

        // Biaya berat menyusul ongkir: satu kali per paket, atas berat seluruh
        // isinya. Tanpa ini dua barang dari toko yang sama membayar biaya berat
        // dua kali padahal masuk ke satu kardus.
        $packageGram = $packageWeightGram ?? $weightGram;
        $weightFee = $firstOfStore ? $this->weightFee($packageGram) : 0;

        $packagingType = $this->normalizePackagingType($packagingType);
        $packagingFee = $this->packagingFee($quantity, $firstOfStore, $packagingType);
        $serviceFee = $this->serviceFee($subtotal);

        return [
            'region' => $region,
            'distance_km' => $distanceKm,
            'shipping_cost' => $shippingCost,
            'weight_gram' => $weightGram,
            'actual_weight_gram' => $actualGram,
            'volumetric_weight_gram' => $volumetricGram,
            'package_weight_gram' => $packageGram,
            'billable_weight_gram' => $this->billableWeightGram($packageGram),
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
