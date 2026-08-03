<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temuan K-06: dana escrow dicairkan ke saldo seller begitu SELLER sendiri
 * menandai pesanan "Delivered" — tanpa konfirmasi apa pun dari pembeli. Seller
 * dapat menerima pesanan, menandainya terkirim tanpa mengirim barang, lalu
 * mengajukan pencairan.
 *
 * Perbaikannya:
 *  - delivered_at  : kapan seller menandai barang dikirim/sampai. Jadi titik
 *                    awal masa sanggah pembeli.
 *  - completed_at  : kapan pembeli mengonfirmasi barang benar-benar diterima.
 *                    Hanya status "Completed" yang melepas dana.
 *  - transfer_proof / transferred_at pada withdrawal_requests: bukti bahwa
 *    admin sudah benar-benar mentransfer dana ke rekening yang diajukan seller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('seller_released_at');
            $table->timestamp('completed_at')->nullable()->after('delivered_at');
        });

        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->string('transfer_proof')->nullable()->after('admin_note');
            $table->timestamp('transferred_at')->nullable()->after('transfer_proof');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivered_at', 'completed_at']);
        });

        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropColumn(['transfer_proof', 'transferred_at']);
        });
    }
};
