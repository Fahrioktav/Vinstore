<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Putuskan sesi pengguna yang akunnya dinonaktifkan admin.
 *
 * Memblokir di halaman login saja tidak cukup: seseorang yang sedang masuk
 * ketika akunnya dinonaktifkan akan tetap bisa berbelanja, menawar lelang, atau
 * mengelola tokonya sampai ia keluar sendiri. Pemeriksaan ini berjalan di
 * setiap permintaan web, jadi penonaktifan berlaku pada klik berikutnya.
 *
 * Sesi sengaja dihancurkan seluruhnya (invalidate + regenerateToken), bukan
 * sekadar logout, supaya tidak ada sisa data pengguna itu yang terbawa.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->isDeactivated()) {
            $reason = $user->deactivation_reason;

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'Akun Anda dinonaktifkan oleh admin.'
                .($reason ? ' Alasan: '.$reason : '')
                .' Hubungi admin bila menurut Anda ini keliru.';

            // Webhook dan permintaan JSON tidak boleh diarahkan ke halaman
            // login — mereka butuh jawaban yang bisa dibaca mesin.
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return redirect('/login')->with('error', $message);
        }

        return $next($request);
    }
}
