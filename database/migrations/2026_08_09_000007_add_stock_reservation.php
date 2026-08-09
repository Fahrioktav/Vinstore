<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pisahkan STOK dari RESERVASI.
 *
 * Sebelumnya checkout langsung memotong `products.stock`. Akibatnya produk yang
 * baru diklik checkout — dan belum tentu dibayar — langsung hilang dari halaman
 * produk, karena daftar produk menyaring `stock > 0`. Untuk barang antik yang
 * stoknya kerap hanya 1, satu orang yang membuka Snap lalu menutupnya membuat
 * barang itu lenyap dari etalase.
 *
 * Sekarang:
 *   - `stock`          = jumlah barang yang benar-benar dimiliki seller.
 *                        Baru berkurang ketika pembayaran LUNAS.
 *   - `reserved_stock` = jumlah yang sedang ditahan pesanan yang belum lunas.
 *
 * Yang boleh dibeli = stock - reserved_stock. Produk tetap TAMPIL selama
 * `stock > 0`, jadi barangnya tidak hilang dari etalase hanya karena ada orang
 * lain yang sedang di tengah pembayaran — ia cuma belum bisa dibeli sampai
 * reservasi itu tuntas atau kedaluwarsa.
 *
 * Menahan, bukan sekadar membiarkan, tetap penting: tanpa reservasi tiga orang
 * bisa sama-sama membayar guci terakhir dan dua di antaranya harus direfund.
 *
 * Penanda pada pesanan:
 *   - stock_committed_at : reservasi sudah menjadi pengurangan stok (lunas)
 *   - stock_restored_at  : reservasi dilepas tanpa jadi dibeli (batal/kedaluwarsa)
 * Keduanya membuat tiap transisi hanya bisa terjadi sekali, walau webhook
 * Midtrans datang berulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('reserved_stock')->default(0)->after('stock');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('stock_committed_at')->nullable()->after('stock_restored_at');
        });

        $this->backfillExistingOrders();
    }

    /**
     * Data lama dibuat dengan aturan lain: stoknya sudah terlanjur dipotong di
     * saat checkout. Menandai pesanan lunas sebagai "sudah dipotong" mencegah
     * stoknya dipotong untuk kedua kalinya bila webhook lama datang lagi.
     *
     * Pesanan lama yang belum lunas sengaja TIDAK dijadikan reservasi: stoknya
     * sudah berkurang sejak dulu, dan menambahkan reservasi di atasnya justru
     * memotong dua kali. Reservasi hanya berlaku untuk pesanan baru.
     */
    private function backfillExistingOrders(): void
    {
        DB::table('orders')
            ->where('payment_status', 'paid')
            ->whereNull('stock_committed_at')
            ->update(['stock_committed_at' => DB::raw('COALESCE(paid_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('stock_committed_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('reserved_stock');
        });
    }
};
