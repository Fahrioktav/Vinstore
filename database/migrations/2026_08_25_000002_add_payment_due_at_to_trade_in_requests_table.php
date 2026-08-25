<?php

use App\Models\TradeInRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenggat pembayaran selisih tukar tambah.
 *
 * Sejak tukar tambah disetujui, kedua produk dikunci dari penjualan sampai
 * urusannya tuntas. Tahap "menunggu pembayaran selisih" adalah satu-satunya
 * tahap yang tidak pernah punya batas waktu: pembayar yang berubah pikiran
 * cukup diam, dan dua produk milik dua toko berbeda ikut membeku selamanya
 * (temuan V2-01/V3-02/V3-10).
 *
 * Angkanya 24 jam, disamakan dengan masa berlaku transaksi Snap Midtrans,
 * supaya tombol bayar dan tenggat sistem mati pada saat yang sama.
 *
 * Baris lama yang sedang menunggu pembayaran ikut diberi tenggat — dihitung
 * dari waktu persetujuannya. Yang tenggatnya sudah telanjur lewat akan
 * dibereskan pada jalan pertama `trade-in:expire`, dan itu memang yang
 * diinginkan: mereka justru yang paling lama membeku.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_in_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('trade_in_requests', 'payment_due_at')) {
                $table->timestamp('payment_due_at')->nullable()->after('payment_reference');
            }
        });

        DB::table('trade_in_requests')
            ->where('status', TradeInRequest::STATUS_ACCEPTED)
            ->where('payment_status', TradeInRequest::PAYMENT_PENDING)
            ->whereNull('payment_due_at')
            ->orderBy('id')
            ->each(function ($row) {
                $mulai = $row->responded_at ?? $row->created_at ?? now();

                DB::table('trade_in_requests')
                    ->where('id', $row->id)
                    ->update([
                        'payment_due_at' => \Illuminate\Support\Carbon::parse($mulai)
                            ->addHours(TradeInRequest::PAYMENT_DEADLINE_HOURS),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('trade_in_requests', function (Blueprint $table) {
            if (Schema::hasColumn('trade_in_requests', 'payment_due_at')) {
                $table->dropColumn('payment_due_at');
            }
        });
    }
};
