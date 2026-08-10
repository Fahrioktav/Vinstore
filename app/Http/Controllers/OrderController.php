<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\PlatformRevenue;
use App\Models\Product;
use App\Services\GeocodingService;
use App\Services\MidtransService;
use App\Services\PriceGuessService;
use App\Services\ShippingCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Throwable;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function showCheckout(Product $product)
    {
        $user = Auth::user();

        if ($product->approval_status !== 'approved') {
            return back()->with('error', 'Produk ini belum disetujui admin.');
        }

        // Scope approved() sudah menyaringnya dari daftar, tetapi halaman ini
        // bisa dibuka lewat URL langsung.
        if ($product->isSellerDeactivated()) {
            return back()->with('error', 'Produk ini sedang tidak tersedia karena akun penjualnya dinonaktifkan.');
        }

        if ($user->role === 'seller' && $user->store && $product->store_id === $user->store->id) {
            return redirect()->back()->with('error', 'Anda tidak dapat membeli produk dari toko Anda sendiri!');
        }

        if ($product->isLockedForTradeIn()) {
            return back()->with('error', 'Produk ini sedang dalam proses tukar tambah dan belum tersedia untuk dibeli.');
        }

        // Sinkronkan siklus hidup tebak harga sebelum menampilkan halaman.
        app(PriceGuessService::class)->sync();
        $product->refresh();

        // Cek apakah produk bisa dibeli
        if ($product->isTebakHarga() && ! $product->isPurchasableBy($user)) {
            return back()->with('error', $this->tebakHargaBlockMessage($product, $user));
        }

        // Sembunyikan harga asli bila viewer belum berhak melihatnya.
        $product->maskRealPriceFor($user);

        $product->load('store');

        return Inertia::render('checkout', [
            'product' => $product,
            // Tarif dikirim ke frontend supaya pratinjau biaya memakai angka
            // yang sama persis dengan yang dihitung server saat menagih.
            'feeRates' => app(ShippingCostService::class)->publicRates(),
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        // Validasi tracking_number wajib jika status Processing atau On The Way
        $rules = [
            'status' => 'required|in:Waiting,Processing,On The Way,Delivered,Cancelled',
            'tracking_number' => 'nullable|string|max:100',
        ];

        // Jika status diubah menjadi Processing atau On The Way, tracking_number wajib diisi
        if (in_array($request->status, ['Processing', 'On The Way'])) {
            $rules['tracking_number'] = 'required|string|max:100';
        }

        $request->validate($rules, [
            'tracking_number.required' => 'Nomor resi wajib diisi saat status Processing atau On The Way.',
        ]);

        $order = Order::where('public_id', $id)->firstOrFail();

        $store = Auth::user()->store;

        if (! $store || $order->store_id !== $store->id) {
            abort(403, 'Anda tidak memiliki akses untuk mengupdate order ini.');
        }

        // Update status dan tracking number sekaligus
        $updateData = [
            'status' => $request->status,
        ];

        if ($request->filled('tracking_number')) {
            $updateData['tracking_number'] = $request->tracking_number;
        }

        // Catat kapan seller menyatakan barang sampai. Ini titik awal masa
        // sanggah pembeli sebelum dana dilepas otomatis.
        if ($request->status === 'Delivered' && $order->delivered_at === null) {
            $updateData['delivered_at'] = now();
        }

        $order->update($updateData);

        // Catatan: dana TIDAK cair di sini, dan tidak akan cair sendiri.
        // Seller harus mengajukan pencairan per pesanan dan admin yang
        // menyetujuinya. Lihat PayoutRequest.

        $message = $request->status === 'Delivered'
            ? 'Status pesanan diperbarui. Anda kini dapat mengajukan pencairan dana untuk pesanan ini lewat menu Aksi.'
            : 'Status pesanan berhasil diperbarui.';

        return back()->with('success', $message);
    }

    /**
     * Pembeli mengonfirmasi barang sudah diterima. Inilah satu-satunya jalur
     * (selain pelepasan otomatis) yang mencairkan dana ke saldo seller.
     */
    public function confirmReceipt($id)
    {
        $order = Order::where('public_id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($order->status === 'Completed') {
            return back()->with('error', 'Pesanan ini sudah dikonfirmasi sebelumnya.');
        }

        if (! $order->canBeConfirmedByBuyer()) {
            return back()->with('error', 'Pesanan ini belum dapat dikonfirmasi. Pastikan pesanan sudah dibayar dan sedang dikirim.');
        }

        $order->completeByBuyer();

        return back()->with('success', 'Terima kasih! Pesanan ditandai selesai dan dana diteruskan ke penjual.');
    }

    public function destroy($id)
    {
        $order = Order::where('public_id', $id)->firstOrFail();

        // Seller hanya boleh menghapus pesanan tokonya sendiri.
        $store = Auth::user()->store;

        if (! $store || $order->store_id !== $store->id) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus pesanan ini.');
        }

        // Pesanan yang sudah dibayar adalah bukti transaksi milik pembeli dan
        // tidak boleh dihapus sepihak oleh seller.
        if ($order->payment_status === 'paid') {
            return back()->with('error', 'Pesanan yang sudah dibayar tidak dapat dihapus.');
        }

        $order->delete();

        return back()->with('success', 'Order berhasil dihapus.');
    }

    public function processCheckout(Request $request, Product $product)
    {
        $request->validate(self::shippingRules() + [
            'quantity' => 'required|integer|min:1',
        ], self::shippingMessages());

        $user = Auth::user();

        if ($user->role === 'seller' && $user->store && $product->store_id === $user->store->id) {
            return back()->with('error', 'Anda tidak dapat membeli produk dari toko Anda sendiri!');
        }

        // Pastikan status tebak harga terkini sebelum memproses pembelian.
        app(PriceGuessService::class)->sync();
        $product->refresh();

        if ($product->isTebakHarga() && ! $product->isPurchasableBy($user)) {
            return back()->with('error', $this->tebakHargaBlockMessage($product, $user));
        }

        // Dibaca sebelum transaksi dibuka; lihat resolveShippingArea().
        $shippingArea = self::resolveShippingArea($request);

        try {
            $transaction = DB::transaction(function () use ($request, $product, $user, $shippingArea) {
                $quantity = (int) $request->quantity;
                $product = Product::whereKey($product->getKey())->with('store')->lockForUpdate()->firstOrFail();

                if ($product->isSellerDeactivated()) {
                    throw new \RuntimeException('Produk ini sedang tidak tersedia karena akun penjualnya dinonaktifkan.');
                }

                if ($product->isTebakHarga() && ! $product->isPurchasableBy($user)) {
                    throw new \RuntimeException('Produk ini belum dapat dibeli.');
                }

                // Dicek ulang di dalam lock: tukar tambah bisa saja disetujui tepat
                // setelah pengecekan awal di luar transaksi.
                if ($product->isLockedForTradeIn()) {
                    throw new \RuntimeException('Produk ini sedang dalam proses tukar tambah dan belum tersedia untuk dibeli.');
                }

                // Yang diperiksa adalah sisa yang BISA DIBELI, bukan stok
                // mentah: sebagian stok bisa sedang ditahan pesanan orang lain
                // yang belum lunas.
                if ($product->available_stock < $quantity) {
                    throw new \RuntimeException(self::stockErrorMessage($product, $quantity));
                }

                // Pemenang tebak harga yang masih memegang prioritas membayar
                // harga diskon; selebihnya harga normal.
                $unitPrice = (int) round($product->effectivePriceFor($user));

                // Seluruh komponen biaya dihitung ulang di sisi server. Angka
                // yang dikirim frontend hanya pratinjau dan tidak dipercaya.
                $quote = app(ShippingCostService::class)->quoteLine(
                    $product,
                    $quantity,
                    $unitPrice,
                    $request->shipping_method,
                    self::floatOrNull($request->input('shipping_latitude')),
                    self::floatOrNull($request->input('shipping_longitude')),
                    true,
                    $request->input('packaging_type'),
                );

                $totalPrice = $quote['total'];

                // Stok DITAHAN, belum dipotong. Barangnya baru benar-benar
                // keluar dari stok saat pembayaran lunas — sampai saat itu ia
                // tetap tampil di etalase. Lihat Order::commitReservedStock().
                $product->reserved_stock = $product->reserved_stock + $quantity;

                // Pemenang tebak harga menggunakan hak prioritasnya -> produk menjadi penjualan biasa.
                if ($product->isTebakHarga() && $product->guess_status === Product::GUESS_ENDED) {
                    $product->guess_status = Product::GUESS_PUBLIC;
                    $product->winner_priority_until = null;
                }

                $product->save();

                $order = Order::create([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    // Snapshot identitas barang & toko: riwayat pesanan harus
                    // tetap terbaca meski produk/toko dihapus di kemudian hari.
                    'product_name' => $product->name,
                    'product_price' => $unitPrice,
                    'store_id' => $product->store_id,
                    'store_name' => $product->store?->store_name,
                    'quantity' => $quantity,
                    'price' => $totalPrice,
                    'status' => 'Waiting',
                    // Detail pengiriman disimpan sebagai bagian dari pesanan:
                    // tanpa ini seller tidak tahu ke mana barang dikirim.
                    'shipping_address' => $request->shipping_address,
                    'shipping_area' => $shippingArea,
                    'shipping_method' => $request->shipping_method,
                    'shipping_cost' => $quote['shipping_cost'],
                    'packaging_fee' => $quote['packaging_fee'],
                    'packaging_type' => $quote['packaging_type'],
                    'weight_fee' => $quote['weight_fee'],
                    'service_fee' => $quote['service_fee'],
                    'weight_gram' => $quote['weight_gram'],
                    'volumetric_weight_gram' => $quote['volumetric_weight_gram'],
                    'shipping_distance_km' => $quote['distance_km'],
                    'shipping_latitude' => self::floatOrNull($request->input('shipping_latitude')),
                    'shipping_longitude' => self::floatOrNull($request->input('shipping_longitude')),
                    'notes' => $request->notes,
                    'payment_status' => 'pending',
                    'payment_method' => 'midtrans',
                ]);

                $paymentReference = $order->public_id;
                $order->update(['payment_reference' => $paymentReference]);

                // Setiap komponen biaya menjadi barisnya sendiri. Midtrans
                // menolak transaksi bila jumlah item_details tidak sama persis
                // dengan gross_amount.
                $items = array_merge([
                    [
                        'id' => $product->public_id,
                        'price' => $unitPrice,
                        'quantity' => $quantity,
                        'name' => MidtransService::truncate($product->name, 50),
                    ],
                ], self::feeItemDetails($order, $quote, $request->shipping_method, $product->store?->store_name));

                $transaction = app(MidtransService::class)->createSnapTransaction(
                    $paymentReference,
                    $totalPrice,
                    $user,
                    $items
                );

                $order->update([
                    'snap_token' => $transaction['token'] ?? null,
                    'snap_redirect_url' => $transaction['redirect_url'] ?? null,
                ]);

                return $transaction;
            });
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal membuat pembayaran Midtrans: '.$e->getMessage());
        }

        // Refresh product data
        $product->refresh();
        $product->load('store');

        // Return ke halaman checkout dengan snap_token untuk trigger Snap Popup
        return Inertia::render('checkout', [
            'product' => $product,
            'feeRates' => app(ShippingCostService::class)->publicRates(),
            'flash' => [
                'success' => 'Order berhasil dibuat. Silakan selesaikan pembayaran.',
                'snap_token' => $transaction['token'],
            ],
        ]);
    }

    public function userOrders()
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
        }

        $orders = Order::with(['product', 'auction', 'store', 'refundRequest'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $this->syncPendingMidtransPayments($orders);

        $orders = Order::with(['product', 'auction', 'store', 'refundRequest'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return Inertia::render('order', compact('orders'));
    }

    public function cancelOrder($id)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
        }

        $order = Order::where('public_id', $id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['Waiting', 'On The Way'])
            ->firstOrFail();

        $order->status = 'Cancelled';
        $order->payment_status = $order->payment_status === 'paid'
            ? $order->payment_status
            : 'cancelled';
        $order->save();

        if ($order->payment_status !== 'paid') {
            $order->restoreReservedStock();
        }

        return back()->with('success', 'Pesanan berhasil dibatalkan.');
    }

    public function checkoutFromCart(Request $request)
    {
        // Di keranjang, metode pengiriman dan jenis pengemasan dipilih PER TOKO,
        // bukan sekali untuk semua. Satu keranjang bisa memuat guci keramik dari
        // satu toko dan koin dari toko lain — memaksa keduanya memakai peti kayu
        // dan kurir yang sama membuat pembeli membayar perlindungan yang tidak
        // ia butuhkan. Pembayarannya tetap satu transaksi Snap.
        $request->validate(
            self::cartShippingRules(),
            self::shippingMessages() + [
                'store_options.*.shipping_method.required' => 'Metode pengiriman untuk setiap toko wajib dipilih.',
                'store_options.*.packaging_type.required' => 'Jenis pengemasan untuk setiap toko wajib dipilih.',
            ]
        );

        $user = Auth::user();

        $shippingArea = self::resolveShippingArea($request);

        try {
            $transaction = DB::transaction(function () use ($request, $user, $shippingArea) {
                $cartItems = Cart::with('product')->where('user_id', $user->id)->get();

                if ($cartItems->isEmpty()) {
                    throw new \RuntimeException('Keranjang kamu kosong.');
                }

                $costs = app(ShippingCostService::class);
                $destLat = self::floatOrNull($request->input('shipping_latitude'));
                $destLng = self::floatOrNull($request->input('shipping_longitude'));
                $storeOptions = (array) $request->input('store_options', []);

                // Ongkir dan biaya peti ditagih sekali per toko, bukan per baris
                // keranjang: dua barang dari toko yang sama dikirim dalam satu paket.
                $shippingChargedForStore = [];

                $paymentReference = 'PAY'.random_int(100000000, 999999999);
                $grossAmount = 0;
                $itemDetails = [];
                $orderIds = [];

                foreach ($cartItems as $item) {
                    $product = Product::whereKey($item->product_id)->with('store')->lockForUpdate()->firstOrFail();
                    $quantity = (int) $item->quantity;

                    if ($product->approval_status !== 'approved') {
                        throw new \RuntimeException('Produk "'.$product->name.'" belum disetujui admin.');
                    }

                    if ($product->isSellerDeactivated()) {
                        throw new \RuntimeException(
                            'Produk "'.$product->name.'" sedang tidak tersedia karena akun penjualnya dinonaktifkan.'
                        );
                    }

                    if ($product->isTebakHarga() && ! $product->isPurchasableBy($user)) {
                        throw new \RuntimeException('Produk Tebak Harga "'.$product->name.'" belum dapat dibeli saat ini.');
                    }

                    if ($product->isLockedForTradeIn()) {
                        throw new \RuntimeException('Produk "'.$product->name.'" sedang dalam proses tukar tambah dan belum tersedia untuk dibeli.');
                    }

                    if ($user->role === 'seller' && $user->store && $product->store_id === $user->store->id) {
                        throw new \RuntimeException('Keranjang Anda mengandung produk dari toko Anda sendiri. Silakan hapus produk tersebut terlebih dahulu.');
                    }

                    if ($product->available_stock < $quantity) {
                        throw new \RuntimeException(self::stockErrorMessage($product, $quantity));
                    }

                    // Pemenang tebak harga yang masih memegang prioritas
                    // membayar harga diskon; selebihnya harga normal.
                    $unitPrice = (int) round($product->effectivePriceFor($user));

                    // Kunci pengelompokan memakai public_id toko karena itulah
                    // yang dikirim frontend; produk tanpa toko masuk kelompok
                    // 'none' agar tetap punya satu ongkir sendiri.
                    $storeKey = $product->store?->public_id ?? 'none';
                    $firstOfStore = ! isset($shippingChargedForStore[$storeKey]);
                    $shippingChargedForStore[$storeKey] = true;

                    $options = $storeOptions[$storeKey] ?? [];
                    $shippingMethod = $options['shipping_method'] ?? 'standard';
                    $packagingType = $options['packaging_type'] ?? null;

                    $quote = $costs->quoteLine(
                        $product,
                        $quantity,
                        $unitPrice,
                        $shippingMethod,
                        $destLat,
                        $destLng,
                        $firstOfStore,
                        $packagingType,
                    );

                    $grossAmount += $quote['total'];

                    // Ditahan, bukan dipotong — lihat catatan di processCheckout().
                    $product->reserved_stock = $product->reserved_stock + $quantity;

                    if ($product->isTebakHarga() && $product->guess_status === Product::GUESS_ENDED) {
                        $product->guess_status = Product::GUESS_PUBLIC;
                        $product->winner_priority_until = null;
                    }

                    $product->save();

                    $order = Order::create([
                        'user_id' => $user->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_price' => $unitPrice,
                        'store_id' => $product->store_id,
                        'store_name' => $product->store?->store_name,
                        'quantity' => $quantity,
                        'price' => $quote['total'],
                        'status' => 'Waiting',
                        'shipping_address' => $request->shipping_address,
                        'shipping_area' => $shippingArea,
                        'shipping_method' => $shippingMethod,
                        'shipping_cost' => $quote['shipping_cost'],
                        'packaging_fee' => $quote['packaging_fee'],
                        'packaging_type' => $quote['packaging_type'],
                        'weight_fee' => $quote['weight_fee'],
                        'service_fee' => $quote['service_fee'],
                        'weight_gram' => $quote['weight_gram'],
                        'volumetric_weight_gram' => $quote['volumetric_weight_gram'],
                        'shipping_distance_km' => $quote['distance_km'],
                        'shipping_latitude' => $destLat,
                        'shipping_longitude' => $destLng,
                        'notes' => $request->notes,
                        'payment_reference' => $paymentReference,
                        'payment_status' => 'pending',
                        'payment_method' => 'midtrans',
                    ]);

                    $orderIds[] = $order->id;
                    $itemDetails[] = [
                        'id' => $product->public_id,
                        'price' => $unitPrice,
                        'quantity' => $quantity,
                        // mb_substr, bukan substr: memotong per byte bisa
                        // membelah karakter multibyte dan membuat payload gagal
                        // di-encode ke JSON.
                        'name' => MidtransService::truncate($product->name, 50),
                    ];

                    // Tiap komponen biaya jadi baris tersendiri agar total
                    // item_details cocok dengan gross_amount — Midtrans menolak
                    // transaksi bila tidak sama.
                    foreach (self::feeItemDetails($order, $quote, $shippingMethod, $product->store?->store_name) as $feeItem) {
                        $itemDetails[] = $feeItem;
                    }
                }

                $transaction = app(MidtransService::class)->createSnapTransaction(
                    $paymentReference,
                    $grossAmount,
                    $user,
                    $itemDetails
                );

                Order::whereIn('id', $orderIds)->update([
                    'snap_token' => $transaction['token'] ?? null,
                    'snap_redirect_url' => $transaction['redirect_url'] ?? null,
                ]);

                Cart::where('user_id', $user->id)->delete();

                return $transaction;
            });
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('cart.index')->with('error', 'Gagal membuat pembayaran Midtrans: '.$e->getMessage());
        }

        // Return snap token ke frontend untuk trigger popup
        return redirect()->route('order')->with('snap_token', $transaction['token']);
    }

    public function showInvoice($id)
    {
        $user = Auth::user();

        $order = Order::with(['product', 'auction', 'store', 'user'])
            ->where('public_id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Validasi: Invoice hanya bisa diakses jika pembayaran sudah berhasil
        if ($order->payment_status !== 'paid') {
            return redirect()->route('order')->with('error', 'Invoice hanya dapat diakses setelah pembayaran berhasil.');
        }

        return Inertia::render('invoice', compact('order'));
    }

    /**
     * Aturan validasi detail pengiriman, dipakai checkout produk maupun keranjang.
     *
     * Koordinat tujuan bersifat opsional — pembeli yang menolak izin lokasi tetap
     * harus bisa berbelanja — tetapi harus lengkap: satu koordinat saja tanpa
     * pasangannya tidak bisa dipakai menghitung jarak.
     */
    private static function shippingRules(): array
    {
        return [
            'shipping_address' => 'required|string|max:500',
            'shipping_method' => 'required|in:standard,express',
            // Titik antar WAJIB. Sebelumnya opsional, dan ongkir jatuh ke tarif
            // rata bila kosong — itu membuat ongkir berbasis jarak bisa
            // dihindari cukup dengan tidak memilih titik (temuan V4-01).
            'shipping_latitude' => 'required|numeric|between:-90,90',
            'shipping_longitude' => 'required|numeric|between:-180,180',
            // Pilihan pengemasan pembeli. Opsional supaya jalur lama tetap
            // jalan; yang kosong memakai jenis default dari config.
            'packaging_type' => ['nullable', Rule::in(array_keys(config('marketplace.packaging.options')))],
            'notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Aturan checkout keranjang: alamat & titik antar berlaku untuk seluruh
     * keranjang, tetapi metode pengiriman dan pengemasan dipilih per toko.
     *
     * `shipping_method` dan `packaging_type` tunggal sengaja dibuang dari sini —
     * membiarkannya ikut diterima akan menciptakan dua sumber kebenaran untuk
     * hal yang sama.
     */
    private static function cartShippingRules(): array
    {
        $rules = self::shippingRules();

        unset($rules['shipping_method'], $rules['packaging_type']);

        return $rules + [
            'store_options' => 'required|array|min:1',
            'store_options.*.shipping_method' => 'required|in:standard,express',
            'store_options.*.packaging_type' => [
                'required',
                Rule::in(array_keys(config('marketplace.packaging.options'))),
            ],
        ];
    }

    private static function shippingMessages(): array
    {
        $titikWajib = 'Tentukan dulu titik pengantaran pada peta. '
            .'Ongkos kirim dihitung dari jarak toko ke titik tersebut.';

        return [
            'shipping_latitude.required' => $titikWajib,
            'shipping_longitude.required' => $titikWajib,
            'shipping_address.required' => 'Detail alamat wajib diisi.',
        ];
    }

    /**
     * Nama wilayah titik antar, dibaca dari koordinatnya.
     *
     * Dipanggil DI LUAR transaksi: ini permintaan HTTP ke layanan luar, dan
     * menahannya sambil memegang lockForUpdate atas baris produk akan mengunci
     * stok selama jaringan lambat.
     *
     * Mengembalikan null bila layanannya mati atau titiknya tidak dikenali —
     * checkout tetap berjalan, hanya keterangan wilayahnya yang kosong.
     */
    private static function resolveShippingArea(Request $request): ?string
    {
        return app(GeocodingService::class)->areaName(
            self::floatOrNull($request->input('shipping_latitude')),
            self::floatOrNull($request->input('shipping_longitude')),
        );
    }

    /**
     * Pesan yang menjelaskan KENAPA barangnya tidak bisa dibeli.
     *
     * Sejak stok ditahan alih-alih dipotong, produk bisa terlihat masih ada
     * tetapi tidak bisa dibeli karena sedang dipesan orang lain. Tanpa
     * penjelasan ini pembeli hanya melihat penolakan tanpa sebab.
     */
    private static function stockErrorMessage(Product $product, int $quantity): string
    {
        if ($product->stock <= 0) {
            return 'Produk "'.$product->name.'" sudah habis.';
        }

        if ($product->is_fully_reserved) {
            return 'Produk "'.$product->name.'" sedang dalam proses pembayaran pembeli lain. '
                .'Silakan coba lagi beberapa saat lagi bila pembayarannya batal.';
        }

        return 'Stok produk "'.$product->name.'" tidak mencukupi. Tersisa: '.$product->available_stock
            .', diminta: '.$quantity.'.';
    }

    private static function floatOrNull($value): ?float
    {
        return ($value === null || $value === '') ? null : (float) $value;
    }

    /**
     * Ubah rincian biaya menjadi baris item_details Midtrans.
     *
     * Hanya komponen bernilai > 0 yang dikirim. Jumlah seluruh baris (termasuk
     * baris barang) wajib sama persis dengan gross_amount.
     *
     * Id baris disuffiks public_id pesanan supaya tetap unik saat satu
     * pembayaran mencakup beberapa pesanan sekaligus (checkout keranjang).
     */
    private static function feeItemDetails(Order $order, array $quote, string $method, ?string $storeName): array
    {
        $suffix = $order->public_id;
        $methodLabel = $method === 'express' ? 'Express' : 'Standard';
        $distanceLabel = $quote['distance_km'] !== null
            ? ' '.number_format((float) $quote['distance_km'], 1, ',', '.').' km'
            : '';

        // Ditandai "volumetrik" bila yang menentukan tagihan adalah dimensi
        // paketnya, bukan beratnya — supaya pembeli tahu dari mana angkanya.
        $isVolumetric = ($quote['volumetric_weight_gram'] ?? 0) > ($quote['actual_weight_gram'] ?? 0);
        $weightLabel = 'Biaya Berat '.number_format($quote['weight_gram'] / 1000, 2, ',', '.').' kg'
            .($isVolumetric ? ' (volumetrik)' : '');

        $rows = [
            ['SHIP', $quote['shipping_cost'], 'Ongkir '.$methodLabel.$distanceLabel.' - '.($storeName ?? 'Toko')],
            ['WEIGHT', $quote['weight_fee'], $weightLabel],
            ['PACK', $quote['packaging_fee'], 'Pengemasan '.app(ShippingCostService::class)->packagingLabel($quote['packaging_type'] ?? null)],
            ['SVC', $quote['service_fee'], 'Biaya Layanan'],
        ];

        $items = [];

        foreach ($rows as [$prefix, $amount, $name]) {
            if ((int) $amount <= 0) {
                continue;
            }

            $items[] = [
                'id' => MidtransService::truncate($prefix.'-'.$suffix, 50),
                'price' => (int) $amount,
                'quantity' => 1,
                'name' => MidtransService::truncate($name, 50),
            ];
        }

        return $items;
    }

    private function tebakHargaBlockMessage(Product $product, $user): string
    {
        return match ($product->guess_status) {
            Product::GUESS_SCHEDULED => 'Periode tebak harga produk ini belum dimulai.',
            Product::GUESS_ACTIVE => 'Periode tebak harga masih berlangsung. Produk belum dapat dibeli.',
            Product::GUESS_ENDED => 'Saat ini hanya pemenang tebak harga yang dapat membeli produk ini.',
            default => 'Produk ini belum dapat dibeli saat ini.',
        };
    }

    private function syncPendingMidtransPayments($orders): void
    {
        $references = $orders
            ->filter(fn (Order $order) => $order->payment_reference && in_array($order->payment_status, ['pending', 'unpaid'], true))
            ->pluck('payment_reference')
            ->unique();

        foreach ($references as $reference) {
            try {
                $status = app(MidtransService::class)->getTransactionStatus($reference);
            } catch (Throwable $e) {
                continue;
            }

            $paymentStatus = app(MidtransService::class)->mapPaymentStatus(
                $status['transaction_status'] ?? null,
                $status['fraud_status'] ?? null
            );

            $isFailure = in_array($paymentStatus, ['cancelled', 'denied', 'expired'], true);

            foreach (Order::where('payment_reference', $reference)->get() as $order) {
                $updates = [
                    'payment_method' => $status['payment_type'] ?? 'midtrans',
                    'midtrans_transaction_id' => $status['transaction_id'] ?? $order->midtrans_transaction_id,
                ];

                // Lihat Order::canApplyPaymentStatus(): pesanan yang sudah lunas
                // tidak boleh turun kembali ke 'pending'.
                $applyStatus = $order->canApplyPaymentStatus($paymentStatus);

                if ($applyStatus) {
                    $updates['payment_status'] = $paymentStatus;

                    if ($paymentStatus === 'paid') {
                        $updates['paid_at'] = now();
                    }

                    if ($isFailure) {
                        $updates['status'] = 'Cancelled';
                    }
                }

                $order->update($updates);

                // Lihat MidtransNotificationController: biaya layanan dicatat
                // sekali saja, saat pesanan benar-benar lunas.
                if ($applyStatus && $paymentStatus === 'paid') {
                    $order->commitReservedStock();

                    PlatformRevenue::recordServiceFee($order);
                }

                if ($applyStatus && $paymentStatus === 'refunded') {
                    PlatformRevenue::reverseServiceFee(
                        $order,
                        'Pembalikan biaya layanan atas refund Midtrans pesanan '.$order->public_id
                    );
                }

                if ($applyStatus && $isFailure) {
                    $order->restoreReservedStock();
                }
            }
        }
    }
}
