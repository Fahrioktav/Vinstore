<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Services\MidtransService;
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

        return Inertia::render('checkout', compact('product'));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:Waiting,Processing,On The Way,Delivered,Cancelled']);

        $order = Order::where('public_id', $id)->firstOrFail();

        if ($order->store_id !== Auth::user()->store->id) {
            abort(403, 'Anda tidak memiliki akses untuk mengupdate order ini.');
        }

        $order->update([
            'status' => $request->status,
        ]);

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
        ]);

        $user = Auth::user();

        if ($user->role === 'seller' && $user->store && $product->store_id === $user->store->id) {
            return back()->with('error', 'Anda tidak dapat membeli produk dari toko Anda sendiri!');
        }

        try {
            $transaction = DB::transaction(function () use ($request, $product, $user) {
                $quantity = (int) $request->quantity;
                $product = Product::whereKey($product->getKey())->lockForUpdate()->firstOrFail();

                if ($product->stock < $quantity || $product->stock <= 0) {
                    throw new \RuntimeException('Stok produk tidak mencukupi atau sudah habis. Stok tersedia: ' . $product->stock);
                }

                $unitPrice = (int) round((float) $product->price);
                $totalPrice = $unitPrice * $quantity;
                $product->stock = $product->stock - $quantity;
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

                $transaction = app(MidtransService::class)->createSnapTransaction(
                    $paymentReference,
                    $totalPrice,
                    $user,
                    [[
                        'id' => $product->public_id,
                        'price' => $unitPrice,
                        'quantity' => $quantity,
                        'name' => substr($product->name, 0, 50),
                    ]]
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

        return Inertia::location($transaction['redirect_url']);
    }

    public function userOrders()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
        }

        $orders = Order::with(['product', 'auction', 'store'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $this->syncPendingMidtransPayments($orders);

        $orders = Order::with(['product', 'auction', 'store'])
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

        return Inertia::location($transaction['redirect_url']);
    }

    public function showInvoice($id)
    {
        $user = Auth::user();

        $order = Order::with(['product', 'auction', 'store', 'user'])
            ->where('public_id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return Inertia::render('invoice', compact('order'));
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

                if (in_array($paymentStatus, ['cancelled', 'denied', 'expired'], true)) {
                    $order->restoreReservedStock();
                }
            }
        }
    }
}
