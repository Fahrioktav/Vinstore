<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Services\PriceGuessService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Sinkronkan siklus hidup tebak harga (aktivasi/penutupan/pengembalian ke normal).
        app(PriceGuessService::class)->sync();

        $keyword = $request->query('q');
        // Sembunyikan produk yang sudah sold out (stok habis) dari halaman produk
        $query = Product::approved()->where('stock', '>', 0);

        if (!empty($keyword)) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                ->orWhere('category', 'like', "%{$keyword}%")
                ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->query('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->query('max_price'));
        }

        if ($request->boolean('in_stock')) {
            $query->where('stock', '>', 0);
        }

        if ($request->boolean('certified')) {
            $query->whereNotNull('certificate');
        }

        match ($request->query('sort')) {
            'price_low' => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            'oldest' => $query->oldest(),
            default => $query->latest(),
        };

        $paginatedProducts = $query->paginate(12)->withQueryString();

        // Sembunyikan harga asli produk tebak harga yang masih dalam periode/prioritas.
        $viewer = Auth::user();
        $paginatedProducts->getCollection()->each(fn (Product $product) => $product->maskRealPriceFor($viewer));

        $categories = Category::orderBy('name')->get(['name']);

        return Inertia::render('products/index', [
            'paginatedProducts' => $paginatedProducts,
            'showSearch' => true,
            'categories' => $categories,
            'filters' => $request->only([
                'q',
                'category',
                'min_price',
                'max_price',
                'in_stock',
                'certified',
                'sort',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'stock' => 'required|integer',
            'price' => 'required|numeric',
            'category' => ['required', Rule::exists('categories', 'name')],
            'description' => 'required',
            'image' => 'nullable|image|max:2048',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|max:2048',
            'video' => 'nullable|file|mimes:mp4,webm,mov|max:20480',
            'certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'is_barterable' => 'nullable|boolean',
            'sale_type' => ['nullable', Rule::in([Product::SALE_TYPE_NORMAL, Product::SALE_TYPE_TEBAK_HARGA])],
            'guess_starts_at' => 'nullable|required_if:sale_type,tebak_harga|date|after_or_equal:now',
            'guess_ends_at' => 'nullable|required_if:sale_type,tebak_harga|date|after:guess_starts_at',
        ]);

        $storeId = Auth::user()->store->id;
        $imagePath = null;
        $certificatePath = null;
        $galleryPaths = [];
        $videoPath = null;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('products', $imageName, 'public');
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $galleryImage) {
                $galleryName = time() . '_' . $index . '_' . $galleryImage->getClientOriginalName();
                $galleryPaths[] = $galleryImage->storeAs('products', $galleryName, 'public');
            }
        }

        if ($request->hasFile('video')) {
            $video = $request->file('video');
            $videoName = time() . '_video_' . $video->getClientOriginalName();
            $videoPath = $video->storeAs('product-videos', $videoName, 'public');
        }

        if ($request->hasFile('certificate')) {
            $certificate = $request->file('certificate');
            $certificateName = time() . '_certificate_' . $certificate->getClientOriginalName();
            $certificatePath = $certificate->storeAs('certificates', $certificateName, 'public');
        }

        $saleType = $request->input('sale_type', Product::SALE_TYPE_NORMAL);
        $isTebakHarga = $saleType === Product::SALE_TYPE_TEBAK_HARGA;

        Product::create([
            'store_id' => $storeId,
            'name' => $request->name,
            'stock' => $request->stock,
            'price' => $request->price,
            'category' => $request->category,
            'description' => $request->description,
            'image' => $imagePath,
            'images' => $galleryPaths ?: null,
            'video' => $videoPath,
            'certificate' => $certificatePath,
            // Produk tebak harga tidak bisa dibarter selama proses berlangsung.
            'is_barterable' => $isTebakHarga ? false : $request->boolean('is_barterable'),
            'approval_status' => Product::STATUS_PENDING_VALIDATOR,
            'sale_type' => $saleType,
            'guess_starts_at' => $isTebakHarga
                ? Carbon::createFromFormat('Y-m-d\TH:i', $request->guess_starts_at, config('app.timezone'))
                : null,
            'guess_ends_at' => $isTebakHarga
                ? Carbon::createFromFormat('Y-m-d\TH:i', $request->guess_ends_at, config('app.timezone'))
                : null,
            // guess_status tetap null sampai admin menyetujui produk.
            'guess_status' => null,
        ]);

        $message = $isTebakHarga
            ? 'Produk Tebak Harga berhasil dikirim dan menunggu validasi dari validator barang antik.'
            : 'Produk berhasil dikirim dan menunggu validasi dari validator barang antik.';

        return redirect()->route('seller.dashboard')->with('success', $message);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'stock' => 'required|integer',
            'price' => 'required|numeric',
            'category' => ['required', Rule::exists('categories', 'name')],
            'description' => 'required',
            'image' => 'nullable|image|max:2048',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|max:2048',
            'video' => 'nullable|file|mimes:mp4,webm,mov|max:20480',
            'certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'is_barterable' => 'nullable|boolean',
            'sale_type' => ['nullable', Rule::in([Product::SALE_TYPE_NORMAL, Product::SALE_TYPE_TEBAK_HARGA])],
            'guess_starts_at' => 'nullable|required_if:sale_type,tebak_harga|date|after_or_equal:now',
            'guess_ends_at' => 'nullable|required_if:sale_type,tebak_harga|date|after:guess_starts_at',
        ]);

        $product = Product::where('public_id', $id)->firstOrFail();

        // Pastikan hanya pemilik toko yang bisa update produk ini
        if ($product->store->user_id !== Auth::id()) {
            abort(403, 'Anda tidak memiliki akses ke produk ini.');
        }

        // Produk tebak harga yang sudah berjalan / sudah menentukan pemenang tidak boleh diubah
        // agar tidak merusak keadilan tebakan yang sudah masuk.
        if ($product->isTebakHarga() && in_array($product->guess_status, [Product::GUESS_ACTIVE, Product::GUESS_ENDED], true)) {
            return redirect()->route('seller.dashboard')->with('error', 'Produk Tebak Harga yang sedang berjalan atau sudah selesai tidak dapat diubah.');
        }

        // Update gambar utama jika ada gambar baru
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

        // Update galeri foto tambahan jika seller mengunggah yang baru
        if ($request->hasFile('images')) {
            // Hapus galeri lama
            foreach ((array) $product->images as $oldImage) {
                if ($oldImage && Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
            }
            $galleryPaths = [];
            foreach ($request->file('images') as $index => $galleryImage) {
                $galleryName = time() . '_' . $index . '_' . $galleryImage->getClientOriginalName();
                $galleryPaths[] = $galleryImage->storeAs('products', $galleryName, 'public');
            }
            $product->images = $galleryPaths;
        }

        // Update video jika ada video baru
        if ($request->hasFile('video')) {
            if ($product->video && Storage::disk('public')->exists($product->video)) {
                Storage::disk('public')->delete($product->video);
            }
            $video = $request->file('video');
            $videoName = time() . '_video_' . $video->getClientOriginalName();
            $product->video = $video->storeAs('product-videos', $videoName, 'public');
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

        $saleType = $request->input('sale_type', Product::SALE_TYPE_NORMAL);
        $isTebakHarga = $saleType === Product::SALE_TYPE_TEBAK_HARGA;

        $product->name = $request->name;
        $product->stock = $request->stock;
        $product->price = $request->price;
        $product->category = $request->category;
        $product->description = $request->description;
        $product->is_barterable = $isTebakHarga ? false : $request->boolean('is_barterable');
        $product->approval_status = Product::STATUS_PENDING_VALIDATOR;
        $product->approved_at = null;
        $product->approved_by = null;
        $product->validated_at = null;
        $product->validated_by = null;
        $product->rejection_reason = null;

        // Atur ulang konfigurasi & siklus hidup tebak harga.
        $product->sale_type = $saleType;
        $product->guess_starts_at = $isTebakHarga
            ? Carbon::createFromFormat('Y-m-d\TH:i', $request->guess_starts_at, config('app.timezone'))
            : null;
        $product->guess_ends_at = $isTebakHarga
            ? Carbon::createFromFormat('Y-m-d\TH:i', $request->guess_ends_at, config('app.timezone'))
            : null;
        $product->guess_status = null;
        $product->guess_winner_id = null;
        $product->guess_winning_amount = null;
        $product->guess_finished_at = null;
        $product->winner_priority_until = null;
        $product->save();

        // Bersihkan tebakan lama (jika ada) agar tidak ikut terhitung saat diajukan ulang.
        $product->priceGuesses()->delete();

        $message = $isTebakHarga
            ? 'Produk Tebak Harga berhasil diperbarui dan menunggu validasi ulang dari validator barang antik.'
            : 'Produk berhasil diperbarui dan menunggu validasi ulang dari validator barang antik.';

        return redirect()->route('seller.dashboard')->with('success', $message);
    }

    public function updateStock(Request $request, $id) {
        $validator = Validator::make($request->all(), [
            'stock' => 'required|integer',
        ]);
        
        if ($validator->fails()) {
            return back()->with('error', $validator->errors());
        }

        $product = Product::where('public_id', $id)->firstOrFail();

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
        $product = Product::where('public_id', $id)->firstOrFail();

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
        $categories = Category::orderBy('name')->get(['name']);

        return Inertia::render('seller/products/create', compact('sessions', 'categories'));
    }

    public function edit($id)
    {
        $product = Product::where('public_id', $id)->firstOrFail();
        $categories = Category::orderBy('name')->get(['name']);

        return Inertia::render('seller/products/edit', compact('product', 'categories'));

        $product->update($data);
        return redirect()->route('seller.dashboard')->with('success', 'Produk berhasil diperbarui.');
    }
}
