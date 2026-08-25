<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminOrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('user', 'product', 'auction', 'store')->latest()->get();

        return Inertia::render('admin/orders/index', compact('orders'));
    }

    public function edit($id)
    {
        $order = Order::with('user', 'product', 'auction', 'store')->where('public_id', $id)->firstOrFail();

        return Inertia::render('admin/orders/edit', compact('order'));
    }

    public function update(Request $request, $id)
    {
        // Daftar putih, bukan sekadar `required`. Kolom ini dibaca belasan
        // penyaring di seluruh aplikasi; satu nilai di luar daftar membuat
        // pesanannya lenyap dari semua daftar sekaligus — tidak muncul sebagai
        // aktif, tidak muncul sebagai selesai, dan tidak bisa dikembalikan ke
        // jalur mana pun (temuan S-12).
        $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
        ], [
            'status.in' => 'Status pesanan tidak dikenal.',
        ]);

        $order = Order::where('public_id', $id)->firstOrFail();

        // Pesanan yang sudah dikonfirmasi pembeli tidak boleh dimundurkan.
        // Konfirmasi itulah dasar pencairan dana ke seller; menganulirnya
        // sesudahnya membuat dua catatan yang saling bertentangan tentang
        // transaksi yang sama (temuan V2-05).
        if ($order->status === 'Completed') {
            return back()->with('error', 'Pesanan yang sudah dikonfirmasi pembeli tidak dapat diubah statusnya.');
        }

        $order->status = $request->status;
        $order->save();

        // Mengubah status tidak mencairkan dana. Pencairan hanya lewat
        // pengajuan seller yang disetujui admin (PayoutRequest).

        return redirect()->route('admin.orders.index')->with('success', 'Status pesanan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        Order::where('public_id', $id)->firstOrFail()->delete();

        return back()->with('success', 'Pesanan berhasil dihapus.');
    }
}
