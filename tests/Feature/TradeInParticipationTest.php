<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\TradeInRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Siapa yang boleh ikut tukar tambah.
 *
 * Tukar tambah adalah pertukaran ANTAR SELLER: dua toko saling menyerahkan
 * barang, dan selisih harganya diselesaikan dengan uang. Pembeli tidak punya
 * barang untuk ditukarkan, jadi ia tidak pernah menjadi pihak di dalamnya.
 *
 * Aturannya sudah dijalankan kode sejak lama — seluruh rutenya berada di grup
 * `role:seller`, dan controller-nya menolak produk milik toko sendiri — tetapi
 * belum pernah ada yang menguncinya. Berkas ini menutup celah itu.
 *
 * Alasannya konkret: aturan serupa pada lelang juga "sudah ada" tetapi
 * cakupannya kelewat sempit (seller hanya ditahan di lelang tokonya sendiri),
 * dan yang justru mengunci perilaku keliru itu adalah sebuah pengujian. Aturan
 * yang tidak diuji gampang bergeser tanpa ada yang menyadarinya.
 */
class TradeInParticipationTest extends TestCase
{
    use RefreshDatabase;

    private User $sellerA;

    private User $sellerB;

    private Store $storeA;

    private Store $storeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sellerA = $this->makeUser('sellera', 'seller');
        $this->sellerB = $this->makeUser('sellerb', 'seller');

        $this->storeA = $this->makeStore($this->sellerA, 'Toko A');
        $this->storeB = $this->makeStore($this->sellerB, 'Toko B');
    }

    private function makeUser(string $username, string $role): User
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

    private function makeProduct(Store $store, string $name): Product
    {
        return Product::create([
            'store_id' => $store->id,
            'name' => $name,
            'stock' => 1,
            'price' => 1_000_000,
            'category' => 'Antik',
            'description' => 'Barang antik',
            'approval_status' => Product::STATUS_APPROVED,
            'is_trade_in_enabled' => true,
        ]);
    }

    // ------------------------------------------------------------------
    // Pembeli bukan pihak dalam tukar tambah
    // ------------------------------------------------------------------

    public function test_pembeli_tidak_dapat_membuka_halaman_tukar_tambah(): void
    {
        $this->actingAs($this->makeUser('pembeli', 'user'))
            ->get('/seller/tukar-tambah')
            ->assertRedirect('/');
    }

    public function test_pembeli_tidak_dapat_mengajukan_tukar_tambah(): void
    {
        $produkA = $this->makeProduct($this->storeA, 'Jam Dinding');
        $produkB = $this->makeProduct($this->storeB, 'Sepeda Ontel');

        $this->actingAs($this->makeUser('pembeli2', 'user'))
            ->post('/seller/tukar-tambah/'.$produkB->public_id, [
                'offered_product_id' => $produkA->public_id,
            ])
            ->assertRedirect('/');

        $this->assertSame(0, TradeInRequest::count());
    }

    public function test_validator_dan_admin_juga_bukan_pihak_tukar_tambah(): void
    {
        $this->actingAs($this->makeUser('validatortt', 'validator'))
            ->get('/seller/tukar-tambah')
            ->assertRedirect('/validator/dashboard');

        $this->actingAs($this->makeUser('admintt', 'admin'))
            ->get('/seller/tukar-tambah')
            ->assertRedirect('/admin/dashboard');
    }

    // ------------------------------------------------------------------
    // Lawannya selalu toko lain
    // ------------------------------------------------------------------

    public function test_tidak_dapat_tukar_tambah_dengan_produk_toko_sendiri(): void
    {
        $satu = $this->makeProduct($this->storeA, 'Jam Dinding');
        $dua = $this->makeProduct($this->storeA, 'Radio Tua');

        $this->actingAs($this->sellerA)
            ->post('/seller/tukar-tambah/'.$dua->public_id, [
                'offered_product_id' => $satu->public_id,
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, TradeInRequest::count());
    }

    public function test_produk_yang_ditawarkan_harus_milik_toko_sendiri(): void
    {
        $produkB = $this->makeProduct($this->storeB, 'Sepeda Ontel');
        $produkBLain = $this->makeProduct($this->storeB, 'Mesin Ketik');

        // Seller A mencoba menukar barang yang bukan miliknya.
        $this->actingAs($this->sellerA)
            ->post('/seller/tukar-tambah/'.$produkB->public_id, [
                'offered_product_id' => $produkBLain->public_id,
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, TradeInRequest::count());
    }

    /**
     * Jalur yang sah, sebagai pembanding: dua toko berbeda, masing-masing
     * menyerahkan barangnya sendiri.
     */
    public function test_tukar_tambah_antar_dua_seller_diterima(): void
    {
        $produkA = $this->makeProduct($this->storeA, 'Jam Dinding');
        $produkB = $this->makeProduct($this->storeB, 'Sepeda Ontel');

        $this->actingAs($this->sellerA)
            ->post('/seller/tukar-tambah/'.$produkB->public_id, [
                'offered_product_id' => $produkA->public_id,
            ])
            ->assertSessionHas('success');

        $tradeIn = TradeInRequest::firstOrFail();

        $this->assertSame($this->storeA->id, $tradeIn->requester_store_id);
        $this->assertSame($this->storeB->id, $tradeIn->responder_store_id);
    }

    /**
     * Seller tanpa toko belum bisa menjadi pihak mana pun — tidak ada barang
     * yang bisa ia serahkan.
     */
    public function test_seller_tanpa_toko_tidak_dapat_mengajukan(): void
    {
        $produkB = $this->makeProduct($this->storeB, 'Sepeda Ontel');
        $produkA = $this->makeProduct($this->storeA, 'Jam Dinding');

        $this->actingAs($this->makeUser('sellertanpatoko', 'seller'))
            ->post('/seller/tukar-tambah/'.$produkB->public_id, [
                'offered_product_id' => $produkA->public_id,
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, TradeInRequest::count());
    }
}
