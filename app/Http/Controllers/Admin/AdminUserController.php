<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::where('role', 'user')->latest()->get();

        return Inertia::render('admin/users/index', compact('users'));
    }

    public function edit($id)
    {
        $editedUser = User::where('role', 'user')->where('public_id', $id)->firstOrFail();

        return Inertia::render('admin/users/edit', compact('editedUser'));
    }

    public function update(Request $request, $id)
    {
        $user = User::where('role', 'user')->where('public_id', $id)->firstOrFail();

        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users,username,'.$user->getKey(),
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->getKey(),
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $user->update($validated);

        return redirect()->route('admin.users.index')->with('success', 'User berhasil diperbarui.');
    }

    /**
     * Buatkan tautan reset password untuk pengguna yang terkunci di luar.
     *
     * Selama `MAIL_MAILER` masih `log`, tautan reset hanya ditulis ke
     * storage/logs dan tidak pernah sampai ke kotak masuk siapa pun. Pengguna
     * yang lupa passwordnya tidak bisa masuk, sehingga chat bantuan pun tidak
     * terjangkau olehnya — ia berdiri di depan pintu tanpa cara menghubungi
     * siapa pun di dalam. Jalur yang tersisa: ia melapor lewat halaman Kontak
     * yang terbuka untuk umum, admin memastikan identitasnya, lalu membuatkan
     * tautan ini (temuan V11-03).
     *
     * Tautannya memakai token bawaan Laravel — sama persis dengan yang dikirim
     * lewat surel — sehingga masa berlakunya, sekali-pakainya, dan seluruh
     * penjagaannya mengikuti aturan yang sudah ada. Admin tidak pernah
     * mengetahui password barunya; yang ia pegang hanya tautan sekali pakai.
     *
     * Begitu `MAIL_MAILER` diarahkan ke SMTP sungguhan, jalur ini boleh tetap
     * ada sebagai cadangan, tetapi bukan lagi jalur utama.
     */
    public function passwordResetLink(Request $request, $id)
    {
        // Akun admin sengaja tidak ikut: yang boleh memulihkan admin adalah
        // admin lain, lewat jalur yang tidak dibuat di sini.
        $user = User::where('public_id', $id)
            ->whereIn('role', ['user', 'seller', 'validator'])
            ->firstOrFail();

        if (empty($user->password) && $user->google_id) {
            return back()->with(
                'error',
                'Akun ini masuk lewat Google dan belum punya password. Minta pemiliknya menekan tombol "Login dengan Google".'
            );
        }

        $token = Password::broker()->createToken($user);

        $link = route('password.reset', ['token' => $token]).'?email='.urlencode($user->email);

        return back()->with([
            'success' => 'Tautan reset password dibuat. Sampaikan kepada pemilik akun — tautannya sekali pakai.',
            'passwordResetLink' => $link,
            'passwordResetFor' => $user->email,
        ]);
    }

    /**
     * Nonaktifkan akun — TIDAK menghapusnya.
     *
     * Menghapus user berarti menghapus seluruh riwayat pesanannya
     * (`orders.user_id` memakai ON DELETE CASCADE), termasuk pesanan yang sudah
     * lunas dan dananya sudah dicairkan ke seller. Pembukuan marketplace jadi
     * berlubang tanpa jejak. Lihat temuan V4-12.
     *
     * Akun nonaktif tidak bisa masuk lagi dan sesinya yang sedang berjalan
     * langsung diputus oleh EnsureAccountIsActive.
     */
    public function destroy(Request $request, $id)
    {
        $user = User::where('role', 'user')->where('public_id', $id)->firstOrFail();

        if ($user->isDeactivated()) {
            $user->reactivate();

            return back()->with('success', 'Akun '.$user->username.' diaktifkan kembali.');
        }

        if (! $user->canBeDeactivated()) {
            return back()->with('error', 'Akun ini tidak dapat dinonaktifkan.');
        }

        $user->deactivate($request->input('reason'));

        return back()->with(
            'success',
            'Akun '.$user->username.' dinonaktifkan. Riwayat pesanannya tetap tersimpan.'
        );
    }
}
