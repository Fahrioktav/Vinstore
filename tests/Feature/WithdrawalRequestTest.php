<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pencairan saldo toko.
 *
 * Jalur ini memindahkan uang sungguhan keluar dari marketplace dan sebelumnya
 * sama sekali tanpa pengujian (temuan V6-05), padahal aturannya justru yang
 * paling mudah rusak tanpa disadari — terutama "saldo yang dapat dicairkan
 * adalah available_balance dikurangi seluruh pengajuan yang masih menggantung".
 */
class WithdrawalRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Store $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = $this->makeUser('sellerwdr', 'seller');
        $this->admin = $this->makeUser('adminwdr', 'admin');

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Pencairan',
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
            'available_balance' => 1_000_000,
        ]);
    }

    private function makeUser(string $username, string $role = 'user'): User
    {
        return User::create([
            'username' => $username,
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
            'email' => $username.'@vinstore.test',
            'phone' => '0811'.random_int(1000000, 9999999),
            'address' => 'Jl. Antik No. 1',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function ajukan(int $amount)
    {
        return $this->actingAs($this->seller)->post('/seller/withdrawals', [
            'amount' => $amount,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Seller Wdr',
        ]);
    }

    private function pengajuanTertunda(int $amount = 400_000): WithdrawalRequest
    {
        return WithdrawalRequest::create([
            'store_id' => $this->store->id,
            'amount' => $amount,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Seller Wdr',
            'status' => 'pending',
        ]);
    }

    // ------------------------------------------------------------------
    // Pengajuan
    // ------------------------------------------------------------------

    public function test_pengajuan_dalam_batas_saldo_diterima(): void
    {
        $this->ajukan(600_000)->assertSessionHas('success');

        $withdrawal = WithdrawalRequest::firstOrFail();

        $this->assertSame('pending', $withdrawal->status);
        $this->assertSame('600000.00', $withdrawal->amount);

        // Saldo belum berpindah: yang memindahkannya adalah persetujuan admin.
        $this->assertSame('1000000.00', $this->store->fresh()->available_balance);
    }

    public function test_pengajuan_melebihi_saldo_ditolak(): void
    {
        $this->ajukan(1_500_000)->assertSessionHas('error');

        $this->assertSame(0, WithdrawalRequest::count());
    }

    /**
     * Inti aturannya: pengajuan yang masih menggantung ikut mengurangi saldo
     * yang boleh diajukan lagi. Tanpa ini seller bisa mengajukan Rp 600.000 dua
     * kali atas saldo Rp 1.000.000 dan menguras lebih dari yang ia punya.
     */
    public function test_pengajuan_yang_masih_menggantung_mengurangi_saldo_yang_bisa_diajukan(): void
    {
        $this->pengajuanTertunda(700_000);

        $this->ajukan(600_000)->assertSessionHas('error');

        $this->assertSame(1, WithdrawalRequest::count());
    }

    public function test_sisa_saldo_setelah_pengajuan_menggantung_masih_bisa_diajukan(): void
    {
        $this->pengajuanTertunda(700_000);

        $this->ajukan(300_000)->assertSessionHas('success');

        $this->assertSame(2, WithdrawalRequest::count());
    }

    public function test_pengajuan_yang_sudah_ditolak_tidak_lagi_menahan_saldo(): void
    {
        $this->pengajuanTertunda(700_000)->update(['status' => 'rejected']);

        $this->ajukan(900_000)->assertSessionHas('success');
    }

    // ------------------------------------------------------------------
    // Persetujuan admin
    // ------------------------------------------------------------------

    public function test_persetujuan_memindahkan_saldo_tepat_sekali(): void
    {
        Storage::fake('public');

        $withdrawal = $this->pengajuanTertunda(400_000);

        $this->actingAs($this->admin)
            ->post('/admin/withdrawals/'.$withdrawal->public_id.'/approve', [
                'admin_note' => 'Sudah ditransfer',
                'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertSessionHas('success');

        $store = $this->store->fresh();

        $this->assertSame('600000.00', $store->available_balance);
        $this->assertSame('400000.00', $store->withdrawn_balance);

        $withdrawal->refresh();

        $this->assertSame('approved', $withdrawal->status);
        $this->assertNotNull($withdrawal->transfer_proof);
        $this->assertNotNull($withdrawal->transferred_at);
    }

    public function test_persetujuan_tanpa_bukti_transfer_ditolak(): void
    {
        $withdrawal = $this->pengajuanTertunda();

        $this->actingAs($this->admin)
            ->post('/admin/withdrawals/'.$withdrawal->public_id.'/approve', [
                'admin_note' => 'Tanpa bukti',
            ])
            ->assertSessionHasErrors('transfer_proof');

        $this->assertSame('pending', $withdrawal->fresh()->status);
        $this->assertSame('1000000.00', $this->store->fresh()->available_balance);
    }

    public function test_pengajuan_yang_sudah_disetujui_tidak_diproses_ulang(): void
    {
        Storage::fake('public');

        $withdrawal = $this->pengajuanTertunda(400_000);

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($this->admin)
                ->post('/admin/withdrawals/'.$withdrawal->public_id.'/approve', [
                    'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
                ]);
        }

        // Saldo hanya berpindah sekali walau tombolnya ditekan dua kali.
        $this->assertSame('600000.00', $this->store->fresh()->available_balance);
    }

    public function test_persetujuan_gagal_bila_saldo_toko_sudah_tidak_cukup(): void
    {
        Storage::fake('public');

        $withdrawal = $this->pengajuanTertunda(400_000);

        // Saldonya terpakai di tempat lain sebelum admin memutuskan.
        $this->store->update(['available_balance' => 100_000]);

        $this->actingAs($this->admin)
            ->post('/admin/withdrawals/'.$withdrawal->public_id.'/approve', [
                'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertSessionHas('error');

        $this->assertSame('pending', $withdrawal->fresh()->status);
        $this->assertSame('100000.00', $this->store->fresh()->available_balance);
    }

    // ------------------------------------------------------------------
    // Penolakan admin (temuan V6-04)
    // ------------------------------------------------------------------

    public function test_penolakan_mencatat_alasan_tanpa_menyentuh_saldo(): void
    {
        $withdrawal = $this->pengajuanTertunda();

        $this->actingAs($this->admin)
            ->post('/admin/withdrawals/'.$withdrawal->public_id.'/reject', [
                'admin_note' => 'Rekening tidak sesuai nama pemilik toko.',
            ])
            ->assertSessionHas('success');

        $withdrawal->refresh();

        $this->assertSame('rejected', $withdrawal->status);
        $this->assertSame('Rekening tidak sesuai nama pemilik toko.', $withdrawal->admin_note);
        $this->assertSame($this->admin->id, $withdrawal->reviewed_by);
        $this->assertSame('1000000.00', $this->store->fresh()->available_balance);
    }

    /**
     * Pengajuan yang sudah disetujui tidak boleh ditimpa menjadi "ditolak".
     *
     * Sebelumnya reject() tidak memakai transaksi maupun lock, sehingga catatan
     * bisa berbunyi ditolak padahal saldonya sudah benar-benar keluar — jejak
     * auditnya berbohong tentang uang yang sudah ditransfer (V6-04).
     */
    public function test_pengajuan_yang_sudah_disetujui_tidak_bisa_ditolak(): void
    {
        Storage::fake('public');

        $withdrawal = $this->pengajuanTertunda(400_000);

        $this->actingAs($this->admin)
            ->post('/admin/withdrawals/'.$withdrawal->public_id.'/approve', [
                'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
            ]);

        $this->actingAs($this->admin)
            ->post('/admin/withdrawals/'.$withdrawal->public_id.'/reject', [
                'admin_note' => 'Berubah pikiran',
            ])
            ->assertSessionHas('error');

        $withdrawal->refresh();

        $this->assertSame('approved', $withdrawal->status);
        $this->assertSame('600000.00', $this->store->fresh()->available_balance);
        $this->assertSame('400000.00', $this->store->fresh()->withdrawn_balance);
    }
}
