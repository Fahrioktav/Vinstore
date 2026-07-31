<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\PriceGuess;
use App\Services\MidtransService;
use App\Services\PriceGuessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        if ($user->role === 'seller' && $user->store && $product->store_id === $user->store->id) {
            return redirect()->back()->with('error', 'Anda tidak dapat membeli produk dari toko Anda sendiri!');
        }

        // Sinkronkan siklus hidup tebak harga sebelum menampilkan halaman.
        app(PriceGuessService::class)->sync();
        $product->refresh();

        // Cek apakah produk bisa dibeli
        if ($product->isTebakHarga() && !$product->isPurchasableBy($user)) {
            return back()->with('error', $this->tebakHargaBlockMessage($product, $user));
        }

        // Sembunyikan harga asli bila viewer belum berhak melihatnya.
        $product->maskRealPriceFor($user);

        $product->load('store');

        return Inertia::render('checkout', [
            'product' => $product,
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

        if ($order->store_id !== Auth::user()->store->id) {
            abort(403, 'Anda tidak memiliki akses untuk mengupdate order ini.');
        }

        // Update status dan tracking number sekaligus
        $updateData = [
            'status' => $request->status,
        ];

        if ($request->filled('tracking_number')) {
            $updateData['tracking_number'] = $request->tracking_number;
        }

        $order->update($updateData);

        $order->releaseSellerFunds();

        return back()->with('success', 'Status pesanan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $order = Order::where('public_id', $id)->firstOrFail();
        $order->delete();

        return back()->with('success', 'Order berhasil dihapus.');
    }

    public function processCheckout(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'shipping_address' => 'required|string|max:500',
            'shipping_method' => 'required|in:standard,express',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();

        if ($user->role === 'seller' && $user->store && $product->store_id === $user->store->id) {
            return back()->with('error', 'Anda tidak dapat membeli produk dari toko Anda sendiri!');
        }

        // Pastikan status tebak harga terkini sebelum memproses pembelian.
        app(PriceGuessService::class)->sync();
        $product->refresh();

        if ($product->isTebakHarga() && !$product->isPurchasableBy($user)) {
            return back()->with('error', $this->tebakHargaBlockMessage($product, $user));
        }

        try {
            $transaction = DB::transaction(function () use ($request, $product, $user) {
                $quantity = (int) $request->quantity;
                $product = Product::whereKey($product->getKey())->lockForUpdate()->firstOrFail();

                if ($product->isTebakHarga() && !$product->isPurchasableBy($user)) {
                    throw new \RuntimeException('Produk ini belum dapat dibeli.');
                }

                if ($product->stock < $quantity || $product->stock <= 0) {
                    throw new \RuntimeException('Stok produk tidak mencukupi atau sudah habis. Stok tersedia: ' . $product->stock);
                }

                $unitPrice = (int) round((float) $product->price);
                $shippingCost = $request->shipping_method === 'express' ? 25000 : 10000;
                $totalPrice = ($unitPrice * $quantity) + $shippingCost;
                
                $product->stock = $product->stock - $quantity;

                // Pemenang tebak harga menggunakan hak prioritasnya -> produk menjadi penjualan biasa.
                if ($product->isTebakHarga() && $product->guess_status === Product::GUESS_ENDED) {
                    $product->guess_status = Product::GUESS_PUBLIC;
                    $product->winner_priority_until = null;
                }

                $product->save();

                $order = Order::create([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'store_id' => $product->store_id,
                    'quantity' => $quantity,
                    'price' => $totalPrice,
                    'status' => 'Waiting',
                    'payment_status' => 'pending',
                    'payment_method' => 'midtrans',
                ]);

                $paymentReference = $order->public_id;
                $order->update(['payment_reference' => $paymentReference]);

                // Item details harus include shipping agar total match
                $items = [
                    [
                        'id' => $product->public_id,
                        'price' => $unitPrice,
                        'quantity' => $quantity,
                        'name' => substr($product->name, 0, 50),
                    ],
                    [
                        'id' => 'SHIPPING',
                        'price' => $shippingCost,
                        'quantity' => 1,
                        'name' => $request->shipping_method === 'express' ? 'Pengiriman Express' : 'Pengiriman Standard',
                    ]
                ];

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

            return back()->with('error', 'Gagal membuat pembayaran Midtrans: ' . $e->getMessage());
        }

        // Refresh product data
        $product->refresh();
        $product->load('store');

        // Return ke halaman checkout dengan snap_token untuk trigger Snap Popup
        return Inertia::render('checkout', [
            'product' => $product,
            'flash' => [
                'success' => 'Order berhasil dibuat. Silakan selesaikan pembayaran.',
                'snap_token' => $transaction['token']
            ]
        ]);
    }

    public function userOrders()
    {
        $user = Auth::user();
        if (!$user) {
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
        if (!$user) {
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

    public function checkoutFromCart()
    {
        $user = Auth::user();

        try {
            $transaction = DB::transaction(function () use ($user) {
                $cartItems = Cart::with('product')->where('user_id', $user->id)->get();

                if ($cartItems->isEmpty()) {
                    throw new \RuntimeException('Keranjang kamu kosong.');
                }

                $paymentReference = 'PAY' . random_int(100000000, 999999999);
                $grossAmount = 0;
                $itemDetails = [];
                $orderIds = [];

                foreach ($cartItems as $item) {
                    $product = Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                    $quantity = (int) $item->quantity;

                    if ($product->approval_status !== 'approved') {
                        throw new \RuntimeException('Produk "' . $product->name . '" belum disetujui admin.');
                    }

                    if ($product->isTebakHarga() && !$product->isPurchasableBy($user)) {
                        throw new \RuntimeException('Produk Tebak Harga "' . $product->name . '" belum dapat dibeli saat ini.');
                    }

                    if ($user->role === 'seller' && $user->store && $product->store_id === $user->store->id) {
                        throw new \RuntimeException('Keranjang Anda mengandung produk dari toko Anda sendiri. Silakan hapus produk tersebut terlebih dahulu.');
                    }

                    if ($product->stock < $quantity || $product->stock <= 0) {
                        throw new \RuntimeException('Stok produk "' . $product->name . '" tidak mencukupi. Stok tersedia: ' . $product->stock);
                    }

                    $unitPrice = (int) round((float) $product->price);
                    $lineTotal = $unitPrice * $quantity;
                    $grossAmount += $lineTotal;

                    $product->stock = $product->stock - $quantity;

                    if ($product->isTebakHarga() && $product->guess_status === Product::GUESS_ENDED) {
                        $product->guess_status = Product::GUESS_PUBLIC;
                        $product->winner_priority_until = null;
                    }

                    $product->save();

                    $order = Order::create([
                        'user_id' => $user->id,
                        'product_id' => $product->id,
                        'store_id' => $product->store_id,
                        'quantity' => $quantity,
                        'price' => $lineTotal,
                        'status' => 'Waiting',
                        'payment_reference' => $paymentReference,
                        'payment_status' => 'pending',
                        'payment_method' => 'midtrans',
                    ]);

                    $orderIds[] = $order->id;
                    $itemDetails[] = [
                        'id' => $product->public_id,
                        'price' => $unitPrice,
                        'quantity' => $quantity,
                        'name' => substr($product->name, 0, 50),
                    ];
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

            return redirect()->route('cart.index')->with('error', 'Gagal membuat pembayaran Midtrans: ' . $e->getMessage());
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

            $updates = [
                'payment_status' => $paymentStatus,
                'payment_method' => $status['payment_type'] ?? 'midtrans',
                'midtrans_transaction_id' => $status['transaction_id'] ?? null,
            ];

            if ($paymentStatus === 'paid') {
                $updates['paid_at'] = now();
            }

            if (in_array($paymentStatus, ['cancelled', 'denied', 'expired'], true)) {
                $updates['status'] = 'Cancelled';
            }

            $orders = Order::where('payment_reference', $reference)->get();

            foreach ($orders as $order) {
                $order->update($updates);

                $order->releaseSellerFunds();

                if (in_array($paymentStatus, ['cancelled', 'denied', 'expired'], true)) {
                    $order->restoreReservedStock();
                }
            }
        }
    }
}
