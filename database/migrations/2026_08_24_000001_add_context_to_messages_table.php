<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konteks barang/pesanan pada pesan percakapan pembeli-penjual.
 *
 * Satu percakapan berumur panjang: pembeli yang sama menanyakan guci hari ini
 * dan pesanan yang berbeda pekan depan, semuanya di utas yang sama karena
 * `conversations` unik per (store_id, buyer_id). Tanpa penanda ini seller
 * membaca "apakah masih ada?" tanpa tahu barang mana yang dimaksud.
 *
 * Karena itu konteksnya melekat pada PESAN, bukan pada percakapan.
 *
 * Keduanya `nullOnDelete`: produk yang dihapus atau pesanan yang hilang tidak
 * boleh ikut menghapus riwayat percakapannya — yang hilang cukup kartu
 * konteksnya saja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('sender_id')
                ->constrained('products')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->after('product_id')
                ->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
