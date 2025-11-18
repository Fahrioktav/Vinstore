<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        $products = \App\Models\Product::latest()->paginate(12);
        return view('products.index', compact('products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'stock' => 'required|integer',
            'price' => 'required|numeric',
            'category' => 'required',
            'description' => 'required',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $storeId = Auth::user()->store->id;
        $imagePath = null;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/products'), $imageName);
            $imagePath = 'uploads/products/' . $imageName;
        }

        Product::create([
            'store_id' => $storeId,
            'name' => $request->name,
            'stock' => $request->stock,
            'price' => $request->price,
            'category' => $request->category,
            'description' => $request->description,
            'image' => $imagePath
        ]);

        return back()->with('success', 'Produk berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'stock' => 'required|integer',
            'price' => 'required|numeric',
            'category' => 'required',
            'description' => 'required',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $product = Product::findOrFail($id);

        // Pastikan hanya pemilik toko yang bisa update produk ini
        if ($product->store->user_id !== Auth::id()) {
            abort(403, 'Anda tidak memiliki akses ke produk ini.');
        }

        // Update gambar jika ada gambar baru
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/products'), $imageName);
            $product->image = 'uploads/products/' . $imageName;
        }

        $product->name = $request->name;
        $product->stock = $request->stock;
        $product->price = $request->price;
        $product->category = $request->category;
        $product->description = $request->description;
        $product->save();

        return back()->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // Pastikan hanya pemilik toko yang bisa hapus
        if ($product->store->user_id !== Auth::id()) {
            abort(403, 'Anda tidak memiliki akses ke produk ini.');
        }

        $product->delete();
        return back()->with('success', 'Produk berhasil dihapus.');
    }

    public function create() {
        $sessions = [
            'error' => session('error'),
            'success' => session('success'),
        ];
        return Inertia::render('seller/products/create', compact('sessions'));
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        // return view('seller.products.edit', compact('product'));
        return Inertia::render('seller/products/edit', compact('product'));

        $product->update($data);
        return redirect()->route('seller.dashboard')->with('success', 'Produk berhasil diperbarui.');
    }
}
