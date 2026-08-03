<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminStoreController extends Controller
{
    public function index()
    {
        $stores = Store::with('user')->get();

        return Inertia::render('admin/stores/index', compact('stores'));
    }

    public function edit($id)
    {
        $store = Store::with('user')->where('public_id', $id)->firstOrFail();

        return Inertia::render('admin/stores/edit', compact('store'));
    }

    public function update(Request $request, $id)
    {
        $store = Store::where('public_id', $id)->firstOrFail();

        $validated = $request->validate([
            'store_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
        ]);

        $store->update($validated);

        return redirect()->route('admin.stores.index')->with('success', 'Toko berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $store = Store::where('public_id', $id)->firstOrFail();

        // Pesanan pembeli tidak ikut terhapus: orders.store_id dan
        // orders.product_id sudah nullOnDelete, dan setiap pesanan menyimpan
        // snapshot nama toko serta nama & harga produknya.
        // Turunkan role pemilik agar tidak terjebak redirect loop
        // seller dashboard <-> store register (temuan K-05).
        if ($store->user && $store->user->role === 'seller') {
            $store->user->update(['role' => 'user']);
        }

        // Hapus semua produk terkait
        $store->products()->delete();

        $store->delete();

        return redirect()->back()->with('success', 'Toko berhasil dihapus. Riwayat pesanan pembeli tetap tersimpan.');
    }
}
