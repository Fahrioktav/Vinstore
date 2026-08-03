<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pencairan dana per pesanan.
 *
 * Dana pesanan tidak pernah cair otomatis. Seller mengajukan pencairan dari
 * baris pesanan yang sudah Delivered/Completed, dan admin menyetujuinya sambil
 * mengunggah bukti transfer ke rekening yang diajukan.
 */
class PayoutRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $buyer;

    private User $admin;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seller = User::create([
            'username' => 'sellerpayout',
            'first_name' => 'Seller',
            'last_name' => 'Payout',
            'email' => 'sellerpayout@vinstore.test',
            'phone' => '08110003',
            'address' => 'Jl. Antik No. 1',
            'password' => 'password',
            'role' => 'seller',
        ]);

        $this->buyer = User::create([
            'username' => 'buyerpayout',
            'first_name' => 'Pembeli',
            'last_name' => 'Payout',
            'email' => 'buyerpayout@vinstore.test',
            'phone' => '08210003',
            'address' => 'Jl. Pembeli No. 2',
            'password' => 'password',
            'role' => 'user',
        ]);

        $this->admin = User::create([
            'username' => 'adminpayout',
            'first_name' => 'Admin',
            'last_name' => 'Payout',
            'email' => 'adminpayout@vinstore.test',
            'phone' => '08910003',
            'address' => 'Jl. Admin',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Payout',
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);

        $this->product = Product::create([
            'store_id' => $this->store->id,
            'name' => 'Guci Ming',
            'stock' => 5,
            'price' => 1000000,
            'category' => 'Keramik',
            'description' => 'Guci antik',
            'approval_status' => Product::STATUS_APPROVED,
        ]);
    }

    private function order(string $status = 'Delivered', string $paymentStatus = 'paid'): Order
    {
        return Order::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_price' => $this->product->price,
            'store_id' => $this->store->id,
            'store_name' => $this->store->store_name,
            'quantity' => 1,
            'price' => 1000000,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'payment_method' => 'midtrans',
        ]);
    }

    private function bankPayload(): array
    {
        return [
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Seller Payout',
        ];
    }

    private function requestPayout(Order $order, array $overrides = [])
    {
        return $this->actingAs($this->seller)
            ->post('/seller/orders/'.$order->public_id.'/payout', array_merge($this->bankPayload(), $overrides));
    }

    public function test_seller_bisa_mengajukan_pencairan_pesanan_delivered(): void
    {
        $order = $this->order('Delivered');

        $this->requestPayout($order)->assertSessionHas('success');

        $payout = PayoutRequest::where('order_id', $order->id)->firstOrFail();

        $this->assertSame(PayoutRequest::STATUS_PENDING, $payout->status);
        $this->assertSame('1000000.00', (string) $payout->amount);
        $this->assertSame('BCA', $payout->bank_name);
        // Mengajukan saja belum memindahkan uang.
        $this->assertNull($order->fresh()->seller_released_at);
    }

    public function test_seller_bisa_mengajukan_pencairan_pesanan_completed(): void
    {
        $order = $this->order('Completed');

        $this->requestPayout($order)->assertSessionHas('success');

        $this->assertSame(1, PayoutRequest::where('order_id', $order->id)->count());
    }

    public function test_pesanan_belum_dibayar_tidak_bisa_diajukan(): void
    {
        $order = $this->order('Delivered', 'pending');

        $this->requestPayout($order)->assertSessionHas('error');

        $this->assertSame(0, PayoutRequest::count());
    }

    public function test_pesanan_masih_dikirim_tidak_bisa_diajukan(): void
    {
        $order = $this->order('On The Way');

        $this->requestPayout($order)->assertSessionHas('error');

        $this->assertSame(0, PayoutRequest::count());
    }

    public function test_pesanan_dengan_refund_pending_tidak_bisa_diajukan(): void
    {
        $order = $this->order('Delivered');

        RefundRequest::create([
            'order_id' => $order->id,
            'user_id' => $this->buyer->id,
            'reason' => 'Barang tidak sesuai deskripsi sama sekali.',
            'status' => 'pending',
        ]);

        $this->requestPayout($order)->assertSessionHas('error');

        $this->assertSame(0, PayoutRequest::count());
    }

    public function test_tidak_bisa_mengajukan_dua_kali_untuk_pesanan_yang_sama(): void
    {
        $order = $this->order('Delivered');

        $this->requestPayout($order)->assertSessionHas('success');
        $this->requestPayout($order)->assertSessionHas('error');

        $this->assertSame(1, PayoutRequest::where('order_id', $order->id)->count());
    }

    public function test_seller_lain_tidak_bisa_mengajukan_pencairan_pesanan_orang(): void
    {
        $order = $this->order('Delivered');

        $penyusup = User::create([
            'username' => 'sellerlain',
            'first_name' => 'Seller',
            'last_name' => 'Lain',
            'email' => 'sellerlain@vinstore.test',
            'phone' => '08110004',
            'address' => 'Jl. Lain',
            'password' => 'password',
            'role' => 'seller',
        ]);

        Store::create([
            'user_id' => $penyusup->id,
            'store_name' => 'Toko Lain',
            'category' => 'Keramik',
            'description' => 'Toko lain',
            'location' => 'Solo',
        ]);

        $this->actingAs($penyusup)
            ->post('/seller/orders/'.$order->public_id.'/payout', $this->bankPayload())
            ->assertForbidden();

        $this->assertSame(0, PayoutRequest::count());
    }

    public function test_admin_menyetujui_mencairkan_dana_dan_menyimpan_bukti_transfer(): void
    {
        $order = $this->order('Delivered');
        $this->requestPayout($order);

        $payout = PayoutRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/payouts/'.$payout->public_id.'/approve', [
                'admin_note' => 'Sudah ditransfer.',
                'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertSessionHas('success');

        $payout->refresh();
        $order->refresh();

        $this->assertSame(PayoutRequest::STATUS_APPROVED, $payout->status);
        $this->assertNotNull($payout->transfer_proof);
        $this->assertNotNull($payout->transferred_at);
        Storage::disk('public')->assertExists($payout->transfer_proof);

        $this->assertNotNull($order->seller_released_at);
        $this->assertSame('1000000.00', (string) $this->store->fresh()->withdrawn_balance);
        // Dana langsung ke rekening, tidak singgah di saldo toko.
        $this->assertSame('0.00', (string) $this->store->fresh()->available_balance);
    }

    public function test_persetujuan_tanpa_bukti_transfer_ditolak(): void
    {
        $order = $this->order('Delivered');
        $this->requestPayout($order);

        $payout = PayoutRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/payouts/'.$payout->public_id.'/approve', [
                'admin_note' => 'Tanpa bukti.',
            ])
            ->assertSessionHasErrors('transfer_proof');

        $this->assertSame(PayoutRequest::STATUS_PENDING, $payout->fresh()->status);
        $this->assertNull($order->fresh()->seller_released_at);
    }

    public function test_refund_yang_muncul_setelah_pengajuan_menghalangi_persetujuan(): void
    {
        $order = $this->order('Delivered');
        $this->requestPayout($order);

        RefundRequest::create([
            'order_id' => $order->id,
            'user_id' => $this->buyer->id,
            'reason' => 'Barang tidak sesuai deskripsi sama sekali.',
            'status' => 'pending',
        ]);

        $payout = PayoutRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/payouts/'.$payout->public_id.'/approve', [
                'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertSessionHas('error');

        $this->assertSame(PayoutRequest::STATUS_PENDING, $payout->fresh()->status);
        $this->assertNull($order->fresh()->seller_released_at);
    }

    public function test_pengajuan_ditolak_bisa_diajukan_ulang(): void
    {
        $order = $this->order('Delivered');
        $this->requestPayout($order);

        $payout = PayoutRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/payouts/'.$payout->public_id.'/reject', [
                'admin_note' => 'Nomor rekening salah.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(PayoutRequest::STATUS_REJECTED, $payout->fresh()->status);
        $this->assertTrue($order->fresh()->canRequestPayout());

        // Seller memperbaiki nomor rekening lalu mengajukan ulang.
        $this->requestPayout($order, ['account_number' => '9998887776'])
            ->assertSessionHas('success');

        $this->assertSame(2, PayoutRequest::where('order_id', $order->id)->count());
        $this->assertSame(
            '9998887776',
            PayoutRequest::where('order_id', $order->id)->latest('id')->first()->account_number
        );
    }

    public function test_pencairan_yang_sudah_disetujui_tidak_bisa_diajukan_lagi(): void
    {
        $order = $this->order('Delivered');
        $this->requestPayout($order);

        $payout = PayoutRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/payouts/'.$payout->public_id.'/approve', [
                'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
            ]);

        $this->requestPayout($order)->assertSessionHas('error');

        $this->assertSame(1, PayoutRequest::where('order_id', $order->id)->count());
    }

    public function test_data_bank_pengajuan_terakhir_dikirim_sebagai_prefill(): void
    {
        $order = $this->order('Delivered');
        $this->requestPayout($order, ['bank_name' => 'Mandiri', 'account_number' => '5551112223']);

        $this->actingAs($this->seller)
            ->get('/seller/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('bankPrefill.bank_name', 'Mandiri')
                ->where('bankPrefill.account_number', '5551112223')
            );
    }
}
