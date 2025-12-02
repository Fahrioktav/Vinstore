<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Models\Product;
use Inertia\Inertia;

class OrderController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function showCheckout(Product $product)
    {
        $user = Auth::user();

        // Cek apakah user adalah seller dan mencoba checkout produk dari toko sendiri
        if ($user->role === 'seller' && $user->store && $product->store_id === $user->store->id) {
            return redirect()->back()->with('error', 'Anda tidak dapat membeli produk dari toko Anda sendiri!');
        }

        return Inertia::render('checkout', compact('product'));
    }
    
    // Update status pesanan oleh seller
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:Waiting,Processing,On The Way,Delivered,Cancelled']);
        
        $order = Order::findOrFail($id);
        
        // Pastikan hanya seller yang memiliki toko ini yang bisa update
        if ($order->store_id !== Auth::user()->store->id) {
            abort(403, 'Anda tidak memiliki akses untuk mengupdate order ini.');
        }
        
        $order->update([
            'status' => $request->status
        ]);

        return back()->with('success', 'Status pesanan berhasil diperbarui.');
    }

    // Delete order
    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return back()->with('success', 'Order berhasil dihapus.');
    }

    // Proses checkout oleh customer
    public function processCheckout(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'payment_method' => 'required|string',
        ]);

        $user = Auth::user();

        // Cek apakah user adalah seller dan mencoba membeli produk dari toko sendiri
        if ($user->role === 'seller' && $user->store && $product->store_id === $user->store->id) {
            return back()->with('error', 'Anda tidak dapat membeli produk dari toko Anda sendiri!');
        }

        // 🔒 Cek apakah stok mencukupi
        if ($product->stock < $request->quantity) {
            return back()->with('error', 'Stok produk tidak mencukupi atau sudah habis.');
        }

        // Hitung total harga
        $totalPrice = $product->price * $request->quantity;

        // Kurangi stok produk
        $product->stock -= $request->quantity;
        $product->save();

        // Simpan order ke database
        Order::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'store_id' => $product->store_id,
            'quantity' => $request->quantity,
            'price' => $totalPrice,
            'status' => 'Waiting',
        ]);

        return redirect()->route('order')->with('success', 'Pesanan berhasil dibuat!');
    }


    public function userOrders()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
        }

        $orders = Order::with(['product', 'store'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return Inertia::render('order', compact('orders'));
    }

    public function cancelOrder($id)
    {
        $user = Auth::user(); // Pastikan pakai Auth::user()
        if (!$user) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
        }

        $order = Order::where('id', $id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['Waiting', 'On The Way'])
            ->firstOrFail();

        $order->status = 'Cancelled';
        $order->save();

        return back()->with('success', 'Pesanan berhasil dibatalkan.');
    }

    public function checkoutFromCart()
    {
        $user = Auth::user();
        $cartItems = Cart::with('product')->where('user_id', $user->id)->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang kamu kosong.');
        }

        // Cek apakah user adalah seller dan ada produk dari toko sendiri di keranjang
        if ($user->role === 'seller' && $user->store) {
            $ownProducts = $cartItems->filter(function($item) use ($user) {
                return $item->product->store_id === $user->store->id;
            });

            if ($ownProducts->isNotEmpty()) {
                return redirect()->route('cart.index')->with('error', 'Keranjang Anda mengandung produk dari toko Anda sendiri. Silakan hapus produk tersebut terlebih dahulu.');
            }
        }

        $total = 0;
        foreach ($cartItems as $item) {
            $total += $item->product->price * $item->quantity;

            // Buat order untuk tiap item
            Order::create([
                'user_id'    => $user->id,
                'product_id' => $item->product_id,
                'store_id'   => $item->product->store_id,
                'quantity'   => $item->quantity,
                'price'      => $item->product->price * $item->quantity,
                'status'     => 'Waiting',
            ]);

            // Kurangi stok produk
            $item->product->stock -= $item->quantity;
            $item->product->save();
        }

        // Hapus semua item dari keranjang setelah checkout
        Cart::where('user_id', $user->id)->delete();

        return redirect()->route('order')->with('success', 'Checkout berhasil untuk semua produk!');
    }
}
