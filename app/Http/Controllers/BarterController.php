<?php

namespace App\Http\Controllers;

use App\Models\BarterRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

        // Hitung selisih harga otomatis
        // Jika produk yang ditawarkan lebih murah, maka seller pengaju harus menambah uang
        $offeredPrice = (float) $offeredProduct->price;
        $requestedPrice = (float) $requestedProduct->price;
        $additionalCash = max(0, $requestedPrice - $offeredPrice);

        BarterRequest::create([
            'requester_store_id' => $store->id,
            'responder_store_id' => $requestedProduct->store_id,
            'offered_product_id' => $offeredProduct->id,
            'requested_product_id' => $requestedProduct->id,
            'additional_cash' => $additionalCash,
            'note' => $request->input('note'),
            'status' => BarterRequest::STATUS_PENDING,
        ]);

        if ($additionalCash > 0) {
            return back()->with('success', 'Pengajuan barter berhasil dikirim. Anda perlu membayar tambahan ' . number_format($additionalCash, 0, ',', '.') . ' jika barter disetujui.');
        }

        return back()->with('success', 'Pengajuan barter berhasil dikirim. Menunggu persetujuan seller pemilik produk.');
    }

    /**
     * Seller pemilik produk yang diminta menyetujui barter.
     * Jika ada additional_cash, maka kepemilikan produk baru ditukar setelah pembayaran selesai.
     * Jika tidak ada additional_cash, kepemilikan langsung ditukar.
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

                // Update status barter menjadi accepted
                $barter->status = BarterRequest::STATUS_ACCEPTED;
                $barter->responded_at = now();

                // Cek apakah ada additional_cash yang perlu dibayar
                if ($barter->requiresPayment()) {
                    // Ada pembayaran, set status payment ke pending
                    $barter->payment_status = BarterRequest::PAYMENT_PENDING;
                    $barter->payment_reference = 'BARTER-' . $barter->public_id . '-' . time();
                    $barter->save();

                    // Produk belum ditukar, menunggu pembayaran
                    // Batalkan pengajuan pending lain yang melibatkan kedua produk ini
                    $this->cancelOtherPendingRequests($barter);
                } else {
                    // Tidak ada pembayaran, langsung tukar produk
                    $barter->payment_status = BarterRequest::PAYMENT_NOT_REQUIRED;
                    $barter->save();

                    // Tukar kepemilikan produk
                    $this->exchangeProducts($offered, $requested, $barter);
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() ?: 'Gagal memproses barter.');
        }

        if ($barter->requiresPayment()) {
            return back()->with('success', 'Barter disetujui. Requester harus melakukan pembayaran sebesar Rp ' . number_format($barter->additional_cash, 0, ',', '.') . ' untuk menyelesaikan barter.');
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
     * Requester melakukan pembayaran untuk additional_cash setelah barter disetujui.
     * Generate Midtrans Snap Token dan redirect ke halaman pembayaran.
     */
    public function pay(BarterRequest $barter)
    {
        $store = Auth::user()->store;

        if (!$store || $barter->requester_store_id !== $store->id) {
            abort(403, 'Anda tidak memiliki akses untuk membayar barter ini.');
        }

        // Validasi: barter harus sudah accepted dan payment pending
        if ($barter->status !== BarterRequest::STATUS_ACCEPTED) {
            return back()->with('error', 'Barter belum disetujui.');
        }

        if ($barter->payment_status !== BarterRequest::PAYMENT_PENDING) {
            return back()->with('error', 'Pembayaran tidak diperlukan atau sudah selesai.');
        }

        // Jika snap token sudah ada, gunakan yang lama
        if ($barter->snap_token) {
            return Inertia::render('seller/barter/payment', [
                'barter' => $barter->load([
                    'offeredProduct',
                    'requestedProduct',
                    'requesterStore',
                    'responderStore',
                ]),
                'snapToken' => $barter->snap_token,
            ]);
        }

        // Generate snap token baru
        try {
            $midtransService = app(\App\Services\MidtransService::class);
            
            // Custom payload untuk barter payment
            $payload = [
                'transaction_details' => [
                    'order_id' => $barter->payment_reference,
                    'gross_amount' => (int) $barter->additional_cash,
                ],
                'customer_details' => [
                    'first_name' => Auth::user()->first_name,
                    'last_name' => Auth::user()->last_name,
                    'email' => Auth::user()->email,
                    'phone' => Auth::user()->phone,
                ],
                'item_details' => [
                    [
                        'id' => $barter->public_id,
                        'price' => (int) $barter->additional_cash,
                        'quantity' => 1,
                        'name' => 'Pembayaran Selisih Barter - ' . $barter->public_id,
                    ],
                ],
                'callbacks' => [
                    'finish' => route('seller.barter.index'),
                ],
            ];

            $serverKey = config('services.midtrans.server_key');
            if (empty($serverKey)) {
                throw new \RuntimeException('MIDTRANS_SERVER_KEY belum diatur di file .env.');
            }

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($serverKey . ':'),
            ])->post(config('services.midtrans.snap_url'), $payload)
                ->throw()
                ->json();

            $barter->update([
                'snap_token' => $response['token'],
            ]);

            return Inertia::render('seller/barter/payment', [
                'barter' => $barter->load([
                    'offeredProduct',
                    'requestedProduct',
                    'requesterStore',
                    'responderStore',
                ]),
                'snapToken' => $response['token'],
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal membuat pembayaran Midtrans: ' . $e->getMessage());
        }
    }

    /**
     * Check status payment dari Midtrans (untuk fallback jika webhook gagal)
     */
    public function checkPaymentStatus(BarterRequest $barter)
    {
        $store = Auth::user()->store;

        if (!$store || $barter->requester_store_id !== $store->id) {
            abort(403);
        }

        try {
            $midtransService = app(\App\Services\MidtransService::class);
            $status = $midtransService->getTransactionStatus($barter->payment_reference);

            $paymentStatus = $midtransService->mapPaymentStatus(
                $status['transaction_status'] ?? null,
                $status['fraud_status'] ?? null
            );

            // Jika payment berhasil tapi belum diproses (webhook lambat/gagal)
            if ($paymentStatus === 'paid' && $barter->payment_status !== BarterRequest::PAYMENT_PAID) {
                DB::transaction(function () use ($barter, $status) {
                    $barter->payment_status = BarterRequest::PAYMENT_PAID;
                    $barter->midtrans_transaction_id = $status['transaction_id'] ?? null;
                    $barter->paid_at = now();
                    $barter->save();

                    // Tukar kepemilikan produk
                    $offered = Product::where('id', $barter->offered_product_id)->lockForUpdate()->first();
                    $requested = Product::where('id', $barter->requested_product_id)->lockForUpdate()->first();

                    if ($offered && $requested) {
                        $this->exchangeProducts($offered, $requested, $barter);
                    }
                });
            }

            return response()->json([
                'payment_status' => $barter->fresh()->payment_status,
                'is_paid' => $barter->fresh()->payment_status === BarterRequest::PAYMENT_PAID,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
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

    /**
     * Helper: tukar kepemilikan produk setelah barter disetujui dan dibayar
     */
    private function exchangeProducts(Product $offered, Product $requested, BarterRequest $barter): void
    {
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

        // Batalkan pengajuan pending lain yang melibatkan salah satu produk
        $this->cancelOtherPendingRequests($barter);
    }

    /**
     * Helper: batalkan pengajuan barter pending lain yang melibatkan produk yang sama
     */
    private function cancelOtherPendingRequests(BarterRequest $barter): void
    {
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
    }
}
