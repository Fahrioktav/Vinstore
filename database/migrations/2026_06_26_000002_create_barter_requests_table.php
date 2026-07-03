<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel pengajuan barter antar seller (seller-to-seller).
     * Alur status:
     *   pending  -> menunggu persetujuan seller pemilik produk yang diminta
     *   accepted -> disetujui kedua belah pihak, kepemilikan produk telah ditukar
     *   rejected -> ditolak oleh pemilik produk yang diminta
     *   cancelled-> dibatalkan oleh pengaju, atau otomatis batal karena produk sudah tertukar
     */
    public function up(): void
    {
        Schema::create('barter_requests', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();

            // Seller pengaju barter (yang menawarkan produknya)
            $table->foreignId('requester_store_id')->constrained('stores')->cascadeOnDelete();
            // Seller pemilik produk yang ingin diperoleh
            $table->foreignId('responder_store_id')->constrained('stores')->cascadeOnDelete();

            // Produk yang ditawarkan oleh pengaju
            $table->foreignId('offered_product_id')->constrained('products')->cascadeOnDelete();
            // Produk milik responder yang ingin ditukar
            $table->foreignId('requested_product_id')->constrained('products')->cascadeOnDelete();

            // Tambahan uang opsional dari pengaju untuk menyeimbangkan nilai barang
            $table->decimal('additional_cash', 12, 2)->default(0);
            $table->text('note')->nullable();

            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])->default('pending');
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barter_requests');
    }
};
