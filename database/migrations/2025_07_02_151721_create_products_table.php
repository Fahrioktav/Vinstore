<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id'); // Foreign key ke tabel stores
            $table->string('name');
            $table->integer('stock');
            $table->decimal('price', 12, 2);
            $table->string('category');
            $table->text('description');
            $table->string('image')->nullable(); // simpan nama file / path foto
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
