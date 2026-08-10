<?php

use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminContactController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminRevenueController;
use App\Http\Controllers\Admin\AdminSellerController;
use App\Http\Controllers\Admin\AdminStoreController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\MidtransNotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PayoutRequestController;
use App\Http\Controllers\PriceGuessController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RefundRequestController;
use App\Http\Controllers\SellerDashboardController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TradeInController;
use App\Http\Controllers\TradeInPaymentNotificationController;
use App\Http\Controllers\ValidatorController;
use App\Http\Controllers\WithdrawalRequestController;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Routes (Tanpa Login)
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    // Redirect berdasarkan role jika sudah login
    if (Auth::check()) {
        $user = Auth::user();
        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        } elseif ($user->role === 'seller') {
            return redirect()->route('seller.dashboard');
        } elseif ($user->role === 'validator') {
            return redirect()->route('validator.dashboard');
        }
        // User biasa tetap ke home
    }

    // Sembunyikan harga asli produk tebak harga yang masih dalam periode/prioritas.
    app(\App\Services\PriceGuessService::class)->sync();

    // Ambil produk yang approved dan stok masih tersedia (stok > 0)
    $products = Product::approved()
        ->where('stock', '>', 0)
        ->latest()
        ->take(6)
        ->get();
    $products->each(fn (Product $product) => $product->maskRealPriceFor(Auth::user()));

    $categories = Category::latest()->take(12)->get();

    return Inertia::render('home', [
        'heroText' => 'Males Ke Pasar Barang Antik? Pesan VINSTORE Aja!',
        'showSearch' => true,
        'products' => $products,
        'categories' => $categories,
    ]);
});

Route::get('/toko', [StoreController::class, 'index'])->name('toko.index');
Route::get('/toko/{store}', [StoreController::class, 'show'])->name('toko.show');

Route::get('/contact', [ContactController::class, 'index'])->name('contact.index');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
Route::get('/auctions', [AuctionController::class, 'index'])->name('auctions.index');
Route::get('/auctions/{auction}', [AuctionController::class, 'show'])->name('auctions.show');

Route::post('/midtrans/notification', MidtransNotificationController::class)->name('midtrans.notification');
// URI-nya sengaja tetap "barter" walau fiturnya sudah berganti nama menjadi
// tukar tambah — alamat inilah yang terdaftar di dashboard Midtrans. Nama
// route-nya boleh ikut berubah karena tidak pernah keluar dari aplikasi.
Route::post('/midtrans/barter/notification', TradeInPaymentNotificationController::class)->name('midtrans.trade-in.notification');

/*
|--------------------------------------------------------------------------
| Guest Only Routes (Yang Udah Login Dilarang Masuk)
|--------------------------------------------------------------------------
*/

