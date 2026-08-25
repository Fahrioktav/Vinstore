<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\Store;
use App\Models\TradeInRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use RuntimeException;

/**
 * Pencairan dana per pesanan.
 *
 * Dana pesanan tidak pernah cair otomatis. Seller mengajukan lewat tombol di
 * baris pesanan (dashboard seller), admin menyetujui sambil mengunggah bukti
 * transfer ke rekening yang diajukan.
 */
class PayoutRequestController extends Controller
{
    public function store(Request $request, $order)
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:100',
            'account_holder' => 'required|string|max:150',
        ], [
            'bank_name.required' => 'Nama bank wajib diisi.',
            'account_number.required' => 'Nomor rekening wajib diisi.',
            'account_holder.required' => 'Nama pemilik rekening wajib diisi.',
        ]);

        $store = Auth::user()->store;

        if (! $store) {
            return redirect()->route('store.register')->with('error', 'Anda harus memiliki toko terlebih dahulu.');
        }

        $order = Order::where('public_id', $order)->firstOrFail();

        if ($order->store_id !== $store->id) {
            abort(403, 'Anda tidak memiliki akses ke pesanan ini.');
        }

        try {
            DB::transaction(function () use ($order, $store, $validated) {
                // Kunci pesanan: tanpa ini dua klik beruntun bisa membuat dua
                // pengajuan untuk pesanan yang sama.
                $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

                if (! $locked->canRequestPayout()) {
                    throw new RuntimeException(
                        $locked->payoutBlockReason() ?? 'Pesanan ini belum dapat diajukan pencairannya.'
                    );
                }

                PayoutRequest::create([
                    'order_id' => $locked->id,
                    'store_id' => $store->id,
                    // Bukan `price`: total tagihan mengandung ongkir dan biaya
                    // layanan — satu-satunya komponen yang bukan hak seller.
                    // Lihat Order::sellerPayoutAmount().
                    'amount' => $locked->sellerPayoutAmount(),
                    'bank_name' => $validated['bank_name'],
                    'account_number' => $validated['account_number'],
                    'account_holder' => $validated['account_holder'],
                    'status' => PayoutRequest::STATUS_PENDING,
                ]);
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan pencairan dikirim dan menunggu persetujuan admin.');
    }

    /**
     * Responder mengajukan pencairan selisih uang (additional_cash) tukar tambah.
     *
     * Uang itu dibayar requester karena produk yang ia tawarkan lebih murah,
     * jadi haknya ada pada responder. Baru bisa diajukan setelah tukar tambah
     * berstatus completed — yaitu kedua pihak sudah mengonfirmasi barang
     * diterima.
     */
    public function storeForTradeIn(Request $request, TradeInRequest $tradeIn)
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:100',
            'account_holder' => 'required|string|max:150',
        ], [
            'bank_name.required' => 'Nama bank wajib diisi.',
            'account_number.required' => 'Nomor rekening wajib diisi.',
            'account_holder.required' => 'Nama pemilik rekening wajib diisi.',
        ]);

        $store = Auth::user()->store;

        if (! $store || $tradeIn->payoutRecipientStoreId() !== $store->id) {
            abort(403, 'Hanya penerima selisih uang tukar tambah ini yang dapat mengajukan pencairan.');
        }

        try {
            DB::transaction(function () use ($tradeIn, $store, $validated) {
                $locked = TradeInRequest::whereKey($tradeIn->getKey())->lockForUpdate()->firstOrFail();

                if (! $locked->canRequestPayout()) {
                    throw new RuntimeException(
                        $locked->payoutBlockReason() ?? 'Tukar tambah ini belum dapat diajukan pencairannya.'
                    );
                }

                PayoutRequest::create([
                    'trade_in_request_id' => $locked->id,
                    'store_id' => $store->id,
                    'amount' => $locked->additional_cash,
                    'bank_name' => $validated['bank_name'],
                    'account_number' => $validated['account_number'],
                    'account_holder' => $validated['account_holder'],
                    'status' => PayoutRequest::STATUS_PENDING,
                ]);
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan pencairan selisih tukar tambah dikirim dan menunggu persetujuan admin.');
    }

    public function adminIndex()
    {
        $payouts = PayoutRequest::with([
            'store.user',
            'reviewer',
            'order.user',
            'order.product',
            'order.auction',
            'tradeInRequest.offeredProduct',
            'tradeInRequest.requestedProduct',
            'tradeInRequest.requesterStore',
            'tradeInRequest.responderStore',
        ])->latest()->get();

        // Pembayar selisih bisa pengaju maupun penerima, jadi namanya dihitung
        // di server alih-alih ditebak frontend dari requester_store.
        $payouts->each(fn ($payout) => $payout->tradeInRequest?->append('payer_store_name'));

        return Inertia::render('admin/payouts/index', compact('payouts'));
    }

    /**
     * Admin menyetujui pencairan setelah benar-benar mentransfer dana ke
     * rekening yang diajukan. Bukti transfer wajib agar ada jejak audit atas
     * uang yang keluar — sama seperti pencairan saldo.
     */
    public function approve(Request $request, PayoutRequest $payout)
    {
        if ($payout->status !== PayoutRequest::STATUS_PENDING) {
            return back()->with('error', 'Pengajuan pencairan ini sudah diproses.');
        }

        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:1000',
            'transfer_proof' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'transfer_proof.required' => 'Bukti transfer wajib diunggah sebelum pencairan disetujui.',
        ]);

        $proofPath = $request->file('transfer_proof')->store('payouts', 'public');

        try {
            DB::transaction(function () use ($validated, $payout, $proofPath) {
                $payout = PayoutRequest::whereKey($payout->getKey())->lockForUpdate()->firstOrFail();

                if ($payout->status !== PayoutRequest::STATUS_PENDING) {
                    throw new RuntimeException('Pengajuan pencairan ini sudah diproses.');
                }

                // Sumber dananya bisa pesanan atau selisih tukar tambah. Keduanya
                // dikunci dan diperiksa ulang di dalam transaksi, karena
                // sengketa bisa muncul setelah pengajuan dibuat.
                if ($payout->trade_in_request_id !== null) {
                    $tradeIn = TradeInRequest::whereKey($payout->trade_in_request_id)->lockForUpdate()->firstOrFail();

                    if ($tradeIn->payout_released_at !== null) {
                        throw new RuntimeException('Selisih uang tukar tambah ini sudah pernah dicairkan.');
                    }

                    if ($tradeIn->status !== TradeInRequest::STATUS_COMPLETED) {
                        throw new RuntimeException('Tukar tambah ini belum selesai; kedua pihak harus mengonfirmasi barang diterima.');
                    }

                    if ($tradeIn->hasPendingRefund() || $tradeIn->hasApprovedRefund()) {
                        throw new RuntimeException('Ada pengajuan pengembalian dana untuk tukar tambah ini.');
                    }

                    $tradeIn->markPayoutReleased();
                } else {
                    $order = Order::whereKey($payout->order_id)->lockForUpdate()->firstOrFail();

                    if ($order->seller_released_at !== null) {
                        throw new RuntimeException('Dana pesanan ini sudah pernah dicairkan.');
                    }

                    if ($order->hasPendingRefund()) {
                        throw new RuntimeException('Ada pengajuan refund yang belum diputus untuk pesanan ini.');
                    }

                    $order->markSellerPaid();
                }

                // Dana langsung ke rekening seller, tidak singgah di saldo toko.
                // withdrawn_balance tetap dijaga sebagai akumulasi yang sudah
                // ditransfer, karena dashboard seller menampilkannya.
                Store::whereKey($payout->store_id)->increment('withdrawn_balance', $payout->amount);

                $payout->update([
                    'status' => PayoutRequest::STATUS_APPROVED,
                    'admin_note' => $validated['admin_note'] ?? null,
                    'transfer_proof' => $proofPath,
                    'transferred_at' => now(),
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
            });
        } catch (RuntimeException $e) {
            // Batalkan unggahan bukti bila pencairannya sendiri gagal diproses.
            Storage::disk('public')->delete($proofPath);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pencairan disetujui dan ditandai sudah ditransfer.');
    }

    public function reject(Request $request, PayoutRequest $payout)
    {
        if ($payout->status !== PayoutRequest::STATUS_PENDING) {
            return back()->with('error', 'Pengajuan pencairan ini sudah diproses.');
        }

        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        // Ditolak berarti pesanan kembali bisa diajukan — misal seller salah
        // menulis nomor rekening dan perlu mengajukan ulang.
        $payout->update([
            'status' => PayoutRequest::STATUS_REJECTED,
            'admin_note' => $validated['admin_note'] ?? null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Pengajuan pencairan ditolak. Seller dapat mengajukan ulang.');
    }
}
