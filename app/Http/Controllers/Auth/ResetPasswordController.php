<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ResetPasswordController extends Controller
{
    /**
     * Tampilkan form reset password
     */
    public function showResetForm(Request $request, $token)
    {
        return Inertia::render('auth/reset-password', [
            'token' => $token,
            'email' => $request->email,
            'heroText' => 'Reset Password'
        ]);
    }

    /**
     * Reset password
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ], [
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user && $user->google_id && empty($user->password)) {
            return redirect()->route('login.form')
                ->withErrors(['email' => 'Akun ini terdaftar menggunakan Google. Silakan login dengan tombol Google.']);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login.form')
                ->with('status', 'Password berhasil direset! Silakan login dengan password baru.');
        }

        return back()->withErrors(['email' => 'Token reset password tidak valid atau sudah kadaluarsa.']);
    }
}
