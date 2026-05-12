<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Store;
use App\Models\Product;
use App\Models\Order;
use Inertia\Inertia;

class SellerDashboardController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $store = $user->store;

        // Jika seller belum punya toko, redirect ke halaman registrasi toko
        if (!$store) {
            return redirect()->route('store.register')->with('error', 'Anda harus mendaftarkan toko terlebih dahulu');
        }

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

        // Hitung total income (hanya order yang sudah selesai/delivered)
        $totalIncome = Order::whereIn('product_id', $products->pluck('id'))
            ->whereIn('status', ['Delivered', 'Completed'])
            ->sum('price');

        // Income bulan ini
        $monthlyIncome = Order::whereIn('product_id', $products->pluck('id'))
            ->whereIn('status', ['Delivered', 'Completed'])
            ->whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('m'))
            ->sum('price');

        // Income per bulan untuk grafik (6 bulan terakhir)
        $monthlyIncomeData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $monthName = date('M Y', strtotime("-$i months"));
            
            $income = Order::whereIn('product_id', $products->pluck('id'))
                ->whereIn('status', ['Delivered', 'Completed'])
                ->whereYear('created_at', date('Y', strtotime("-$i months")))
                ->whereMonth('created_at', date('m', strtotime("-$i months")))
                ->sum('price');
            
            $monthlyIncomeData[] = [
                'month' => $monthName,
                'income' => $income
            ];
        }

        // Income per produk (top 5)
        $productIncome = Order::whereIn('product_id', $products->pluck('id'))
            ->whereIn('status', ['Delivered', 'Completed'])
            ->selectRaw('product_id, SUM(price) as total_income')
            ->groupBy('product_id')
            ->orderByDesc('total_income')
            ->limit(5)
            ->with('product')
            ->get()
            ->map(function($order) {
                return [
                    'name' => $order->product->name,
                    'income' => $order->total_income
                ];
            });

        return Inertia::render('seller/dashboard', compact(
            'products', 
            'orders', 
            'productCount', 
            'orderCount', 
            'store',
            'totalIncome',
            'monthlyIncome',
            'monthlyIncomeData',
            'productIncome'
        ));
    }
}
