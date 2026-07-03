<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        if (!$store) {
            return redirect()->route('store.register')->with('error', 'Anda harus memiliki toko terlebih dahulu.');
        }

        $pendingAmount = WithdrawalRequest::where('store_id', $store->id)
            ->where('status', 'pending')
            ->sum('amount');

        $withdrawable = (float) $store->available_balance - (float) $pendingAmount;

        if ((float) $validated['amount'] > $withdrawable) {
            return back()->with('error', 'Saldo tersedia tidak mencukupi untuk pencairan ini.');
        }

        WithdrawalRequest::create([
            'store_id' => $store->id,
            'amount' => $validated['amount'],
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'account_holder' => $validated['account_holder'],
            'status' => 'pending',
        ]);

        return back()->with('success', 'Pengajuan pencairan berhasil dikirim dan menunggu persetujuan admin.');
    }

    public function adminIndex()
    {
        $withdrawals = WithdrawalRequest::with(['store.user', 'reviewer'])->latest()->get();

        return Inertia::render('admin/withdrawals/index', compact('withdrawals'));
    }

    public function approve(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->status !== 'pending') {
            return back()->with('error', 'Pengajuan pencairan ini sudah diproses.');
        }

        try {
            DB::transaction(function () use ($request, $withdrawal) {
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
                    'admin_note' => $request->input('admin_note'),
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan pencairan berhasil disetujui.');
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
