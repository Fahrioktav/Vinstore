<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class StoreController extends Controller
{
    public function showRegisterForm()
    {
        $user = Auth::user();

        // Cek apakah user sudah memiliki toko
        if ($user->store) {
            return redirect()->route('seller.dashboard')->with('error', 'Anda sudah memiliki toko!');
        }

        return Inertia::render('store/register');
    }

    public function index(Request $request)
    {
        $keyword = $request->query('q');
        $latitude = $request->query('latitude');
        $longitude = $request->query('longitude');
        $radius = $request->query('radius', 10); // Default 10 km

        // Jika ada koordinat, gunakan nearby search
        if ($latitude !== null && $longitude !== null) {
            $query = Store::nearby(
                floatval($latitude),
                floatval($longitude),
                floatval($radius)
            );

            // Filter berdasarkan keyword jika ada
            if (! empty($keyword)) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('store_name', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%")
                        ->orWhere('category', 'like', "%{$keyword}%")
                        ->orWhere('location', 'like', "%{$keyword}%");
                });
            }

            $stores = $query->get()->map(function ($store) {
                // Tambahkan informasi jarak yang sudah diformat
                $store->distance_text = $store->distance < 1
                    ? round($store->distance * 1000).' meter'
                    : round($store->distance, 1).' km';

                return $store;
            });
        } else {
            // Jika tidak ada koordinat, tampilkan semua toko (mode lama)
            $query = Store::latest();

            if (! empty($keyword)) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('store_name', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%")
                        ->orWhere('category', 'like', "%{$keyword}%")
                        ->orWhere('location', 'like', "%{$keyword}%");
                });
            }

            $stores = $query->get();
        }

        return Inertia::render('toko', [
            'stores' => $stores,
            'heroText' => 'Males Ke Pasar Barang Antik? Pesan VINSTORE Aja!',
            'showSearch' => true,
            'hasCoordinates' => $latitude !== null && $longitude !== null,
            'searchRadius' => floatval($radius),
        ]);
    }

    public function register(Request $request)
    {
        $user = Auth::user(); // Ambil user yang sedang login

        // Cek apakah user sudah memiliki toko
        if ($user->store) {
            return redirect()->route('seller.dashboard')->with('error', 'Anda sudah memiliki toko!');
        }

        $request->validate([
            'store_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $storeData = [
            'user_id' => $user->id,
            'store_name' => $request->store_name,
            'category' => $request->category,
            'description' => $request->description,
            'location' => $request->location,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ];

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('stores', 'public');
            $storeData['photo'] = $photoPath;
        }

        // Simpan data toko ke tabel stores
        Store::create($storeData);

        User::where('id', $user->id)->update([
            'role' => 'seller',
        ]);

        return redirect('/profile')->with('success', 'Toko berhasil didaftarkan!');
    }

    // public function dashboard()
    // {
    //     $user = Auth::user();
    //     $store = $user->store;

    //     if (!$store) {
    //         return redirect()->route('profile.edit')->with('error', 'Anda belum memiliki toko.');
    //     }

    //     $orders = Order::where('store_id', $store->id)->latest()->get();
    //     $products = Product::where('store_id', $store->id)->get();

    //     return view('seller.dashboard', compact('store', 'orders', 'products'));
    // }

    public function show($id)
    {
        app(\App\Services\PriceGuessService::class)->sync();

        $store = Store::with(['products' => fn ($query) => $query->approved()->latest(), 'user'])
            ->where('public_id', $id)
            ->firstOrFail();

        // Sembunyikan harga asli produk tebak harga yang masih dalam periode/prioritas.
        $viewer = Auth::user();
        $store->products->each(fn (Product $product) => $product->maskRealPriceFor($viewer));

        return Inertia::render('store/show', compact('store'));
    }

    public function edit()
    {
        $user = Auth::user();
        $store = $user->store;

        if (! $store) {
            return redirect()->route('store.register')->with('error', 'Anda belum memiliki toko!');
        }

        return Inertia::render('store/edit', compact('store'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $store = $user->store;

        if (! $store) {
            return redirect()->route('store.register')->with('error', 'Anda belum memiliki toko!');
        }

        $request->validate([
            'store_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $store->store_name = $request->store_name;
        $store->category = $request->category;
        $store->description = $request->description;
        $store->location = $request->location;
        $store->latitude = $request->latitude;
        $store->longitude = $request->longitude;

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Hapus foto lama jika ada
            if ($store->photo && Storage::disk('public')->exists($store->photo)) {
                Storage::disk('public')->delete($store->photo);
            }
            // Simpan foto baru
            $photoPath = $request->file('photo')->store('stores', 'public');
            $store->photo = $photoPath;
        }

        $store->save();

        return redirect()->route('seller.dashboard')->with('success', 'Toko berhasil diperbarui!');
    }
}
