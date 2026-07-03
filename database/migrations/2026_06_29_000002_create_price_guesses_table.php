<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel tebakan harga dari pembeli.
     * - Setiap pembeli hanya boleh menebak SATU kali per produk (unique product_id+user_id).
     * - Tebakan bersifat final / tidak dapat diubah (tidak ada endpoint update).
     */
    public function up(): void
    {
        Schema::create('price_guesses', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['product_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_guesses');
    }
};
