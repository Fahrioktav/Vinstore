<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penyaringan daftar lelang menurut jalannya lelang: sedang berlangsung atau
 * sudah selesai.
 *
 * Yang dikunci di sini bukan tampilannya, melainkan aturan penggolongannya.
 * Kolom `status` saja tidak cukup dijadikan patokan — ia baru berpindah ke
 * `ended` saat penjadwal `auctions:finish` berjalan, dan penjadwal adalah
 * proses terpisah yang bisa sedang mati. Lelang yang tenggatnya sudah lewat
 * harus tergolong "selesai" walau kolomnya belum sempat diperbarui.
 */
class AuctionStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $seller = User::create([
            'username' => 'seller_filter',
            'first_name' => 'Uji',
            'last_name' => 'Seller',
            'email' => 'seller_filter@vinstore.test',
            'phone' => '0811'.random_int(100000, 999999),
            'address' => 'Jl. Uji No. 1',
            'password' => 'password',
            'role' => 'seller',
        ]);

        $this->store = Store::create([
            'user_id' => $seller->id,
            'store_name' => 'Toko Antik',
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);
    }

    private function lelang(string $name, array $override = []): Auction
    {
        return Auction::create(array_merge([
            'store_id' => $this->store->id,
            'name' => $name,
            'description' => 'Barang antik',
            'starting_price' => 500000,
            'min_increment' => 50000,
            'current_price' => 500000,
            'approval_status' => Auction::STATUS_APPROVED,
            'status' => 'active',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
        ], $override));
    }

    public function test_filter_berlangsung_hanya_memuat_lelang_yang_penawarannya_terbuka(): void
    {
        $this->lelang('Guci Berjalan');
        $this->lelang('Guci Selesai', [
            'status' => 'ended',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
        ]);
        $this->lelang('Guci Akan Datang', [
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(3),
        ]);

        $nama = $this->namaLelangPada('/auctions?status=ongoing');

        $this->assertSame(['Guci Berjalan'], $nama);
    }

    public function test_filter_selesai_hanya_memuat_lelang_yang_sudah_ditutup(): void
    {
        $this->lelang('Guci Berjalan');
        $this->lelang('Guci Selesai', [
            'status' => 'ended',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
        ]);

        $nama = $this->namaLelangPada('/auctions?status=finished');

        $this->assertSame(['Guci Selesai'], $nama);
    }

    /**
     * Lelang yang tenggatnya lewat sementara penjadwal sedang tidak berjalan
     * tetap harus tergolong selesai — bukan berlangsung.
     */
    public function test_lelang_lewat_tenggat_tidak_pernah_tergolong_berlangsung(): void
    {
        $this->lelang('Guci Kedaluwarsa', [
            'status' => 'active',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subHour(),
        ]);

        $this->assertSame([], $this->namaLelangPada('/auctions?status=ongoing'));
        $this->assertSame(['Guci Kedaluwarsa'], $this->namaLelangPada('/auctions?status=finished'));
    }

    public function test_tanpa_filter_seluruh_lelang_yang_disetujui_tetap_tampil(): void
    {
        $this->lelang('Guci Berjalan');
        $this->lelang('Guci Selesai', [
            'status' => 'ended',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
        ]);
        $this->lelang('Guci Akan Datang', [
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(3),
        ]);

        $this->assertCount(3, $this->namaLelangPada('/auctions'));
    }

    /**
     * Nilai `status` yang tidak dikenal diabaikan, bukan menghasilkan daftar
     * kosong. Tautan usang atau URL yang diketik keliru tetap menampilkan
     * sesuatu.
     */
    public function test_nilai_filter_asing_diabaikan(): void
    {
        $this->lelang('Guci Berjalan');

        $response = $this->get('/auctions?status=ngawur');

        $response->assertOk();
        $this->assertNull($response->viewData('page')['props']['filters']['status']);
        $this->assertCount(1, $response->viewData('page')['props']['auctions']);
    }

    public function test_lelang_belum_disetujui_tidak_ikut_terhitung(): void
    {
        $this->lelang('Guci Menunggu Validator', [
            'approval_status' => Auction::STATUS_PENDING_VALIDATOR,
        ]);

        $response = $this->get('/auctions?status=ongoing');

        $counts = $response->viewData('page')['props']['counts'];

        $this->assertSame(0, $counts['all']);
        $this->assertSame(0, $counts['ongoing']);
        $this->assertSame(0, $counts['finished']);
    }

    /** @return array<int, string> */
    private function namaLelangPada(string $url): array
    {
        $response = $this->get($url);

        $response->assertOk();

        return collect($response->viewData('page')['props']['auctions'])
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }
}
