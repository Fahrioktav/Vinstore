<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\TradeInRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TEMUAN V3-05 — selisih harga dibekukan saat pengajuan dibuat dan tidak
 * pernah dihitung ulang saat disetujui.
 *
 * Kartu tukar tambah menampilkan harga produk yang HIDUP (relasi ke products),
 * sementara additional_cash dan payer_role berasal dari harga saat pengajuan
 * dikirim. Bila salah satu seller mengubah harga produknya di antara kedua
 * momen itu, yang ditagih bisa menjadi pihak yang justru menyerahkan barang
 * lebih mahal — persis kebalikan dari yang seharusnya.
 */
class TradeInStalePriceTest extends TestCase
{
    use RefreshDatabase;

    private User $requesterUser;

    private User $responderUser;

    private Store $requesterStore;

    private Store $responderStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requesterUser = $this->makeSeller('a');
        $this->responderUser = $this->makeSeller('b');

        $this->requesterStore = $this->makeStore($this->requesterUser, 'Toko A');
        $this->responderStore = $this->makeStore($this->responderUser, 'Toko B');

        // ProductController::update memvalidasi category lewat Rule::exists.
        // Tanpa baris ini, setiap PUT produk gagal validasi dan test yang
        // menguji penjagaan harga akan lulus karena alasan yang salah.
        Category::create(['name' => 'Antik']);
    }

    /**
     * Penjaga untuk test-test di bawah: memastikan PUT produk memang berhasil
     * ketika tidak ada yang menghalanginya. Kalau ini gagal, test lain yang
     * mengandalkan "harga tidak berubah" menjadi tidak bermakna.
     */
    public function test_pengeditan_harga_produk_biasa_memang_berhasil(): void
    {
        $produk = $this->makeProduct($this->requesterStore, 'Gramofon', 1_000_000);

        $this->actingAs($this->requesterUser)
            ->put('/seller/products/'.$produk->public_id, [
                'name' => $produk->name,
                'stock' => $produk->stock,
                'price' => 7_000_000,
                'category' => 'Antik',
                'description' => $produk->description,
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals(7_000_000, $produk->fresh()->price);
    }

    private function makeSeller(string $suffix): User
    {
        return User::create([
            'username' => 'seller'.$suffix,
            'first_name' => 'Seller',
            'last_name' => strtoupper($suffix),
            'email' => "seller{$suffix}@vinstore.test",
            'phone' => '0811000'.$suffix,
            'address' => 'Jl. Antik',
            'password' => 'password',
            'role' => 'seller',
        ]);
    }

    private function makeStore(User $user, string $name): Store
    {
        return Store::create([
            'user_id' => $user->id,
            'store_name' => $name,
            'category' => 'Antik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);
    }

    private function makeProduct(Store $store, string $name, int $price): Product
    {
        return Product::create([
            'store_id' => $store->id,
            'name' => $name,
            'stock' => 1,
            'price' => $price,
            'category' => 'Antik',
            'description' => 'Barang antik',
            'approval_status' => Product::STATUS_APPROVED,
            'is_trade_in_enabled' => true,
        ]);
    }

    /**
     * Skenario yang dilaporkan pengguna: pengaju ditagih padahal barangnya
     * lebih mahal.
     */
    public function test_selisih_dihitung_ulang_saat_tukar_tambah_disetujui(): void
    {
        // Saat diajukan: produk pengaju LEBIH MURAH (1jt vs 3jt).
        $offered = $this->makeProduct($this->requesterStore, 'Gramofon', 1_000_000);
        $requested = $this->makeProduct($this->responderStore, 'Guci Antik', 3_000_000);

        $this->actingAs($this->requesterUser)->post(
            '/seller/tukar-tambah/'.$requested->public_id,
            ['offered_product_id' => $offered->public_id]
        );

        $tradeIn = TradeInRequest::firstOrFail();
        $this->assertSame(TradeInRequest::PAYER_REQUESTER, $tradeIn->payer_role);

        // Pengaju menaikkan harga produknya: sekarang produknya JAUH LEBIH MAHAL.
        $offered->forceFill(['price' => 5_000_000])->save();

        // Penerima menyetujui berdasarkan harga yang ia lihat sekarang:
        // Gramofon 5jt ditukar Guci Antik 3jt -> PENERIMA yang harus menambah 2jt.
        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/accept');

        $tradeIn->refresh();

        $this->assertSame(
            TradeInRequest::PAYER_RESPONDER,
            $tradeIn->payer_role,
            'Pengaju ditagih padahal produknya lebih mahal — selisih tidak dihitung ulang saat disetujui.'
        );
        $this->assertEquals(
            2_000_000,
            $tradeIn->additional_cash,
            'Nominal selisih masih memakai harga lama saat pengajuan dibuat.'
        );
    }

    /**
     * Menghitung ulang selisih saat persetujuan membuka pertanyaan baru: bisakah
     * PENERIMA menaikkan harga produknya sendiri lalu menyetujui, sehingga
     * pengaju ditagih lebih besar daripada yang ia setujui saat mengajukan?
     *
     * Tidak bisa — mengedit produk mengembalikan approval_status ke
     * pending_validator, dan isTradeInEnabled() mensyaratkan approved. Tukar tambahnya
     * ditolak sebelum sempat menagih siapa pun.
     */
    public function test_penerima_tidak_bisa_menaikkan_harga_lalu_menyetujui(): void
    {
        $offered = $this->makeProduct($this->requesterStore, 'Gramofon', 3_000_000);
        $requested = $this->makeProduct($this->responderStore, 'Guci Antik', 3_000_000);

        $this->actingAs($this->requesterUser)->post(
            '/seller/tukar-tambah/'.$requested->public_id,
            ['offered_product_id' => $offered->public_id]
        );

        $tradeIn = TradeInRequest::firstOrFail();
        $this->assertEquals(0, $tradeIn->additional_cash);

        // Penerima menaikkan harga produknya sendiri setelah menerima pengajuan.
        $this->actingAs($this->responderUser)
            ->put('/seller/products/'.$requested->public_id, [
                'name' => $requested->name,
                'stock' => $requested->stock,
                'price' => 9_000_000,
                'category' => $requested->category,
                'description' => $requested->description,
            ]);

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/accept');

        $tradeIn->refresh();

        $this->assertSame(
            TradeInRequest::STATUS_PENDING,
            $tradeIn->status,
            'Penerima berhasil menyetujui setelah menaikkan harga produknya sendiri.'
        );
        $this->assertEquals(0, $tradeIn->additional_cash);
    }

    /**
     * Setelah disetujui, selisihnya dibekukan. Harga produk yang terkunci
     * tukar tambah karena itu tidak boleh berubah lagi — kalau boleh, kartu tukar tambah
     * akan menampilkan harga yang bertentangan dengan nominal yang ditagih.
     */
    public function test_harga_produk_terkunci_tukar_tambah_tidak_dapat_diubah(): void
    {
        $offered = $this->makeProduct($this->requesterStore, 'Gramofon', 1_000_000);
        $requested = $this->makeProduct($this->responderStore, 'Guci Antik', 3_000_000);

        $this->actingAs($this->requesterUser)->post(
            '/seller/tukar-tambah/'.$requested->public_id,
            ['offered_product_id' => $offered->public_id]
        );

        $tradeIn = TradeInRequest::firstOrFail();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/accept');

        $this->assertNotNull($offered->fresh()->locked_for_trade_in_id);

        $this->actingAs($this->requesterUser)
            ->put('/seller/products/'.$offered->public_id, [
                'name' => $offered->name,
                'stock' => $offered->stock,
                'price' => 9_000_000,
                'category' => $offered->category,
                'description' => $offered->description,
            ]);

        $this->assertEquals(
            1_000_000,
            $offered->fresh()->price,
            'Harga produk yang sedang terikat tukar tambah berhasil diubah.'
        );
    }

    /**
     * Turunan yang sama: selisih menjadi nol setelah harga disamakan, sehingga
     * tidak boleh ada pihak yang ditagih sama sekali.
     */
    public function test_tukar_tambah_tidak_menagih_siapa_pun_bila_harga_menjadi_sama(): void
    {
        $offered = $this->makeProduct($this->requesterStore, 'Gramofon', 1_000_000);
        $requested = $this->makeProduct($this->responderStore, 'Guci Antik', 3_000_000);

        $this->actingAs($this->requesterUser)->post(
            '/seller/tukar-tambah/'.$requested->public_id,
            ['offered_product_id' => $offered->public_id]
        );

        $tradeIn = TradeInRequest::firstOrFail();

        // Harga disamakan sebelum disetujui.
        $offered->forceFill(['price' => 3_000_000])->save();

        $this->actingAs($this->responderUser)
            ->post('/seller/tukar-tambah/'.$tradeIn->public_id.'/accept');

        $tradeIn->refresh();

        $this->assertEquals(0, $tradeIn->additional_cash);
        $this->assertNull($tradeIn->payer_role);
        $this->assertSame(TradeInRequest::PAYMENT_NOT_REQUIRED, $tradeIn->payment_status);
        $this->assertSame(TradeInRequest::STATUS_SHIPPING, $tradeIn->status);
    }
}
