<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan pencairan dana per pesanan.
 *
 * Sebelumnya dana pesanan cair otomatis ke saldo toko — saat pembeli menekan
 * konfirmasi terima, atau otomatis 3 hari setelah ditandai Delivered. Sekarang
 * seller harus mengajukan pencairan untuk tiap pesanan dan admin yang
 * menyetujui sambil mengunggah bukti transfer.
 *
 * Data bank ikut disimpan di sini karena dana langsung ditransfer ke rekening
 * seller, tidak singgah dulu di saldo toko.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('bank_name', 100);
            $table->string('account_number', 100);
            $table->string('account_holder', 150);
            $table->string('status')->default('pending');
            $table->text('admin_note')->nullable();
            $table->string('transfer_proof')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payout_requests');
    }
};
