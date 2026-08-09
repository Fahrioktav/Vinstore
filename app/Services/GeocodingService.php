<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Membaca nama wilayah dari sepasang koordinat (reverse geocoding).
 *
 * Dipakai checkout untuk menerjemahkan titik yang dipilih pembeli di peta
 * menjadi nama wilayah yang bisa dibaca seller — lihat temuan V4-01. Tanpa ini
 * seller hanya melihat teks bebas dari pembeli, yang belum tentu berhubungan
 * dengan titik yang dipakai menghitung ongkir.
 *
 * Layanannya OpenStreetMap Nominatim: gratis, tanpa kunci API, dan sudah
 * sejalan dengan peta OSM yang dipakai di frontend.
 *
 * DUA HAL YANG DISENGAJA:
 *
 * 1. Kegagalan tidak pernah menggagalkan checkout. Nominatim bisa lambat,
 *    membatasi laju, atau tidak mengenali titik di tengah sawah. Yang terjadi
 *    hanyalah `shipping_area` kosong — ongkirnya tetap benar karena dihitung
 *    dari koordinat, bukan dari nama wilayah.
 *
 * 2. Hasilnya di-cache berdasarkan koordinat yang dibulatkan. Nominatim
 *    membatasi 1 permintaan per detik, dan pembeli di satu kelurahan yang sama
 *    tidak perlu ditanyakan berulang kali.
 */
class GeocodingService
{
    /**
     * Berapa lama nama wilayah sebuah titik disimpan. Batas administratif
     * praktis tidak berubah, jadi 30 hari sangat aman.
     */
    private const CACHE_DAYS = 30;

    /**
     * Ketelitian pembulatan koordinat untuk kunci cache.
     *
     * 3 angka desimal ≈ 110 meter. Cukup halus untuk membedakan kelurahan,
     * cukup kasar untuk membuat cache-nya benar-benar terpakai.
     */
    private const CACHE_PRECISION = 3;

    /**
     * Nama wilayah untuk sebuah titik, atau null bila tidak bisa ditentukan.
     *
     * Hasilnya berupa rangkaian dari yang paling kecil ke paling besar —
     * misalnya "Caturtunggal, Depok, Sleman, DI Yogyakarta".
     */
    public function areaName(?float $latitude, ?float $longitude): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        if (! config('marketplace.geocoding.enabled')) {
            return null;
        }

        $key = sprintf(
            'geocode:%s,%s',
            number_format($latitude, self::CACHE_PRECISION, '.', ''),
            number_format($longitude, self::CACHE_PRECISION, '.', '')
        );

        return Cache::remember(
            $key,
            now()->addDays(self::CACHE_DAYS),
            fn () => $this->lookup($latitude, $longitude)
        );
    }

    /**
     * Panggilan sesungguhnya ke Nominatim.
     *
     * Seluruh kegagalan ditelan dan dicatat ke log: checkout tidak boleh
     * bergantung pada layanan pihak ketiga yang bisa mati kapan saja.
     */
    private function lookup(float $latitude, float $longitude): ?string
    {
        try {
            $response = Http::withHeaders([
                // Nominatim mewajibkan User-Agent yang bisa diidentifikasi.
                'User-Agent' => config('marketplace.geocoding.user_agent'),
                'Accept-Language' => 'id',
            ])
                ->timeout((int) config('marketplace.geocoding.timeout'))
                ->get(config('marketplace.geocoding.url'), [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'format' => 'jsonv2',
                    // 14 = tingkat kecamatan/kelurahan. Lebih detail dari ini
                    // justru mengembalikan nama gedung yang tidak membantu.
                    'zoom' => 14,
                    'addressdetails' => 1,
                ]);

            if (! $response->successful()) {
                return null;
            }

            return $this->formatAddress($response->json('address') ?? []);
        } catch (Throwable $e) {
            Log::warning('Reverse geocoding gagal', [
                'lat' => $latitude,
                'lon' => $longitude,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Susun nama wilayah dari bagian-bagian alamat Nominatim.
     *
     * Nominatim memakai nama bidang yang berbeda-beda antar negara, jadi tiap
     * tingkatan dicari dari beberapa kemungkinan. Yang kosong dilewati, dan
     * nama yang berulang dibuang supaya tidak muncul "Sleman, Sleman".
     */
    private function formatAddress(array $address): ?string
    {
        $levels = [
            ['village', 'suburb', 'neighbourhood', 'hamlet'],
            ['city_district', 'municipality', 'town', 'city'],
            ['county', 'regency'],
            ['state', 'province'],
        ];

        $parts = [];

        foreach ($levels as $candidates) {
            foreach ($candidates as $key) {
                $value = trim((string) ($address[$key] ?? ''));

                if ($value !== '' && ! in_array($value, $parts, true)) {
                    $parts[] = $value;
                    break;
                }
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }
}
