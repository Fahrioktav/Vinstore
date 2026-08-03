<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Titik awal tenggat pengiriman barter.
 *
 * Barter yang masuk tahap saling kirim tidak punya batas waktu sama sekali:
 * kalau salah satu seller tidak pernah mengisi resi, barternya menggantung
 * selamanya dan kedua produk tetap terkunci dari penjualan.
 *
 * Kolom ini mencatat kapan tahap kirim dimulai, sehingga tenggatnya bisa
 * dihitung (lihat BarterRequest::SHIPPING_DEADLINE_DAYS) dan pihak yang
 * dirugikan bisa melaporkan barter yang macet ke admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barter_requests', function (Blueprint $table) {
            $table->timestamp('shipping_started_at')->nullable()->after('payment_status');
        });

        // Barter yang sudah berjalan sebelum kolom ini ada tetap perlu titik
        // awal, kalau tidak tenggatnya tidak pernah terlampaui dan barter lama
        // yang macet tidak bisa dilaporkan.
        DB::table('barter_requests')
            ->whereNull('shipping_started_at')
            ->whereIn('status', ['shipping', 'completed'])
            ->update([
                'shipping_started_at' => DB::raw('COALESCE(paid_at, responded_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('barter_requests', function (Blueprint $table) {
            $table->dropColumn('shipping_started_at');
        });
    }
};
