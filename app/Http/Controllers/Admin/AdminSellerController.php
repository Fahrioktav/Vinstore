<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminSellerController extends Controller
{
    public function index()
    {
        $sellers = User::where('role', 'seller')->with('store')->latest()->get();

        return Inertia::render('admin/sellers/index', compact('sellers'));
    }

    public function edit($id)
    {
        $seller = User::where('role', 'seller')->where('public_id', $id)->firstOrFail();

        return Inertia::render('admin/sellers/edit', compact('seller'));
    }

    public function update(Request $request, $id)
    {
        $seller = User::where('role', 'seller')->where('public_id', $id)->firstOrFail();

        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users,username,'.$seller->getKey(),
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$seller->getKey(),
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $seller->update($validated);

        return redirect()->route('admin.sellers.index')->with('success', 'Seller berhasil diperbarui.');
    }

    /**
     * Nonaktifkan akun — TIDAK menghapusnya.
     *
     * Menghapus seller berarti menghapus seluruh riwayat pesanannya
     * (`orders.seller_id` memakai ON DELETE CASCADE), termasuk pesanan yang sudah
     * lunas dan dananya sudah dicairkan ke seller. Pembukuan marketplace jadi
     * berlubang tanpa jejak. Lihat temuan V4-12.
     *
     * Akun nonaktif tidak bisa masuk lagi dan sesinya yang sedang berjalan
     * langsung diputus oleh EnsureAccountIsActive.
     */
    public function destroy(Request $request, $id)
    {
        $seller = User::where('role', 'seller')->where('public_id', $id)->firstOrFail();

        if ($seller->isDeactivated()) {
            $seller->reactivate();

            return back()->with(
                'success',
                'Akun '.$seller->username.' diaktifkan kembali. Produk tokonya tampil lagi di etalase.'
            );
        }

        $seller->deactivate($request->input('reason'));

        // Produknya ikut berhenti tampil — lihat Product::scopeApproved().
        // Produk TIDAK dihapus: pesanan lama masih merujuk padanya.
        return back()->with(
            'success',
            'Akun '.$seller->username.' dinonaktifkan. Produk tokonya berhenti tampil, '
            .'tetapi riwayat pesanan pembeli tetap tersimpan.'
        );
    }
}
