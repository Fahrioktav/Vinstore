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
            'heroText' => 'Lupa Password?',
        ]);
    }

    /**
     * Kirim link reset password ke email
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ]);

        // Balasan seragam untuk semua kasus. Membedakan "email terdaftar" dan
        // "tidak terdaftar" akan membocorkan daftar akun ke penyerang.
        $genericMessage = 'Jika email tersebut terdaftar, kami telah mengirimkan link reset password ke sana. Silakan periksa kotak masuk Anda.';

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->with('status', $genericMessage);
        }

        if ($user->google_id && empty($user->password)) {
            return back()->withErrors([
                'email' => 'Akun ini terdaftar menggunakan Google. Silakan login dengan tombol Google.',
            ]);
        }

        // Link reset HANYA dikirim lewat email. Sebelumnya, saat MAIL_MAILER=log
        // token reset dikembalikan langsung ke browser — siapa pun bisa mengambil
        // alih akun mana pun cukup dengan mengetahui alamat emailnya.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', $genericMessage);
    }
}
