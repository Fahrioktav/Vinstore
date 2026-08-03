<?php

namespace App\Http\Controllers;

use App\Models\BarterRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\RefundRequest;
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

        if (! in_array($order->status, ['Delivered', 'Completed'], true)) {
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

    /**
     * Requester meminta selisih uang barternya dikembalikan karena barter tidak
     * pernah tuntas — misalnya pihak lawan tidak pernah mengirimkan barangnya.
     *
     * Keputusan ada di admin, yang bisa melihat status pengiriman kedua pihak
     * sebelum memutuskan.
     */
    public function storeForBarter(Request $request, BarterRequest $barter)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
            'proof_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $store = Auth::user()->store;

        // Yang membayar selisih adalah requester, jadi hanya dia yang berhak
        // memintanya kembali.
        if (! $store || $barter->requester_store_id !== $store->id) {
            abort(403, 'Hanya pengaju barter yang dapat meminta pengembalian dana.');
        }

        if (! $barter->canRequestRefund()) {
            return back()->with('error', $this->barterRefundBlockReason($barter));
        }

        $proofImagePath = null;

        if ($request->hasFile('proof_image')) {
            $proofImagePath = $request->file('proof_image')->store('refunds', 'public');
        }

        RefundRequest::create([
            'barter_request_id' => $barter->id,
            'user_id' => Auth::id(),
            'reason' => $validated['reason'],
            'proof_image' => $proofImagePath,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Pengajuan pengembalian dana dikirim dan menunggu persetujuan admin.');
    }

    /**
     * Pihak yang sudah mengirim melaporkan bahwa lawannya tidak kunjung
     * mengisi resi setelah tenggat terlampaui.
     *
     * Laporannya disimpan sebagai RefundRequest atas barter tersebut, karena
     * keputusan admin yang diinginkan sama persis: batalkan barter, lepas kunci
     * kedua produk, dan kembalikan selisih uangnya bila ada. Berbeda dengan
     * storeForBarter(), jalur ini terbuka untuk KEDUA peran dan tidak
     * mensyaratkan adanya selisih uang — barter tanpa uang pun bisa macet.
     */
    public function reportStalledBarter(Request $request, BarterRequest $barter)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
            'proof_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $store = Auth::user()->store;

        if (! $store || $barter->roleOfStore($store) === null) {
            abort(403, 'Anda bukan pihak dalam barter ini.');
        }

        if (! $barter->canReportStalledBy($store)) {
            return back()->with(
                'error',
                $barter->reportBlockReasonFor($store) ?? 'Barter ini belum dapat dilaporkan.'
            );
        }

        $proofImagePath = null;

        if ($request->hasFile('proof_image')) {
            $proofImagePath = $request->file('proof_image')->store('refunds', 'public');
        }

        RefundRequest::create([
            'barter_request_id' => $barter->id,
            'user_id' => Auth::id(),
            'reason' => $validated['reason'],
            'proof_image' => $proofImagePath,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Laporan dikirim. Admin akan meninjau status pengiriman kedua pihak.');
    }

    private function barterRefundBlockReason(BarterRequest $barter): string
    {
        if (! $barter->requiresPayment()) {
            return 'Barter ini tidak melibatkan selisih uang.';
        }

        if ($barter->payment_status !== BarterRequest::PAYMENT_PAID) {
            return 'Belum ada dana yang dibayarkan untuk barter ini.';
        }

        if ($barter->isCompleted()) {
            return 'Barter ini sudah selesai dan tidak dapat dimintakan pengembalian dana.';
        }

        if ($barter->payout_released_at !== null) {
            return 'Selisih uang barter ini sudah dicairkan ke pihak lawan.';
        }

        return 'Pengajuan pengembalian dana untuk barter ini sudah ada.';
    }

    public function adminIndex()
    {
        $refunds = RefundRequest::with([
            'user',
            'reviewer',
            'order.product',
            'order.auction',
            'order.store',
            'barterRequest.offeredProduct',
            'barterRequest.requestedProduct',
            'barterRequest.requesterStore',
            'barterRequest.responderStore',
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

                if ($refund->status !== 'pending') {
                    throw new \RuntimeException('Pengajuan refund ini sudah diproses.');
                }

                if ($refund->barter_request_id !== null) {
                    $this->approveBarterRefund($refund);
                } else {
                    $order = Order::whereKey($refund->order_id)->lockForUpdate()->firstOrFail();

                    // Dana pesanan ditransfer langsung ke rekening seller saat
                    // pencairan disetujui, jadi tidak ada saldo internal yang
                    // bisa ditarik balik. Refund atas pesanan yang sudah cair
                    // harus diselesaikan di luar sistem.
                    if ($order->seller_released_at !== null) {
                        throw new \RuntimeException(
                            'Dana pesanan ini sudah dicairkan ke rekening seller, sehingga tidak dapat dibalik otomatis. '
                            .'Selesaikan pengembalian dana secara manual lalu tolak pengajuan ini.'
                        );
                    }

                    $order->update([
                        'payment_status' => 'refunded',
                    ]);
                }

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

    /**
     * Barter gagal: selisih uang dikembalikan ke pengaju, barternya dibatalkan,
     * dan kedua produk dilepas dari kunci barter agar bisa dijual/dibarter lagi.
     *
     * Kepemilikan produk tidak pernah berpindah di jalur ini — pertukaran hanya
     * terjadi lewat BarterController::confirmReceipt() saat kedua pihak
     * mengonfirmasi penerimaan.
     */
    private function approveBarterRefund(RefundRequest $refund): void
    {
        $barter = BarterRequest::whereKey($refund->barter_request_id)->lockForUpdate()->firstOrFail();

        if ($barter->payout_released_at !== null) {
            throw new \RuntimeException(
                'Selisih uang barter ini sudah dicairkan ke pihak lawan, sehingga tidak dapat dibalik otomatis. '
                .'Selesaikan pengembalian dana secara manual lalu tolak pengajuan ini.'
            );
        }

        if ($barter->isCompleted()) {
            throw new \RuntimeException('Barter ini sudah selesai; pengembalian dana tidak dapat disetujui.');
        }

        $updates = ['status' => BarterRequest::STATUS_CANCELLED];

        // Barter tanpa selisih uang tetap bisa dibatalkan lewat jalur ini
        // (laporan pihak lawan tidak mengirim). Tidak ada uang yang kembali,
        // jadi payment_status-nya jangan diubah jadi 'refunded'.
        if ($barter->requiresPayment()) {
            $updates['payment_status'] = BarterRequest::PAYMENT_REFUNDED;
        }

        $barter->forceFill($updates)->save();

        Product::where('locked_for_barter_id', $barter->id)
            ->update(['locked_for_barter_id' => null]);
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

        // Sanggahan ditolak berarti penahanan dicabut: seller kini bisa
        // mengajukan pencairan atas pesanan/barter ini. Dananya sendiri tidak
        // cair di sini — pencairan selalu lewat PayoutRequest yang disetujui
        // admin.

        return back()->with('success', 'Pengajuan refund berhasil ditolak. Seller dapat mengajukan pencairan atas dana ini.');
    }
}
