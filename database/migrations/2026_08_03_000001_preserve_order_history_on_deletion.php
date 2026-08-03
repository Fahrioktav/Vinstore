<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Temuan K-04: menghapus produk (atau toko) ikut menghapus seluruh riwayat
 * pesanannya karena foreign key orders.product_id dan orders.store_id memakai
 * cascade delete. Riwayat pembelian pembeli, invoice, dan catatan pembukuan
 * hilang permanen — dan seller bisa memakainya untuk menghilangkan bukti
 * pesanan yang bermasalah.
 *
 * Perbaikannya dua bagian:
 *  1. Ubah cascade menjadi nullOnDelete, sehingga baris pesanan tetap ada.
 *  2. Simpan salinan (snapshot) identitas produk/toko di baris pesanan itu
 *     sendiri, supaya pesanan tetap bisa ditampilkan setelah produknya hilang.
 *
 * Snapshot juga memperbaiki masalah lain: sebelumnya invoice menampilkan harga
 * produk saat ini, bukan harga saat transaksi terjadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Untuk pesanan lelang, product_name diisi nama lelangnya.
            $table->string('product_name')->nullable()->after('product_id');
            $table->decimal('product_price', 12, 2)->nullable()->after('product_name');
            $table->string('store_name')->nullable()->after('store_id');
        });

        $this->backfillSnapshots();

        // store_id harus nullable agar bisa di-null-kan saat toko dihapus.
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['store_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
        });
    }

    /**
     * Isi snapshot untuk pesanan yang sudah ada sebelum migration ini.
     * Ditulis lewat query builder agar portabel antara MySQL dan SQLite.
     */
    private function backfillSnapshots(): void
    {
        DB::table('products')->select('id', 'name', 'price')->orderBy('id')
            ->chunk(200, function ($products) {
                foreach ($products as $product) {
                    DB::table('orders')
                        ->where('product_id', $product->id)
                        ->whereNull('product_name')
                        ->update([
                            'product_name' => $product->name,
                            'product_price' => $product->price,
                        ]);
                }
            });

        if (Schema::hasTable('auctions')) {
            DB::table('auctions')->select('id', 'name')->orderBy('id')
                ->chunk(200, function ($auctions) {
                    foreach ($auctions as $auction) {
                        DB::table('orders')
                            ->where('auction_id', $auction->id)
                            ->whereNull('product_name')
                            ->update(['product_name' => $auction->name]);
                    }
                });
        }

        DB::table('stores')->select('id', 'store_name')->orderBy('id')
            ->chunk(200, function ($stores) {
                foreach ($stores as $store) {
                    DB::table('orders')
                        ->where('store_id', $store->id)
                        ->whereNull('store_name')
                        ->update(['store_name' => $store->store_name]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['store_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'product_price', 'store_name']);
        });
    }
};
