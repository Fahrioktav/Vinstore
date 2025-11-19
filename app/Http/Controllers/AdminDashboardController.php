<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index()
    {
        // Pastikan hanya admin yang bisa akses
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized');
        }

        $totalUsers = User::where('role', 'user')->count();
        $totalSellers = User::where('role', 'seller')->count();
        $totalStores = Store::count();
        $totalProducts = Product::count();
        $totalOrders = Order::count();
        $totalCategories = Category::count();
        $products = Product::with('store')->latest()->get();

        return Inertia::render('admin/dashboard', compact(
            'totalUsers', 'totalSellers', 'totalStores', 'totalProducts',
            'totalOrders', 'totalCategories', 'products'
        ));
    }
}
