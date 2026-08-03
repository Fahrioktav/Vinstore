<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class WithdrawalRequestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:10000',
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:100',
            'account_holder' => 'required|string|max:150',
        ]);

        $store = Auth::user()->store;

        if (! $store) {
            return redirect()->route('store.register')->with('error', 'Anda harus memiliki toko terlebih dahulu.');
        }

        try {
            // Pembacaan saldo dan pembuatan pengajuan harus berada dalam satu
            // transaksi ber-lock. Tanpa itu dua pengajuan bersamaan sama-sama
            // lolos pengecekan dan totalnya bisa melebihi saldo yang tersedia.
            DB::transaction(function () use ($store, $validated) {
                $lockedStore = Store::whereKey($store->id)->lockForUpdate()->firstOrFail();

                $pendingAmount = WithdrawalRequest::where('store_id', $lockedStore->id)
                    ->where('status', 'pending')
                    ->sum('amount');

                $withdrawable = (float) $lockedStore->available_balance - (float) $pendingAmount;

                if ((float) $validated['amount'] > $withdrawable) {
                    throw new \RuntimeException(
                        'Saldo tersedia tidak mencukupi untuk pencairan ini. Dapat dicairkan saat ini: Rp '
                        .number_format(max($withdrawable, 0), 0, ',', '.')
                    );
                }

                WithdrawalRequest::create([
                    'store_id' => $lockedStore->id,
                    'amount' => $validated['amount'],
                    'bank_name' => $validated['bank_name'],
                    'account_number' => $validated['account_number'],
                    'account_holder' => $validated['account_holder'],
                    'status' => 'pending',
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan pencairan berhasil dikirim dan menunggu verifikasi admin.');
    }

    public function adminIndex()
    {
        $withdrawals = WithdrawalRequest::with(['store.user', 'reviewer'])->latest()->get();

        return Inertia::render('admin/withdrawals/index', compact('withdrawals'));
    }

    /**
     * Admin menyetujui pencairan setelah benar-benar mentransfer dana ke
     * rekening yang diajukan seller. Bukti transfer wajib diunggah agar ada
     * jejak audit atas uang yang keluar.
     */
    public function approve(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->status !== 'pending') {
            return back()->with('error', 'Pengajuan pencairan ini sudah diproses.');
        }

        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:1000',
            'transfer_proof' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'transfer_proof.required' => 'Bukti transfer wajib diunggah sebelum pencairan disetujui.',
        ]);

        $proofPath = $request->file('transfer_proof')->store('withdrawals', 'public');

        try {
            DB::transaction(function () use ($validated, $withdrawal, $proofPath) {
                $withdrawal = WithdrawalRequest::whereKey($withdrawal->getKey())->lockForUpdate()->firstOrFail();
                $store = Store::whereKey($withdrawal->store_id)->lockForUpdate()->firstOrFail();

                if ($withdrawal->status !== 'pending') {
                    throw new \RuntimeException('Pengajuan pencairan ini sudah diproses.');
                }

                if ((float) $store->available_balance < (float) $withdrawal->amount) {
                    throw new \RuntimeException('Saldo toko tidak mencukupi.');
                }

                $store->decrement('available_balance', $withdrawal->amount);
                $store->increment('withdrawn_balance', $withdrawal->amount);

                $withdrawal->update([
                    'status' => 'approved',
                    'admin_note' => $validated['admin_note'] ?? null,
                    'transfer_proof' => $proofPath,
                    'transferred_at' => now(),
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            // Batalkan unggahan bukti bila pencairannya sendiri gagal diproses.
            Storage::disk('public')->delete($proofPath);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pencairan disetujui dan ditandai sudah ditransfer.');
    }

    public function reject(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->status !== 'pending') {
            return back()->with('error', 'Pengajuan pencairan ini sudah diproses.');
        }

        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $withdrawal->update([
            'status' => 'rejected',
            'admin_note' => $validated['admin_note'] ?? null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Pengajuan pencairan berhasil ditolak.');
    }
}
