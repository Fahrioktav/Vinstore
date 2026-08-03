<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Temuan T-08, dua bagian.
 *
 * (1) KUNCI PRODUK.
 * Saat barter disetujui tetapi pembayaran selisihnya belum lunas, kedua produk
 * tetap berstatus approved dengan stok > 0 — jadi masih bisa dibeli pembeli
 * biasa atau ditawarkan pada barter lain. Bila produk keburu terjual lalu
 * pembayaran barter menyusul settle, kepemilikan tetap dipindahkan dan stok
 * jadi tidak konsisten. Kolom locked_for_barter_id menandai produk yang sedang
 * terikat sebuah barter.
 *
 * (2) PENGIRIMAN DUA ARAH.
 * Sebelumnya barter hanya menukar kolom store_id lalu dianggap selesai — tidak
 * ada mekanisme apa pun untuk benar-benar mengirimkan barangnya. Kolom-kolom
 * pengiriman di bawah membuat kedua seller saling mengirim barang, mengisi
 * nomor resi, dan mengonfirmasi penerimaan. Kepemilikan baru berpindah penuh
 * setelah keduanya saling menerima.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('locked_for_barter_id')
                ->nullable()
                ->after('is_barterable')
                ->constrained('barter_requests')
                ->nullOnDelete();
        });

        Schema::table('barter_requests', function (Blueprint $table) {
            // Arah 1: requester mengirim offered_product ke responder.
            $table->string('requester_tracking_number')->nullable()->after('paid_at');
            $table->timestamp('requester_shipped_at')->nullable()->after('requester_tracking_number');
            $table->timestamp('requester_received_at')->nullable()->after('requester_shipped_at');

            // Arah 2: responder mengirim requested_product ke requester.
            $table->string('responder_tracking_number')->nullable()->after('requester_received_at');
            $table->timestamp('responder_shipped_at')->nullable()->after('responder_tracking_number');
            $table->timestamp('responder_received_at')->nullable()->after('responder_shipped_at');

            // Terisi setelah kedua belah pihak saling menerima barang.
            $table->timestamp('completed_at')->nullable()->after('responder_received_at');
        });

        // Tambah nilai status baru: 'shipping' (barang sedang saling dikirim)
        // dan 'completed' (kedua pihak sudah menerima).
        //
        // Di MySQL kolomnya ENUM sungguhan. Di SQLite, enum() Laravel
        // diterjemahkan menjadi VARCHAR + CHECK constraint — jadi menambah
        // nilai baru harus lewat perubahan kolom, bukan ALTER TABLE MySQL.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE barter_requests MODIFY COLUMN status ENUM('pending', 'accepted', 'shipping', 'completed', 'rejected', 'cancelled') DEFAULT 'pending'");
        } else {
            Schema::table('barter_requests', function (Blueprint $table) {
                $table->string('status', 20)->default('pending')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::table('barter_requests')
                ->whereIn('status', ['shipping', 'completed'])
                ->update(['status' => 'accepted']);

            DB::statement("ALTER TABLE barter_requests MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'cancelled') DEFAULT 'pending'");
        }

        Schema::table('barter_requests', function (Blueprint $table) {
            $table->dropColumn([
                'requester_tracking_number',
                'requester_shipped_at',
                'requester_received_at',
                'responder_tracking_number',
                'responder_shipped_at',
                'responder_received_at',
                'completed_at',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['locked_for_barter_id']);
            $table->dropColumn('locked_for_barter_id');
        });
    }
};
