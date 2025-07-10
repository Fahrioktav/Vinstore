<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // foreign key ke users
            $table->string('store_name');
            $table->string('category');         // Tambahkan kategori toko
            $table->text('description');        // Tambahkan deskripsi
            $table->string('location');         // Ganti dari address → location
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
