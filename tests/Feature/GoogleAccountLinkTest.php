<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Regression test untuk temuan T-01.
 *
 * Callback Google dulu mencocokkan akun lewat `google_id` ATAU alamat surel,
 * lalu menautkan `google_id` ke akun yang cocok. Aplikasi ini tidak pernah
 * memverifikasi surel saat pendaftaran biasa, sehingga siapa pun bisa
 * mendaftar memakai alamat orang lain — dan ketika pemilik alamat itu menekan
 * "Login dengan Google", ia justru masuk ke akun si pendaftar, yang tetap
 * memegang passwordnya.
 *
 * Sekarang pencocokannya hanya lewat `google_id`. Akun lama yang ingin memakai
 * Google menautkannya sendiri dari halaman profil — bukti kepemilikan yang
 * tidak dimiliki pencocokan alamat surel.
 */
class GoogleAccountLinkTest extends TestCase
{
    use RefreshDatabase;

    private const GOOGLE_ID = '1234567890';

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function fakeGoogleUser(string $email, bool $emailVerified = true): void
    {
        $socialiteUser = new SocialiteUser;
        $socialiteUser->id = self::GOOGLE_ID;
        $socialiteUser->email = $email;
        $socialiteUser->name = 'Pemilik Asli';
        $socialiteUser->user = ['email_verified' => $emailVerified];

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function makeLocalUser(string $email): User
    {
        return User::create([
            'username' => 'pemilik',
            'first_name' => 'Pemilik',
            'last_name' => 'Akun',
            'email' => $email,
            'phone' => '081100011122',
            'address' => 'Jl. Uji',
            'password' => Hash::make('rahasia-lama'),
            'role' => 'user',
        ]);
    }

    // ------------------------------------------------------------------
    // Inti temuan
    // ------------------------------------------------------------------

    public function test_login_google_tidak_menautkan_diri_ke_akun_berpassword_yang_alamatnya_sama(): void
    {
        $korban = $this->makeLocalUser('korban@vinstore.test');

        $this->fakeGoogleUser('korban@vinstore.test');

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login.form'));

        $this->assertGuest();
        $this->assertNull($korban->fresh()->google_id);
    }

    public function test_alasan_penolakannya_menunjukkan_jalan_keluarnya(): void
    {
        $this->makeLocalUser('korban@vinstore.test');
        $this->fakeGoogleUser('korban@vinstore.test');

        $this->get('/auth/google/callback')
            ->assertSessionHasErrors('google');

        $pesan = session('errors')->first('google');

        $this->assertStringContainsString('password', $pesan);
        $this->assertStringContainsString('Profil', $pesan);
    }

    // ------------------------------------------------------------------
    // Jalur yang harus tetap berfungsi
    // ------------------------------------------------------------------

    public function test_akun_baru_tetap_dibuat_bila_alamatnya_belum_terdaftar(): void
    {
        $this->fakeGoogleUser('baru@vinstore.test');

        $this->get('/auth/google/callback');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'baru@vinstore.test',
            'google_id' => self::GOOGLE_ID,
        ]);
    }

    public function test_akun_yang_sudah_tertaut_tetap_bisa_masuk(): void
    {
        $user = $this->makeLocalUser('pemilik@vinstore.test');
        $user->forceFill(['google_id' => self::GOOGLE_ID])->save();

        $this->fakeGoogleUser('pemilik@vinstore.test');

        $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_surel_google_yang_belum_terverifikasi_ditolak(): void
    {
        $this->fakeGoogleUser('belumverif@vinstore.test', emailVerified: false);

        $this->get('/auth/google/callback')
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'belumverif@vinstore.test']);
    }

    // ------------------------------------------------------------------
    // Jalan keluar: menautkan sendiri dari halaman profil
    // ------------------------------------------------------------------

    public function test_pemilik_akun_dapat_menautkan_google_dari_halaman_profil(): void
    {
        $user = $this->makeLocalUser('pemilik@vinstore.test');

        $this->fakeGoogleUser('pemilik@vinstore.test');

        $this->actingAs($user)->get('/auth/google/link');
        $this->actingAs($user)->get('/auth/google/callback')
            ->assertRedirect(route('profile.edit'));

        $this->assertSame(self::GOOGLE_ID, $user->fresh()->google_id);
    }

    public function test_akun_google_yang_sudah_dipakai_orang_lain_tidak_dapat_ditautkan(): void
    {
        $pemilikPertama = $this->makeLocalUser('pertama@vinstore.test');
        $pemilikPertama->forceFill(['google_id' => self::GOOGLE_ID])->save();

        $orangLain = User::create([
            'username' => 'oranglain',
            'first_name' => 'Orang',
            'last_name' => 'Lain',
            'email' => 'lain@vinstore.test',
            'phone' => '081199988877',
            'address' => 'Jl. Lain',
            'password' => Hash::make('rahasia'),
            'role' => 'user',
        ]);

        $this->fakeGoogleUser('pertama@vinstore.test');

        $this->actingAs($orangLain)->get('/auth/google/link');
        $this->actingAs($orangLain)->get('/auth/google/callback')
            ->assertSessionHas('error');

        $this->assertNull($orangLain->fresh()->google_id);
        $this->assertSame(self::GOOGLE_ID, $pemilikPertama->fresh()->google_id);
    }

    public function test_tautan_google_dapat_dilepas_bila_akunnya_punya_password(): void
    {
        $user = $this->makeLocalUser('pemilik@vinstore.test');
        $user->forceFill(['google_id' => self::GOOGLE_ID])->save();

        $this->actingAs($user)
            ->delete('/auth/google/link')
            ->assertSessionHas('success');

        $this->assertNull($user->fresh()->google_id);
    }

    /**
     * Akun yang lahir dari Google tidak punya password. Melepas tautannya
     * berarti mengunci pemiliknya di luar tanpa satu pun cara masuk.
     */
    public function test_tautan_google_tidak_dapat_dilepas_bila_akunnya_tanpa_password(): void
    {
        $user = User::create([
            'username' => 'akun-google',
            'first_name' => 'Akun',
            'last_name' => 'Google',
            'email' => 'google@vinstore.test',
            'phone' => '081155566677',
            'address' => 'Jl. Google',
            'password' => null,
            'google_id' => self::GOOGLE_ID,
            'role' => 'user',
        ]);

        $this->actingAs($user)
            ->delete('/auth/google/link')
            ->assertSessionHas('error');

        $this->assertSame(self::GOOGLE_ID, $user->fresh()->google_id);
    }
}
