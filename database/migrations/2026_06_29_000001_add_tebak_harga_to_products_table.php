<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan jenis penjualan "Tebak Harga" pada produk.
     *
     * Konsep:
     * - sale_type: 'normal' (default) atau 'tebak_harga'.
     * - Untuk produk tebak harga, kolom `price` yang sudah ada berperan sebagai
     *   HARGA ASLI yang hanya diketahui sistem (disembunyikan dari pembeli).
     * - guess_starts_at / guess_ends_at: periode pelaksanaan tebak harga.
     * - guess_status: siklus hidup tebak harga setelah disetujui admin:
     *     scheduled -> active -> ended -> public
     *     (scheduled: menunggu periode mulai,
     *      active: periode berjalan, pembeli boleh menebak,
     *      ended: periode selesai, pemenang ditentukan & punya hak prioritas,
     *      public: hak prioritas berakhir -> jadi penjualan biasa, harga ditampilkan)
     * - guess_winner_id / guess_winning_amount: pemenang & nominal tebakannya.
     * - guess_finished_at: waktu sistem menutup periode & menentukan pemenang.
     * - winner_priority_until: batas waktu hak prioritas pembelian pemenang.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sale_type')->default('normal')->after('price');
            $table->timestamp('guess_starts_at')->nullable()->after('sale_type');
            $table->timestamp('guess_ends_at')->nullable()->after('guess_starts_at');
            $table->string('guess_status')->nullable()->after('guess_ends_at');
            $table->foreignId('guess_winner_id')->nullable()->after('guess_status')->constrained('users')->nullOnDelete();
            $table->decimal('guess_winning_amount', 12, 2)->nullable()->after('guess_winner_id');
            $table->timestamp('guess_finished_at')->nullable()->after('guess_winning_amount');
            $table->timestamp('winner_priority_until')->nullable()->after('guess_finished_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['guess_winner_id']);
            $table->dropColumn([
                'sale_type',
                'guess_starts_at',
                'guess_ends_at',
                'guess_status',
                'guess_winner_id',
                'guess_winning_amount',
                'guess_finished_at',
                'winner_priority_until',
            ]);
        });
    }
};
