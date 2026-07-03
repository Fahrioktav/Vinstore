<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan tahap validasi oleh "validator barang antik" sebelum admin.
     * Alur status produk:
     *   pending_validator -> pending_admin -> approved
     *   (atau rejected pada salah satu tahap)
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('validated_at')->nullable()->after('rejection_reason');
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete();
        });

        // Migrasikan data lama ke skema status baru.
        // Produk yang masih 'pending' berarti belum divalidasi siapapun -> pending_validator.
        DB::table('products')->where('approval_status', 'pending')->update([
            'approval_status' => 'pending_validator',
        ]);

        // Produk yang sudah 'approved' dianggap sudah lolos kedua tahap (data legacy).
        // Samakan info validasi dengan info approval agar konsisten.
        DB::table('products')->where('approval_status', 'approved')->update([
            'validated_at' => DB::raw('approved_at'),
            'validated_by' => DB::raw('approved_by'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan status ke skema lama sebelum menghapus kolom.
        DB::table('products')->where('approval_status', 'pending_validator')->update([
            'approval_status' => 'pending',
        ]);
        DB::table('products')->where('approval_status', 'pending_admin')->update([
            'approval_status' => 'pending',
        ]);

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn(['validated_at', 'validated_by']);
        });
    }
};
