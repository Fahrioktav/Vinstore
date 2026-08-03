<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ALTER TABLE ... MODIFY COLUMN ... ENUM adalah sintaks khusus MySQL.
        // SQLite (dipakai oleh phpunit.xml) tidak mengenalnya dan tidak punya
        // tipe ENUM sama sekali, jadi langkah ini dilewati di luar MySQL.
        $isMySql = DB::connection()->getDriverName() === 'mysql';

        if ($isMySql) {
            // Tambah nilai sementara di enum untuk transisi
            DB::statement("ALTER TABLE auctions MODIFY COLUMN approval_status ENUM('pending', 'pending_validator', 'pending_admin', 'approved', 'rejected') DEFAULT 'pending'");
        }

        // Update data yang sudah ada: 'pending' → 'pending_validator'
        DB::table('auctions')
            ->where('approval_status', 'pending')
            ->update(['approval_status' => 'pending_validator']);

        if ($isMySql) {
            // Sekarang hapus 'pending' dari enum
            DB::statement("ALTER TABLE auctions MODIFY COLUMN approval_status ENUM('pending_validator', 'pending_admin', 'approved', 'rejected') DEFAULT 'pending_validator'");
        }

        Schema::table('auctions', function (Blueprint $table) {
            // Tambah field untuk validator
            $table->timestamp('validated_at')->nullable()->after('approved_by');
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn(['validated_at', 'validated_by']);
        });

        // Kembalikan enum approval_status ke nilai lama (khusus MySQL, lihat up())
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE auctions MODIFY COLUMN approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
        }

        // Update data kembali
        DB::table('auctions')
            ->where('approval_status', 'pending_validator')
            ->update(['approval_status' => 'pending']);
    }
};
