<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Models\Product;

class OrderController extends Controller
{
    // Update status pesanan oleh seller
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required']);
        $order = Order::findOrFail($id);
        $order->status = $request->status;
        $order->save();

        return back()->with('success', 'Status pesanan diperbarui.');
    }

    // Proses checkout oleh customer
    public function processCheckout(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'payment_method' => 'required|string',
        ]);

        $user = Auth::user();

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
        $user = Auth::user(); // ← Pasti ada method user()
        if (!$user) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
        }

        $orders = Order::with('product')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return view('order', compact('orders'));
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
}
