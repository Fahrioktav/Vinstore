<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    public function show($productId)
    {
        $product = Product::findOrFail($productId);
        return view('checkout', compact('product'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required',
            'quantity' => 'required|integer|min:1'
        ]);

        $product = Product::findOrFail($request->product_id);

        // Cek apakah stok tersedia
        if ($product->stock < $request->quantity) {
            return back()->with('error', 'Stok tidak mencukupi! Stok tersedia: ' . $product->stock);
        }

        // Cek apakah stok habis
        if ($product->stock <= 0) {
            return back()->with('error', 'Maaf, produk ini sudah habis!');
        }

        // Buat order
        Order::create([
            'user_id'    => Auth::id(),
            'product_id' => $product->id,
            'store_id'   => $product->store_id,
            'quantity'   => $request->quantity,
            'price'      => $product->price * $request->quantity,
            'status'     => 'Waiting'
        ]);

        // Kurangi stok produk
        $product->decrement('stock', $request->quantity);

        return redirect()->route('order')->with('success', 'Pesanan berhasil dibuat!');
    }
}
