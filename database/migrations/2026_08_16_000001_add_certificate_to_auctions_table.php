<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sertifikat keaslian barang lelang.
 *
 * Produk biasa sudah lama bisa dilampiri sertifikat, lelang belum — padahal
 * justru pada lelanglah bukti keaslian paling menentukan: penawar mengangkat
 * harga tanpa pernah memegang barangnya, dan validator memutuskan kelayakannya
 * hanya dari foto dan deskripsi.
 *
 * Kolomnya disamakan dengan `products.certificate` (jalur berkas pada disk
 * publik) supaya penampil yang sudah ada bisa dipakai apa adanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->string('certificate')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('certificate');
        });
    }
};
