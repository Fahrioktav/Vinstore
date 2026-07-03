<?php

namespace App\Http\Controllers;

use App\Models\BarterRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BarterController extends Controller
{
    /**
     * Halaman utama barter untuk seller:
     * - Produk milik seller lain yang tersedia untuk dibarter
     * - Produk milik seller sendiri yang bisa ditawarkan
     * - Daftar pengajuan masuk & keluar
     */
    public function index()
    {
        $store = Auth::user()->store;

        if (!$store) {
            return redirect()->route('store.register')
                ->with('error', 'Anda harus mendaftarkan toko terlebih dahulu.');
        }

        // Produk seller lain yang available untuk barter
        $availableProducts = Product::barterable()
            ->where('store_id', '!=', $store->id)
            ->with('store:id,public_id,store_name')
            ->latest()
            ->get();

        // Produk milik seller sendiri yang bisa ditawarkan sebagai alat tukar
        $myProducts = Product::barterable()
            ->where('store_id', $store->id)
            ->get();

        // Pengajuan masuk (seller lain ingin barter dengan produk saya)
        $incomingRequests = $store->barterRequestsReceived()
            ->with([
                'requesterStore:id,public_id,store_name',
                'offeredProduct',
                'requestedProduct',
            ])
            ->latest()
            ->get();

        // Pengajuan keluar (saya mengajukan barter ke seller lain)
        $outgoingRequests = $store->barterRequestsSent()
            ->with([
                'responderStore:id,public_id,store_name',
                'offeredProduct',
                'requestedProduct',
            ])
            ->latest()
            ->get();

        return Inertia::render('seller/barter/index', compact(
            'availableProducts',
            'myProducts',
            'incomingRequests',
            'outgoingRequests'
        ));
    }

    /**
     * Seller mengajukan barter terhadap satu produk milik seller lain.
     */
    public function store(Request $request, Product $product)
    {
        $store = Auth::user()->store;

        if (!$store) {
            return back()->with('error', 'Anda harus mendaftarkan toko terlebih dahulu.');
        }

        $request->validate([
            'offered_product_id' => ['required', Rule::exists('products', 'public_id')],
            'additional_cash' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:1000',
        ]);

        $requestedProduct = $product; // produk milik seller lain

        // Produk yang diminta harus bisa dibarter dan bukan milik sendiri
        if (!$this->isBarterable($requestedProduct)) {
            return back()->with('error', 'Produk ini tidak tersedia untuk barter.');
        }

        if ($requestedProduct->store_id === $store->id) {
            return back()->with('error', 'Anda tidak dapat membarter produk milik toko sendiri.');
        }

        // Produk yang ditawarkan harus milik seller sendiri dan bisa dibarter
        $offeredProduct = Product::where('public_id', $request->offered_product_id)->first();

        if (!$offeredProduct || $offeredProduct->store_id !== $store->id) {
            return back()->with('error', 'Produk yang ditawarkan tidak valid.');
        }

        if (!$this->isBarterable($offeredProduct)) {
            return back()->with('error', 'Produk yang Anda tawarkan tidak tersedia untuk barter.');
        }

        // Cegah pengajuan duplikat yang masih pending untuk pasangan produk yang sama
        $alreadyPending = BarterRequest::pending()
            ->where('requester_store_id', $store->id)
            ->where('offered_product_id', $offeredProduct->id)
            ->where('requested_product_id', $requestedProduct->id)
            ->exists();

        if ($alreadyPending) {
            return back()->with('error', 'Anda sudah mengajukan barter untuk produk ini dan masih menunggu persetujuan.');
        }

        BarterRequest::create([
            'requester_store_id' => $store->id,
            'responder_store_id' => $requestedProduct->store_id,
            'offered_product_id' => $offeredProduct->id,
            'requested_product_id' => $requestedProduct->id,
            'additional_cash' => $request->input('additional_cash', 0) ?: 0,
            'note' => $request->input('note'),
            'status' => BarterRequest::STATUS_PENDING,
        ]);

        return back()->with('success', 'Pengajuan barter berhasil dikirim. Menunggu persetujuan seller pemilik produk.');
    }

    /**
     * Seller pemilik produk yang diminta menyetujui barter.
     * Saat disetujui, kepemilikan kedua produk ditukar dalam satu transaksi.
     */
    public function accept(BarterRequest $barter)
    {
        $store = Auth::user()->store;

        if (!$store || $barter->responder_store_id !== $store->id) {
            abort(403, 'Anda tidak memiliki akses untuk menyetujui barter ini.');
        }

        if ($barter->status !== BarterRequest::STATUS_PENDING) {
            return back()->with('error', 'Pengajuan barter ini sudah tidak dapat diproses.');
        }

        try {
            DB::transaction(function () use ($barter) {
                // Kunci baris produk agar aman dari race condition
                $offered = Product::where('id', $barter->offered_product_id)->lockForUpdate()->first();
                $requested = Product::where('id', $barter->requested_product_id)->lockForUpdate()->first();

                if (!$offered || !$requested) {
                    throw new \RuntimeException('Produk tidak ditemukan.');
                }

                // Pastikan kedua produk masih layak dibarter
                if (!$this->isBarterable($offered) || !$this->isBarterable($requested)) {
                    throw new \RuntimeException('Salah satu produk sudah tidak tersedia untuk barter.');
                }

                $requesterStoreId = $barter->requester_store_id;
                $responderStoreId = $barter->responder_store_id;

                // Tukar kepemilikan produk
                $offered->store_id = $responderStoreId;
                $requested->store_id = $requesterStoreId;

                // Setelah tertukar, matikan flag barter (pemilik baru bisa mengaktifkan lagi)
                $offered->is_barterable = false;
                $requested->is_barterable = false;

                $offered->save();
                $requested->save();

                // Tandai pengajuan ini diterima
                $barter->status = BarterRequest::STATUS_ACCEPTED;
                $barter->responded_at = now();
                $barter->save();

                // Batalkan pengajuan pending lain yang melibatkan salah satu produk
                BarterRequest::pending()
                    ->where('id', '!=', $barter->id)
                    ->where(function ($query) use ($barter) {
                        $query->whereIn('offered_product_id', [$barter->offered_product_id, $barter->requested_product_id])
                            ->orWhereIn('requested_product_id', [$barter->offered_product_id, $barter->requested_product_id]);
                    })
                    ->update([
                        'status' => BarterRequest::STATUS_CANCELLED,
                        'responded_at' => now(),
                    ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() ?: 'Gagal memproses barter.');
        }

        return back()->with('success', 'Barter disetujui. Kepemilikan produk telah ditukar.');
    }

    /**
     * Seller pemilik produk yang diminta menolak barter.
     */
    public function reject(BarterRequest $barter)
    {
        $store = Auth::user()->store;

        if (!$store || $barter->responder_store_id !== $store->id) {
            abort(403, 'Anda tidak memiliki akses untuk menolak barter ini.');
        }

        if ($barter->status !== BarterRequest::STATUS_PENDING) {
            return back()->with('error', 'Pengajuan barter ini sudah tidak dapat diproses.');
        }

        $barter->update([
            'status' => BarterRequest::STATUS_REJECTED,
            'responded_at' => now(),
        ]);

        return back()->with('success', 'Pengajuan barter ditolak.');
    }

    /**
     * Seller pengaju membatalkan pengajuannya sendiri.
     */
    public function cancel(BarterRequest $barter)
    {
        $store = Auth::user()->store;

        if (!$store || $barter->requester_store_id !== $store->id) {
            abort(403, 'Anda tidak memiliki akses untuk membatalkan barter ini.');
        }

        if ($barter->status !== BarterRequest::STATUS_PENDING) {
            return back()->with('error', 'Pengajuan barter ini sudah tidak dapat dibatalkan.');
        }

        $barter->update([
            'status' => BarterRequest::STATUS_CANCELLED,
            'responded_at' => now(),
        ]);

        return back()->with('success', 'Pengajuan barter dibatalkan.');
    }

    /**
     * Helper: produk layak barter (approved, ditandai barterable, stok > 0).
     */
    private function isBarterable(Product $product): bool
    {
        return $product->approval_status === Product::STATUS_APPROVED
            && $product->is_barterable
            && $product->stock > 0;
    }
}
