<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buku besar pendapatan marketplace ("dompet admin").
 *
 * Sengaja berupa buku besar (ledger), bukan satu kolom saldo di tabel users:
 * saldo yang disimpan sebagai angka tunggal gampang melenceng dan tidak bisa
 * menjawab "uang ini datang dari transaksi mana". Saldo dompet admin adalah
 * SUM(amount) dari tabel ini, jadi selalu konsisten dengan riwayatnya.
 *
 * `amount` boleh negatif: pembalikan saat refund disetujui dicatat sebagai
 * baris tersendiri, bukan dengan menghapus baris pendapatannya, agar jejak
 * audit tetap utuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_revenues', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->bigInteger('amount');
            $table->string('description')->nullable();
            $table->timestamps();

            // Pencatatan dipicu webhook Midtrans yang bisa datang berkali-kali
            // untuk transaksi yang sama. Kunci unik ini yang membuat pencatatan
            // idempoten — bukan pengecekan di kode aplikasi yang bisa balapan.
            $table->unique(['order_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_revenues');
    }
};
