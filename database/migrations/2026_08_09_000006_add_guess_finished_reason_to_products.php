<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alasan sebuah sesi tebak harga berakhir.
 *
 * Sesi kini bisa berakhir LEBIH CEPAT dari jadwalnya: begitu ada yang menebak
 * tepat pada angkanya, tidak ada gunanya menunggu — pemenangnya sudah pasti dan
 * tidak mungkin dikalahkan. Tanpa kolom ini, halaman produk tidak bisa
 * membedakan "periodenya habis" dari "sudah ada yang menebak tepat", padahal
 * yang kedua perlu diumumkan.
 *
 * Nilai:
 *   exact_guess  - ada tebakan persis; sesi ditutup seketika
 *   period_ended - periode habis dan ada pemenang terdekat dalam ambang
 *   no_winner    - periode habis tanpa tebakan yang cukup dekat
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('guess_finished_reason')->nullable()->after('guess_finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('guess_finished_reason');
        });
    }
};
