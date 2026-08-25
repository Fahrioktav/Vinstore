<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /** Percobaan gagal yang ditoleransi sebelum akun dikunci sementara. */
    const MAX_ATTEMPTS = 5;

    /** Lama penguncian setelah batas terlampaui, dalam detik. */
    const LOCKOUT_SECONDS = 60;

    public function login(Request $request)
    {
        // Validasi input
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $login = $request->input('login'); // bisa email atau username
        $password = $request->input('password');

        // Penebak password tidak boleh mencoba tanpa batas. Kuncinya gabungan
        // login + alamat IP: satu IP yang menyerang banyak akun tetap tertahan,
        // sementara pengguna sah yang berbagi IP kantor tidak ikut terkunci
        // karena kesalahan orang lain (temuan V9-02).
        $kunci = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($kunci, self::MAX_ATTEMPTS)) {
            return back()->withErrors([
                'login' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '
                    .RateLimiter::availableIn($kunci).' detik.',
            ])->withInput();
        }

        // Tentukan apakah input adalah email atau username
        $fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // Coba otentikasi
        if (Auth::attempt([$fieldType => $login, 'password' => $password])) {
            $user = Auth::user();

            RateLimiter::clear($kunci);

            // Akun nonaktif tidak boleh masuk
            if ($user->isDeactivated()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'login' => self::deactivatedMessage($user),
                ])->withInput();
            }

            $request->session()->regenerate(); // regenerasi session untuk keamanan

            // Cek role user untuk redirect ke dashboard yang sesuai
            if ($user->role === 'admin') {
                return redirect()->route('admin.dashboard');
            } elseif ($user->role === 'seller') {
                return redirect()->route('seller.dashboard');
            } elseif ($user->role === 'validator') {
                return redirect()->route('validator.dashboard');
            } else {
                return redirect()->intended('/');
            }
        }

        // Gagal login
        RateLimiter::hit($kunci, self::LOCKOUT_SECONDS);

        return back()->withErrors([
            'login' => 'Username/Email atau password salah.',
        ])->withInput();
    }

    /**
     * Kunci penghitung percobaan: login yang dicoba, digabung alamat IP peminta.
     */
    private function throttleKey(Request $request): string
    {
        return 'login|'.Str::lower((string) $request->input('login')).'|'.$request->ip();
    }

    /**
     * Pesan untuk akun yang dinonaktifkan. Dipakai jalur password maupun Google
     * supaya keduanya menjelaskan hal yang sama.
     */
    public static function deactivatedMessage(User $user): string
    {
        return 'Akun Anda dinonaktifkan oleh admin.'
            .($user->deactivation_reason ? ' Alasan: '.$user->deactivation_reason : '')
            .' Bila menurut Anda ini keliru, sampaikan lewat halaman Kontak di /contact —'
            .' halaman itu terbuka tanpa perlu masuk.';
    }
}
