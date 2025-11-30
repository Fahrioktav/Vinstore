<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SellerDashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Models\Product;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminSellerController;
use App\Http\Controllers\Admin\AdminStoreController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\CartController;
use App\Models\Category;
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
        }
        // User biasa tetap ke home
    }
    
    $products = Product::latest()->take(6)->get(); // Ambil 6 produk terbaru dari database
    $categories = Category::latest()->take(12)->get(); // Ambil 6 produk terbaru dari database
    return Inertia::render('home', [
        'heroText' => 'Males Ke Pasar Barang Antik? Pesan VINSTORE Aja!',
        'showSearch' => true,
        'products' => $products,
        'categories' => $categories
    ]);
});

Route::get('/toko', [StoreController::class, 'index'])->name('toko.index');
Route::get('/toko/{store}', [StoreController::class, 'show'])->name('toko.show');

Route::get('/contact', fn() => Inertia::render('contact', [
    'heroText' => 'Butuh Sesuatu? Hubungi Kami!'
]));

Route::get('/products', [ProductController::class, 'index'])->name('products.index');

/*
|--------------------------------------------------------------------------
| Guest Only Routes (Yang Udah Login Dilarang Masuk)
|--------------------------------------------------------------------------
*/

Route::middleware(['role:guestOnly'])->group(function () {
    // Register
    Route::get('/register', fn() => Inertia::render('auth/register', [
        'heroText' => 'Halo!, Selamat Datang di VINSTORE'
    ]))->name('register.form');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.submit');

    // Login
    Route::get('/login', fn() => Inertia::render('auth/login', [
        'heroText' => 'Selamat Datang Kembali!'
    ]))->name('login.form');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
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

// Both role user and seller can access
Route::middleware(['auth', 'role:user,seller'])->group(function () {
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
});

// Only role = seller can access
Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    // Dashboard Seller
    Route::get('dashboard', [SellerDashboardController::class, 'index'])->name('dashboard');
    
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
    
    // Order Status & Delete
    Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->name('orders.destroy');
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
    Route::resource('products', AdminProductController::class)->only(['index', 'edit', 'update', 'destroy']);

    Route::resource('orders', AdminOrderController::class)->only(['index', 'edit', 'update', 'destroy']);

    Route::resource('categories', AdminCategoryController::class)->except(['show']);
});