Route::middleware(['role:guestOnly'])->group(function () {
    // Register
    Route::get('/register', fn () => Inertia::render('auth/register', [
        'heroText' => 'Halo!, Selamat Datang di VINSTORE',
    ]))->name('register.form');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.submit');

    // Login
    Route::get('/login', fn () => Inertia::render('auth/login', [
        'heroText' => 'Selamat Datang Kembali!',
    ]))->name('login.form');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');

    // Google OAuth
    Route::get('/auth/google', [SocialAuthController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

    // Forgot Password
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

    // Reset Password
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Login Required)
|--------------------------------------------------------------------------
*/

// All authenticated user can access
Route::middleware(['auth'])->group(function () {
    // Profil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Logout
    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    })->name('logout');
});

// Only role = user can access
Route::middleware(['auth', 'role:user'])->group(function () {
    // Register Toko
    Route::get('/store/register', [StoreController::class, 'showRegisterForm'])->name('store.register');
    Route::post('/store/register', [StoreController::class, 'register'])->name('store.register.submit');
});

// Chat bantuan (user/seller/validator -> admin) realtime via WebSocket
Route::middleware(['auth', 'role:user,seller,validator'])->group(function () {
    Route::get('/bantuan', [SupportController::class, 'userChat'])->name('support.chat');
    Route::post('/bantuan/messages', [SupportController::class, 'userSend'])->name('support.send');
});

// Both role user and seller can access
Route::middleware(['auth', 'role:user,seller'])->group(function () {
    // Riwayat pesan contact untuk user/seller
    Route::get('/my-contacts', [ContactController::class, 'userContacts'])->name('user.contacts');

    // Chat bantuan dipindah ke grup khusus di bawah (user/seller/validator).

    // Lihat, tambahkan, atau hapus barang dari keranjang
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/add/{product}', [CartController::class, 'add'])->name('cart.add');
    Route::delete('/cart/{cart}', [CartController::class, 'remove'])->name('cart.remove');

    // Untuk checkout satu produk (menampilkan halaman konfirmasi pembelian)
    Route::get('/checkout/show/{product}', [OrderController::class, 'showCheckout'])->name('checkout.show');

    // ✅ Checkout satu produk langsung dari detail produk
    Route::get('/checkout/product/{product}', [OrderController::class, 'showCheckout'])->name('checkout.product');
    Route::post('/checkout/product/{product}', [OrderController::class, 'processCheckout'])->name('checkout.process');

    // ✅ Checkout dari keranjang (semua item)
    Route::post('/checkout/cart', [OrderController::class, 'checkoutFromCart'])->name('checkout.fromCart');

    // User order
    Route::get('/order', [OrderController::class, 'userOrders'])->name('order');
    Route::delete('/order/{id}', [OrderController::class, 'cancelOrder'])->name('order.cancel');
    // Konfirmasi penerimaan oleh pembeli. Ini yang melepas dana ke saldo seller.
    Route::post('/order/{id}/confirm', [OrderController::class, 'confirmReceipt'])->name('order.confirm');
    Route::post('/order/{order}/refund', [RefundRequestController::class, 'store'])->name('refunds.store');

    // Invoice
    Route::get('/invoice/{id}', [OrderController::class, 'showInvoice'])->name('invoice.show');

    // Lelang
    Route::post('/auctions/{auction}/bid', [AuctionController::class, 'bid'])->name('auctions.bid');
    Route::post('/auctions/{auction}/pay', [AuctionController::class, 'pay'])->name('auctions.pay');

    // Tebak Harga - kirim satu tebakan (final, tidak dapat diubah)
    Route::post('/products/{product}/guess', [PriceGuessController::class, 'store'])->name('products.guess');
});

// Only role = seller can access
Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    // Dashboard Seller
    Route::get('/dashboard', [SellerDashboardController::class, 'index'])->name('dashboard');

    // Edit Toko
    Route::get('/store/edit', [StoreController::class, 'edit'])->name('store.edit');
    Route::post('/store/update', [StoreController::class, 'update'])->name('store.update');

    // Produk - CRUD
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{id}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{id}', [ProductController::class, 'update'])->name('products.update');
    Route::patch('/products/{id}', [ProductController::class, 'updateStock'])->name('products.updateStock');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('products.destroy');

    // Tukar tambah antar seller (seller-to-seller)
    Route::get('/tukar-tambah', [TradeInController::class, 'index'])->name('trade-in.index');
    Route::post('/tukar-tambah/{product}', [TradeInController::class, 'store'])->name('trade-in.store');
    Route::post('/tukar-tambah/{tradeIn}/accept', [TradeInController::class, 'accept'])->name('trade-in.accept');
    Route::post('/tukar-tambah/{tradeIn}/reject', [TradeInController::class, 'reject'])->name('trade-in.reject');
    Route::post('/tukar-tambah/{tradeIn}/cancel', [TradeInController::class, 'cancel'])->name('trade-in.cancel');
    Route::get('/tukar-tambah/{tradeIn}/pay', [TradeInController::class, 'pay'])->name('trade-in.pay');
    Route::get('/tukar-tambah/{tradeIn}/payment-status', [TradeInController::class, 'checkPaymentStatus'])->name('trade-in.payment.status');
    // Pengiriman dua arah: masing-masing seller mengisi resi lalu mengonfirmasi
    // penerimaan. Kepemilikan berpindah setelah keduanya saling menerima.
    Route::post('/tukar-tambah/{tradeIn}/ship', [TradeInController::class, 'ship'])->name('trade-in.ship');
    Route::post('/tukar-tambah/{tradeIn}/receive', [TradeInController::class, 'confirmReceipt'])->name('trade-in.receive');

    // Selisih uang tukar tambah: responder mencairkan setelah tukar tambah selesai,
    // requester meminta kembali bila tukar tambahnya gagal.
    Route::post('/tukar-tambah/{tradeIn}/payout', [PayoutRequestController::class, 'storeForTradeIn'])->name('trade-in.payout');
    Route::post('/tukar-tambah/{tradeIn}/refund', [RefundRequestController::class, 'storeForTradeIn'])->name('trade-in.refund');
    Route::post('/tukar-tambah/{tradeIn}/report', [RefundRequestController::class, 'reportStalledTradeIn'])->name('trade-in.report');

    // Lelang seller
    Route::get('/auctions/create', [AuctionController::class, 'create'])->name('auctions.create');
    Route::post('/auctions', [AuctionController::class, 'store'])->name('auctions.store');
    Route::get('/auctions/{auction}/edit', [AuctionController::class, 'edit'])->name('auctions.edit');
    Route::put('/auctions/{auction}', [AuctionController::class, 'update'])->name('auctions.update');
    // Tarik kembali pengajuan agar bisa diperbaiki lalu diajukan ulang.
    Route::post('/auctions/{auction}/withdraw', [AuctionController::class, 'withdrawSubmission'])->name('auctions.withdraw');
    Route::delete('/auctions/{auction}', [AuctionController::class, 'destroy'])->name('auctions.destroy');
    Route::get('/auctions/{auction}/relist', [AuctionController::class, 'relistForm'])->name('auctions.relist.form');
    Route::post('/auctions/{auction}/relist', [AuctionController::class, 'relist'])->name('auctions.relist');

    // Pencairan saldo seller
    Route::post('/withdrawals', [WithdrawalRequestController::class, 'store'])->name('withdrawals.store');

    // Pengajuan pencairan dana per pesanan (Delivered/Completed).
    Route::post('/orders/{order}/payout', [PayoutRequestController::class, 'store'])->name('orders.payout');

    // Order Status & Delete
    Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->name('orders.destroy');
});

