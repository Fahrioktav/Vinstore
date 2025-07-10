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

        Order::create([
            'user_id'    => Auth::id(),
            'product_id' => $product->id,
            'store_id'   => $product->store_id,
            'quantity'   => $request->quantity,
            'price'      => $product->price * $request->quantity,
            'status'     => 'Waiting'
        ]);

        return redirect()->route('order')->with('success', 'Pesanan berhasil dibuat!');
    }
}
