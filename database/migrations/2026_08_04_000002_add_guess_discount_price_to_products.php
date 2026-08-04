<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tebak harga sebelumnya hanya punya SATU harga: `price` disembunyikan selama
 * periode tebak, dan tebakan terdekat selalu menang berapa pun melesetnya.
 *
 * Niat fiturnya berbeda: produk ditawarkan dengan harga DISKON yang harus
 * ditebak pembeli. Yang menebak cukup dekat memenangkan hak membeli di harga
 * diskon itu; bila tidak ada yang cukup dekat, produk dijual di harga normal.
 *
 * `price` tetap menjadi harga normal — tidak ada perubahan makna bagi produk
 * non-tebak-harga, keranjang, pesanan, maupun laporan pendapatan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('guess_discount_price', 12, 2)
                ->nullable()
                ->after('sale_type');
        });

        // Sengaja TIDAK di-backfill. Dua produk tebak harga yang ada sudah
        // berstatus 'public' tanpa tebakan aktif, dan mengisi harga diskon
        // dengan harga normal justru mengarang diskon yang tidak pernah ada.
        // Kolom null berarti "diskon belum dikonfigurasi": tidak ada pemenang
        // yang mungkin, dan harga yang berlaku tetap harga normal.
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('guess_discount_price');
        });
    }
};
