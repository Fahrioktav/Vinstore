<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uang jaminan peserta lelang.
 *
 * Lelang barang antik bernilai tinggi punya satu kelemahan yang tidak dimiliki
 * jual beli biasa: pemenangnya belum mengeluarkan sepeser pun saat menang.
 * Tidak ada yang menahan seseorang menawar sampai puluhan juta lalu menghilang,
 * dan yang menanggung akibatnya seller — barangnya tertahan berhari-hari
 * menunggu pembayaran yang tidak pernah datang, lalu lelangnya harus diulang
 * dari awal.
 *
 * Deposit menutup celah itu. Hanya yang sudah menaruh jaminan boleh menawar,
 * dan jaminan itu:
 *
 *  - menjadi UANG MUKA bila ia menang dan membayar (status `applied`),
 *  - DIKEMBALIKAN bila ia kalah (`refund_requested` -> `refunded`), dan
 *  - HANGUS bila ia menang tetapi tidak pernah membayar (`forfeited`).
 *
 * Pengembaliannya sengaja lewat pengajuan ke admin dengan bukti transfer,
 * mengikuti pola WithdrawalRequest yang sudah dipakai untuk pencairan saldo
 * toko: uang keluar dari marketplace selalu meninggalkan jejak audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_deposits', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('amount');
            $table->string('status')->default('pending');

            // Jalur pembayaran Midtrans, sama seperti pesanan.
            $table->string('payment_reference')->nullable()->unique();
            $table->string('midtrans_transaction_id')->nullable();
            $table->string('snap_token')->nullable();
            $table->string('snap_redirect_url')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Rekening tujuan pengembalian, diisi pembeli saat mengajukan.
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_holder')->nullable();
            $table->timestamp('refund_requested_at')->nullable();

            // Keputusan admin atas pengajuan pengembalian.
            $table->string('transfer_proof')->nullable();
            $table->string('admin_note', 1000)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->timestamp('applied_at')->nullable();
            $table->timestamp('forfeited_at')->nullable();

            $table->timestamps();

            // Satu peserta hanya punya satu jaminan per lelang. Dijaga di level
            // basis data karena dua permintaan bersamaan bisa sama-sama lolos
            // pengecekan di PHP.
            $table->unique(['auction_id', 'user_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            // Bagian tagihan yang sudah tertutup deposit pemenang. `price` tetap
            // berisi tagihan penuh — itulah nilai transaksi yang sesungguhnya,
            // dan dari sanalah hak seller serta biaya layanan dihitung. Yang
            // ditagihkan lewat Midtrans adalah price dikurangi kolom ini.
            $table->unsignedBigInteger('deposit_credit')->default(0)->after('service_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('deposit_credit');
        });

        Schema::dropIfExists('auction_deposits');
    }
};
