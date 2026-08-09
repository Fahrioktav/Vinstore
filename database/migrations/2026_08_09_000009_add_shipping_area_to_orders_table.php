<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temuan V4-01: titik peta tidak pernah dicocokkan dengan alamat teksnya.
 *
 * Ongkir dihitung dari pin yang digeser sendiri oleh pembeli, sementara seller
 * mengirim ke alamat teks yang diketik terpisah. Pembeli bisa menulis alamat
 * Jayapura lalu meletakkan pin di depan toko di Yogyakarta, membayar ongkir
 * jarak minimum, dan seller tetap wajib mengirim ke Jayapura.
 *
 * Perbaikannya: pin menjadi SUMBER TUNGGAL lokasi tujuan.
 *
 *  - `shipping_area` menyimpan nama wilayah hasil pembacaan balik koordinat
 *    (reverse geocoding OpenStreetMap). Inilah yang dilihat seller sebagai
 *    tujuan sebenarnya, bukan lagi semata-mata teks bebas dari pembeli.
 *  - Kolom alamat lama tetap ada, tetapi perannya berubah menjadi DETAIL di
 *    dalam wilayah itu: nama jalan, nomor rumah, patokan.
 *
 * Dengan begitu tidak ada lagi dua sumber lokasi yang bisa saling bertentangan:
 * ongkir dan tujuan pengiriman sama-sama berasal dari satu titik.
 *
 * `shipping_area` boleh null — layanan geocoding bisa gagal atau tidak
 * mengenali titiknya, dan checkout tidak boleh ikut gagal karenanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_area')->nullable()->after('shipping_address');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_area');
        });
    }
};
