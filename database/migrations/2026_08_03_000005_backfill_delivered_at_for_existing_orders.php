<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lanjutan temuan K-06.
 *
 * Kolom orders.delivered_at baru ditambahkan oleh migration 000002, sehingga
 * pesanan yang SUDAH berstatus "Delivered" sebelum itu bernilai NULL. Command
 * orders:auto-complete mensyaratkan delivered_at terisi, jadi pesanan-pesanan
 * lama tersebut tidak akan pernah diselesaikan otomatis — dananya bisa
 * menggantung selamanya bila pembeli tidak pernah menekan tombol konfirmasi.
 *
 * updated_at dipakai sebagai perkiraan terbaik kapan pesanan ditandai sampai:
 * perubahan status adalah penulisan terakhir pada baris-baris itu.
 *
 * Pesanan yang dananya sudah terlanjur dilepas di bawah aturan lama sengaja
 * ikut diisi agar konsisten; releaseSellerFunds() tetap tidak akan melepas
 * dana dua kali karena dijaga oleh seller_released_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->where('status', 'Delivered')
            ->whereNull('delivered_at')
            ->update([
                'delivered_at' => DB::raw('updated_at'),
            ]);

        // Pesanan yang dananya sudah dilepas berarti secara efektif sudah
        // tuntas menurut aturan lama. Tandai selesai agar tidak tampil sebagai
        // "menunggu konfirmasi pembeli" yang menyesatkan.
        DB::table('orders')
            ->where('status', 'Delivered')
            ->whereNotNull('seller_released_at')
            ->whereNull('completed_at')
            ->update([
                'status' => 'Completed',
                'completed_at' => DB::raw('seller_released_at'),
            ]);
    }

    public function down(): void
    {
        // Tidak dapat dibalik dengan aman: nilai asli delivered_at memang tidak
        // pernah ada. Membiarkannya terisi lebih aman daripada mengosongkan.
    }
};
