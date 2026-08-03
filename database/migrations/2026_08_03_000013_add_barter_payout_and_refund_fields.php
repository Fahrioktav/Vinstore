<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi barter agar uang selisihnya punya jalur keluar yang jelas.
 *
 * - barter_requests.payout_released_at: penanda selisih uang sudah ditransfer
 *   ke responder, sekaligus pengunci agar tidak dicairkan dua kali.
 * - barter_requests.payment_status: nilai 'refunded' ditambahkan ke enum untuk
 *   barter gagal yang uangnya dikembalikan ke pengaju.
 * - refund_requests.barter_request_id: sanggahan kini bisa menunjuk barter yang
 *   gagal, bukan hanya pesanan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barter_requests', function (Blueprint $table) {
            $table->timestamp('payout_released_at')->nullable()->after('completed_at');
        });

        $this->widenPaymentStatus(['not_required', 'pending', 'paid', 'failed', 'expired', 'refunded']);

        Schema::table('refund_requests', function (Blueprint $table) {
            $table->foreignId('barter_request_id')
                ->nullable()
                ->after('order_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        $this->setRefundOrderIdNullable(true);
    }

    /**
     * ENUM hanya ada di MySQL. Di SQLite (dipakai test suite) kolomnya berupa
     * varchar dengan check constraint, jadi cukup dijadikan string biasa.
     */
    private function widenPaymentStatus(array $values): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('barter_requests', function (Blueprint $table) {
                $table->string('payment_status')->default('not_required')->change();
            });

            return;
        }

        $list = collect($values)->map(fn ($value) => "'".$value."'")->implode(',');

        DB::statement(
            "ALTER TABLE barter_requests MODIFY payment_status
             ENUM({$list}) NOT NULL DEFAULT 'not_required'"
        );
    }

    private function setRefundOrderIdNullable(bool $nullable): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE refund_requests MODIFY order_id BIGINT UNSIGNED '
                .($nullable ? 'NULL' : 'NOT NULL')
            );

            return;
        }

        Schema::table('refund_requests', function (Blueprint $table) use ($nullable) {
            $table->unsignedBigInteger('order_id')->nullable($nullable)->change();
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropForeign(['barter_request_id']);
            $table->dropColumn('barter_request_id');
        });

        $this->setRefundOrderIdNullable(false);
        $this->widenPaymentStatus(['not_required', 'pending', 'paid', 'failed', 'expired']);

        Schema::table('barter_requests', function (Blueprint $table) {
            $table->dropColumn('payout_released_at');
        });
    }
};
