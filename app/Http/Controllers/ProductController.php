<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\PriceGuessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        if (! empty($keyword)) {
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

    public function show($public_id)
    {
        // Sinkronkan status tebak harga
        app(PriceGuessService::class)->sync();

        $product = Product::where('public_id', $public_id)
            ->where('approval_status', Product::STATUS_APPROVED)
            ->with('store')
            ->firstOrFail();

        $viewer = Auth::user();

        // Data tebak harga (jika produk adalah tebak harga)
        $tebakHarga = null;
        if ($product->isTebakHarga()) {
            $guessService = app(PriceGuessService::class);

            $tebakHarga = [
                'guess_status' => $product->guess_status,
                'guess_starts_at' => $product->guess_starts_at,
                'guess_ends_at' => $product->guess_ends_at,
                'guess_finished_at' => $product->guess_finished_at,
                'guess_finished_reason' => $product->guess_finished_reason,
                'winner_priority_until' => $product->winner_priority_until,
                'guesses_count' => $product->priceGuesses()->count(),
                'my_guess' => $viewer ? $product->priceGuesses()->where('user_id', $viewer->id)->first() : null,
                'is_winner' => $viewer && $product->guessWinner?->id === $viewer->id,
                'winner_username' => $product->guessWinner?->username,
                'can_buy' => $product->isPurchasableBy($viewer),
                // Papan tebakan. Selama periode berjalan hanya berisi urutan
                // waktu tanpa peringkat; peringkat baru muncul setelah selesai.
                // Lihat PriceGuessService::leaderboard().
                'leaderboard' => $guessService->leaderboard($product, $viewer),
                'tolerance_percent' => Product::GUESS_TOLERANCE_PERCENT,
                'winner_priority_hours' => Product::WINNER_PRIORITY_HOURS,
            ];
        }

        // Sembunyikan harga asli jika perlu
        $product->maskRealPriceFor($viewer);

        return Inertia::render('product-detail', [
            'product' => $product,
            'tebakHarga' => $tebakHarga,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'stock' => 'required|integer',
            'price' => 'required|numeric',
            'weight' => 'nullable|integer|min:1|max:500000',
            'length' => 'nullable|integer|min:1|max:500',
            'width' => 'nullable|integer|min:1|max:500',
            'height' => 'nullable|integer|min:1|max:500',
            'category' => ['required', Rule::exists('categories', 'name')],
            'description' => 'required',
            'image' => 'nullable|image|max:2048',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|max:2048',
            'video' => 'nullable|file|mimes:mp4,webm,mov|max:20480',
            'certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'is_trade_in_enabled' => 'nullable|boolean',
            'sale_type' => ['nullable', Rule::in([Product::SALE_TYPE_NORMAL, Product::SALE_TYPE_TEBAK_HARGA])],
            'guess_discount_price' => 'nullable|required_if:sale_type,tebak_harga|numeric|min:1|lt:price',
            'guess_starts_at' => ['nullable', 'required_if:sale_type,tebak_harga', 'date', 'after_or_equal:'.now()->startOfMinute()->format('Y-m-d H:i:s')],
            'guess_ends_at' => 'nullable|required_if:sale_type,tebak_harga|date|after:guess_starts_at',
        ]);

        $storeId = Auth::user()->store->id;
        $imagePath = null;
        $certificatePath = null;
        $galleryPaths = [];
        $videoPath = null;

        // Nama berkasnya diserahkan kepada `store()` yang mengarangnya secara
        // acak. Nama susunan `time()` + nama berkas asal tidak menjamin
        // keunikan: dua seller yang menyimpan "foto.jpg" pada detik yang sama
        // menghasilkan jalur identik, dan yang belakangan menimpa yang duluan
        // tanpa memberi tahu siapa pun (temuan V8-01).
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $galleryImage) {
                $galleryPaths[] = $galleryImage->store('products', 'public');
            }
        }

        if ($request->hasFile('video')) {
            $videoPath = $request->file('video')->store('product-videos', 'public');
        }

        if ($request->hasFile('certificate')) {
            $certificatePath = $request->file('certificate')->store('certificates', 'public');
        }

        $saleType = $request->input('sale_type', Product::SALE_TYPE_NORMAL);
        $isTebakHarga = $saleType === Product::SALE_TYPE_TEBAK_HARGA;

        Product::create([
            'store_id' => $storeId,
            'name' => $request->name,
            'stock' => $request->stock,
            'price' => $request->price,
            'weight' => $request->weight ?: config('marketplace.weight.default_gram'),
            'length' => $request->length ?: null,
            'width' => $request->width ?: null,
            'height' => $request->height ?: null,
            'category' => $request->category,
            'description' => $request->description,
            'image' => $imagePath,
            'images' => $galleryPaths ?: null,
            'video' => $videoPath,
            'certificate' => $certificatePath,
            // Produk tebak harga tidak bisa ditukar tambah selama proses berlangsung.
            'is_trade_in_enabled' => $isTebakHarga ? false : $request->boolean('is_trade_in_enabled'),
            'approval_status' => Product::STATUS_PENDING_VALIDATOR,
            'sale_type' => $saleType,
            'guess_discount_price' => $isTebakHarga ? $request->guess_discount_price : null,
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
            // Berat dalam gram; dipakai menghitung biaya berat di checkout.
            // Opsional agar produk lama tetap bisa disunting; yang kosong
            // memakai berat default dari config/marketplace.php.
            'weight' => 'nullable|integer|min:1|max:500000',
            // Dimensi paket dalam cm, untuk menghitung berat volumetrik.
            // Boleh kosong: yang kosong berarti biaya beratnya murni dari
            // berat asli, bukan mendadak ditagih lebih mahal.
            'length' => 'nullable|integer|min:1|max:500',
            'width' => 'nullable|integer|min:1|max:500',
            'height' => 'nullable|integer|min:1|max:500',
            'category' => ['required', Rule::exists('categories', 'name')],
            'description' => 'required',
            'image' => 'nullable|image|max:2048',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|max:2048',
            'video' => 'nullable|file|mimes:mp4,webm,mov|max:20480',
            'certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'is_trade_in_enabled' => 'nullable|boolean',
            'sale_type' => ['nullable', Rule::in([Product::SALE_TYPE_NORMAL, Product::SALE_TYPE_TEBAK_HARGA])],
            'guess_discount_price' => 'nullable|required_if:sale_type,tebak_harga|numeric|min:1|lt:price',
            // Pembandingnya awal menit berjalan, bukan 'now': input
            // datetime-local tidak mengirim detik, sehingga menit yang
            // sedang berjalan selalu terbaca sedikit di masa lalu dan
            // ditolak tanpa alasan yang masuk akal (temuan V6-08).
            'guess_starts_at' => ['nullable', 'required_if:sale_type,tebak_harga', 'date', 'after_or_equal:'.now()->startOfMinute()->format('Y-m-d H:i:s')],
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

        // Harga produk yang sedang terikat tukar tambah dibekukan. Selisih yang harus
        // dibayar dikunci saat tukar tambah disetujui, jadi mengubah harga sesudahnya
        // hanya membuat kartu tukar tambah menampilkan angka yang bertentangan dengan
        // yang benar-benar ditagih (temuan V3-05).
        if ($product->isLockedForTradeIn() && (float) $request->input('price') !== (float) $product->price) {
            return back()->with('error', 'Harga produk ini tidak dapat diubah karena sedang terikat proses tukar tambah. Selesaikan atau batalkan tukar tambahnya terlebih dahulu.');
        }

        // Update gambar utama jika ada gambar baru
        if ($request->hasFile('image')) {
            // Hapus gambar lama jika ada
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            // Upload gambar baru
            $product->image = $request->file('image')->store('products', 'public');
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
            foreach ($request->file('images') as $galleryImage) {
                $galleryPaths[] = $galleryImage->store('products', 'public');
            }
            $product->images = $galleryPaths;
        }

        // Update video jika ada video baru
        if ($request->hasFile('video')) {
            if ($product->video && Storage::disk('public')->exists($product->video)) {
                Storage::disk('public')->delete($product->video);
            }
            $product->video = $request->file('video')->store('product-videos', 'public');
        }

        // Update sertifikat jika ada sertifikat baru
        if ($request->hasFile('certificate')) {
            // Hapus gambar lama jika ada
            if ($product->certificate && Storage::disk('public')->exists($product->certificate)) {
                Storage::disk('public')->delete($product->certificate);
            }
            // Upload sertifikat baru
            $product->certificate = $request->file('certificate')->store('certificates', 'public');
        }

        $saleType = $request->input('sale_type', Product::SALE_TYPE_NORMAL);
        $isTebakHarga = $saleType === Product::SALE_TYPE_TEBAK_HARGA;

        $product->name = $request->name;
        $product->stock = $request->stock;
        $product->price = $request->price;
        $product->weight = $request->weight ?: config('marketplace.weight.default_gram');
        $product->length = $request->length ?: null;
        $product->width = $request->width ?: null;
        $product->height = $request->height ?: null;
        $product->category = $request->category;
        $product->description = $request->description;
        $product->is_trade_in_enabled = $isTebakHarga ? false : $request->boolean('is_trade_in_enabled');
        $product->approval_status = Product::STATUS_PENDING_VALIDATOR;
        $product->approved_at = null;
        $product->approved_by = null;
        $product->validated_at = null;
        $product->validated_by = null;
        $product->rejection_reason = null;

        // Atur ulang konfigurasi & siklus hidup tebak harga.
        $product->sale_type = $saleType;
        $product->guess_discount_price = $isTebakHarga ? $request->guess_discount_price : null;
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

    public function updateStock(Request $request, $id)
    {
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

        // Produk boleh dihapus kapan pun. Riwayat pesanan pembeli TIDAK ikut
        // terhapus: foreign key orders.product_id sudah nullOnDelete dan setiap
        // pesanan menyimpan snapshot nama & harga produknya sendiri.
        $product->delete();

        return back()->with('success', 'Produk berhasil dihapus.');
    }

    public function create()
    {
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

        // Pastikan hanya pemilik toko yang bisa membuka form edit. Tanpa cek ini
        // seller lain dapat melihat seluruh data produk — termasuk harga asli
        // produk Tebak Harga yang seharusnya dirahasiakan dari calon penebak.
        if ($product->store->user_id !== Auth::id()) {
            abort(403, 'Anda tidak memiliki akses ke produk ini.');
        }

        $categories = Category::orderBy('name')->get(['name']);

        return Inertia::render('seller/products/edit', compact('product', 'categories'));
    }
}
