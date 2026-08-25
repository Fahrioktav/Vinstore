<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Percakapan langsung pembeli-penjual.
 *
 * Yang paling ditekankan berkas ini adalah batas aksesnya. Sebuah percakapan
 * hanya punya dua pihak, dan setiap kebocoran di sini berarti orang asing ikut
 * membaca tawar-menawar orang lain — termasuk nomor pesanan dan nama barang
 * yang menempel sebagai konteks.
 */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $pembeli;

    private User $penjual;

    private Store $toko;

    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pembeli = $this->buatUser('pembeli', 'pembeli@vinstore.test', 'user');
        $this->penjual = $this->buatUser('penjual', 'penjual@vinstore.test', 'seller');

        $this->toko = Store::create([
            'user_id' => $this->penjual->id,
            'store_name' => 'Toko Antik',
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);

        $this->produk = Product::create([
            'store_id' => $this->toko->id,
            'name' => 'Guci Ming',
            'stock' => 3,
            'price' => 750000,
            'category' => 'Keramik',
            'description' => 'Guci antik',
            'approval_status' => Product::STATUS_APPROVED,
        ]);
    }

    private function buatUser(string $username, string $email, string $role): User
    {
        return User::create([
            'username' => $username,
            'first_name' => 'Uji',
            'last_name' => ucfirst($role),
            'email' => $email,
            'phone' => '0811'.random_int(100000, 999999),
            'address' => 'Jl. Uji No. 1',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function pesanan(array $override = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $this->pembeli->id,
            'product_id' => $this->produk->id,
            'product_name' => $this->produk->name,
            'store_id' => $this->toko->id,
            'store_name' => $this->toko->store_name,
            'quantity' => 1,
            'price' => 750000,
            'status' => 'Waiting',
            'payment_status' => 'paid',
        ], $override));
    }

    // ================= MEMBUKA PERCAKAPAN =================

    public function test_pembeli_membuka_percakapan_dari_halaman_produk(): void
    {
        $response = $this->actingAs($this->pembeli)
            ->get('/chat/produk/'.$this->produk->public_id);

        $conversation = Conversation::first();

        $this->assertNotNull($conversation);
        $this->assertSame($this->toko->id, $conversation->store_id);
        $this->assertSame($this->pembeli->id, $conversation->buyer_id);
        $response->assertRedirect('/chat/'.$conversation->public_id.'?produk='.$this->produk->public_id);
    }

    /**
     * Menekan tombolnya dua kali tidak boleh melahirkan utas kedua — tabelnya
     * unik per (store_id, buyer_id) dan akan menolak dengan galat basis data.
     */
    public function test_membuka_dua_kali_tidak_menggandakan_percakapan(): void
    {
        $this->actingAs($this->pembeli)->get('/chat/produk/'.$this->produk->public_id);
        $this->actingAs($this->pembeli)->get('/chat/produk/'.$this->produk->public_id);

        $this->assertSame(1, Conversation::count());
    }

    public function test_penjual_tidak_dapat_chat_tokonya_sendiri(): void
    {
        $this->actingAs($this->penjual)
            ->get('/chat/produk/'.$this->produk->public_id)
            ->assertSessionHas('error');

        $this->assertSame(0, Conversation::count());
    }

    public function test_pembeli_membuka_percakapan_dari_pesanan(): void
    {
        $order = $this->pesanan();

        $this->actingAs($this->pembeli)
            ->get('/chat/pesanan/'.$order->public_id)
            ->assertRedirect('/chat/'.Conversation::first()->public_id.'?pesanan='.$order->public_id);
    }

    public function test_pesanan_milik_orang_lain_tidak_dapat_dijadikan_pintu_masuk(): void
    {
        $order = $this->pesanan();
        $penyusup = $this->buatUser('penyusup', 'penyusup@vinstore.test', 'user');

        $this->actingAs($penyusup)
            ->get('/chat/pesanan/'.$order->public_id)
            ->assertNotFound();
    }

    public function test_tamu_diarahkan_untuk_masuk(): void
    {
        $this->get('/chat/produk/'.$this->produk->public_id)
            ->assertRedirect('/login');
    }

    // ================= MENGIRIM PESAN =================

    public function test_pembeli_mengirim_pesan_dengan_konteks_produk(): void
    {
        $conversation = Conversation::between($this->toko, $this->pembeli);

        $this->actingAs($this->pembeli)
            ->postJson('/chat/'.$conversation->public_id.'/messages', [
                'body' => 'Apakah guci ini masih ada?',
                'produk' => $this->produk->public_id,
            ])
            ->assertOk()
            ->assertJsonPath('message.context.type', 'product')
            ->assertJsonPath('message.context.name', 'Guci Ming');

        $this->assertSame(1, Message::count());
        $this->assertSame($this->produk->id, Message::first()->product_id);
    }

    public function test_penjual_dapat_membalas(): void
    {
        $conversation = Conversation::between($this->toko, $this->pembeli);

        $this->actingAs($this->penjual)
            ->postJson('/chat/'.$conversation->public_id.'/messages', ['body' => 'Masih ada, silakan.'])
            ->assertOk();

        $this->assertSame($this->penjual->id, Message::first()->sender_id);
    }

    public function test_orang_luar_tidak_dapat_mengirim_ke_percakapan_orang_lain(): void
    {
        $conversation = Conversation::between($this->toko, $this->pembeli);
        $penyusup = $this->buatUser('penyusup', 'penyusup@vinstore.test', 'user');

        $this->actingAs($penyusup)
            ->postJson('/chat/'.$conversation->public_id.'/messages', ['body' => 'Halo'])
            ->assertForbidden();

        $this->assertSame(0, Message::count());
    }

    public function test_orang_luar_tidak_dapat_membaca_percakapan_orang_lain(): void
    {
        $conversation = Conversation::between($this->toko, $this->pembeli);
        $penyusup = $this->buatUser('penyusup', 'penyusup@vinstore.test', 'user');

        $this->actingAs($penyusup)
            ->get('/chat/'.$conversation->public_id)
            ->assertForbidden();
    }

    /**
     * Pesanan orang lain tidak boleh ditempelkan sebagai konteks. Kalau bisa,
     * nama barang pada pesanan itu ikut terbaca penjual di seberang.
     */
    public function test_konteks_pesanan_milik_orang_lain_diabaikan(): void
    {
        $orangLain = $this->buatUser('oranglain', 'oranglain@vinstore.test', 'user');
        $pesananOrangLain = $this->pesanan(['user_id' => $orangLain->id]);

        $conversation = Conversation::between($this->toko, $this->pembeli);

        $this->actingAs($this->pembeli)
            ->postJson('/chat/'.$conversation->public_id.'/messages', [
                'body' => 'Halo',
                'pesanan' => $pesananOrangLain->public_id,
            ])
            ->assertOk()
            ->assertJsonPath('message.context', null);

        $this->assertNull(Message::first()->order_id);
    }

    /**
     * Produk toko lain tidak ada urusannya dengan utas ini.
     */
    public function test_konteks_produk_toko_lain_diabaikan(): void
    {
        $penjualLain = $this->buatUser('penjual2', 'penjual2@vinstore.test', 'seller');
        $tokoLain = Store::create([
            'user_id' => $penjualLain->id,
            'store_name' => 'Toko Lain',
            'category' => 'Koin',
            'description' => 'Toko lain',
            'location' => 'Solo',
        ]);
        $produkLain = Product::create([
            'store_id' => $tokoLain->id,
            'name' => 'Koin VOC',
            'stock' => 1,
            'price' => 200000,
            'category' => 'Koin',
            'description' => 'Koin antik',
            'approval_status' => Product::STATUS_APPROVED,
        ]);

        $conversation = Conversation::between($this->toko, $this->pembeli);

        $this->actingAs($this->pembeli)
            ->postJson('/chat/'.$conversation->public_id.'/messages', [
                'body' => 'Halo',
                'produk' => $produkLain->public_id,
            ])
            ->assertOk()
            ->assertJsonPath('message.context', null);
    }

    public function test_pesan_kosong_ditolak(): void
    {
        $conversation = Conversation::between($this->toko, $this->pembeli);

        $this->actingAs($this->pembeli)
            ->postJson('/chat/'.$conversation->public_id.'/messages', ['body' => ''])
            ->assertStatus(422);
    }

    public function test_pembeli_tidak_dapat_mengirim_ke_penjual_nonaktif(): void
    {
        $conversation = Conversation::between($this->toko, $this->pembeli);
        $this->penjual->deactivate('Melanggar aturan');

        $this->actingAs($this->pembeli)
            ->postJson('/chat/'.$conversation->public_id.'/messages', ['body' => 'Halo'])
            ->assertStatus(422);

        $this->assertSame(0, Message::count());
    }

    // ================= KOTAK MASUK =================

    public function test_kotak_masuk_penjual_hanya_memuat_percakapan_tokonya(): void
    {
        $pembeliLain = $this->buatUser('pembeli2', 'pembeli2@vinstore.test', 'user');

        foreach ([$this->pembeli, $pembeliLain] as $orang) {
            $conversation = Conversation::between($this->toko, $orang);
            $this->actingAs($orang)->postJson(
                '/chat/'.$conversation->public_id.'/messages',
                ['body' => 'Halo dari '.$orang->username]
            );
        }

        $response = $this->actingAs($this->penjual)->get('/seller/chat');

        $response->assertOk();
        $this->assertCount(2, $response->viewData('page')['props']['threads']);
    }

    public function test_kotak_masuk_pembeli_tidak_memuat_percakapan_orang_lain(): void
    {
        $pembeliLain = $this->buatUser('pembeli2', 'pembeli2@vinstore.test', 'user');
        $conversation = Conversation::between($this->toko, $pembeliLain);
        $this->actingAs($pembeliLain)->postJson(
            '/chat/'.$conversation->public_id.'/messages',
            ['body' => 'Rahasia']
        );

        $response = $this->actingAs($this->pembeli)->get('/chat');

        $response->assertOk();
        $this->assertCount(0, $response->viewData('page')['props']['threads']);
    }

    /**
     * Utas yang baru dibuat lewat tombol "Chat Penjual" belum punya pesan, jadi
     * ia tidak ikut di daftar — tetapi tetap harus bisa dibuka. Kalau tidak,
     * pembeli sampai di halaman chat kosong tanpa kotak ketik.
     */
    public function test_percakapan_tanpa_pesan_tetap_dapat_dibuka(): void
    {
        $conversation = Conversation::between($this->toko, $this->pembeli);

        $response = $this->actingAs($this->pembeli)->get('/chat/'.$conversation->public_id);

        $props = $response->viewData('page')['props'];

        $response->assertOk();
        $this->assertCount(0, $props['threads']);
        $this->assertSame($conversation->public_id, $props['selected']['public_id']);
    }

    public function test_membuka_percakapan_menandai_pesan_lawan_sudah_dibaca(): void
    {
        $conversation = Conversation::between($this->toko, $this->pembeli);

        $this->actingAs($this->penjual)->postJson(
            '/chat/'.$conversation->public_id.'/messages',
            ['body' => 'Selamat datang']
        );

        $this->assertNull(Message::first()->read_at);

        $this->actingAs($this->pembeli)->get('/chat/'.$conversation->public_id);

        $this->assertNotNull(Message::first()->fresh()->read_at);
    }

    public function test_pesan_sendiri_tidak_ikut_ditandai_terbaca(): void
    {
        $conversation = Conversation::between($this->toko, $this->pembeli);

        $this->actingAs($this->pembeli)->postJson(
            '/chat/'.$conversation->public_id.'/messages',
            ['body' => 'Halo']
        );

        $this->actingAs($this->pembeli)->get('/chat/'.$conversation->public_id);

        $this->assertNull(Message::first()->fresh()->read_at);
    }

    public function test_validator_tidak_memiliki_akses_ke_chat(): void
    {
        $validator = $this->buatUser('validator1', 'validator1@vinstore.test', 'validator');

        $this->actingAs($validator)->get('/chat')->assertRedirect('/validator/dashboard');
    }

    public function test_admin_tidak_dapat_membaca_percakapan_jual_beli(): void
    {
        $admin = $this->buatUser('admin1', 'admin1@vinstore.test', 'admin');
        $conversation = Conversation::between($this->toko, $this->pembeli);

        $this->actingAs($admin)
            ->get('/chat/'.$conversation->public_id)
            ->assertRedirect('/admin/dashboard');
    }
}
