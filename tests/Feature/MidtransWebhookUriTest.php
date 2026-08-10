<?php

namespace Tests\Feature;

use App\Http\Controllers\TradeInPaymentNotificationController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Mengunci URI kedua webhook Midtrans.
 *
 * URI ini bukan detail internal: keduanya terdaftar sebagai Payment
 * Notification URL di dashboard Midtrans. Mengubahnya di kode saja membuat
 * notifikasi pembayaran masuk ke alamat yang tidak ada — pesanan dan selisih
 * tukar tambah tidak pernah ditandai lunas, dan tidak ada galat apa pun yang
 * muncul di aplikasi. Kegagalannya sunyi, itulah yang membuatnya berbahaya.
 *
 * Khusus `midtrans/barter/notification`: kata "barter" di situ adalah sisa nama
 * lama fitur tukar tambah yang SENGAJA dipertahankan. Tes ini ada supaya
 * seseorang yang sedang merapikan penamaan tidak ikut mengganti URI-nya tanpa
 * memperbarui dashboard Midtrans lebih dulu.
 */
class MidtransWebhookUriTest extends TestCase
{
    public function test_uri_webhook_pesanan_tidak_berubah(): void
    {
        $this->assertSame(
            'midtrans/notification',
            Route::getRoutes()->getByName('midtrans.notification')->uri(),
            'URI webhook pesanan berubah. Perbarui dashboard Midtrans lebih dulu sebelum menyesuaikan tes ini.'
        );
    }

    public function test_uri_webhook_tukar_tambah_tetap_memakai_kata_barter(): void
    {
        $route = Route::getRoutes()->getByName('midtrans.trade-in.notification');

        $this->assertNotNull($route, 'Route webhook selisih tukar tambah hilang.');
        $this->assertSame(
            'midtrans/barter/notification',
            $route->uri(),
            'URI webhook tukar tambah berubah. Kata "barter" di sini disengaja — '
            .'perbarui Payment Notification URL di dashboard Midtrans lebih dulu.'
        );
        $this->assertSame(
            TradeInPaymentNotificationController::class,
            $route->getActionName(),
        );
    }

    /**
     * Webhook datang dari server Midtrans: tanpa sesi, tanpa token CSRF. Kalau
     * pengecualian CSRF-nya lepas, seluruh notifikasi pembayaran ditolak 419
     * dan tidak ada satu pun transaksi yang pernah ditandai lunas.
     *
     * Diuji lewat perilaku, bukan isi properti middleware: yang penting
     * permintaan tanpa token tidak dijawab 419. Signature-nya sengaja dibuat
     * ngawur, jadi responsnya boleh apa saja selain 419.
     */
    public function test_kedua_webhook_menerima_permintaan_tanpa_token_csrf(): void
    {
        foreach (['midtrans/notification', 'midtrans/barter/notification'] as $uri) {
            $response = $this->post('/'.$uri, [
                'order_id' => 'TIDAK-ADA',
                'status_code' => '200',
                'gross_amount' => '1000.00',
                'signature_key' => 'ngawur',
                'transaction_status' => 'settlement',
            ]);

            $this->assertNotSame(
                419,
                $response->getStatusCode(),
                "Webhook /{$uri} ditolak CSRF. Periksa daftar pengecualian di bootstrap/app.php."
            );
        }
    }
}
