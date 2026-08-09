<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
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
