<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda bahwa stok pesanan yang sudah dipotong telah dikembalikan ke seller.
 *
 * Dipakai ketika refund disetujui atas pesanan yang barangnya belum pernah
 * berpindah. Tanpa penanda tersendiri, satu-satunya cara mengetahui apakah
 * stoknya sudah dikembalikan adalah menebak dari status — dan tebakan itu akan
 * salah begitu ada pengembalian kedua yang datang atas pesanan yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'stock_returned_at')) {
                $table->timestamp('stock_returned_at')->nullable()->after('stock_committed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'stock_returned_at')) {
                $table->dropColumn('stock_returned_at');
            }
        });
    }
};
