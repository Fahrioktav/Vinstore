<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PlatformRevenue;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\TradeInRequest;
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
     * Requester meminta selisih uang tukar tambahnya dikembalikan karena tukar tambah tidak
     * pernah tuntas — misalnya pihak lawan tidak pernah mengirimkan barangnya.
     *
     * Keputusan ada di admin, yang bisa melihat status pengiriman kedua pihak
     * sebelum memutuskan.
     */
    public function storeForTradeIn(Request $request, TradeInRequest $tradeIn)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
            'proof_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $store = Auth::user()->store;

        // Hanya pihak yang benar-benar membayar selisihnya yang berhak
        // memintanya kembali — bisa pengaju, bisa juga penerima tukar tambah.
        if (! $tradeIn->isPayer($store)) {
            abort(403, 'Hanya pembayar selisih tukar tambah yang dapat meminta pengembalian dana.');
        }

        if (! $tradeIn->canRequestRefund()) {
            return back()->with('error', $this->tradeInRefundBlockReason($tradeIn));
        }

        $proofImagePath = null;

        if ($request->hasFile('proof_image')) {
            $proofImagePath = $request->file('proof_image')->store('refunds', 'public');
        }

        RefundRequest::create([
            'trade_in_request_id' => $tradeIn->id,
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
     * Laporannya disimpan sebagai RefundRequest atas tukar tambah tersebut, karena
     * keputusan admin yang diinginkan sama persis: batalkan tukar tambah, lepas kunci
     * kedua produk, dan kembalikan selisih uangnya bila ada. Berbeda dengan
     * storeForTradeIn(), jalur ini terbuka untuk KEDUA peran dan tidak
     * mensyaratkan adanya selisih uang — tukar tambah tanpa uang pun bisa macet.
     */
    public function reportStalledTradeIn(Request $request, TradeInRequest $tradeIn)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
            'proof_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $store = Auth::user()->store;

        if (! $store || $tradeIn->roleOfStore($store) === null) {
            abort(403, 'Anda bukan pihak dalam tukar tambah ini.');
        }

        if (! $tradeIn->canReportStalledBy($store)) {
            return back()->with(
                'error',
                $tradeIn->reportBlockReasonFor($store) ?? 'Tukar tambah ini belum dapat dilaporkan.'
            );
        }

        $proofImagePath = null;

        if ($request->hasFile('proof_image')) {
            $proofImagePath = $request->file('proof_image')->store('refunds', 'public');
        }

        RefundRequest::create([
            'trade_in_request_id' => $tradeIn->id,
            'user_id' => Auth::id(),
            'reason' => $validated['reason'],
            'proof_image' => $proofImagePath,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Laporan dikirim. Admin akan meninjau status pengiriman kedua pihak.');
    }

    private function tradeInRefundBlockReason(TradeInRequest $tradeIn): string
    {
        if (! $tradeIn->requiresPayment()) {
            return 'Tukar tambah ini tidak melibatkan selisih uang.';
        }

        if ($tradeIn->payment_status !== TradeInRequest::PAYMENT_PAID) {
            return 'Belum ada dana yang dibayarkan untuk tukar tambah ini.';
        }

        if ($tradeIn->isCompleted()) {
            return 'Tukar tambah ini sudah selesai dan tidak dapat dimintakan pengembalian dana.';
        }

        if ($tradeIn->payout_released_at !== null) {
            return 'Selisih uang tukar tambah ini sudah dicairkan ke pihak lawan.';
        }

        return 'Pengajuan pengembalian dana untuk tukar tambah ini sudah ada.';
    }

    public function adminIndex()
    {
        $refunds = RefundRequest::with([
            'user',
            'reviewer',
            'order.product',
            'order.auction',
            'order.store',
            'tradeInRequest.offeredProduct',
            'tradeInRequest.requestedProduct',
            'tradeInRequest.requesterStore',
            'tradeInRequest.responderStore',
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

                if ($refund->trade_in_request_id !== null) {
                    $this->approveTradeInRefund($refund);
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

                    // Uangnya dikembalikan ke pembeli, jadi biaya layanan atas
                    // pesanan ini tidak lagi menjadi pendapatan marketplace.
                    // Dicatat sebagai baris pembalikan, bukan dengan menghapus
                    // baris pendapatannya, agar riwayatnya tetap utuh.
                    PlatformRevenue::reverseServiceFee($order);
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
     * Tukar tambah gagal: selisih uang dikembalikan ke pembayarnya, tukar tambahnya dibatalkan,
     * dan kedua produk dilepas dari kunci tukar tambah agar bisa dijual/ditukar tambah lagi.
     *
     * Kepemilikan produk tidak pernah berpindah di jalur ini — pertukaran hanya
     * terjadi lewat TradeInController::confirmReceipt() saat kedua pihak
     * mengonfirmasi penerimaan.
     */
    private function approveTradeInRefund(RefundRequest $refund): void
    {
        $tradeIn = TradeInRequest::whereKey($refund->trade_in_request_id)->lockForUpdate()->firstOrFail();

        if ($tradeIn->payout_released_at !== null) {
            throw new \RuntimeException(
                'Selisih uang tukar tambah ini sudah dicairkan ke pihak lawan, sehingga tidak dapat dibalik otomatis. '
                .'Selesaikan pengembalian dana secara manual lalu tolak pengajuan ini.'
            );
        }

        if ($tradeIn->isCompleted()) {
            throw new \RuntimeException('Tukar tambah ini sudah selesai; pengembalian dana tidak dapat disetujui.');
        }

        $updates = ['status' => TradeInRequest::STATUS_CANCELLED];

        // Tukar tambah tanpa selisih uang tetap bisa dibatalkan lewat jalur ini
        // (laporan pihak lawan tidak mengirim). Tidak ada uang yang kembali,
        // jadi payment_status-nya jangan diubah jadi 'refunded'.
        if ($tradeIn->requiresPayment()) {
            $updates['payment_status'] = TradeInRequest::PAYMENT_REFUNDED;
        }

        $tradeIn->forceFill($updates)->save();

        Product::where('locked_for_trade_in_id', $tradeIn->id)
            ->update(['locked_for_trade_in_id' => null]);
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
        // mengajukan pencairan atas pesanan/tukar tambah ini. Dananya sendiri tidak
        // cair di sini — pencairan selalu lewat PayoutRequest yang disetujui
        // admin.

        return back()->with('success', 'Pengajuan refund berhasil ditolak. Seller dapat mengajukan pencairan atas dana ini.');
    }
}
