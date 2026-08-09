<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua penambahan pada perhitungan biaya checkout.
 *
 * 1. DIMENSI PRODUK (cm). Kurir menagih berdasarkan berat yang lebih besar
 *    antara berat asli dan berat volumetrik (P x L x T / pembagi). Guci besar
 *    yang ringan memakan ruang truk sama banyaknya dengan barang berat, dan
 *    tanpa dimensi biayanya tidak bisa dihitung adil.
 *
 * 2. JENIS PENGEMASAN sebagai PILIHAN pembeli, bukan lagi tarif tunggal yang
 *    dipaksakan. Koin antik tidak perlu peti kayu; guci keramik perlu. Kolom
 *    `packaging_type` menyimpan pilihan itu pada pesanan, dan
 *    `volumetric_weight_gram` menyimpan berat volumetrik yang dipakai menagih
 *    agar bisa ditelusuri ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('length')->nullable()->after('weight');
            $table->unsignedInteger('width')->nullable()->after('length');
            $table->unsignedInteger('height')->nullable()->after('width');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('packaging_type')->nullable()->after('packaging_fee');
            $table->unsignedInteger('volumetric_weight_gram')->default(0)->after('weight_gram');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['packaging_type', 'volumetric_weight_gram']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['length', 'width', 'height']);
        });
    }
};
