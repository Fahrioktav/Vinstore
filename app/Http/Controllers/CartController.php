<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CartController extends Controller
{
    public function index()
    {
        // Toko ikut dimuat karena ongkir dihitung per toko di halaman keranjang.
        $cartItems = Cart::with('product.store:id,public_id,store_name')
            ->where('user_id', Auth::id())
            ->get();

        return Inertia::render('cart', compact('cartItems'));
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

        // Cek apakah user adalah seller dan mencoba membeli produk dari toko sendiri
        if ($user->role === 'seller' && $user->store && $product->store_id === $user->store->id) {
            return back()->with('error', 'Anda tidak dapat membeli produk dari toko Anda sendiri!');
        }

        // Produk Tebak Harga hanya bisa masuk keranjang ketika sudah jadi penjualan biasa (public).
        if ($product->isTebakHarga() && ! $product->isPurchasableBy($user)) {
            return back()->with('error', 'Produk Tebak Harga ini belum dapat dibeli. Ikuti dulu proses tebak harganya.');
        }

        // Produk yang sedang terikat barter berjalan tidak boleh dibeli sampai
        // barternya tuntas atau batal (temuan T-08).
        if ($product->isLockedForBarter()) {
            return back()->with('error', 'Produk ini sedang dalam proses barter dan belum tersedia untuk dibeli.');
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
