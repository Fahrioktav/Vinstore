<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Store;
use App\Models\Product;

class AdminController extends Controller
{
    public function index()
    {
        return view('inertia.admin.dashboard');
    }

    public function users()
    {
        $users = User::all();
        return view('admin.manage.users', compact('users'));
    }

    public function sellers()
    {
        $sellers = User::where('role', 'seller')->get();
        return view('admin.manage.sellers', compact('sellers'));
    }

    public function stores()
    {
        $stores = Store::with('user')->get();
        return view('admin.manage.stores', compact('stores'));
    }

    public function products()
    {
        $products = Product::with('store')->get();
        return view('admin.manage.products', compact('products'));
    }
}
