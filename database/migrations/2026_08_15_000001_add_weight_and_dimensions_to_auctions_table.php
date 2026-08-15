<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berat dan dimensi barang lelang.
 *
 * Sampai sekarang pesanan lelang tidak punya komponen biaya sama sekali:
 * pemenang hanya ditagih harga menangnya, dan ongkirnya entah ditanggung siapa.
 * Penyebabnya sederhana — lelang tidak pernah punya kolom berat dan dimensi,
 * sehingga tidak ada yang bisa dihitung.
 *
 * Kolomnya disamakan persis dengan produk biasa (gram dan sentimeter, dimensi
 * boleh kosong) supaya App\Services\ShippingCostService dapat menghitung
 * keduanya dengan jalur yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->unsignedInteger('weight')->default(1000)->after('image');
            $table->unsignedInteger('length')->nullable()->after('weight');
            $table->unsignedInteger('width')->nullable()->after('length');
            $table->unsignedInteger('height')->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['weight', 'length', 'width', 'height']);
        });
    }
};
