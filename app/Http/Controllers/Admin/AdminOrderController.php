<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('user', 'product')->latest()->get();
        return view('admin.orders.index', compact('orders'));
    }

    public function edit($id)
    {
        $order = Order::with('user', 'product')->findOrFail($id);
        return view('admin.orders.edit', compact('order'));
    }

    public function update(Request $request, $id)
    {
        $request->validate(['status' => 'required']);
        $order = Order::findOrFail($id);
        $order->status = $request->status;
        $order->save();

        return redirect()->route('admin.orders.index')->with('success', 'Status pesanan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        Order::destroy($id);
        return back()->with('success', 'Pesanan berhasil dihapus.');
    }
}
