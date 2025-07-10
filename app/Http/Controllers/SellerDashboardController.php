<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Store;
use App\Models\Product;
use App\Models\Order;

class SellerDashboardController extends Controller
{
    public function index()
    {
        $store = Auth::user()->store;

        $products = $store->products()->latest()->get();

        // Ambil semua orders untuk produk dari toko ini
        $orders = Order::whereIn('product_id', $products->pluck('id'))
            ->with(['product', 'user']) // include relasi
            ->latest()
            ->get();

        return view('seller.dashboard', compact('products', 'orders'));
    }
}
