<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Penanda sesi bahwa perjalanan ke Google kali ini bermaksud MENAUTKAN,
     * bukan masuk. Callback-nya satu untuk keduanya, sebab hanya satu alamat
     * yang terdaftar sebagai Authorized Redirect URI di Google Cloud Console.
     */
    private const LINK_INTENT_KEY = 'google_link_intent';

    private function googleRedirectUrl(): string
    {
        return url('/auth/google/callback');
    }

    /**
     * Redirect ke Google OAuth untuk masuk.
     */
    public function redirectToGoogle()
    {
        session()->forget(self::LINK_INTENT_KEY);

        return Socialite::driver('google')
            ->redirectUrl($this->googleRedirectUrl())
            ->redirect();
    }

    /**
     * Redirect ke Google OAuth untuk menautkan akun yang sedang login.
     */
    public function redirectToLinkGoogle()
    {
        session()->put(self::LINK_INTENT_KEY, true);

        return Socialite::driver('google')
            ->redirectUrl($this->googleRedirectUrl())
            ->redirect();
    }

    /**
     * Handle callback dari Google — melayani masuk maupun menautkan.
     */
    public function handleGoogleCallback()
    {
        $bermaksudMenautkan = (bool) session()->pull(self::LINK_INTENT_KEY, false);

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl($this->googleRedirectUrl())
                ->user();
        } catch (\Exception $e) {
            return $this->gagal($bermaksudMenautkan, 'Gagal terhubung dengan Google. Silakan coba lagi.');
        }

        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();

        if (empty($googleId) || empty($email)) {
            return $this->gagal($bermaksudMenautkan, 'Google tidak mengirimkan data akun yang lengkap. Silakan coba lagi.');
        }

        // Google menyertakan `email_verified` pada setiap jawabannya. Alamat
        // yang belum terverifikasi tidak membuktikan kepemilikan apa pun, dan
        // di aplikasi ini alamat surel adalah kunci pemulihan akun.
        if (($googleUser->user['email_verified'] ?? true) === false) {
            return $this->gagal(
                $bermaksudMenautkan,
                'Alamat email akun Google Anda belum terverifikasi oleh Google, sehingga belum dapat dipakai di sini.'
            );
        }

        return $bermaksudMenautkan
            ? $this->tautkan($googleId, $email)
            : $this->masuk($googleId, $email, $googleUser->getName() ?: $googleUser->getNickname() ?: $email);
    }

    /**
     * Masuk lewat Google.
     *
     * Pencocokannya HANYA lewat `google_id`. Dulu akun juga dicocokkan lewat
     * alamat surel, dan itulah lubangnya: aplikasi ini tidak pernah memverifikasi
     * surel saat pendaftaran biasa, sehingga siapa pun bisa mendaftar memakai
     * alamat orang lain. Ketika pemilik asli alamat itu kemudian menekan "Login
     * dengan Google", ia justru masuk ke akun milik si pendaftar — yang tetap
     * memegang passwordnya, dan ikut membaca pesanan, alamat, serta nomor
     * rekening pencairan di dalamnya (temuan T-01).
     *
     * Akun lama yang memang ingin memakai Google tetap punya jalan: masuk
     * dengan password, lalu menautkannya sendiri dari halaman profil. Penautan
     * yang dilakukan sendiri adalah bukti kepemilikan yang tidak dimiliki
     * pencocokan alamat surel.
     */
    private function masuk(string $googleId, string $email, string $name)
    {
        $user = User::where('google_id', $googleId)->first();

        if (! $user) {
            $pemilikSurel = User::where('email', $email)->first();

            if ($pemilikSurel) {
                return redirect()->route('login.form')->withErrors([
                    'google' => 'Alamat email ini sudah terdaftar dengan password. '
                        .'Masuk memakai password Anda, lalu hubungkan akun Google dari halaman Profil '
                        .'agar lain kali bisa langsung masuk lewat Google.',
                ]);
            }

            $user = $this->buatAkunBaru($googleId, $email, $name);
        }

        // Akun nonaktif tidak boleh masuk lewat jalur mana pun. Tanpa
        // pemeriksaan ini, penonaktifan bisa dilewati cukup dengan menekan
        // "Login dengan Google".
        if ($user->isDeactivated()) {
            return redirect()->route('login.form')
                ->withErrors(['google' => LoginController::deactivatedMessage($user)]);
        }

        Auth::login($user);

        return match ($user->role) {
            'admin' => redirect()->route('admin.dashboard'),
            'seller' => redirect()->route('seller.dashboard'),
            'validator' => redirect()->route('validator.dashboard'),
            default => redirect()->intended('/'),
        };
    }

    /**
     * Tautkan akun Google ke akun yang sedang login.
     */
    private function tautkan(string $googleId, string $email)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login.form')
                ->withErrors(['google' => 'Sesi Anda sudah berakhir. Silakan masuk lagi sebelum menghubungkan akun Google.']);
        }

        $pemakaiLain = User::where('google_id', $googleId)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($pemakaiLain) {
            return redirect()->route('profile.edit')
                ->with('error', 'Akun Google ini sudah terhubung ke akun Vinstore yang lain.');
        }

        if ($user->google_id && $user->google_id !== $googleId) {
            return redirect()->route('profile.edit')
                ->with('error', 'Akun Anda sudah terhubung ke akun Google yang berbeda. Lepaskan tautannya terlebih dahulu.');
        }

        $user->forceFill(['google_id' => $googleId])->save();

        return redirect()->route('profile.edit')
            ->with('success', 'Akun Google '.$email.' berhasil dihubungkan. Lain kali Anda bisa masuk lewat tombol Google.');
    }

    /**
     * Lepaskan tautan akun Google.
     *
     * Hanya boleh bila akunnya punya password. Akun yang lahir dari Google tidak
     * punya password sama sekali; melepas tautannya berarti mengunci pemiliknya
     * di luar tanpa satu pun cara masuk — dan pemulihan lewat surel pun belum
     * hidup (temuan V11-03).
     */
    public function unlinkGoogle()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->google_id) {
            return back()->with('error', 'Akun Anda memang belum terhubung ke akun Google mana pun.');
        }

        if (empty($user->password)) {
            return back()->with(
                'error',
                'Akun Anda masuk lewat Google dan belum punya password. '
                .'Buat password terlebih dahulu di halaman ini, baru tautan Google boleh dilepas.'
            );
        }

        $user->forceFill(['google_id' => null])->save();

        return back()->with('success', 'Tautan akun Google dilepas. Mulai sekarang masuklah memakai email dan password Anda.');
    }

    private function buatAkunBaru(string $googleId, string $email, string $name): User
    {
        $nameParts = explode(' ', trim($name), 2);

        return User::create([
            'username' => $this->generateUniqueUsername($email),
            'first_name' => $nameParts[0],
            'last_name' => $nameParts[1] ?? '',
            'email' => $email,
            'google_id' => $googleId,
            'phone' => $this->generateGooglePlaceholderPhone($googleId), // User bisa update nanti
            'address' => 'Belum diisi', // Default, user bisa update nanti
            'password' => null, // Tidak perlu password untuk Google login
            'role' => 'user', // Default role
        ]);
    }

    private function gagal(bool $bermaksudMenautkan, string $pesan)
    {
        if ($bermaksudMenautkan && Auth::check()) {
            return redirect()->route('profile.edit')->with('error', $pesan);
        }

        return redirect()->route('login.form')->withErrors(['google' => $pesan]);
    }

    /**
     * Generate username unik dari email
     */
    private function generateUniqueUsername($email)
    {
        $baseUsername = explode('@', $email)[0];
        $username = $baseUsername;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $baseUsername.$counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Generate phone placeholder unik karena kolom phone wajib unik.
     */
    private function generateGooglePlaceholderPhone($googleId)
    {
        $basePhone = 'google-'.$googleId;
        $phone = $basePhone;
        $counter = 1;

        while (User::where('phone', $phone)->exists()) {
            $phone = $basePhone.'-'.$counter;
            $counter++;
        }

        return $phone;
    }
}
