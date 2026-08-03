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

    /**
     * Daftar detail produk yang menunggu persetujuan admin
     * (sudah divalidasi validator, status pending_admin).
     */
    public function pending()
    {
        $products = Product::with('store.user')
            ->where('approval_status', Product::STATUS_PENDING_ADMIN)
            ->latest()
            ->get();

        return Inertia::render('admin/products/pending', compact('products'));
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

        // Terapkan field hasil validasi. Kolom 'image' dikeluarkan karena
        // ditangani terpisah di bawah (nilainya berupa UploadedFile, bukan path).
        $product->fill(collect($validated)->except('image')->all());

        // Handle upload gambar jika ada
        if ($request->hasFile('image')) {
            // Hapus gambar lama jika ada. Gambar disimpan di disk 'public'
            // (storage/app/public), bukan di public_path().
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            // Simpan foto baru
            $image = $request->file('image');
            $imageName = time().'_'.$image->getClientOriginalName();
            $imagePath = $image->storeAs('products', $imageName, 'public');
            $product->image = $imagePath;
        }

        $product->save();

        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function approve($id)
    {
        $product = Product::where('public_id', $id)->firstOrFail();

        // Admin hanya boleh menyetujui produk yang sudah divalidasi oleh validator.
        if ($product->approval_status === Product::STATUS_PENDING_VALIDATOR) {
            return redirect()->back()->with('error', 'Produk belum divalidasi oleh validator barang antik.');
        }

        $product->update([
            'approval_status' => Product::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'rejection_reason' => null,
        ]);

        // Untuk produk Tebak Harga, mulai siklus hidup (scheduled/active) saat disetujui.
        if ($product->isTebakHarga()) {
            $service = app(\App\Services\PriceGuessService::class);
            $product->update([
                'guess_status' => $service->initialStatusOnApproval($product),
            ]);
            // Jika periode ternyata sudah lewat, langsung finalisasi pemenang.
            $service->sync();
        }

        return redirect()->back()->with('success', 'Produk berhasil disetujui dan kini tampil ke pembeli.');
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $product = Product::where('public_id', $id)->firstOrFail();

        $product->update([
            'approval_status' => Product::STATUS_REJECTED,
            'approved_at' => null,
            'approved_by' => Auth::id(),
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Produk berhasil ditolak.');
    }

    public function destroy($id)
    {
        $product = Product::where('public_id', $id)->firstOrFail();

        // Riwayat pesanan pembeli tidak ikut terhapus — lihat migration
        // 2026_08_03_000001_preserve_order_history_on_deletion.

        // Hapus gambar jika ada (disk 'public', bukan public_path())
        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return redirect()->back()->with('success', 'Produk berhasil dihapus.');
    }
}
