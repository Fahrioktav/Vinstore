<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
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

        // Upload gambar jika ada
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
        $product = Product::findOrFail($id);
        $product->update($request->only('name', 'stock', 'price', 'category', 'description'));

        return back()->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy($id)
    {
        Product::destroy($id);
        return back()->with('success', 'Produk berhasil dihapus.');
    }
}
