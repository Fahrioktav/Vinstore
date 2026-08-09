<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berat produk dalam GRAM.
 *
 * Dibutuhkan biaya berat di checkout (berat x tarif per kg). Produk lama tidak
 * punya berat, jadi default 1000 gram dipakai agar tagihannya tetap wajar
 * sampai seller memperbaruinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('weight')->default(1000)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};
