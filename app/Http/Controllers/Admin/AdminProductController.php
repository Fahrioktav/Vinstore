<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AdminProductController extends Controller
{
    public function index()
    {
        $products = Product::with('store')->latest()->get();
        return Inertia::render('admin/products/index', compact('products'));
    }

    public function edit($id)
    {
        $product = Product::with('store')->where('public_id', $id)->firstOrFail();
        $stores = Store::all();
        return Inertia::render('admin/products/edit', compact('product', 'stores'));
    }

    public function update(Request $request, $id)
    {
        $product = Product::where('public_id', $id)->firstOrFail();
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'stock' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Handle upload gambar jika ada
        if ($request->hasFile('image')) {
            // Hapus gambar lama jika ada
            if ($product->image && file_exists(public_path($product->image))) {
                unlink(public_path($product->image));
            }
            // Simpan foto baru
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('products', $imageName, 'public');
            $product->image = $imagePath;
        }

        $product->save();

        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function approve($id)
    {
        $product = Product::where('public_id', $id)->firstOrFail();

        $product->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'rejection_reason' => null,
        ]);

        return redirect()->back()->with('success', 'Produk berhasil disetujui.');
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $product = Product::where('public_id', $id)->firstOrFail();

        $product->update([
            'approval_status' => 'rejected',
            'approved_at' => null,
            'approved_by' => Auth::id(),
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Produk berhasil ditolak.');
    }

    public function destroy($id)
    {
        $product = Product::where('public_id', $id)->firstOrFail();
        
        // Hapus gambar jika ada
        if ($product->image && file_exists(public_path($product->image))) {
            unlink(public_path($product->image));
        }
        
        $product->delete();
        
        return redirect()->back()->with('success', 'Produk berhasil dihapus.');
    }
}
