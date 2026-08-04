<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Selisih harga barter sebelumnya hanya dihitung satu arah: pengaju membayar
 * bila produknya lebih murah. Bila justru produk pengaju yang lebih mahal,
 * selisihnya dibulatkan menjadi nol dan tidak ada pihak yang ditagih.
 *
 * Kolom ini mencatat SIAPA yang wajib membayar selisih, sehingga penerima pun
 * bisa menjadi pembayar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barter_requests', function (Blueprint $table) {
            $table->string('payer_role', 20)->nullable()->after('additional_cash');
        });

        // Sebelum perubahan ini, satu-satunya pembayar yang mungkin adalah pengaju.
        DB::table('barter_requests')
            ->where('additional_cash', '>', 0)
            ->update(['payer_role' => 'requester']);
    }

    public function down(): void
    {
        Schema::table('barter_requests', function (Blueprint $table) {
            $table->dropColumn('payer_role');
        });
    }
};
