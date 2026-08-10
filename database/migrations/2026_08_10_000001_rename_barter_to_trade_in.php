<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penggantian istilah "barter" menjadi "tukar tambah" (trade-in).
 *
 * Mekanismenya tidak berubah sedikit pun — yang diganti hanya penamaannya.
 * Alasannya: fitur ini bukan barter murni. Ketika kedua produk berbeda harga,
 * pihak yang barangnya lebih murah membayar selisihnya lewat Midtrans, dan
 * pertukaran baru terjadi setelah pembayaran itu settle. Secara istilah, tukar
 * menukar barang yang disertai penambahan uang adalah TUKAR TAMBAH, bukan
 * barter.
 *
 * Yang di-rename:
 *   barter_requests                  -> trade_in_requests
 *   products.is_barterable           -> products.is_trade_in_enabled
 *   products.locked_for_barter_id    -> products.locked_for_trade_in_id
 *   payout_requests.barter_request_id-> payout_requests.trade_in_request_id
 *   refund_requests.barter_request_id-> refund_requests.trade_in_request_id
 *
 * Prefix public_id sengaja TIDAK diubah dan tetap `BRT`. Nilainya sudah
 * tersimpan di baris yang ada dan ditampilkan ke pengguna; menggantinya hanya
 * akan membuat data lama dan data baru berbeda pola tanpa manfaat apa pun.
 *
 * Foreign key dijatuhkan lalu dipasang ulang khusus di MySQL supaya nama
 * constraint-nya ikut mengikuti nama kolom yang baru. Kalau tidak, migrasi
 * berikutnya yang memanggil dropForeign(['trade_in_request_id']) akan menebak
 * nama yang tidak pernah ada — persis masalah yang dulu terjadi waktu
 * order_payout_requests di-rename (lihat migrasi 2026_08_09_000004).
 *
 * SQLite (dipakai test suite) tidak bisa menjatuhkan foreign key, tapi juga
 * tidak perlu: ALTER TABLE ... RENAME miliknya memperbarui sendiri klausa
 * REFERENCES di tabel lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        if ($isMysql) {
            $this->dropForeignKeys([
                'products' => ['locked_for_barter_id'],
                'payout_requests' => ['barter_request_id'],
                'refund_requests' => ['barter_request_id'],
                'barter_requests' => [
                    'requester_store_id',
                    'responder_store_id',
                    'offered_product_id',
                    'requested_product_id',
                ],
            ]);
        }

        Schema::rename('barter_requests', 'trade_in_requests');
        $this->renameIndex('trade_in_requests', 'barter_requests_public_id_unique', 'trade_in_requests_public_id_unique');

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('is_barterable', 'is_trade_in_enabled');
            $table->renameColumn('locked_for_barter_id', 'locked_for_trade_in_id');
        });

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->renameColumn('barter_request_id', 'trade_in_request_id');
        });

        Schema::table('refund_requests', function (Blueprint $table) {
            $table->renameColumn('barter_request_id', 'trade_in_request_id');
        });

        if ($isMysql) {
            $this->addForeignKeys();
        }
    }

    public function down(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        if ($isMysql) {
            $this->dropForeignKeys([
                'products' => ['locked_for_trade_in_id'],
                'payout_requests' => ['trade_in_request_id'],
                'refund_requests' => ['trade_in_request_id'],
                'trade_in_requests' => [
                    'requester_store_id',
                    'responder_store_id',
                    'offered_product_id',
                    'requested_product_id',
                ],
            ]);
        }

        Schema::rename('trade_in_requests', 'barter_requests');
        $this->renameIndex('barter_requests', 'trade_in_requests_public_id_unique', 'barter_requests_public_id_unique');

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('is_trade_in_enabled', 'is_barterable');
            $table->renameColumn('locked_for_trade_in_id', 'locked_for_barter_id');
        });

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->renameColumn('trade_in_request_id', 'barter_request_id');
        });

        Schema::table('refund_requests', function (Blueprint $table) {
            $table->renameColumn('trade_in_request_id', 'barter_request_id');
        });

        if ($isMysql) {
            $this->addLegacyForeignKeys();
        }
    }

    /**
     * RENAME TABLE membawa serta nama index lamanya. Dibiarkan begitu, migrasi
     * berikutnya yang memanggil dropUnique(['public_id']) akan menebak nama
     * baru yang tidak pernah ada. Dijaga hasIndex() supaya aman dijalankan
     * ulang dan tidak meledak bila indexnya sudah pernah diganti.
     */
    private function renameIndex(string $table, string $from, string $to): void
    {
        if (! Schema::hasIndex($table, $from)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($from, $to) {
            $blueprint->renameIndex($from, $to);
        });
    }

    /**
     * Nama constraint dibaca dari information_schema, bukan ditebak dari pola
     * bawaan Laravel — tabel-tabel ini sudah pernah di-rename sebelumnya
     * sehingga sebagian constraint masih memakai nama tabel lamanya.
     *
     * @param  array<string, list<string>>  $tables
     */
    private function dropForeignKeys(array $tables): void
    {
        foreach ($tables as $table => $columns) {
            foreach ($columns as $column) {
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
        }
    }

    /**
     * Semua nullOnDelete, mengikuti keputusan migrasi 2026_08_09_000004:
     * riwayat tukar tambah dan jejak uangnya tidak boleh ikut lenyap ketika
     * produk atau tokonya dihapus.
     */
    private function addForeignKeys(): void
    {
        Schema::table('trade_in_requests', function (Blueprint $table) {
            $table->foreign('requester_store_id')->references('id')->on('stores')->nullOnDelete();
            $table->foreign('responder_store_id')->references('id')->on('stores')->nullOnDelete();
            $table->foreign('offered_product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('requested_product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('locked_for_trade_in_id')->references('id')->on('trade_in_requests')->nullOnDelete();
        });

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->foreign('trade_in_request_id')->references('id')->on('trade_in_requests')->nullOnDelete();
        });

        Schema::table('refund_requests', function (Blueprint $table) {
            $table->foreign('trade_in_request_id')->references('id')->on('trade_in_requests')->nullOnDelete();
        });
    }

    private function addLegacyForeignKeys(): void
    {
        Schema::table('barter_requests', function (Blueprint $table) {
            $table->foreign('requester_store_id')->references('id')->on('stores')->nullOnDelete();
            $table->foreign('responder_store_id')->references('id')->on('stores')->nullOnDelete();
            $table->foreign('offered_product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('requested_product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('locked_for_barter_id')->references('id')->on('barter_requests')->nullOnDelete();
        });

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->foreign('barter_request_id')->references('id')->on('barter_requests')->nullOnDelete();
        });

        Schema::table('refund_requests', function (Blueprint $table) {
            $table->foreign('barter_request_id')->references('id')->on('barter_requests')->nullOnDelete();
        });
    }
};
