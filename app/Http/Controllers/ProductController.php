<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $keyword = $request->query('q');
        $query = Product::latest();

        if (!empty($keyword)) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                ->orWhere('category', 'like', "%{$keyword}%")
                ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        $paginatedProducts = $query->paginate(12);
        return Inertia::render('products/index', [
            'paginatedProducts' => $paginatedProducts,
            'showSearch' => true,
        ]);
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
            'certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $storeId = Auth::user()->store->id;
        $imagePath = null;
        $certificatePath = null;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('products', $imageName, 'public');
        }

        if ($request->hasFile('certificate')) {
            $certificate = $request->file('certificate');
            $certificateName = time() . '_certificate_' . $certificate->getClientOriginalName();
            $certificatePath = $certificate->storeAs('certificates', $certificateName, 'public');
        }

        Product::create([
            'store_id' => $storeId,
            'name' => $request->name,
            'stock' => $request->stock,
            'price' => $request->price,
            'category' => $request->category,
            'description' => $request->description,
            'image' => $imagePath,
            'certificate' => $certificatePath
        ]);

        return redirect()->route('seller.dashboard')->with('success', 'Produk berhasil ditambahkan.');
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
            'certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $product = Product::findOrFail($id);

        // Pastikan hanya pemilik toko yang bisa update produk ini
        if ($product->store->user_id !== Auth::id()) {
            abort(403, 'Anda tidak memiliki akses ke produk ini.');
        }

        // Update gambar jika ada gambar baru
        if ($request->hasFile('image')) {
            // Hapus gambar lama jika ada
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            // Upload gambar baru
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('products', $imageName, 'public');
            $product->image = $imagePath;
        }

        // Update sertifikat jika ada sertifikat baru
        if ($request->hasFile('certificate')) {
            // Hapus gambar lama jika ada
            if ($product->certificate && Storage::disk('public')->exists($product->certificate)) {
                Storage::disk('public')->delete($product->certificate);
            }
            // Upload gambar baru
            $certificate = $request->file('certificate');
            $certificateName = time() . '_certificate_' . $certificate->getClientOriginalName();
            $certificatePath = $certificate->storeAs('certificates', $certificateName, 'public');
            $product->certificate = $certificatePath;
        }

        $product->name = $request->name;
        $product->stock = $request->stock;
        $product->price = $request->price;
        $product->category = $request->category;
        $product->description = $request->description;
        $product->save();

        return redirect()->route('seller.dashboard')->with('success', 'Produk berhasil diperbarui.');
    }

    public function updateStock(Request $request, $id) {
        $validator = Validator::make($request->all(), [
            'stock' => 'required|integer',
        ]);
        
        if ($validator->fails()) {
            return back()->with('error', $validator->errors());
        }

        $product = Product::findOrFail($id);

        // Pastikan hanya pemilik toko yang bisa update produk ini
        if ($product->store->user_id !== Auth::id()) {
            abort(403, 'Anda tidak memiliki akses ke produk ini.');
        }

        $product->stock = $request->stock;
        $product->save();   

        return back()->with('success', 'Stok produk berhasil diperbarui.');
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
        return Inertia::render('seller/products/edit', compact('product'));

        $product->update($data);
        return redirect()->route('seller.dashboard')->with('success', 'Produk berhasil diperbarui.');
    }
}
