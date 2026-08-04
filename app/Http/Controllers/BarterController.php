<?php

namespace App\Http\Controllers;

use App\Models\BarterRequest;
use App\Models\PayoutRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        if (! $store) {
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

        // Pengajuan masuk (seller lain ingin barter dengan produk saya).
        // payoutRequests & refundRequests ikut dimuat supaya perhitungan
        // kelayakan pencairan tidak memicu query per kartu.
        $incomingRequests = $store->barterRequestsReceived()
            ->with([
                'requesterStore:id,public_id,store_name',
                'offeredProduct',
                'requestedProduct',
                'payoutRequests',
                'refundRequests',
            ])
            ->latest()
            ->get();

        // Pengajuan keluar (saya mengajukan barter ke seller lain)
        $outgoingRequests = $store->barterRequestsSent()
            ->with([
                'responderStore:id,public_id,store_name',
                'offeredProduct',
                'requestedProduct',
                'payoutRequests',
                'refundRequests',
            ])
            ->latest()
            ->get();

        // Sejak selisih harga mengalir dua arah, peran pembayar/penerima tidak
        // lagi bisa disimpulkan dari daftar mana kartunya muncul: penerima pun
        // bisa menjadi pembayar. Karena itu kedua daftar mendapat atribut yang
        // sama, dan masing-masing atribut sudah menilai toko yang sedang login.
        $barterAttributes = [
            'is_payer',
            'can_pay',
            'payer_label',
            'can_request_payout',
            'payout_block_reason',
            'can_request_refund',
            'latest_payout',
            'latest_refund',
            'can_report_stalled',
            'report_block_reason',
            'shipping_deadline_at',
        ];

        $incomingRequests->each->append($barterAttributes);
        $outgoingRequests->each->append($barterAttributes);

        $lastPayout = PayoutRequest::where('store_id', $store->id)->latest('id')->first();

        $bankPrefill = [
            'bank_name' => $lastPayout->bank_name ?? '',
            'account_number' => $lastPayout->account_number ?? '',
            'account_holder' => $lastPayout->account_holder ?? Auth::user()->first_name.' '.Auth::user()->last_name,
        ];

        return Inertia::render('seller/barter/index', compact(
            'availableProducts',
            'myProducts',
            'incomingRequests',
            'outgoingRequests',
            'bankPrefill'
        ));
    }

    /**
     * Seller mengajukan barter terhadap satu produk milik seller lain.
     */
    public function store(Request $request, Product $product)
    {
        $store = Auth::user()->store;

        if (! $store) {
            return back()->with('error', 'Anda harus mendaftarkan toko terlebih dahulu.');
        }

        $request->validate([
            'offered_product_id' => ['required', Rule::exists('products', 'public_id')],
            'note' => 'nullable|string|max:1000',
        ]);

        $requestedProduct = $product; // produk milik seller lain

        // Produk yang diminta harus bisa dibarter dan bukan milik sendiri
        if (! $this->isBarterable($requestedProduct)) {
            return back()->with('error', 'Produk ini tidak tersedia untuk barter.');
        }

        if ($requestedProduct->store_id === $store->id) {
            return back()->with('error', 'Anda tidak dapat membarter produk milik toko sendiri.');
        }

        // Produk yang ditawarkan harus milik seller sendiri dan bisa dibarter
        $offeredProduct = Product::where('public_id', $request->offered_product_id)->first();

        if (! $offeredProduct || $offeredProduct->store_id !== $store->id) {
            return back()->with('error', 'Produk yang ditawarkan tidak valid.');
        }

        if (! $this->isBarterable($offeredProduct)) {
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

        // Hitung selisih harga otomatis. Selisihnya berlaku DUA ARAH: pihak yang
        // produknya lebih murah wajib menambah uang. Sebelumnya hanya arah
        // "pengaju menambah" yang dihitung, sehingga barter dengan produk
        // pengaju yang lebih mahal berjalan tanpa ada yang membayar selisihnya.
        $offeredPrice = (float) $offeredProduct->price;
        $requestedPrice = (float) $requestedProduct->price;
        $priceDifference = $requestedPrice - $offeredPrice;
        $additionalCash = abs($priceDifference);

        $payerRole = match (true) {
            $priceDifference > 0 => BarterRequest::PAYER_REQUESTER,
            $priceDifference < 0 => BarterRequest::PAYER_RESPONDER,
            default => null,
        };

        BarterRequest::create([
            'requester_store_id' => $store->id,
            'responder_store_id' => $requestedProduct->store_id,
            'offered_product_id' => $offeredProduct->id,
            'requested_product_id' => $requestedProduct->id,
            'additional_cash' => $additionalCash,
            'payer_role' => $payerRole,
            'note' => $request->input('note'),
            'status' => BarterRequest::STATUS_PENDING,
        ]);

        $formattedCash = number_format($additionalCash, 0, ',', '.');

        if ($payerRole === BarterRequest::PAYER_REQUESTER) {
            return back()->with('success', 'Pengajuan barter berhasil dikirim. Anda perlu membayar tambahan Rp '.$formattedCash.' jika barter disetujui.');
        }

        if ($payerRole === BarterRequest::PAYER_RESPONDER) {
            return back()->with('success', 'Pengajuan barter berhasil dikirim. Karena produk Anda lebih mahal, seller pemilik produk perlu membayar tambahan Rp '.$formattedCash.' jika ia menyetujui.');
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

        if (! $store || $barter->responder_store_id !== $store->id) {
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

                if (! $offered || ! $requested) {
                    throw new \RuntimeException('Produk tidak ditemukan.');
                }

                // Pastikan kedua produk masih layak dibarter
                if (! $this->isBarterable($offered) || ! $this->isBarterable($requested)) {
                    throw new \RuntimeException('Salah satu produk sudah tidak tersedia untuk barter.');
                }

                // Update status barter menjadi accepted
                $barter->status = BarterRequest::STATUS_ACCEPTED;
                $barter->responded_at = now();

                // Cek apakah ada additional_cash yang perlu dibayar
                if ($barter->requiresPayment()) {
                    // Ada pembayaran, set status payment ke pending
                    $barter->payment_status = BarterRequest::PAYMENT_PENDING;
                    $barter->payment_reference = 'BARTER-'.$barter->public_id.'-'.time();
                    $barter->save();
                } else {
                    $barter->payment_status = BarterRequest::PAYMENT_NOT_REQUIRED;
                    $barter->save();
                }

                // Kunci kedua produk sejak barter disetujui — baik yang menunggu
                // pembayaran maupun yang langsung masuk tahap pengiriman.
                // Tanpa ini produk masih bisa dibeli pembeli biasa sementara
                // barternya berjalan (temuan T-08).
                $this->lockProductsForBarter($offered, $requested, $barter);

                // Batalkan pengajuan pending lain yang melibatkan kedua produk ini
                $this->cancelOtherPendingRequests($barter);

                // Tanpa pembayaran, barter langsung masuk tahap saling kirim.
                if (! $barter->requiresPayment()) {
                    $this->startShipping($barter);
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() ?: 'Gagal memproses barter.');
        }

        if ($barter->requiresPayment()) {
            $selisih = 'Rp '.number_format($barter->additional_cash, 0, ',', '.');

            $pesan = $barter->payerRole() === BarterRequest::PAYER_RESPONDER
                ? 'Barter disetujui. Karena produk Anda lebih murah, Anda harus membayar selisih '.$selisih.' sebelum barang dikirim.'
                : 'Barter disetujui. Pengaju harus membayar selisih '.$selisih.' sebelum barang dikirim.';

            return back()->with('success', $pesan.' Kedua produk sementara dikunci dari penjualan.');
        }

        return back()->with('success', 'Barter disetujui. Silakan saling mengirim barang dan isi nomor resinya.');
    }

    /**
     * Seller pemilik produk yang diminta menolak barter.
     */
    public function reject(BarterRequest $barter)
    {
        $store = Auth::user()->store;

        if (! $store || $barter->responder_store_id !== $store->id) {
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

        if (! $store || $barter->requester_store_id !== $store->id) {
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
     * Pembayar selisih melakukan pembayaran additional_cash setelah barter
     * disetujui. Pembayarnya adalah pihak yang produknya lebih murah — bisa
     * pengaju, bisa juga penerima.
     * Generate Midtrans Snap Token dan redirect ke halaman pembayaran.
     */
    public function pay(BarterRequest $barter)
    {
        $store = Auth::user()->store;

        if (! $barter->isPayer($store)) {
            abort(403, 'Anda tidak memiliki akses untuk membayar barter ini.');
        }

        // Transaksi Midtrans sudah pernah dibuat sebelumnya: pastikan dulu ia
        // belum lunas. Tanpa ini, pembayaran yang webhook-nya tidak sampai akan
        // membuka Snap lagi untuk order_id yang sudah dibayar — dan Midtrans
        // menolaknya, sehingga pembayarannya terlihat macet selamanya.
        if ($barter->snap_token && $barter->payment_status === BarterRequest::PAYMENT_PENDING) {
            try {
                $this->syncPaymentFromMidtrans($barter);
                $barter->refresh();
            } catch (\Throwable $e) {
                // Midtrans tidak dapat dihubungi bukan alasan untuk memblokir
                // pembayaran; lanjutkan saja ke Snap seperti biasa.
                Log::warning('Gagal menyelaraskan status pembayaran barter', [
                    'barter_id' => $barter->public_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($barter->payment_status === BarterRequest::PAYMENT_PAID) {
            return redirect()->route('seller.barter.index')
                ->with('success', 'Pembayaran selisih sudah lunas. Silakan kirim barang Anda dan isi nomor resinya.');
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
                'viewerRole' => $barter->roleOfStore($store),
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
                        'name' => 'Pembayaran Selisih Barter - '.$barter->public_id,
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
                'Authorization' => 'Basic '.base64_encode($serverKey.':'),
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
                'viewerRole' => $barter->roleOfStore($store),
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal membuat pembayaran Midtrans: '.$e->getMessage());
        }
    }

    /**
     * Check status payment dari Midtrans (untuk fallback jika webhook gagal).
     *
     * Yang berhak memanggil adalah PEMBAYAR selisih — bisa pengaju maupun
     * penerima. Sebelumnya dikunci ke pengaju, sehingga ketika penerima yang
     * membayar, polling di halaman pembayaran selalu 403 dan pembayarannya
     * tidak pernah tercatat lunas.
     */
    public function checkPaymentStatus(BarterRequest $barter)
    {
        if (! $barter->isPayer(Auth::user()->store)) {
            abort(403);
        }

        try {
            $this->syncPaymentFromMidtrans($barter);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        $fresh = $barter->fresh();

        return response()->json([
            'payment_status' => $fresh->payment_status,
            'is_paid' => $fresh->payment_status === BarterRequest::PAYMENT_PAID,
        ]);
    }

    /**
     * Tanyakan status transaksi ke Midtrans dan tandai lunas bila memang sudah.
     *
     * Dipakai sebagai jaring pengaman ketika webhook tidak sampai — kondisi
     * yang normal terjadi di localhost. Idempoten dan aman dipanggil berulang:
     * baris barter dikunci, dan barter yang sudah lunas langsung dilewati.
     */
    private function syncPaymentFromMidtrans(BarterRequest $barter): void
    {
        if ($barter->payment_status === BarterRequest::PAYMENT_PAID) {
            return;
        }

        if (empty($barter->payment_reference)) {
            return;
        }

        $midtransService = app(\App\Services\MidtransService::class);
        $status = $midtransService->getTransactionStatus($barter->payment_reference);

        $paymentStatus = $midtransService->mapPaymentStatus(
            $status['transaction_status'] ?? null,
            $status['fraud_status'] ?? null
        );

        if ($paymentStatus !== 'paid') {
            return;
        }

        DB::transaction(function () use ($barter, $status) {
            $locked = BarterRequest::whereKey($barter->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->payment_status === BarterRequest::PAYMENT_PAID) {
                return;
            }

            $locked->forceFill([
                'payment_status' => BarterRequest::PAYMENT_PAID,
                'midtrans_transaction_id' => $status['transaction_id'] ?? null,
                'paid_at' => now(),
            ])->save();

            // Pembayaran lunas TIDAK langsung memindahkan kepemilikan.
            // Barter masuk tahap saling kirim barang lebih dulu.
            $this->startShipping($locked);
        });
    }

    /**
     * Salah satu seller mengisi nomor resi pengiriman barangnya.
     *
     * Requester mengirim produk yang ia tawarkan; responder mengirim produk
     * miliknya yang diminta. Keduanya wajib mengirim.
     */
    public function ship(Request $request, BarterRequest $barter)
    {
        $validated = $request->validate([
            'tracking_number' => 'required|string|max:100',
        ], [
            'tracking_number.required' => 'Nomor resi wajib diisi.',
        ]);

        $role = $barter->roleOfStore(Auth::user()->store);

        if (! $role) {
            abort(403, 'Anda bukan pihak dalam barter ini.');
        }

        if (! $barter->isShipping()) {
            return back()->with('error', 'Barter ini belum masuk tahap pengiriman.');
        }

        $prefix = $role; // 'requester' atau 'responder'

        if ($barter->{$prefix.'_shipped_at'} !== null) {
            return back()->with('error', 'Anda sudah mengisi nomor resi untuk barter ini.');
        }

        $barter->update([
            $prefix.'_tracking_number' => $validated['tracking_number'],
            $prefix.'_shipped_at' => now(),
        ]);

        return back()->with('success', 'Nomor resi tersimpan. Menunggu pihak lain mengonfirmasi penerimaan.');
    }

    /**
     * Salah satu seller mengonfirmasi barang dari pihak lain sudah diterima.
     * Kepemilikan produk baru berpindah ketika KEDUANYA sudah mengonfirmasi.
     */
    public function confirmReceipt(BarterRequest $barter)
    {
        $role = $barter->roleOfStore(Auth::user()->store);

        if (! $role) {
            abort(403, 'Anda bukan pihak dalam barter ini.');
        }

        if (! $barter->isShipping()) {
            return back()->with('error', 'Barter ini belum masuk tahap pengiriman.');
        }

        // Pihak lawan adalah yang mengirimkan barang kepada saya.
        $counterpart = $role === 'requester' ? 'responder' : 'requester';

        if ($barter->{$counterpart.'_shipped_at'} === null) {
            return back()->with('error', 'Pihak lain belum mengirimkan barangnya.');
        }

        // Kolom *_received_at mencatat "saya sudah menerima barang".
        if ($barter->{$role.'_received_at'} !== null) {
            return back()->with('error', 'Anda sudah mengonfirmasi penerimaan barang.');
        }

        try {
            DB::transaction(function () use ($barter, $role) {
                $locked = BarterRequest::whereKey($barter->getKey())->lockForUpdate()->firstOrFail();

                if ($locked->{$role.'_received_at'} !== null) {
                    return;
                }

                $locked->forceFill([
                    $role.'_received_at' => now(),
                ])->save();

                if (! $locked->bothPartiesReceived()) {
                    return;
                }

                // Kedua belah pihak sudah menerima: pindahkan kepemilikan.
                $offered = Product::whereKey($locked->offered_product_id)->lockForUpdate()->first();
                $requested = Product::whereKey($locked->requested_product_id)->lockForUpdate()->first();

                if ($offered && $requested) {
                    $this->exchangeProducts($offered, $requested, $locked);
                }

                $locked->forceFill([
                    'status' => BarterRequest::STATUS_COMPLETED,
                    'completed_at' => now(),
                ])->save();
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal memproses konfirmasi penerimaan.');
        }

        $barter->refresh();

        if ($barter->isCompleted()) {
            return back()->with('success', 'Barter selesai. Kepemilikan kedua produk sudah berpindah sepenuhnya.');
        }

        return back()->with('success', 'Penerimaan dikonfirmasi. Menunggu pihak lain mengonfirmasi juga.');
    }

    /**
     * Helper: produk layak barter (approved, ditandai barterable, stok > 0,
     * dan tidak sedang terikat barter lain).
     */
    private function isBarterable(Product $product): bool
    {
        return $product->approval_status === Product::STATUS_APPROVED
            && $product->is_barterable
            && $product->stock > 0
            && ! $product->isLockedForBarter();
    }

    /**
     * Kunci kedua produk agar tidak bisa dibeli pembeli biasa atau ditawarkan
     * pada barter lain selama barter ini belum tuntas.
     */
    private function lockProductsForBarter(Product $offered, Product $requested, BarterRequest $barter): void
    {
        foreach ([$offered, $requested] as $product) {
            $product->locked_for_barter_id = $barter->id;
            $product->save();
        }
    }

    /**
     * Buka kunci kedua produk (barter batal atau sudah tuntas).
     */
    private function unlockProductsForBarter(BarterRequest $barter): void
    {
        Product::where('locked_for_barter_id', $barter->id)
            ->update(['locked_for_barter_id' => null]);
    }

    /**
     * Masuk tahap saling kirim barang. Dipanggil setelah barter disetujui
     * (tanpa selisih uang) atau setelah pembayaran selisih lunas.
     */
    private function startShipping(BarterRequest $barter): void
    {
        if ($barter->status === BarterRequest::STATUS_SHIPPING || $barter->isCompleted()) {
            return;
        }

        // Titik awal tenggat pengiriman: dari sini kedua seller punya
        // BarterRequest::SHIPPING_DEADLINE_DAYS hari untuk mengisi resi.
        $barter->forceFill([
            'status' => BarterRequest::STATUS_SHIPPING,
            'shipping_started_at' => now(),
        ])->save();
    }

    /**
     * Pindahkan kepemilikan produk. Dipanggil HANYA setelah kedua belah pihak
     * mengonfirmasi barang diterima — bukan saat pembayaran lunas.
     *
     * Sebelumnya kepemilikan berpindah begitu pembayaran settle, padahal
     * barangnya sendiri belum tentu pernah dikirim (temuan T-08).
     */
    private function exchangeProducts(Product $offered, Product $requested, BarterRequest $barter): void
    {
        $requesterStoreId = $barter->requester_store_id;
        $responderStoreId = $barter->responder_store_id;

        // Tukar kepemilikan produk
        $offered->store_id = $responderStoreId;
        $requested->store_id = $requesterStoreId;

        // Setelah tertukar, matikan flag barter (pemilik baru bisa mengaktifkan lagi)
        // dan buka kuncinya karena barter sudah tuntas.
        $offered->is_barterable = false;
        $requested->is_barterable = false;
        $offered->locked_for_barter_id = null;
        $requested->locked_for_barter_id = null;

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
