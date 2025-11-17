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
        $sellers = User::where('role', 'seller')->with('store')->get();
        return Inertia::render('admin/sellers/index', compact('sellers'));
    }

    public function edit($id)
    {
        $seller = User::where('role', 'seller')->findOrFail($id);
        return Inertia::render('admin/sellers/edit', compact('seller'));
    }

    public function update(Request $request, $id)
    {
        $seller = User::where('role', 'seller')->findOrFail($id);
        
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users,username,' . $id,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $seller->update($validated);

        return redirect()->route('admin.sellers.index')->with('success', 'Seller berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $seller = User::where('role', 'seller')->findOrFail($id);
        
        // Hapus store dan produk yang terkait
        if ($seller->store) {
            $seller->store->products()->delete();
            $seller->store->delete();
        }
        
        $seller->delete();
        
        return redirect()->back()->with('success', 'Seller berhasil dihapus.');
    }
}
