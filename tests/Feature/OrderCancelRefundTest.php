<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test untuk temuan V10-01 dan S-12.
 *
 * V10-01: pesanan yang sudah dibayar lalu dibatalkan pembeli berakhir sebagai
 * `Cancelled` + `paid`. Stoknya tidak kembali karena sudah dipotong, dan
 * pengajuan refund ditolak karena dulu mensyaratkan status `Delivered` atau
 * `Completed`. Uang pembeli berhenti di situ tanpa satu pun jalur pulang.
 *
 * S-12: panel admin menerima status apa pun karena validasinya hanya
 * `required`, termasuk salah ketik yang membuat pesanan lenyap dari semua
 * penyaring sekaligus.
 */
class OrderCancelRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $pembeli;

    private User $seller;

    private User $admin;

    private Store $store;

    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pembeli = $this->makeUser('pembeli', 'user');
        $this->seller = $this->makeUser('seller', 'seller');
        $this->admin = $this->makeUser('admin', 'admin');

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Antik',
            'category' => 'Antik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);

        $this->produk = Product::create([
            'store_id' => $this->store->id,
            'name' => 'Gramofon',
            'stock' => 3,
            'price' => 500_000,
            'category' => 'Antik',
            'description' => 'Barang antik',
            'approval_status' => Product::STATUS_APPROVED,
        ]);
    }

    private function makeUser(string $nama, string $role): User
    {
        return User::create([
            'username' => $nama,
            'first_name' => ucfirst($nama),
            'last_name' => 'Uji',
            'email' => $nama.'@vinstore.test',
            'phone' => '0811'.random_int(1000000, 9999999),
            'address' => 'Jl. Uji',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function buatPesanan(string $status, string $paymentStatus): Order
    {
        $order = Order::create([
            'user_id' => $this->pembeli->id,
            'product_id' => $this->produk->id,
            'product_name' => $this->produk->name,
            'product_price' => 500_000,
            'store_id' => $this->store->id,
            'store_name' => $this->store->store_name,
            'quantity' => 1,
            'price' => 500_000,
            'status' => $status,
            'shipping_address' => 'Jl. Pembeli No. 2',
            'payment_status' => $paymentStatus,
            'payment_method' => 'midtrans',
            'paid_at' => $paymentStatus === 'paid' ? now() : null,
        ]);

        if ($paymentStatus === 'paid') {
            // Pesanan lunas berarti stoknya sudah benar-benar dipotong.
            $this->produk->increment('reserved_stock');
            $order->commitReservedStock();
        } else {
            $this->produk->increment('reserved_stock');
        }

        return $order->fresh();
    }

    // ------------------------------------------------------------------
    // V10-01 — pembatalan pesanan lunas
    // ------------------------------------------------------------------

    public function test_pesanan_lunas_tidak_dapat_dibatalkan_sendiri_oleh_pembeli(): void
    {
        $order = $this->buatPesanan('Waiting', 'paid');

        $this->actingAs($this->pembeli)
            ->delete('/order/'.$order->public_id)
            ->assertSessionHas('error');

        $order->refresh();

        $this->assertSame('Waiting', $order->status);
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_pesanan_belum_dibayar_tetap_dapat_dibatalkan_dan_reservasinya_dilepas(): void
    {
        $order = $this->buatPesanan('Waiting', 'pending');
        $reservasiAwal = $this->produk->fresh()->reserved_stock;

        $this->actingAs($this->pembeli)
            ->delete('/order/'.$order->public_id)
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame('Cancelled', $order->status);
        $this->assertSame('cancelled', $order->payment_status);
        $this->assertSame($reservasiAwal - 1, $this->produk->fresh()->reserved_stock);
    }

    public function test_refund_dapat_diajukan_atas_pesanan_lunas_yang_belum_dikirim(): void
    {
        $order = $this->buatPesanan('Waiting', 'paid');

        $this->actingAs($this->pembeli)
            ->post('/order/'.$order->public_id.'/refund', [
                'reason' => 'Saya salah memesan barang ini, mohon dibatalkan.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('refund_requests', 1);
    }

    public function test_refund_masih_dapat_diajukan_atas_pesanan_lunas_yang_telanjur_dibatalkan(): void
    {
        // Data lama dari sebelum penjagaan V10-01 dipasang: Cancelled + paid.
        $order = $this->buatPesanan('Cancelled', 'paid');

        $this->actingAs($this->pembeli)
            ->post('/order/'.$order->public_id.'/refund', [
                'reason' => 'Pesanan ini batal tetapi uang saya belum kembali.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('refund_requests', 1);
    }

    public function test_refund_yang_disetujui_membatalkan_pesanan_dan_mengembalikan_stok(): void
    {
        $order = $this->buatPesanan('Waiting', 'paid');
        $stokSetelahDipotong = $this->produk->fresh()->stock;

        $this->actingAs($this->pembeli)->post('/order/'.$order->public_id.'/refund', [
            'reason' => 'Saya salah memesan barang ini, mohon dibatalkan.',
        ]);

        $refund = RefundRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/refunds/'.$refund->public_id.'/approve', ['admin_note' => 'Disetujui.'])
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('Cancelled', $order->status);
        $this->assertSame($stokSetelahDipotong + 1, $this->produk->fresh()->stock);
        $this->assertNotNull($order->stock_returned_at);
    }

    public function test_pengembalian_stok_tidak_berlipat_bila_dipanggil_dua_kali(): void
    {
        $order = $this->buatPesanan('Waiting', 'paid');
        $stokSetelahDipotong = $this->produk->fresh()->stock;

        $order->returnCommittedStock();
        $order->returnCommittedStock();

        $this->assertSame($stokSetelahDipotong + 1, $this->produk->fresh()->stock);
    }

    public function test_refund_dapat_diajukan_ulang_setelah_pengajuan_sebelumnya_ditolak(): void
    {
        $order = $this->buatPesanan('Delivered', 'paid');

        $this->actingAs($this->pembeli)->post('/order/'.$order->public_id.'/refund', [
            'reason' => 'Barang belum sampai sampai hari ini.',
        ]);

        $refund = RefundRequest::firstOrFail();

        $this->actingAs($this->admin)->post('/admin/refunds/'.$refund->public_id.'/reject', [
            'admin_note' => 'Resi menunjukkan barang sudah diterima.',
        ]);

        // Keadaan berubah: barangnya datang, tetapi rusak.
        $this->actingAs($this->pembeli)
            ->post('/order/'.$order->public_id.'/refund', [
                'reason' => 'Barangnya sudah sampai tetapi pecah di tengah jalan.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('refund_requests', 2);
    }

    public function test_refund_tidak_dapat_diajukan_dua_kali_selagi_masih_diproses(): void
    {
        $order = $this->buatPesanan('Delivered', 'paid');

        $this->actingAs($this->pembeli)->post('/order/'.$order->public_id.'/refund', [
            'reason' => 'Barang belum sampai sampai hari ini.',
        ]);

        $this->actingAs($this->pembeli)
            ->post('/order/'.$order->public_id.'/refund', [
                'reason' => 'Saya ajukan sekali lagi karena belum ada kabar.',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('refund_requests', 1);
    }

    // ------------------------------------------------------------------
    // S-12 — status pesanan di panel admin
    // ------------------------------------------------------------------

    public function test_admin_tidak_dapat_menyimpan_status_di_luar_daftar(): void
    {
        $order = $this->buatPesanan('Waiting', 'paid');

        $this->actingAs($this->admin)
            ->put('/admin/orders/'.$order->public_id, ['status' => 'Dikirim Kemarin'])
            ->assertSessionHasErrors('status');

        $this->assertSame('Waiting', $order->fresh()->status);
    }

    public function test_admin_tidak_dapat_mengubah_pesanan_yang_sudah_dikonfirmasi_pembeli(): void
    {
        $order = $this->buatPesanan('Completed', 'paid');

        $this->actingAs($this->admin)
            ->put('/admin/orders/'.$order->public_id, ['status' => 'Waiting'])
            ->assertSessionHas('error');

        $this->assertSame('Completed', $order->fresh()->status);
    }

    public function test_admin_tetap_dapat_mengubah_status_yang_sah(): void
    {
        $order = $this->buatPesanan('Waiting', 'paid');

        $this->actingAs($this->admin)
            ->put('/admin/orders/'.$order->public_id, ['status' => 'On The Way'])
            ->assertSessionHas('success');

        $this->assertSame('On The Way', $order->fresh()->status);
    }
}
