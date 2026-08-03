<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pencairan dana kini punya dua sumber: pesanan jual-beli, dan selisih uang
 * (additional_cash) pada barter.
 *
 * Tabelnya digeneralisasi ketimbang dibuat kembar: order_id menjadi nullable
 * dan barter_request_id ditambahkan. Tepat satu di antara keduanya terisi.
 * Namanya ikut diganti agar tidak menyesatkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('order_payout_requests', 'payout_requests');

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->foreignId('barter_request_id')
                ->nullable()
                ->after('order_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        $this->makeOrderIdNullable();
    }

    /**
     * Di MySQL dipakai SQL mentah agar foreign key-nya tidak ikut dijatuhkan
     * oleh Blueprint::change(). SQLite (dipakai test suite) tidak mengenal
     * sintaks MODIFY, jadi di sana schema builder yang dipakai.
     */
    private function makeOrderIdNullable(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payout_requests MODIFY order_id BIGINT UNSIGNED NULL');

            return;
        }

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropForeign(['barter_request_id']);
            $table->dropColumn('barter_request_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payout_requests MODIFY order_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('payout_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('order_id')->nullable(false)->change();
            });
        }

        Schema::rename('payout_requests', 'order_payout_requests');
    }
};
