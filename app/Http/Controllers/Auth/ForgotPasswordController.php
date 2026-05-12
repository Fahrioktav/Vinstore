<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

class ForgotPasswordController extends Controller
{
    /**
     * Tampilkan form forgot password
     */
    public function showLinkRequestForm()
    {
        return Inertia::render('auth/forgot-password', [
            'heroText' => 'Lupa Password?'
        ]);
    }

    /**
     * Kirim link reset password ke email
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.exists' => 'Email tidak terdaftar di sistem kami.',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user && $user->google_id && empty($user->password)) {
            return back()->withErrors([
                'email' => 'Akun ini terdaftar menggunakan Google. Silakan login dengan tombol Google.',
            ]);
        }

        if (config('mail.default') === 'log') {
            $token = Password::broker()->createToken($user);

            return back()
                ->with('status', 'Mode email masih log. Gunakan link reset di bawah ini untuk mengganti password.')
                ->with('reset_url', route('password.reset', [
                    'token' => $token,
                    'email' => $user->email,
                ]));
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Link reset password telah dikirim ke email Anda!');
        }

        return back()->withErrors(['email' => 'Gagal mengirim link reset password.']);
    }
}
