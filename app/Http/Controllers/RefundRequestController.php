<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\RefundRequest;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RefundRequestController extends Controller
{
    public function store(Request $request, $order)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
            'proof_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $order = Order::with('refundRequest')
            ->where('public_id', $order)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($order->payment_status !== 'paid') {
            return back()->with('error', 'Refund hanya bisa diajukan untuk pesanan yang sudah dibayar.');
        }

        if (!in_array($order->status, ['Delivered', 'Completed'], true)) {
            return back()->with('error', 'Refund hanya bisa diajukan setelah pesanan diterima/selesai.');
        }

        if ($order->refundRequest) {
            return back()->with('error', 'Refund untuk pesanan ini sudah pernah diajukan.');
        }

        $proofImagePath = null;

        if ($request->hasFile('proof_image')) {
            $proofImagePath = $request->file('proof_image')->store('refunds', 'public');
        }

        RefundRequest::create([
            'order_id' => $order->id,
            'user_id' => Auth::id(),
            'reason' => $validated['reason'],
            'proof_image' => $proofImagePath,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Pengajuan refund berhasil dikirim dan menunggu persetujuan admin.');
    }

    public function adminIndex()
    {
        $refunds = RefundRequest::with([
            'user',
            'reviewer',
            'order.product',
            'order.auction',
            'order.store',
        ])->latest()->get();

        return Inertia::render('admin/refunds/index', compact('refunds'));
    }

    public function approve(Request $request, RefundRequest $refund)
    {
        if ($refund->status !== 'pending') {
            return back()->with('error', 'Pengajuan refund ini sudah diproses.');
        }

        try {
            DB::transaction(function () use ($request, $refund) {
                $refund = RefundRequest::whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
                $order = Order::whereKey($refund->order_id)->lockForUpdate()->firstOrFail();

                if ($refund->status !== 'pending') {
                    throw new \RuntimeException('Pengajuan refund ini sudah diproses.');
                }

                if ($order->seller_released_at !== null) {
                    $store = Store::whereKey($order->store_id)->lockForUpdate()->firstOrFail();

                    if ((float) $store->available_balance < (float) $order->price) {
                        throw new \RuntimeException('Saldo toko tidak mencukupi untuk membalik dana refund. Tolak refund atau proses manual.');
                    }

                    $store->decrement('available_balance', $order->price);
                }

                $order->update([
                    'payment_status' => 'refunded',
                ]);

                $refund->update([
                    'status' => 'approved',
                    'admin_note' => $request->input('admin_note'),
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan refund berhasil disetujui.');
    }

    public function reject(Request $request, RefundRequest $refund)
    {
        if ($refund->status !== 'pending') {
            return back()->with('error', 'Pengajuan refund ini sudah diproses.');
        }

        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $refund->update([
            'status' => 'rejected',
            'admin_note' => $validated['admin_note'] ?? null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Pengajuan refund berhasil ditolak.');
    }
}
