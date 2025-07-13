<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
            $request->session()->regenerate(); // regenerasi session untuk keamanan

            $user = Auth::user();

            // Cek role user untuk redirect ke dashboard yang sesuai
            if ($user->role === 'admin') {
                return redirect()->route('admin.dashboard');
            } elseif ($user->role === 'seller') {
                return redirect()->route('seller.dashboard');
            } else {
                return redirect()->intended('/');
            }
        }

        // Gagal login
        return back()->withErrors([
            'login' => 'Username/Email atau password salah.',
        ])->withInput();
    }
}
