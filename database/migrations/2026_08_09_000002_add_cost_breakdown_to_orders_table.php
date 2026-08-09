<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian biaya pesanan.
 *
 * Sebelumnya pesanan hanya menyimpan `shipping_cost` berupa tarif rata
 * (10rb/25rb) dan `price` sebagai total. Tidak ada jejak dari mana totalnya
 * berasal, sehingga selisih sekecil apa pun antara tagihan Midtrans dan
 * tampilan pesanan mustahil ditelusuri.
 *
 * Sekarang setiap komponen disimpan sendiri:
 *  - shipping_cost         : tarif dasar + komponen jarak (ongkir murni)
 *  - shipping_distance_km  : jarak toko -> titik antar yang dipakai menghitung
 *  - weight_fee            : berat total x tarif per kg
 *  - packaging_fee         : peti per paket + pembungkus per unit
 *  - service_fee           : pendapatan marketplace (masuk dompet admin)
 *
 * Koordinat tujuan ikut disimpan agar ongkir sebuah pesanan lama tetap bisa
 * dihitung ulang dan diperiksa kembali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('packaging_fee')->default(0)->after('shipping_cost');
            $table->unsignedInteger('weight_fee')->default(0)->after('packaging_fee');
            $table->unsignedInteger('service_fee')->default(0)->after('weight_fee');
            $table->unsignedInteger('weight_gram')->default(0)->after('service_fee');
            $table->decimal('shipping_distance_km', 8, 2)->nullable()->after('weight_gram');
            $table->decimal('shipping_latitude', 10, 7)->nullable()->after('shipping_distance_km');
            $table->decimal('shipping_longitude', 10, 7)->nullable()->after('shipping_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'packaging_fee',
                'weight_fee',
                'service_fee',
                'weight_gram',
                'shipping_distance_km',
                'shipping_latitude',
                'shipping_longitude',
            ]);
        });
    }
};
