<?php

namespace App\Http\Controllers;

use App\Models\PayoutRequest;
use App\Models\Product;
use App\Models\TradeInRequest;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TradeInController extends Controller
{
    /**
     * Halaman utama tukar tambah untuk seller:
     * - Produk milik seller lain yang tersedia untuk ditukar tambah
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

        // Produk seller lain yang available untuk tukar tambah
        $availableProducts = Product::tradeInEnabled()
            ->where('store_id', '!=', $store->id)
            ->with('store:id,public_id,store_name')
            ->latest()
            ->get();

        // Produk milik seller sendiri yang bisa ditawarkan sebagai alat tukar
        $myProducts = Product::tradeInEnabled()
            ->where('store_id', $store->id)
            ->get();

        // Pengajuan masuk (seller lain ingin tukar tambah dengan produk saya).
        // payoutRequests & refundRequests ikut dimuat supaya perhitungan
        // kelayakan pencairan tidak memicu query per kartu.
        $incomingRequests = $store->tradeInRequestsReceived()
            ->with([
                'requesterStore:id,public_id,store_name',
                'offeredProduct',
                'requestedProduct',
                'payoutRequests',
                'refundRequests',
            ])
            ->latest()
            ->get();

        // Pengajuan keluar (saya mengajukan tukar tambah ke seller lain)
        $outgoingRequests = $store->tradeInRequestsSent()
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
        $tradeInAttributes = [
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

        $incomingRequests->each->append($tradeInAttributes);
        $outgoingRequests->each->append($tradeInAttributes);

        $lastPayout = PayoutRequest::where('store_id', $store->id)->latest('id')->first();

        $bankPrefill = [
            'bank_name' => $lastPayout->bank_name ?? '',
            'account_number' => $lastPayout->account_number ?? '',
            'account_holder' => $lastPayout->account_holder ?? Auth::user()->first_name.' '.Auth::user()->last_name,
        ];

        return Inertia::render('seller/tukar-tambah/index', compact(
            'availableProducts',
            'myProducts',
            'incomingRequests',
            'outgoingRequests',
            'bankPrefill'
        ));
    }

    /**
     * Seller mengajukan tukar tambah terhadap satu produk milik seller lain.
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

        // Produk yang diminta harus bisa ditukar tambah dan bukan milik sendiri
        if (! $this->isTradeInEnabled($requestedProduct)) {
            return back()->with('error', 'Produk ini tidak tersedia untuk tukar tambah.');
        }

        if ($requestedProduct->store_id === $store->id) {
            return back()->with('error', 'Anda tidak dapat menukar tambah produk milik toko sendiri.');
        }

        // Produk yang ditawarkan harus milik seller sendiri dan bisa ditukar tambah
        $offeredProduct = Product::where('public_id', $request->offered_product_id)->first();

        if (! $offeredProduct || $offeredProduct->store_id !== $store->id) {
            return back()->with('error', 'Produk yang ditawarkan tidak valid.');
        }

        if (! $this->isTradeInEnabled($offeredProduct)) {
            return back()->with('error', 'Produk yang Anda tawarkan tidak tersedia untuk tukar tambah.');
        }

        // Cegah pengajuan duplikat yang masih pending untuk pasangan produk yang sama
        $alreadyPending = TradeInRequest::pending()
            ->where('requester_store_id', $store->id)
            ->where('offered_product_id', $offeredProduct->id)
            ->where('requested_product_id', $requestedProduct->id)
            ->exists();

        if ($alreadyPending) {
            return back()->with('error', 'Anda sudah mengajukan tukar tambah untuk produk ini dan masih menunggu persetujuan.');
        }

        [$additionalCash, $payerRole] = $this->calculatePriceDifference($offeredProduct, $requestedProduct);

        TradeInRequest::create([
            'requester_store_id' => $store->id,
            'responder_store_id' => $requestedProduct->store_id,
            'offered_product_id' => $offeredProduct->id,
            'requested_product_id' => $requestedProduct->id,
            'additional_cash' => $additionalCash,
            'payer_role' => $payerRole,
            'note' => $request->input('note'),
            'status' => TradeInRequest::STATUS_PENDING,
        ]);

        $formattedCash = number_format($additionalCash, 0, ',', '.');

        if ($payerRole === TradeInRequest::PAYER_REQUESTER) {
            return back()->with('success', 'Pengajuan tukar tambah berhasil dikirim. Anda perlu membayar tambahan Rp '.$formattedCash.' jika tukar tambah disetujui.');
        }

        if ($payerRole === TradeInRequest::PAYER_RESPONDER) {
            return back()->with('success', 'Pengajuan tukar tambah berhasil dikirim. Karena produk Anda lebih mahal, seller pemilik produk perlu membayar tambahan Rp '.$formattedCash.' jika ia menyetujui.');
        }

        return back()->with('success', 'Pengajuan tukar tambah berhasil dikirim. Menunggu persetujuan seller pemilik produk.');
    }

    /**
     * Seller pemilik produk yang diminta menyetujui tukar tambah.
     * Jika ada additional_cash, maka kepemilikan produk baru ditukar setelah pembayaran selesai.
     * Jika tidak ada additional_cash, kepemilikan langsung ditukar.
     */
    public function accept(TradeInRequest $tradeIn)
    {
        $store = Auth::user()->store;

        if (! $store || $tradeIn->responder_store_id !== $store->id) {
            abort(403, 'Anda tidak memiliki akses untuk menyetujui tukar tambah ini.');
        }

        if ($tradeIn->status !== TradeInRequest::STATUS_PENDING) {
            return back()->with('error', 'Pengajuan tukar tambah ini sudah tidak dapat diproses.');
        }

        try {
            DB::transaction(function () use ($tradeIn) {
                // Kunci baris produk agar aman dari race condition
                $offered = Product::where('id', $tradeIn->offered_product_id)->lockForUpdate()->first();
                $requested = Product::where('id', $tradeIn->requested_product_id)->lockForUpdate()->first();

                if (! $offered || ! $requested) {
                    throw new \RuntimeException('Produk tidak ditemukan.');
                }

                // Pastikan kedua produk masih layak ditukar tambah
                if (! $this->isTradeInEnabled($offered) || ! $this->isTradeInEnabled($requested)) {
                    throw new \RuntimeException('Salah satu produk sudah tidak tersedia untuk tukar tambah.');
                }

                // Update status tukar tambah menjadi accepted
                $tradeIn->status = TradeInRequest::STATUS_ACCEPTED;
                $tradeIn->responded_at = now();

                // Harga bisa berubah antara pengajuan dikirim dan disetujui.
                // Penerima menyetujui berdasarkan harga yang ia LIHAT sekarang,
                // jadi selisihnya dihitung ulang di sini — bukan memakai angka
                // beku dari saat pengajuan dibuat. Tanpa ini, menaikkan harga
                // produk sendiri setelah mengajukan membuat pihak yang justru
                // menyerahkan barang lebih mahal tetap ditagih.
                [$additionalCash, $payerRole] = $this->calculatePriceDifference($offered, $requested);

                $tradeIn->additional_cash = $additionalCash;
                $tradeIn->payer_role = $payerRole;

                // Cek apakah ada additional_cash yang perlu dibayar
                if ($tradeIn->requiresPayment()) {
                    // Ada pembayaran, set status payment ke pending
                    $tradeIn->payment_status = TradeInRequest::PAYMENT_PENDING;
                    $tradeIn->payment_reference = 'TUKARTAMBAH-'.$tradeIn->public_id.'-'.time();
                    $tradeIn->save();
                } else {
                    $tradeIn->payment_status = TradeInRequest::PAYMENT_NOT_REQUIRED;
                    $tradeIn->save();
                }

                // Kunci kedua produk sejak tukar tambah disetujui — baik yang menunggu
                // pembayaran maupun yang langsung masuk tahap pengiriman.
                // Tanpa ini produk masih bisa dibeli pembeli biasa sementara
                // tukar tambahnya berjalan (temuan T-08).
                $this->lockProductsForTradeIn($offered, $requested, $tradeIn);

                // Batalkan pengajuan pending lain yang melibatkan kedua produk ini
                $this->cancelOtherPendingRequests($tradeIn);

                // Tanpa pembayaran, tukar tambah langsung masuk tahap saling kirim.
                if (! $tradeIn->requiresPayment()) {
                    $this->startShipping($tradeIn);
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() ?: 'Gagal memproses tukar tambah.');
        }

        if ($tradeIn->requiresPayment()) {
            $selisih = 'Rp '.number_format($tradeIn->additional_cash, 0, ',', '.');

            $pesan = $tradeIn->payerRole() === TradeInRequest::PAYER_RESPONDER
                ? 'Tukar tambah disetujui. Karena produk Anda lebih murah, Anda harus membayar selisih '.$selisih.' sebelum barang dikirim.'
                : 'Tukar tambah disetujui. Pengaju harus membayar selisih '.$selisih.' sebelum barang dikirim.';

            return back()->with('success', $pesan.' Kedua produk sementara dikunci dari penjualan.');
        }

        return back()->with('success', 'Tukar tambah disetujui. Silakan saling mengirim barang dan isi nomor resinya.');
    }

    /**
     * Seller pemilik produk yang diminta menolak tukar tambah.
     */
    public function reject(TradeInRequest $tradeIn)
    {
        $store = Auth::user()->store;

        if (! $store || $tradeIn->responder_store_id !== $store->id) {
            abort(403, 'Anda tidak memiliki akses untuk menolak tukar tambah ini.');
        }

        if ($tradeIn->status !== TradeInRequest::STATUS_PENDING) {
            return back()->with('error', 'Pengajuan tukar tambah ini sudah tidak dapat diproses.');
        }

        $tradeIn->update([
            'status' => TradeInRequest::STATUS_REJECTED,
            'responded_at' => now(),
        ]);

        return back()->with('success', 'Pengajuan tukar tambah ditolak.');
    }

    /**
     * Seller pengaju membatalkan pengajuannya sendiri.
     */
    public function cancel(TradeInRequest $tradeIn)
    {
        $store = Auth::user()->store;

        if (! $store || $tradeIn->requester_store_id !== $store->id) {
            abort(403, 'Anda tidak memiliki akses untuk membatalkan tukar tambah ini.');
        }

        if ($tradeIn->status !== TradeInRequest::STATUS_PENDING) {
            return back()->with('error', 'Pengajuan tukar tambah ini sudah tidak dapat dibatalkan.');
        }

        $tradeIn->update([
            'status' => TradeInRequest::STATUS_CANCELLED,
            'responded_at' => now(),
        ]);

        return back()->with('success', 'Pengajuan tukar tambah dibatalkan.');
    }

    /**
     * Pembayar selisih melakukan pembayaran additional_cash setelah tukar tambah
     * disetujui. Pembayarnya adalah pihak yang produknya lebih murah — bisa
     * pengaju, bisa juga penerima.
     * Generate Midtrans Snap Token dan redirect ke halaman pembayaran.
     */
    public function pay(TradeInRequest $tradeIn)
    {
        $store = Auth::user()->store;

        if (! $tradeIn->isPayer($store)) {
            abort(403, 'Anda tidak memiliki akses untuk membayar tukar tambah ini.');
        }

        // Transaksi Midtrans sudah pernah dibuat sebelumnya: pastikan dulu ia
        // belum lunas. Tanpa ini, pembayaran yang webhook-nya tidak sampai akan
        // membuka Snap lagi untuk order_id yang sudah dibayar — dan Midtrans
        // menolaknya, sehingga pembayarannya terlihat macet selamanya.
        if ($tradeIn->snap_token && $tradeIn->payment_status === TradeInRequest::PAYMENT_PENDING) {
            try {
                $this->syncPaymentFromMidtrans($tradeIn);
                $tradeIn->refresh();
            } catch (\Throwable $e) {
                // Midtrans tidak dapat dihubungi bukan alasan untuk memblokir
                // pembayaran; lanjutkan saja ke Snap seperti biasa.
                Log::warning('Gagal menyelaraskan status pembayaran tukar tambah', [
                    'trade_in_id' => $tradeIn->public_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($tradeIn->payment_status === TradeInRequest::PAYMENT_PAID) {
            return redirect()->route('seller.tradeIn.index')
                ->with('success', 'Pembayaran selisih sudah lunas. Silakan kirim barang Anda dan isi nomor resinya.');
        }

        // Validasi: tukar tambah harus sudah accepted dan payment pending
        if ($tradeIn->status !== TradeInRequest::STATUS_ACCEPTED) {
            return back()->with('error', 'Tukar tambah belum disetujui.');
        }

        if ($tradeIn->payment_status !== TradeInRequest::PAYMENT_PENDING) {
            return back()->with('error', 'Pembayaran tidak diperlukan atau sudah selesai.');
        }

        // Jika snap token sudah ada, gunakan yang lama
        if ($tradeIn->snap_token) {
            return Inertia::render('seller/tukar-tambah/payment', [
                'trade_in' => $tradeIn->load([
                    'offeredProduct',
                    'requestedProduct',
                    'requesterStore',
                    'responderStore',
                ]),
                'snapToken' => $tradeIn->snap_token,
                'viewerRole' => $tradeIn->roleOfStore($store),
            ]);
        }

        // Generate snap token baru
        try {
            $midtransService = app(\App\Services\MidtransService::class);

            // Custom payload untuk tukar tambah payment
            $payload = [
                'transaction_details' => [
                    'order_id' => $tradeIn->payment_reference,
                    'gross_amount' => (int) $tradeIn->additional_cash,
                ],
                'customer_details' => [
                    'first_name' => Auth::user()->first_name,
                    'last_name' => Auth::user()->last_name,
                    'email' => Auth::user()->email,
                    'phone' => Auth::user()->phone,
                ],
                'item_details' => [
                    [
                        'id' => $tradeIn->public_id,
                        'price' => (int) $tradeIn->additional_cash,
                        'quantity' => 1,
                        'name' => 'Pembayaran Selisih Tukar Tambah - '.$tradeIn->public_id,
                    ],
                ],
                'callbacks' => [
                    'finish' => route('seller.tradeIn.index'),
                ],
            ];

            $serverKey = config('services.midtrans.server_key');
            if (empty($serverKey)) {
                throw new \RuntimeException('MIDTRANS_SERVER_KEY belum diatur di file .env.');
            }

            // Payload dibersihkan lewat MidtransService: nama pembayar bisa
            // saja mengandung byte yang bukan UTF-8 valid, dan satu byte rusak
            // menggagalkan seluruh transaksi saat payload di-encode ke JSON.
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic '.base64_encode($serverKey.':'),
            ])->post(config('services.midtrans.snap_url'), MidtransService::sanitize($payload))
                ->throw()
                ->json();

            $tradeIn->update([
                'snap_token' => $response['token'],
            ]);

            return Inertia::render('seller/tukar-tambah/payment', [
                'trade_in' => $tradeIn->load([
                    'offeredProduct',
                    'requestedProduct',
                    'requesterStore',
                    'responderStore',
                ]),
                'snapToken' => $response['token'],
                'viewerRole' => $tradeIn->roleOfStore($store),
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
    public function checkPaymentStatus(TradeInRequest $tradeIn)
    {
        if (! $tradeIn->isPayer(Auth::user()->store)) {
            abort(403);
        }

        try {
            $this->syncPaymentFromMidtrans($tradeIn);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        $fresh = $tradeIn->fresh();

        return response()->json([
            'payment_status' => $fresh->payment_status,
            'is_paid' => $fresh->payment_status === TradeInRequest::PAYMENT_PAID,
        ]);
    }

    /**
     * Tanyakan status transaksi ke Midtrans dan tandai lunas bila memang sudah.
     *
     * Dipakai sebagai jaring pengaman ketika webhook tidak sampai — kondisi
     * yang normal terjadi di localhost. Idempoten dan aman dipanggil berulang:
     * baris tukar tambah dikunci, dan tukar tambah yang sudah lunas langsung dilewati.
     */
    private function syncPaymentFromMidtrans(TradeInRequest $tradeIn): void
    {
        if ($tradeIn->payment_status === TradeInRequest::PAYMENT_PAID) {
            return;
        }

        if (empty($tradeIn->payment_reference)) {
            return;
        }

        $midtransService = app(\App\Services\MidtransService::class);
        $status = $midtransService->getTransactionStatus($tradeIn->payment_reference);

        $paymentStatus = $midtransService->mapPaymentStatus(
            $status['transaction_status'] ?? null,
            $status['fraud_status'] ?? null
        );

        if ($paymentStatus !== 'paid') {
            return;
        }

        DB::transaction(function () use ($tradeIn, $status) {
            $locked = TradeInRequest::whereKey($tradeIn->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->payment_status === TradeInRequest::PAYMENT_PAID) {
                return;
            }

            $locked->forceFill([
                'payment_status' => TradeInRequest::PAYMENT_PAID,
                'midtrans_transaction_id' => $status['transaction_id'] ?? null,
                'paid_at' => now(),
            ])->save();

            // Pembayaran lunas TIDAK langsung memindahkan kepemilikan.
            // Tukar tambah masuk tahap saling kirim barang lebih dulu.
            $this->startShipping($locked);
        });
    }

    /**
     * Salah satu seller mengisi nomor resi pengiriman barangnya.
     *
     * Requester mengirim produk yang ia tawarkan; responder mengirim produk
     * miliknya yang diminta. Keduanya wajib mengirim.
     */
    public function ship(Request $request, TradeInRequest $tradeIn)
    {
        $validated = $request->validate([
            'tracking_number' => 'required|string|max:100',
        ], [
            'tracking_number.required' => 'Nomor resi wajib diisi.',
        ]);

        $role = $tradeIn->roleOfStore(Auth::user()->store);

        if (! $role) {
            abort(403, 'Anda bukan pihak dalam tukar tambah ini.');
        }

        if (! $tradeIn->isShipping()) {
            return back()->with('error', 'Tukar tambah ini belum masuk tahap pengiriman.');
        }

        $prefix = $role; // 'requester' atau 'responder'

        if ($tradeIn->{$prefix.'_shipped_at'} !== null) {
            return back()->with('error', 'Anda sudah mengisi nomor resi untuk tukar tambah ini.');
        }

        $tradeIn->update([
            $prefix.'_tracking_number' => $validated['tracking_number'],
            $prefix.'_shipped_at' => now(),
        ]);

        return back()->with('success', 'Nomor resi tersimpan. Menunggu pihak lain mengonfirmasi penerimaan.');
    }

    /**
     * Salah satu seller mengonfirmasi barang dari pihak lain sudah diterima.
     * Kepemilikan produk baru berpindah ketika KEDUANYA sudah mengonfirmasi.
     */
    public function confirmReceipt(TradeInRequest $tradeIn)
    {
        $role = $tradeIn->roleOfStore(Auth::user()->store);

        if (! $role) {
            abort(403, 'Anda bukan pihak dalam tukar tambah ini.');
        }

        if (! $tradeIn->isShipping()) {
            return back()->with('error', 'Tukar tambah ini belum masuk tahap pengiriman.');
        }

        // Pihak lawan adalah yang mengirimkan barang kepada saya.
        $counterpart = $role === 'requester' ? 'responder' : 'requester';

        if ($tradeIn->{$counterpart.'_shipped_at'} === null) {
            return back()->with('error', 'Pihak lain belum mengirimkan barangnya.');
        }

        // Kolom *_received_at mencatat "saya sudah menerima barang".
        if ($tradeIn->{$role.'_received_at'} !== null) {
            return back()->with('error', 'Anda sudah mengonfirmasi penerimaan barang.');
        }

        try {
            DB::transaction(function () use ($tradeIn, $role) {
                $locked = TradeInRequest::whereKey($tradeIn->getKey())->lockForUpdate()->firstOrFail();

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
                    'status' => TradeInRequest::STATUS_COMPLETED,
                    'completed_at' => now(),
                ])->save();
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal memproses konfirmasi penerimaan.');
        }

        $tradeIn->refresh();

        if ($tradeIn->isCompleted()) {
            return back()->with('success', 'Tukar tambah selesai. Kepemilikan kedua produk sudah berpindah sepenuhnya.');
        }

        return back()->with('success', 'Penerimaan dikonfirmasi. Menunggu pihak lain mengonfirmasi juga.');
    }

    /**
     * Hitung selisih harga tukar tambah beserta pihak yang wajib membayarnya.
     *
     * Selisihnya berlaku DUA ARAH: pihak yang produknya lebih murah yang
     * menambah uang. Dikembalikan sebagai [nominal, peran pembayar] — peran
     * bernilai null ketika kedua harga sama.
     *
     * Dipakai bersama oleh store() dan accept() supaya kedua momen itu tidak
     * pernah memakai rumus yang berbeda.
     */
    private function calculatePriceDifference(Product $offered, Product $requested): array
    {
        $difference = (float) $requested->price - (float) $offered->price;

        $payerRole = match (true) {
            $difference > 0 => TradeInRequest::PAYER_REQUESTER,
            $difference < 0 => TradeInRequest::PAYER_RESPONDER,
            default => null,
        };

        return [abs($difference), $payerRole];
    }

    /**
     * Helper: produk layak tukar tambah (approved, ditandai tradeInEnabled, stok > 0,
     * dan tidak sedang terikat tukar tambah lain).
     */
    private function isTradeInEnabled(Product $product): bool
    {
        return $product->approval_status === Product::STATUS_APPROVED
            && $product->is_trade_in_enabled
            && $product->stock > 0
            && ! $product->isLockedForTradeIn();
    }

    /**
     * Kunci kedua produk agar tidak bisa dibeli pembeli biasa atau ditawarkan
     * pada tukar tambah lain selama tukar tambah ini belum tuntas.
     */
    private function lockProductsForTradeIn(Product $offered, Product $requested, TradeInRequest $tradeIn): void
    {
        foreach ([$offered, $requested] as $product) {
            $product->locked_for_trade_in_id = $tradeIn->id;
            $product->save();
        }
    }

    /**
     * Buka kunci kedua produk (tukar tambah batal atau sudah tuntas).
     */
    private function unlockProductsForTradeIn(TradeInRequest $tradeIn): void
    {
        Product::where('locked_for_trade_in_id', $tradeIn->id)
            ->update(['locked_for_trade_in_id' => null]);
    }

    /**
     * Masuk tahap saling kirim barang. Dipanggil setelah tukar tambah disetujui
     * (tanpa selisih uang) atau setelah pembayaran selisih lunas.
     */
    private function startShipping(TradeInRequest $tradeIn): void
    {
        if ($tradeIn->status === TradeInRequest::STATUS_SHIPPING || $tradeIn->isCompleted()) {
            return;
        }

        // Titik awal tenggat pengiriman: dari sini kedua seller punya
        // TradeInRequest::SHIPPING_DEADLINE_DAYS hari untuk mengisi resi.
        $tradeIn->forceFill([
            'status' => TradeInRequest::STATUS_SHIPPING,
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
    private function exchangeProducts(Product $offered, Product $requested, TradeInRequest $tradeIn): void
    {
        $requesterStoreId = $tradeIn->requester_store_id;
        $responderStoreId = $tradeIn->responder_store_id;

        // Tukar kepemilikan produk
        $offered->store_id = $responderStoreId;
        $requested->store_id = $requesterStoreId;

        // Setelah tertukar, matikan flag tukar tambah (pemilik baru bisa mengaktifkan lagi)
        // dan buka kuncinya karena tukar tambah sudah tuntas.
        $offered->is_trade_in_enabled = false;
        $requested->is_trade_in_enabled = false;
        $offered->locked_for_trade_in_id = null;
        $requested->locked_for_trade_in_id = null;

        $offered->save();
        $requested->save();

        // Batalkan pengajuan pending lain yang melibatkan salah satu produk
        $this->cancelOtherPendingRequests($tradeIn);
    }

    /**
     * Helper: batalkan pengajuan tukar tambah pending lain yang melibatkan produk yang sama
     */
    private function cancelOtherPendingRequests(TradeInRequest $tradeIn): void
    {
        TradeInRequest::pending()
            ->where('id', '!=', $tradeIn->id)
            ->where(function ($query) use ($tradeIn) {
                $query->whereIn('offered_product_id', [$tradeIn->offered_product_id, $tradeIn->requested_product_id])
                    ->orWhereIn('requested_product_id', [$tradeIn->offered_product_id, $tradeIn->requested_product_id]);
            })
            ->update([
                'status' => TradeInRequest::STATUS_CANCELLED,
                'responded_at' => now(),
            ]);
    }
}
