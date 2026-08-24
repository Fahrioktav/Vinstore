<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Category;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Berkas unggahan: penamaan dan kepemilikannya.
 *
 * Dua temuan yang dikunci di sini, keduanya berujung pada berkas yang salah
 * ditampilkan atau lenyap tanpa jejak:
 *
 *  V8-01 — nama berkas disusun dari `time()` + nama berkas asal, sehingga dua
 *          seller yang mengunggah "sertifikat.pdf" pada detik yang sama
 *          mendapat jalur simpan yang sama dan yang belakangan menimpa yang
 *          duluan. Untuk sertifikat keaslian artinya satu barang menampilkan
 *          bukti keaslian milik barang lain.
 *
 *  V8-02 — relist memakai ulang JALUR berkas lelang lama, sementara update dan
 *          destroy menghapus berkas pada jalur itu. Akibatnya mengganti
 *          sertifikat pada lelang hasil pengajuan ulang menghapus sertifikat
 *          lelang lama dari disk, padahal kolomnya masih menunjuk ke sana.
 */
class UploadFileNamingTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seller = $this->buatSeller('sellersatu', 'Toko Satu');
        $this->store = $this->seller->store;

        Category::create(['name' => 'Keramik']);
    }

    private function buatSeller(string $username, string $namaToko): User
    {
        $user = User::create([
            'username' => $username,
            'first_name' => ucfirst($username),
            'last_name' => 'Uji',
            'email' => $username.'@vinstore.test',
            'phone' => '0811'.random_int(1000000, 9999999),
            'address' => 'Jl. Antik No. 1',
            'password' => 'password',
            'role' => 'seller',
        ]);

        Store::create([
            'user_id' => $user->id,
            'store_name' => $namaToko,
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function dataLelang(array $override = []): array
    {
        return array_merge([
            'name' => 'Guci Ming',
            'description' => 'Guci antik peninggalan dinasti Ming.',
            'weight' => 4000,
            'starting_price' => 2_000_000,
            'min_increment' => 100_000,
            'starts_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ], $override);
    }

    // ------------------------------------------------------------------
    // V8-01 — nama berkas tidak boleh bertabrakan
    // ------------------------------------------------------------------

    public function test_dua_seller_mengunggah_sertifikat_bernama_sama_tidak_saling_menimpa(): void
    {
        $sellerLain = $this->buatSeller('sellerdua', 'Toko Dua');

        foreach ([$this->seller, $sellerLain] as $seller) {
            $this->actingAs($seller)->post('/seller/auctions', $this->dataLelang([
                'name' => 'Guci '.$seller->username,
                'image' => UploadedFile::fake()->image('foto.jpg'),
                // Nama berkas yang lumrah, dan karena itu mudah bertabrakan.
                'certificate' => UploadedFile::fake()->create('sertifikat.pdf', 100, 'application/pdf'),
            ]));
        }

        $satu = Auction::where('name', 'Guci sellersatu')->firstOrFail();
        $dua = Auction::where('name', 'Guci sellerdua')->firstOrFail();

        $this->assertNotSame($satu->certificate, $dua->certificate);
        $this->assertNotSame($satu->image, $dua->image);

        // Yang lebih penting daripada jalurnya berbeda: keduanya masih ada.
        Storage::disk('public')->assertExists($satu->certificate);
        Storage::disk('public')->assertExists($dua->certificate);
        Storage::disk('public')->assertExists($satu->image);
        Storage::disk('public')->assertExists($dua->image);
    }

    public function test_dua_produk_dengan_gambar_bernama_sama_tidak_saling_menimpa(): void
    {
        $kirim = fn (string $nama) => $this->actingAs($this->seller)->post('/seller/products', [
            'name' => $nama,
            'stock' => 3,
            'price' => 250_000,
            'weight' => 1000,
            'category' => 'Keramik',
            'description' => 'Piring antik.',
            'image' => UploadedFile::fake()->image('foto.jpg'),
            'certificate' => UploadedFile::fake()->create('sertifikat.pdf', 100, 'application/pdf'),
        ]);

        $kirim('Piring Satu');
        $kirim('Piring Dua');

        $satu = \App\Models\Product::where('name', 'Piring Satu')->firstOrFail();
        $dua = \App\Models\Product::where('name', 'Piring Dua')->firstOrFail();

        $this->assertNotSame($satu->image, $dua->image);
        $this->assertNotSame($satu->certificate, $dua->certificate);

        Storage::disk('public')->assertExists($satu->image);
        Storage::disk('public')->assertExists($dua->image);
        Storage::disk('public')->assertExists($satu->certificate);
        Storage::disk('public')->assertExists($dua->certificate);
    }

    // ------------------------------------------------------------------
    // V8-02 — relist tidak boleh berbagi berkas dengan lelang lama
    // ------------------------------------------------------------------

    private function lelangSelesai(): Auction
    {
        return Auction::create([
            'store_id' => $this->store->id,
            'name' => 'Guci Lama',
            'description' => 'Guci antik',
            'weight' => 4000,
            'starting_price' => 2_000_000,
            'min_increment' => 100_000,
            'current_price' => 2_000_000,
            'approval_status' => Auction::STATUS_APPROVED,
            'status' => 'ended',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
            'image' => UploadedFile::fake()->image('lama.jpg')->storeAs('auctions', 'lama.jpg', 'public'),
            'certificate' => UploadedFile::fake()
                ->create('lama.pdf', 100, 'application/pdf')
                ->storeAs('certificates', 'lama.pdf', 'public'),
        ]);
    }

    private function relist(Auction $auction, array $override = []): Auction
    {
        $this->actingAs($this->seller)->post(
            '/seller/auctions/'.$auction->public_id.'/relist',
            $this->dataLelang(array_merge(['name' => 'Guci Ulang'], $override))
        );

        return Auction::where('name', 'Guci Ulang')->firstOrFail();
    }

    public function test_relist_menyalin_berkasnya_bukan_memakai_jalur_yang_sama(): void
    {
        $lama = $this->lelangSelesai();
        $baru = $this->relist($lama);

        $this->assertNotSame($lama->image, $baru->image);
        $this->assertNotSame($lama->certificate, $baru->certificate);

        Storage::disk('public')->assertExists($lama->image);
        Storage::disk('public')->assertExists($lama->certificate);
        Storage::disk('public')->assertExists($baru->image);
        Storage::disk('public')->assertExists($baru->certificate);
    }

    public function test_mengganti_sertifikat_lelang_hasil_relist_tidak_menghapus_milik_lelang_lama(): void
    {
        $lama = $this->lelangSelesai();
        $baru = $this->relist($lama);

        $this->actingAs($this->seller)->put(
            '/seller/auctions/'.$baru->public_id,
            $this->dataLelang([
                'name' => 'Guci Ulang',
                'image' => UploadedFile::fake()->image('baru.jpg'),
                'certificate' => UploadedFile::fake()->create('baru.pdf', 100, 'application/pdf'),
            ])
        );

        $lama->refresh();

        // Berkas lelang lama tetap utuh, dan kolomnya tetap menunjuk ke sana.
        Storage::disk('public')->assertExists($lama->image);
        Storage::disk('public')->assertExists($lama->certificate);
    }

    /**
     * Penjaga untuk data yang terlanjur berbagi berkas sebelum relist
     * diperbaiki: menghapus salah satu lelang tidak boleh menghapus berkas yang
     * masih dirujuk lelang lain.
     */
    public function test_menghapus_lelang_tidak_menghapus_berkas_yang_masih_dipakai_lelang_lain(): void
    {
        $lama = $this->lelangSelesai();

        $kembar = Auction::create([
            'store_id' => $this->store->id,
            'name' => 'Guci Kembar',
            'description' => 'Guci antik',
            'weight' => 4000,
            'starting_price' => 2_000_000,
            'min_increment' => 100_000,
            'current_price' => 2_000_000,
            'approval_status' => Auction::STATUS_PENDING_VALIDATOR,
            'status' => 'pending',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(3),
            // Berbagi jalur, persis seperti data lama hasil relist.
            'image' => $lama->image,
            'certificate' => $lama->certificate,
        ]);

        $this->actingAs($this->seller)->delete('/seller/auctions/'.$kembar->public_id);

        $this->assertNull(Auction::find($kembar->id));

        Storage::disk('public')->assertExists($lama->image);
        Storage::disk('public')->assertExists($lama->certificate);
    }

    public function test_menghapus_lelang_terakhir_pemilik_berkas_tetap_membersihkan_disk(): void
    {
        $auction = Auction::create([
            'store_id' => $this->store->id,
            'name' => 'Guci Sendiri',
            'description' => 'Guci antik',
            'weight' => 4000,
            'starting_price' => 2_000_000,
            'min_increment' => 100_000,
            'current_price' => 2_000_000,
            'approval_status' => Auction::STATUS_PENDING_VALIDATOR,
            'status' => 'pending',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(3),
            'image' => UploadedFile::fake()->image('sendiri.jpg')->storeAs('auctions', 'sendiri.jpg', 'public'),
            'certificate' => UploadedFile::fake()
                ->create('sendiri.pdf', 100, 'application/pdf')
                ->storeAs('certificates', 'sendiri.pdf', 'public'),
        ]);

        $image = $auction->image;
        $certificate = $auction->certificate;

        $this->actingAs($this->seller)->delete('/seller/auctions/'.$auction->public_id);

        Storage::disk('public')->assertMissing($image);
        Storage::disk('public')->assertMissing($certificate);
    }
}
