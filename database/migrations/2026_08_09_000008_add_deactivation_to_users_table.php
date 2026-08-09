<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temuan V4-12: menghapus akun memusnahkan seluruh riwayat pesanannya.
 *
 * `orders.user_id` dan `refund_requests.user_id` memakai ON DELETE CASCADE, dan
 * AdminUserController::destroy() bahkan memanggil `$user->orders()->delete()`
 * secara eksplisit. Satu klik "Hapus" pada seorang pembeli melenyapkan pesanan
 * yang sudah lunas, sudah dikirim, dan sudah dicairkan ke seller — sekaligus
 * memutus asal-usul pendapatan yang sudah tercatat di platform_revenues.
 *
 * Alih-alih membuat foreign key-nya nullable dan menyimpan snapshot data
 * pribadi pembeli di tiap pesanan (yang membawa persoalan privasi sendiri),
 * akun kini TIDAK PERNAH dihapus — hanya dinonaktifkan.
 *
 * Akun nonaktif tidak bisa masuk, sesinya yang sedang berjalan diputus, dan
 * bila ia seorang seller, produknya berhenti tampil di etalase. Seluruh
 * riwayatnya tetap utuh dan tetap bisa ditelusuri admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('role');
            $table->string('deactivation_reason')->nullable()->after('deactivated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['deactivated_at', 'deactivation_reason']);
        });
    }
};
