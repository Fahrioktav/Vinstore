<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use App\Services\ShippingCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CartController extends Controller
{
    public function index()
    {
        // Koordinat toko ikut dimuat karena ongkir dihitung dari jarak toko ke
        // titik pengantaran, dan ongkir ditagih sekali per toko.
        $cartItems = Cart::with('product.store:id,public_id,store_name,latitude,longitude')
            ->where('user_id', Auth::id())
            ->get();

        // Produk tebak harga yang sudah publik tetap tidak boleh membocorkan
        // harga diskonnya lewat serialisasi keranjang.
        $viewer = Auth::user();
        $cartItems->each(fn (Cart $item) => $item->product?->maskRealPriceFor($viewer));

        return Inertia::render('cart', [
            'cartItems' => $cartItems,
            // Tarif dikirim agar pratinjau biaya di keranjang memakai angka
            // yang sama dengan yang ditagihkan server.
            'feeRates' => app(ShippingCostService::class)->publicRates(),
        ]);
    }

    public function add(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();

        if ($product->approval_status !== 'approved') {
            return back()->with('error', 'Produk ini belum disetujui admin.');
        }

        if ($product->isSellerDeactivated()) {
            return back()->with('error', 'Produk ini sedang tidak tersedia karena akun penjualnya dinonaktifkan.');
        }

        // Cek apakah user adalah seller dan mencoba membeli produk dari toko sendiri
        if ($user->role === 'seller' && $user->store && $product->store_id === $user->store->id) {
            return back()->with('error', 'Anda tidak dapat membeli produk dari toko Anda sendiri!');
        }

        // Produk Tebak Harga hanya bisa masuk keranjang ketika sudah jadi penjualan biasa (public).
        if ($product->isTebakHarga() && ! $product->isPurchasableBy($user)) {
            return back()->with('error', 'Produk Tebak Harga ini belum dapat dibeli. Ikuti dulu proses tebak harganya.');
        }

        // Produk yang sedang terikat tukar tambah berjalan tidak boleh dibeli sampai
        // tukar tambahnya tuntas atau batal (temuan T-08).
        if ($product->isLockedForTradeIn()) {
            return back()->with('error', 'Produk ini sedang dalam proses tukar tambah dan belum tersedia untuk dibeli.');
        }

        // Cek stok produk
        if ($product->stock <= 0) {
            return back()->with('error', 'Maaf, produk ini sudah habis!');
        }

        if ($product->stock < $request->quantity) {
            return back()->with('error', 'Stok tidak mencukupi! Stok tersedia: '.$product->stock);
        }

        $user = Auth::user();

        // Cek apakah produk sudah ada di keranjang
        $existing = Cart::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            // Cek total quantity tidak melebihi stok
            $totalQuantity = $existing->quantity + $request->quantity;
            if ($totalQuantity > $product->stock) {
                return back()->with('error', 'Stok tidak mencukupi! Stok tersedia: '.$product->stock.', di keranjang: '.$existing->quantity);
            }
            // Tambah kuantitas jika sudah ada
            $existing->quantity = $totalQuantity;
            $existing->save();
        } else {
            // Tambah item baru
            Cart::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'store_id' => $product->store_id,
                'quantity' => $request->quantity,
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Produk berhasil ditambahkan ke keranjang.');
    }

    public function remove(Cart $cart)
    {
        if ($cart->user_id !== Auth::id()) {
            abort(403);
        }
        $cart->delete();

        return back()->with('success', 'Produk dihapus dari keranjang.');
    }
}
