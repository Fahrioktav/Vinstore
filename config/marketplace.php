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
        // Tarif dasar per paket (dihitung sekali per toko) menurut WILAYAH
        // tujuan, bukan menurut jarak tempuh.
        //
        // Komponen per-kilometer yang dipakai versi sebelumnya sudah dihapus:
        // ongkir kini rata untuk seluruh Pulau Jawa, dan rata pula untuk luar
        // Jawa. Jaraknya sendiri masih dihitung dan disimpan pada pesanan,
        // tetapi hanya sebagai keterangan — tidak lagi memengaruhi tagihan.
        'base_fee' => [
            'jawa' => (int) env('SHIPPING_BASE_FEE_JAWA', 10000),
            'luar_jawa' => (int) env('SHIPPING_BASE_FEE_LUAR_JAWA', 20000),
        ],

        /*
         * Kotak pembatas Pulau Jawa (termasuk Madura).
         *
         * Sebuah pengiriman dihitung "jawa" hanya bila TITIK TOKO DAN TITIK
         * TUJUAN sama-sama berada di dalam kotak ini. Cukup salah satu di luar,
         * tarifnya menjadi luar Jawa — karena paketnya memang menyeberang.
         *
         * Kotak persegi tentu tidak persis mengikuti garis pantai. Dua tempat
         * yang keliru terbaca sebagai Jawa: ujung selatan Lampung (sekitar
         * Bakauheni) dan ujung barat Bali (sekitar Gilimanuk) — keduanya
         * berhimpit di selat yang sama dan tidak bisa dipisahkan oleh kotak.
         * Pemisahan yang lebih tepat butuh data poligon wilayah, yang berlebihan
         * untuk selisih tarif sepuluh ribu rupiah.
         */
        'java_bounds' => [
            'min_lat' => -8.85,
            'max_lat' => -5.72,
            'min_lng' => 105.00,
            'max_lng' => 114.65,
        ],

        // Wilayah yang dipakai bila koordinat toko atau pembeli tidak diketahui.
        // Sengaja luar Jawa: menebak yang lebih murah berarti marketplace
        // menombok ongkir tiap kali koordinatnya kosong.
        'default_region' => env('SHIPPING_DEFAULT_REGION', 'luar_jawa'),

        // Pengali per metode pengiriman.
        'method_multiplier' => [
            'standard' => 1.0,
            'express' => 1.6,
        ],
    ],

    'weight' => [
        /*
         * Biaya berat bertingkat, dihitung SEKALI per paket (per toko) atas
         * berat seluruh barang di dalamnya — bukan per baris keranjang.
         *
         * Tarifnya rata di dalam satu tingkat: 3 kg dan 10 kg sama-sama
         * Rp 3.000. Barang di bawah 3 kg dibulatkan ke 3 kg karena tingkat
         * pertama adalah tarif dasar, jadi tidak ada tagihan berat di bawah itu.
         *
         * `max_gram` null berarti tingkat terakhir — tanpa batas atas.
         */
        'tiers' => [
            ['max_gram' => 10000, 'fee' => (int) env('SHIPPING_WEIGHT_FEE_TIER_1', 3000)],
            ['max_gram' => null, 'fee' => (int) env('SHIPPING_WEIGHT_FEE_TIER_2', 6000)],
        ],

        // Berat terendah yang ditagihkan. Apa pun di bawah ini dibulatkan naik.
        'min_billable_gram' => 3000,

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

    /*
    |--------------------------------------------------------------------------
    | Deposit lelang
    |--------------------------------------------------------------------------
    |
    | Uang jaminan yang harus dibayar pembeli sebelum boleh menawar pada lelang
    | bernilai tinggi. Tujuannya menyaring penawar yang tidak sungguh-sungguh:
    | tanpa jaminan, pemenang yang kabur hanya merugikan seller — barangnya
    | tertahan berhari-hari dan lelangnya harus diulang.
    |
    | Depositnya bukan biaya. Pemenang memakainya sebagai uang muka, dan yang
    | kalah mengajukan pengembaliannya ke admin.
    |
    */
    'auction_deposit' => [
        // Lelang dengan harga awal di bawah ambang ini tidak memungut deposit.
        // Barang murah tidak sepadan dengan rumitnya menagih dan mengembalikan
        // uang jaminan.
        'threshold' => (int) env('AUCTION_DEPOSIT_THRESHOLD', 1000000),

        // Persentase dari harga awal lelang.
        'percent' => (float) env('AUCTION_DEPOSIT_PERCENT', 10),
    ],
];
