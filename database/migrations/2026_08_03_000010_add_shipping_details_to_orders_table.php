<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan detail pengiriman pada pesanan.
 *
 * Sebelumnya alamat pengiriman diisi pembeli di halaman checkout, divalidasi,
 * lalu dibuang begitu saja — tidak pernah masuk ke tabel orders. Akibatnya
 * seller tidak punya informasi ke mana barang harus dikirim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('shipping_address')->nullable()->after('tracking_number');
            $table->string('shipping_method')->nullable()->after('shipping_address');
            $table->unsignedInteger('shipping_cost')->default(0)->after('shipping_method');
            $table->text('notes')->nullable()->after('shipping_cost');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_address', 'shipping_method', 'shipping_cost', 'notes']);
        });
    }
};
