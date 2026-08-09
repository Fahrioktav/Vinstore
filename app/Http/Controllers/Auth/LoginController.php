<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        // Validasi input
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $login = $request->input('login'); // bisa email atau username
        $password = $request->input('password');

        // Tentukan apakah input adalah email atau username
        $fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // Coba otentikasi
        if (Auth::attempt([$fieldType => $login, 'password' => $password])) {
            $user = Auth::user();

            // Akun nonaktif tidak boleh masuk. Diperiksa SETELAH kredensialnya
            // benar, bukan sebelumnya: menolak lebih awal akan memberi tahu
            // penebak password bahwa akun itu ada.
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
        return back()->withErrors([
            'login' => 'Username/Email atau password salah.',
        ])->withInput();
    }

    /**
     * Pesan untuk akun yang dinonaktifkan. Dipakai jalur password maupun Google
     * supaya keduanya menjelaskan hal yang sama.
     */
    public static function deactivatedMessage(User $user): string
    {
        return 'Akun Anda dinonaktifkan oleh admin.'
            .($user->deactivation_reason ? ' Alasan: '.$user->deactivation_reason : '')
            .' Hubungi admin bila menurut Anda ini keliru.';
    }
}
