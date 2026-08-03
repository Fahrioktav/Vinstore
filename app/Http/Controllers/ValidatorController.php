<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ValidatorController extends Controller
{
    /**
     * Dashboard validator barang antik.
     * Menampilkan produk yang menunggu validasi beserta riwayat validasi.
     */
    public function index()
    {
        $pendingProducts = Product::with('store')
            ->pendingValidator()
            ->latest()
            ->get();

        // Lelang yang menunggu validasi
        $pendingAuctions = Auction::with('store')
            ->where('approval_status', Auction::STATUS_PENDING_VALIDATOR)
            ->latest()
            ->get();

        // Riwayat produk yang sudah diproses validator (diteruskan ke admin / ditolak).
        $reviewedProducts = Product::with(['store', 'validator'])
            ->whereIn('approval_status', [
                Product::STATUS_PENDING_ADMIN,
                Product::STATUS_APPROVED,
                Product::STATUS_REJECTED,
            ])
            ->whereNotNull('validated_at')
            ->latest('validated_at')
            ->take(50)
            ->get();

        // Riwayat lelang yang sudah diproses validator
        $reviewedAuctions = Auction::with(['store', 'validator'])
            ->whereIn('approval_status', [
                Auction::STATUS_PENDING_ADMIN,
                Auction::STATUS_APPROVED,
                Auction::STATUS_REJECTED,
            ])
            ->whereNotNull('validated_at')
            ->latest('validated_at')
            ->take(50)
            ->get();

        $stats = [
            'pending' => Product::pendingValidator()->count() + Auction::where('approval_status', Auction::STATUS_PENDING_VALIDATOR)->count(),
            'forwarded' => Product::where('validated_by', Auth::id())
                ->whereIn('approval_status', [Product::STATUS_PENDING_ADMIN, Product::STATUS_APPROVED])
                ->count()
                + Auction::where('validated_by', Auth::id())
                    ->whereIn('approval_status', [Auction::STATUS_PENDING_ADMIN, Auction::STATUS_APPROVED])
                    ->count(),
            'rejected' => Product::where('validated_by', Auth::id())
                ->where('approval_status', Product::STATUS_REJECTED)
                ->count()
                + Auction::where('validated_by', Auth::id())
                    ->where('approval_status', Auction::STATUS_REJECTED)
                    ->count(),
        ];

        return Inertia::render('validator/dashboard', compact(
            'pendingProducts',
            'pendingAuctions',
            'reviewedProducts',
            'reviewedAuctions',
            'stats'
        ));
    }

    /**
     * Detail produk yang diajukan seller untuk ditinjau validator.
     */
    public function show($id)
    {
        $product = Product::with(['store.user', 'validator'])
            ->where('public_id', $id)
            ->firstOrFail();

        return Inertia::render('validator/product-detail', compact('product'));
    }

    /**
     * Validator menyetujui produk -> diteruskan ke admin untuk persetujuan akhir.
     */
    public function approve($id)
    {
        $product = Product::where('public_id', $id)->firstOrFail();

        if ($product->approval_status !== Product::STATUS_PENDING_VALIDATOR) {
            return redirect()->back()->with('error', 'Produk ini tidak sedang menunggu validasi.');
        }

        $product->update([
            'approval_status' => Product::STATUS_PENDING_ADMIN,
            'validated_at' => now(),
            'validated_by' => Auth::id(),
            'rejection_reason' => null,
        ]);

        return redirect()->back()->with('success', 'Produk tervalidasi dan diteruskan ke admin untuk persetujuan akhir.');
    }

    /**
     * Validator menolak produk.
     */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $product = Product::where('public_id', $id)->firstOrFail();

        if ($product->approval_status !== Product::STATUS_PENDING_VALIDATOR) {
            return redirect()->back()->with('error', 'Produk ini tidak sedang menunggu validasi.');
        }

        $product->update([
            'approval_status' => Product::STATUS_REJECTED,
            'validated_at' => now(),
            'validated_by' => Auth::id(),
            'approved_at' => null,
            'approved_by' => null,
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Produk berhasil ditolak.');
    }

    /**
     * Validator menyetujui lelang -> diteruskan ke admin untuk persetujuan akhir.
     */
    public function approveAuction($id)
    {
        $auction = Auction::where('public_id', $id)->firstOrFail();

        if ($auction->approval_status !== Auction::STATUS_PENDING_VALIDATOR) {
            return redirect()->back()->with('error', 'Lelang ini tidak sedang menunggu validasi.');
        }

        $auction->update([
            'approval_status' => Auction::STATUS_PENDING_ADMIN,
            'validated_at' => now(),
            'validated_by' => Auth::id(),
            'rejection_reason' => null,
        ]);

        return redirect()->back()->with('success', 'Lelang tervalidasi dan diteruskan ke admin untuk persetujuan akhir.');
    }

    /**
     * Validator menolak lelang.
     */
    public function rejectAuction(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $auction = Auction::where('public_id', $id)->firstOrFail();

        if ($auction->approval_status !== Auction::STATUS_PENDING_VALIDATOR) {
            return redirect()->back()->with('error', 'Lelang ini tidak sedang menunggu validasi.');
        }

        $auction->update([
            'approval_status' => Auction::STATUS_REJECTED,
            'validated_at' => now(),
            'validated_by' => Auth::id(),
            'approved_at' => null,
            'approved_by' => null,
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Lelang berhasil ditolak.');
    }
}
