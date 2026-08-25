<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression test untuk temuan V10-02 (dan T-03 dari audit pertama).
 *
 * Halaman profil mengganti password tanpa pernah menanyakan password lama, dan
 * mengganti alamat surel tanpa pembuktian apa pun. Siapa pun yang sempat
 * memegang sesi yang masih terbuka dapat mengunci pemilik aslinya keluar
 * secara permanen — dan untuk akun seller, ikut menentukan ke mana uang
 * pencairan dikirim.
 */
class ProfileSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'username' => 'pemilik',
            'first_name' => 'Pemilik',
            'last_name' => 'Akun',
            'email' => 'pemilik@vinstore.test',
            'phone' => '081100011122',
            'address' => 'Jl. Uji',
            'password' => Hash::make('rahasia-lama'),
            'role' => 'user',
        ]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'username' => $this->user->username,
            'first_name' => $this->user->first_name,
            'last_name' => $this->user->last_name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
            'address' => $this->user->address,
        ], $override);
    }

    public function test_ganti_password_ditolak_tanpa_password_lama(): void
    {
        $this->actingAs($this->user)
            ->post('/profile', $this->payload([
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ]))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('rahasia-lama', $this->user->fresh()->password));
    }

    public function test_ganti_email_ditolak_tanpa_password_lama(): void
    {
        $this->actingAs($this->user)
            ->post('/profile', $this->payload(['email' => 'penyerang@vinstore.test']))
            ->assertSessionHasErrors('current_password');

        $this->assertSame('pemilik@vinstore.test', $this->user->fresh()->email);
    }

    public function test_password_lama_yang_salah_ditolak(): void
    {
        $this->actingAs($this->user)
            ->post('/profile', $this->payload([
                'current_password' => 'tebakan-ngawur',
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ]))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('rahasia-lama', $this->user->fresh()->password));
    }

    public function test_ganti_password_berhasil_dengan_password_lama_yang_benar(): void
    {
        $this->actingAs($this->user)
            ->post('/profile', $this->payload([
                'current_password' => 'rahasia-lama',
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ]))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('password-baru', $this->user->fresh()->password));
    }

    /**
     * Penjagaannya tidak boleh menghalangi perubahan yang tidak menyentuh
     * kunci masuk. Tanpa test ini, "wajib password lama" mudah melebar menjadi
     * "tidak bisa mengganti alamat pun".
     */
    public function test_ubah_data_lain_tetap_bisa_tanpa_password_lama(): void
    {
        $this->actingAs($this->user)
            ->post('/profile', $this->payload(['address' => 'Jl. Baru No. 10']))
            ->assertSessionHas('success');

        $this->assertSame('Jl. Baru No. 10', $this->user->fresh()->address);
    }

    /**
     * Akun yang masuk lewat Google saja belum punya password lokal. Meminta
     * password yang tidak pernah mereka buat sama dengan menutup halamannya.
     */
    public function test_akun_google_tanpa_password_lokal_tidak_dimintai_password_lama(): void
    {
        $google = User::create([
            'username' => 'akun-google',
            'first_name' => 'Akun',
            'last_name' => 'Google',
            'email' => 'google@vinstore.test',
            'phone' => '081199988877',
            'address' => 'Jl. Google',
            'password' => null,
            'google_id' => '1234567890',
            'role' => 'user',
        ]);

        $this->actingAs($google)
            ->post('/profile', [
                'username' => $google->username,
                'first_name' => $google->first_name,
                'last_name' => $google->last_name,
                'email' => 'google-baru@vinstore.test',
                'phone' => $google->phone,
                'address' => $google->address,
            ])
            ->assertSessionHas('success');

        $this->assertSame('google-baru@vinstore.test', $google->fresh()->email);
    }
}
