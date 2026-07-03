<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan:
     * - is_barterable: penanda apakah produk boleh diajukan barter oleh seller lain.
     * - images: galeri foto tambahan (tampak depan, samping, kondisi) dalam bentuk JSON array path.
     * - video: path video singkat kondisi barang (opsional, dibatasi ukurannya di controller).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_barterable')->default(false)->after('certificate');
            $table->json('images')->nullable()->after('image');
            $table->string('video')->nullable()->after('images');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_barterable', 'images', 'video']);
        });
    }
};
