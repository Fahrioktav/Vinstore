<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Temuan V3-01 & V3-04: seluruh riwayat barter dan jejak uangnya menggantung
 * pada ON DELETE CASCADE.
 *
 * Rantainya berjalan otomatis di level database:
 *
 *   hapus produk  -> barter_requests ikut terhapus
 *                 -> payout_requests barter itu ikut terhapus
 *                 -> refund_requests barter itu ikut terhapus
 *   hapus toko    -> hal yang sama, plus seluruh riwayat pencairan tokonya
 *                    termasuk yang uangnya SUDAH ditransfer.
 *
 * Artinya seorang seller bisa menghapus produknya sendiri dan bersamanya
 * lenyap pula bukti bahwa pihak lawan sudah membayar dan mengirimkan barang.
 *
 * Perbaikannya mengikuti pola yang sudah dipakai untuk pesanan (temuan K-04,
 * migrasi 2026_08_03_000001):
 *
 *   1. Ganti cascade menjadi nullOnDelete, sehingga barisnya tetap ada.
 *   2. Simpan snapshot identitas produk/toko di baris barter itu sendiri,
 *      supaya riwayatnya tetap terbaca setelah produknya hilang.
 *
 * Snapshot juga memperbaiki masalah kedua: kartu barter lama menampilkan nama
 * produk SAAT INI, bukan nama pada saat barter disepakati.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addSnapshotColumns();
        $this->backfillSnapshots();
        $this->makeColumnsNullable();
        $this->replaceForeignKeys();
    }

    /**
     * Penambahan kolom dijaga hasColumn() agar migrasi ini aman dijalankan
     * ulang. Percobaan pertama di MySQL gagal di tengah jalan — nama foreign
     * key `payout_requests` ternyata masih warisan dari nama tabel lamanya —
     * sehingga sebagian kolom sudah terlanjur dibuat.
     */
    private function addSnapshotColumns(): void
    {
        $barterColumns = [
            'offered_product_name' => 'offered_product_id',
            'requested_product_name' => 'requested_product_id',
            'requester_store_name' => 'requester_store_id',
            'responder_store_name' => 'responder_store_id',
        ];

        foreach ($barterColumns as $column => $after) {
            if (! Schema::hasColumn('barter_requests', $column)) {
                Schema::table('barter_requests', function (Blueprint $table) use ($column, $after) {
                    $table->string($column)->nullable()->after($after);
                });
            }
        }

        if (! Schema::hasColumn('payout_requests', 'store_name')) {
            Schema::table('payout_requests', function (Blueprint $table) {
                $table->string('store_name')->nullable()->after('store_id');
            });
        }
    }

    /**
     * Isi snapshot untuk baris yang sudah ada sebelum migrasi ini.
     * Ditulis lewat query builder agar portabel antara MySQL dan SQLite.
     */
    private function backfillSnapshots(): void
    {
        DB::table('products')->select('id', 'name')->orderBy('id')
            ->chunk(200, function ($products) {
                foreach ($products as $product) {
                    DB::table('barter_requests')
                        ->where('offered_product_id', $product->id)
                        ->whereNull('offered_product_name')
                        ->update(['offered_product_name' => $product->name]);

                    DB::table('barter_requests')
                        ->where('requested_product_id', $product->id)
                        ->whereNull('requested_product_name')
                        ->update(['requested_product_name' => $product->name]);
                }
            });

        DB::table('stores')->select('id', 'store_name')->orderBy('id')
            ->chunk(200, function ($stores) {
                foreach ($stores as $store) {
                    DB::table('barter_requests')
                        ->where('requester_store_id', $store->id)
                        ->whereNull('requester_store_name')
                        ->update(['requester_store_name' => $store->store_name]);

                    DB::table('barter_requests')
                        ->where('responder_store_id', $store->id)
                        ->whereNull('responder_store_name')
                        ->update(['responder_store_name' => $store->store_name]);

                    DB::table('payout_requests')
                        ->where('store_id', $store->id)
                        ->whereNull('store_name')
                        ->update(['store_name' => $store->store_name]);
                }
            });
    }

    /**
     * Kolom relasi harus nullable dulu, kalau tidak nullOnDelete mustahil
     * dijalankan database saat barisnya benar-benar dihapus.
     */
    private function makeColumnsNullable(): void
    {
        Schema::table('barter_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('requester_store_id')->nullable()->change();
            $table->unsignedBigInteger('responder_store_id')->nullable()->change();
            $table->unsignedBigInteger('offered_product_id')->nullable()->change();
            $table->unsignedBigInteger('requested_product_id')->nullable()->change();
        });

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->change();
        });
    }

    /**
     * Jatuhkan foreign key berdasarkan KOLOMnya, bukan menebak namanya.
     *
     * `payout_requests` dulu bernama `order_payout_requests` dan MySQL
     * mempertahankan nama constraint lamanya saat tabel di-rename. Menebak nama
     * dengan pola bawaan Laravel karena itu gagal — nama sebenarnya harus
     * dibaca dari information_schema.
     */
    private function dropForeignByColumn(string $table, string $column): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });

            return;
        }

        $names = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME');

        foreach ($names as $name) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
        }
    }

    private function replaceForeignKeys(): void
    {
        foreach (['requester_store_id', 'responder_store_id', 'offered_product_id', 'requested_product_id'] as $column) {
            $this->dropForeignByColumn('barter_requests', $column);
        }

        Schema::table('barter_requests', function (Blueprint $table) {
            $table->foreign('requester_store_id')->references('id')->on('stores')->nullOnDelete();
            $table->foreign('responder_store_id')->references('id')->on('stores')->nullOnDelete();
            $table->foreign('offered_product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('requested_product_id')->references('id')->on('products')->nullOnDelete();
        });

        // Catatan pencairan adalah bukti uang benar-benar keluar. Ia tidak
        // boleh lenyap hanya karena pesanan, barter, atau tokonya dihapus.
        foreach (['order_id', 'barter_request_id', 'store_id'] as $column) {
            $this->dropForeignByColumn('payout_requests', $column);
        }

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('barter_request_id')->references('id')->on('barter_requests')->nullOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
        });

        foreach (['order_id', 'barter_request_id'] as $column) {
            $this->dropForeignByColumn('refund_requests', $column);
        }

        Schema::table('refund_requests', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('barter_request_id')->references('id')->on('barter_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['barter_request_id']);
        });

        Schema::table('refund_requests', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('barter_request_id')->references('id')->on('barter_requests')->cascadeOnDelete();
        });

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['barter_request_id']);
            $table->dropForeign(['store_id']);
        });

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('barter_request_id')->references('id')->on('barter_requests')->cascadeOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('barter_requests', function (Blueprint $table) {
            $table->dropForeign(['requester_store_id']);
            $table->dropForeign(['responder_store_id']);
            $table->dropForeign(['offered_product_id']);
            $table->dropForeign(['requested_product_id']);
        });

        Schema::table('barter_requests', function (Blueprint $table) {
            $table->foreign('requester_store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('responder_store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('offered_product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('requested_product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropColumn('store_name');
        });

        Schema::table('barter_requests', function (Blueprint $table) {
            $table->dropColumn([
                'offered_product_name',
                'requested_product_name',
                'requester_store_name',
                'responder_store_name',
            ]);
        });
    }
};
