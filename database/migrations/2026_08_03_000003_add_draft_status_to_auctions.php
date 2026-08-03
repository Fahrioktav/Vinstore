<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Temuan T-07: seller tidak pernah bisa mengedit atau membatalkan lelang yang
 * baru diajukan, karena canEditAuction() membandingkan approval_status dengan
 * nilai 'pending' yang tidak pernah ada di kolom itu.
 *
 * Selain memperbaiki perbandingannya, seller juga perlu bisa MENARIK kembali
 * pengajuannya untuk memperbaiki typo lalu mengajukan ulang. Status 'draft'
 * dipakai untuk lelang yang sudah ditarik dari antrean validator dan sedang
 * diperbaiki oleh seller.
 *
 * ALTER TABLE ... MODIFY COLUMN ... ENUM adalah sintaks khusus MySQL; di SQLite
 * (dipakai untuk testing) kolomnya hanya berupa teks sehingga tidak perlu
 * diubah sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE auctions MODIFY COLUMN approval_status ENUM('draft', 'pending_validator', 'pending_admin', 'approved', 'rejected') DEFAULT 'pending_validator'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Kembalikan lelang draft ke antrean validator sebelum nilai enumnya dihapus.
        DB::table('auctions')
            ->where('approval_status', 'draft')
            ->update(['approval_status' => 'pending_validator']);

        DB::statement("ALTER TABLE auctions MODIFY COLUMN approval_status ENUM('pending_validator', 'pending_admin', 'approved', 'rejected') DEFAULT 'pending_validator'");
    }
};
