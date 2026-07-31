<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan field payment untuk barter yang memiliki selisih harga (additional_cash > 0).
     * Jika barter disetujui dan ada additional_cash, requester harus membayar melalui Midtrans.
     */
    public function up(): void
    {
        Schema::table('barter_requests', function (Blueprint $table) {
            // Status pembayaran untuk additional_cash
            $table->enum('payment_status', [
                'not_required', // tidak ada additional_cash
                'pending',      // menunggu pembayaran
                'paid',         // sudah dibayar
                'failed',       // pembayaran gagal
                'expired'       // pembayaran kadaluarsa
            ])->default('not_required')->after('status');

            // Reference ID untuk tracking payment
            $table->string('payment_reference')->nullable()->after('payment_status');
            
            // Snap token dari Midtrans
            $table->string('snap_token')->nullable()->after('payment_reference');
            
            // Transaction ID dari Midtrans
            $table->string('midtrans_transaction_id')->nullable()->after('snap_token');
            
            // Timestamp kapan payment selesai
            $table->timestamp('paid_at')->nullable()->after('midtrans_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barter_requests', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'payment_reference',
                'snap_token',
                'midtrans_transaction_id',
                'paid_at',
            ]);
        });
    }
};
