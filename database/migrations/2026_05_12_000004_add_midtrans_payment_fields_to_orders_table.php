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
            $table->string('payment_reference')->nullable()->after('status');
            $table->string('payment_status')->default('unpaid')->after('payment_reference');
            $table->string('payment_method')->nullable()->after('payment_status');
            $table->string('midtrans_transaction_id')->nullable()->after('payment_method');
            $table->string('snap_token')->nullable()->after('midtrans_transaction_id');
            $table->string('snap_redirect_url')->nullable()->after('snap_token');
            $table->timestamp('paid_at')->nullable()->after('snap_redirect_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_reference',
                'payment_status',
                'payment_method',
                'midtrans_transaction_id',
                'snap_token',
                'snap_redirect_url',
                'paid_at',
            ]);
        });
    }
};
