<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\LoginController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Pembatasan percobaan masuk (temuan V9-02).
 *
 * Sebelum perbaikan, rute login tidak memakai `throttle` dan controllernya tidak
 * menghitung apa pun: dua puluh lima password salah berturut-turut untuk satu
 * akun semuanya dijawab 302, tanpa penguncian, tanpa jeda. Aplikasi ini memegang
 * saldo toko, nomor rekening pada pengajuan pencairan, dan alamat rumah pembeli.
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login|korban@vinstore.test|127.0.0.1');

        $this->pengguna = User::create([
            'username' => 'korban',
            'first_name' => 'Korban',
            'last_name' => 'Uji',
            'email' => 'korban@vinstore.test',
            'phone' => '08110009999',
            'address' => 'Jl. Antik No. 1',
            'password' => 'password123',
            'role' => 'user',
        ]);
    }

    private function cobaMasuk(string $password)
    {
        return $this->post('/login', [
            'login' => $this->pengguna->email,
            'password' => $password,
        ]);
    }

    public function test_percobaan_berlebihan_dikunci_sementara(): void
    {
        for ($i = 0; $i < LoginController::MAX_ATTEMPTS; $i++) {
            $this->cobaMasuk('salah'.$i);
        }

        $this->cobaMasuk('salahlagi')
            ->assertSessionHasErrors('login');

        $this->assertStringContainsString(
            'Terlalu banyak percobaan masuk',
            session('errors')->first('login')
        );

        $this->assertGuest();
    }

    /**
     * Penguncian tidak boleh bisa ditembus hanya dengan menebak password yang
     * benar — kalau bisa, ia tidak menahan apa pun.
     */
    public function test_password_benar_pun_ditolak_selama_masih_terkunci(): void
    {
        for ($i = 0; $i < LoginController::MAX_ATTEMPTS; $i++) {
            $this->cobaMasuk('salah'.$i);
        }

        $this->cobaMasuk('password123');

        $this->assertGuest();
    }

    public function test_masuk_yang_berhasil_menghapus_penghitungnya(): void
    {
        $this->cobaMasuk('salah1');
        $this->cobaMasuk('salah2');

        $this->cobaMasuk('password123');
        $this->assertAuthenticatedAs($this->pengguna->fresh());

        $this->post('/logout');

        // Penghitungnya bersih lagi: percobaan gagal berikutnya dimulai dari nol.
        for ($i = 0; $i < LoginController::MAX_ATTEMPTS; $i++) {
            $this->cobaMasuk('salah'.$i)->assertSessionHasErrors('login');
        }

        $this->assertStringNotContainsString(
            'Terlalu banyak percobaan masuk',
            session('errors')->first('login')
        );
    }

    /**
     * Penguncian dipatok pada akun yang dicoba, bukan pada seluruh rute: satu
     * akun yang sedang diserang tidak boleh ikut mengunci pengguna lain.
     */
    public function test_akun_lain_tidak_ikut_terkunci(): void
    {
        $lain = User::create([
            'username' => 'penggunalain',
            'first_name' => 'Pengguna',
            'last_name' => 'Lain',
            'email' => 'lain@vinstore.test',
            'phone' => '08110008888',
            'address' => 'Jl. Antik No. 2',
            'password' => 'password123',
            'role' => 'user',
        ]);

        for ($i = 0; $i < LoginController::MAX_ATTEMPTS; $i++) {
            $this->cobaMasuk('salah'.$i);
        }

        $this->post('/login', ['login' => $lain->email, 'password' => 'password123']);

        $this->assertAuthenticatedAs($lain->fresh());
    }
}
