<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel users (pembeli)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Relasi ke tabel products (barang yang dipesan)
            $table->foreignId('product_id')->constrained()->onDelete('cascade');

            $table->integer('quantity')->default(1);
            $table->decimal('price', 12, 2); // harga total = quantity * harga satuan
            $table->enum('status', ['Waiting', 'On The Way', 'Delivered', 'Cancelled'])->default('Waiting');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
