<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PayoutRequest;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SellerDashboardController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $store = $user->store;

        // Jika seller belum punya toko, redirect ke halaman registrasi toko
        if (! $store) {
            return redirect()->route('store.register')->with('error', 'Anda harus mendaftarkan toko terlebih dahulu');
        }

        // Ambil semua produk milik toko seller
        $products = $store->products()->latest()->get();
        $auctions = $store->auctions()->with(['winner', 'highestBid.user'])->latest()->get();

        $payouts = PayoutRequest::where('store_id', $store->id)
            ->with('order')
            ->latest()
            ->take(10)
            ->get();

        // Data bank dari pengajuan terakhir dipakai untuk mengisi form secara
        // otomatis, tapi seller tetap bisa mengubahnya saat mengajukan.
        $lastPayout = PayoutRequest::where('store_id', $store->id)->latest('id')->first();

        $bankPrefill = [
            'bank_name' => $lastPayout->bank_name ?? '',
            'account_number' => $lastPayout->account_number ?? '',
            'account_holder' => $lastPayout->account_holder ?? $user->first_name.' '.$user->last_name,
        ];

        // Hitung total produk
        $productCount = $store->products()->count();

        // Ambil semua order yang berkaitan dengan produk toko ini.
        // payoutRequests & refundRequest ikut dimuat supaya perhitungan
        // kelayakan pencairan per baris tidak memicu query tambahan.
        $orders = Order::where('store_id', $store->id)
            ->with(['product', 'auction', 'user', 'payoutRequests', 'refundRequest'])
            ->latest()
            ->get();

        $orders->each->append(['can_request_payout', 'payout_block_reason', 'latest_payout']);

        // Hitung total orders
        $orderCount = $orders->count();

        // Ringkasan dana, dihitung dari pesanan — bukan dari saldo tersimpan,
        // karena dana kini langsung ditransfer ke rekening seller saat
        // pencairan disetujui dan tidak pernah singgah di saldo toko.
        $readyToRequest = $orders->filter->can_request_payout->sum('price');

        $pendingPayoutAmount = PayoutRequest::where('store_id', $store->id)
            ->pending()
            ->sum('amount');

        $paidOutAmount = PayoutRequest::where('store_id', $store->id)
            ->where('status', PayoutRequest::STATUS_APPROVED)
            ->sum('amount');

        // Hitung total income (hanya order yang sudah selesai/delivered)
        $totalIncome = Order::where('store_id', $store->id)
            ->whereIn('status', ['Delivered', 'Completed'])
            ->sum('price');

        // Income bulan ini
        $monthlyIncome = Order::where('store_id', $store->id)
            ->whereIn('status', ['Delivered', 'Completed'])
            ->whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('m'))
            ->sum('price');

        // Catatan: grafik pendapatan bulanan dan "Top 5 Produk Terlaris" sudah
        // dihapus dari dashboard, berikut kedua kueri yang menyiapkan datanya —
        // yang bulanan menjalankan enam SUM terpisah setiap kali halaman ini
        // dibuka. Angka pendapatan yang masih ditampilkan adalah $totalIncome
        // dan $monthlyIncome di atas.

        return Inertia::render('seller/dashboard', compact(
            'products',
            'orders',
            'productCount',
            'orderCount',
            'store',
            'auctions',
            'payouts',
            'bankPrefill',
            'readyToRequest',
            'pendingPayoutAmount',
            'paidOutAmount',
            'totalIncome',
            'monthlyIncome'
        ));
    }
}