// Only role = validator can access
Route::middleware(['auth', 'role:validator'])->prefix('validator')->name('validator.')->group(function () {
    // Dashboard Validator Barang Antik
    Route::get('/dashboard', [ValidatorController::class, 'index'])->name('dashboard');

    // Detail produk yang diajukan seller
    Route::get('/products/{id}', [ValidatorController::class, 'show'])->name('products.show');

    // Validasi produk
    Route::post('/products/{id}/approve', [ValidatorController::class, 'approve'])->name('products.approve');
    Route::post('/products/{id}/reject', [ValidatorController::class, 'reject'])->name('products.reject');

    // Validasi lelang
    Route::post('/auctions/{id}/approve', [ValidatorController::class, 'approveAuction'])->name('auctions.approve');
    Route::post('/auctions/{id}/reject', [ValidatorController::class, 'rejectAuction'])->name('auctions.reject');
});

// Only role = admin can access
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard Admin
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Kelola Users
    Route::resource('users', AdminUserController::class)->only(['index', 'edit', 'update', 'destroy']);

    // Kelola Sellers
    Route::resource('sellers', AdminSellerController::class)->only(['index', 'edit', 'update', 'destroy']);

    // Kelola Toko (Stores)
    Route::resource('stores', AdminStoreController::class)->only(['index', 'edit', 'update', 'destroy']);

    // Kelola Produk
    Route::get('products/pending', [AdminProductController::class, 'pending'])->name('products.pending');
    Route::resource('products', AdminProductController::class)->only(['index', 'edit', 'update', 'destroy']);
    Route::post('products/{id}/approve', [AdminProductController::class, 'approve'])->name('products.approve');
    Route::post('products/{id}/reject', [AdminProductController::class, 'reject'])->name('products.reject');

    Route::resource('orders', AdminOrderController::class)->only(['index', 'edit', 'update', 'destroy']);

    // Kelola Lelang
    Route::get('auctions', [AuctionController::class, 'adminIndex'])->name('auctions.index');
    Route::post('auctions/{auction}/approve', [AuctionController::class, 'approve'])->name('auctions.approve');
    Route::post('auctions/{auction}/reject', [AuctionController::class, 'reject'])->name('auctions.reject');

    // Kelola Refund
    Route::get('refunds', [RefundRequestController::class, 'adminIndex'])->name('refunds.index');
    Route::post('refunds/{refund}/approve', [RefundRequestController::class, 'approve'])->name('refunds.approve');
    Route::post('refunds/{refund}/reject', [RefundRequestController::class, 'reject'])->name('refunds.reject');

    // Dompet admin: pendapatan marketplace dari biaya layanan
    Route::get('pendapatan', [AdminRevenueController::class, 'index'])->name('revenues.index');

    // Kelola Pencairan Dana per Pesanan
    Route::get('payouts', [PayoutRequestController::class, 'adminIndex'])->name('payouts.index');
    Route::post('payouts/{payout}/approve', [PayoutRequestController::class, 'approve'])->name('payouts.approve');
    Route::post('payouts/{payout}/reject', [PayoutRequestController::class, 'reject'])->name('payouts.reject');

    // Kelola Pencairan Saldo (arsip: alur lama sebelum pencairan per pesanan)
    Route::get('withdrawals', [WithdrawalRequestController::class, 'adminIndex'])->name('withdrawals.index');
    Route::post('withdrawals/{withdrawal}/approve', [WithdrawalRequestController::class, 'approve'])->name('withdrawals.approve');
    Route::post('withdrawals/{withdrawal}/reject', [WithdrawalRequestController::class, 'reject'])->name('withdrawals.reject');

    Route::resource('categories', AdminCategoryController::class)->except(['show']);

    // Kelola Pesan Contact
    Route::get('contacts', [AdminContactController::class, 'index'])->name('contacts.index');
    Route::post('contacts/{id}/reply', [AdminContactController::class, 'reply'])->name('contacts.reply');
    Route::post('contacts/{id}/status', [AdminContactController::class, 'updateStatus'])->name('contacts.status');
    Route::delete('contacts/{id}', [AdminContactController::class, 'destroy'])->name('contacts.destroy');

    // Chat bantuan (admin) realtime via WebSocket
    Route::get('bantuan', [SupportController::class, 'adminIndex'])->name('support.index');
    Route::get('bantuan/{userPublicId}', [SupportController::class, 'adminShow'])->name('support.show');
    Route::post('bantuan/{userPublicId}/messages', [SupportController::class, 'adminSend'])->name('support.send');
});
