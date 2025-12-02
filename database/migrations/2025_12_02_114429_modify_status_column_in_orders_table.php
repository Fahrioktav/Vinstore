<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Ubah kolom status menjadi string dengan panjang yang cukup
            $table->string('status', 50)->default('Waiting')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Kembalikan ke enum jika perlu rollback
            $table->enum('status', ['Waiting', 'Processing', 'On The Way', 'Delivered', 'Cancelled'])->default('Waiting')->change();
        });
    }
};
