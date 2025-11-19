<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class StoreController extends Controller
{

    public function showRegisterForm()
    {
        return Inertia::render('store/register'); // ⬅ pastikan view ini ada
    }

    public function index()
    {
        $stores = Store::latest()->get(); // Ambil semua toko

        return Inertia::render('toko', [
            'stores' => $stores,
            'heroText' => 'Males Ke Pasar Barang Antik? Pesan VINSTORE Aja!',
            'showSearch' => true
        ]);
    }


    public function register(Request $request)
    {
        $request->validate([
            'store_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'required|string|max:255',
        ]);

        $user = Auth::user(); // Ambil user yang sedang login

        // Simpan data toko ke tabel stores
        Store::create([
            'user_id'     => $user->id,
            'store_name'  => $request->store_name,
            'category'    => $request->category,
            'description' => $request->description,
            'location'    => $request->location,
        ]);


        User::where('id', $user->id)->update([
            'role' => 'seller'
        ]);

        return redirect('/profile')->with('success', 'Toko berhasil didaftarkan!');
    }

    public function dashboard()
    {
        $user = Auth::user();
        $store = $user->store;

        if (!$store) {
            return redirect()->route('profile.edit')->with('error', 'Anda belum memiliki toko.');
        }

        $orders = Order::where('store_id', $store->id)->latest()->get();
        $products = Product::where('store_id', $store->id)->get();

        return view('seller.dashboard', compact('store', 'orders', 'products'));
    }

    public function show($id)
    {
        $store = Store::with('products', 'user')->findOrFail($id);
        return Inertia::render('store/show', compact('store'));
    }
}
