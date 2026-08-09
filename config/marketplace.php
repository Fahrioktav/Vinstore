<?php

/*
|--------------------------------------------------------------------------
| Komponen biaya marketplace
|--------------------------------------------------------------------------
|
| Semua tarif dalam rupiah. Angka-angka ini sengaja dikumpulkan di sini agar
| bisa disetel tanpa menyentuh logika perhitungan di
| App\Services\ShippingCostService, dan agar frontend dapat menampilkan
| rincian yang persis sama dengan yang ditagihkan server.
|
*/

return [
    'shipping' => [
        // Tarif dasar per paket (dihitung sekali per toko), menutup biaya
        // penanganan kurir terlepas dari jarak.
        'base_fee' => (int) env('SHIPPING_BASE_FEE', 5000),

        // Tarif jarak per kilometer dari titik toko ke titik pengantaran.
        'per_km' => (int) env('SHIPPING_PER_KM', 2000),

        // Jarak minimum yang ditagihkan. Tanpa ini pembeli yang lokasinya
        // persis di depan toko membayar komponen jarak Rp 0.
        'min_distance_km' => 1.0,

        // Batas atas jarak yang ditagihkan. Menjaga ongkir tetap masuk akal
        // untuk kiriman lintas pulau sekaligus meredam koordinat ngawur.
        'max_distance_km' => 100.0,

        // Pengali per metode pengiriman.
        'method_multiplier' => [
            'standard' => 1.0,
            'express' => 1.6,
        ],

        // Dipakai bila toko atau pembeli tidak punya koordinat: jarak tidak
        // dapat dihitung, jadi perhitungan kembali ke tarif rata seperti
        // versi sebelumnya agar checkout tetap bisa jalan.
        'flat_fallback' => [
            'standard' => 10000,
            'express' => 25000,
        ],
    ],

    'weight' => [
        // Tarif per kilogram; berat dibulatkan ke atas ke kilogram penuh.
        'per_kg' => (int) env('SHIPPING_PER_KG', 3000),

        // Berat yang dipakai bila seller belum mengisi berat produk (gram).
        'default_gram' => 1000,

        // Pembagi berat volumetrik: (P x L x T dalam cm) / pembagi = kilogram.
        // 6000 adalah angka yang lazim dipakai kurir dalam negeri. Yang
        // ditagihkan adalah yang LEBIH BESAR antara berat asli dan volumetrik,
        // karena barang besar-tapi-ringan memakan ruang truk yang sama.
        'volumetric_divisor' => (int) env('SHIPPING_VOLUMETRIC_DIVISOR', 6000),
    ],

    'packaging' => [
        // Pembeli memilih sendiri jenis pengemasannya. Koin antik tidak perlu
        // peti kayu; guci keramik perlu. Memaksakan satu tarif untuk semua
        // barang membuat pembeli barang kecil membayar perlindungan yang tidak
        // ia butuhkan.
        //
        // base_fee dihitung SEKALI per toko (satu paket), per_item_fee
        // dihitung per unit barang.
        'default' => 'standard',

        'options' => [
            'standard' => [
                'label' => 'Bubble Wrap + Kardus',
                'description' => 'Pembungkus standar. Cukup untuk barang kecil dan tidak mudah pecah.',
                'base_fee' => (int) env('PACKAGING_STANDARD_BASE_FEE', 3000),
                'per_item_fee' => (int) env('PACKAGING_STANDARD_PER_ITEM_FEE', 1500),
            ],
            'kayu' => [
                'label' => 'Peti Kayu',
                'description' => 'Peti kayu custom. Sangat disarankan untuk keramik, kaca, dan barang rapuh bernilai tinggi.',
                'base_fee' => (int) env('PACKAGING_KAYU_BASE_FEE', 25000),
                'per_item_fee' => (int) env('PACKAGING_KAYU_PER_ITEM_FEE', 5000),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pembacaan balik koordinat (reverse geocoding)
    |--------------------------------------------------------------------------
    |
    | Menerjemahkan titik yang dipilih pembeli di peta menjadi nama wilayah yang
    | bisa dibaca seller. Lihat App\Services\GeocodingService dan temuan V4-01.
    |
    | Bisa dimatikan sepenuhnya lewat .env tanpa merusak checkout: yang hilang
    | hanya nama wilayahnya, ongkirnya tetap dihitung dari koordinat.
    |
    */
    'geocoding' => [
        'enabled' => (bool) env('GEOCODING_ENABLED', true),
        'url' => env('GEOCODING_URL', 'https://nominatim.openstreetmap.org/reverse'),

        // Nominatim mewajibkan User-Agent yang bisa diidentifikasi; permintaan
        // tanpa itu diblokir. Ganti dengan alamat surel Anda sendiri.
        'user_agent' => env('GEOCODING_USER_AGENT', 'Vinstore/1.0 (marketplace barang antik)'),

        // Sengaja pendek. Checkout tidak boleh menunggu lama demi keterangan
        // yang sifatnya pelengkap.
        'timeout' => (int) env('GEOCODING_TIMEOUT', 4),
    ],

    'service_fee' => [
        // Persentase dari nilai barang. Inilah pendapatan marketplace —
        // satu-satunya komponen biaya yang masuk ke dompet admin.
        'percent' => (float) env('SERVICE_FEE_PERCENT', 2.5),

        // Batas bawah per baris pesanan, agar transaksi kecil tetap menutup
        // biaya pemrosesan.
        'min' => (int) env('SERVICE_FEE_MIN', 1000),

        // Batas atas per baris pesanan, agar barang mahal tidak dikenai
        // potongan yang tidak wajar.
        'max' => (int) env('SERVICE_FEE_MAX', 100000),
    ],
];
