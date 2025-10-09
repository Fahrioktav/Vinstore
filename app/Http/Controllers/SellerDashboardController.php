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

        // Ambil semua produk milik toko seller
        $products = $store->products()->latest()->get();

        // Hitung total produk
        $productCount = $store->products()->count();

        // Ambil semua order yang berkaitan dengan produk toko ini
        $orders = Order::whereIn('product_id', $products->pluck('id'))
            ->with(['product', 'user']) // eager load relasi
            ->latest()
            ->get();

        // Hitung total orders
        $orderCount = $orders->count();

        return view('seller.dashboard', compact('products', 'orders', 'productCount', 'orderCount'));
    }
}
