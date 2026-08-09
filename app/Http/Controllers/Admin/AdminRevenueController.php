<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PlatformRevenue;
use Inertia\Inertia;

/**
 * Dompet admin: dari mana marketplace ini mendapat pemasukan.
 *
 * Saldo tidak disimpan sebagai satu angka melainkan dijumlahkan dari buku besar
 * platform_revenues, sehingga angka yang tampil selalu bisa ditelusuri ke
 * pesanan yang menghasilkannya.
 */
class AdminRevenueController extends Controller
{
    public function index()
    {
        $revenues = PlatformRevenue::with('order:id,public_id,product_name,store_name,price')
            ->latest('id')
            ->limit(200)
            ->get();

        $earned = (int) PlatformRevenue::where('source', PlatformRevenue::SOURCE_SERVICE_FEE)->sum('amount');
        $reversed = (int) PlatformRevenue::where('source', PlatformRevenue::SOURCE_SERVICE_FEE_REVERSAL)->sum('amount');

        // Biaya yang ditagihkan tapi BUKAN pendapatan platform, ditampilkan
        // sebagai pembanding agar terlihat jelas mana yang jadi untung dan mana
        // yang hanya lewat (ongkir/berat ke pengiriman, pengemasan ke seller).
        $paidOrders = Order::where('payment_status', 'paid');

        return Inertia::render('admin/revenues/index', [
            'revenues' => $revenues,
            'summary' => [
                'balance' => $earned + $reversed,
                'earned' => $earned,
                'reversed' => abs($reversed),
                'entry_count' => PlatformRevenue::count(),
                'paid_order_count' => (clone $paidOrders)->count(),
                'shipping_collected' => (int) (clone $paidOrders)->sum('shipping_cost'),
                'weight_collected' => (int) (clone $paidOrders)->sum('weight_fee'),
                'packaging_collected' => (int) (clone $paidOrders)->sum('packaging_fee'),
            ],
            'serviceFeePercent' => (float) config('marketplace.service_fee.percent'),
        ]);
    }
}
