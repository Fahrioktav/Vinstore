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
use App\Models\Order;
use App\Models\Store;
use Inertia\Inertia;

Route::prefix('inertia')->name('inertia.')->group(function () {
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
        
        $products = Product::latest()->take(6)->get();
        $categories = \App\Models\Category::all();
        return Inertia::render('home', [
            'heroText' => 'Males Ke Pasar Barang Antik? Pesan VINSTORE Aja!',
            'showSearch' => true,
            'products' => $products,
            'categories' => $categories,
        ]);
    })->name('home');

    Route::get('/toko', function () {
        $stores = Store::latest()->get(); // Ambil semua toko

        return Inertia::render('toko', [
            'stores' => $stores,
            'heroText' => 'Males Ke Pasar Barang Antik? Pesan VINSTORE Aja!',
            // 'showSearch' => true
        ]);
    });

    Route::get('/order', function () {
        $orders = Order::where('user_id', Auth::user()->id)->latest()->get();
        return Inertia::render('order', [
            'orders' => $orders,
            'heroText' => 'Udah Nyampe Mana Nih Pesananmu?'
        ]);
    });

    Route::get('/contact', fn() => Inertia::render('contact', [
        'heroText' => 'Butuh Sesuatu? Hubungi Kami!'
    ]));

    Route::get('/products', function () {
        $paginatedProducts = Product::latest()->paginate(12);
        return Inertia::render('products/index', compact('paginatedProducts'));
    });

    Route::get('/register', function () {
        if (Auth::user()) {
            return redirect()->route('inertia.home');
        }

        return Inertia::render('auth/register', [
            'heroText' => 'Halo!, Selamat Datang di VINSTORE'
        ]);
    });

    Route::get('/login', function () {
        if (Auth::user()) {
            return redirect()->route('inertia.home');
        }

        return Inertia::render('auth/login', [
            'heroText' => 'Selamat Datang Kembali!'
        ]);
    });

    Route::middleware(['auth'])->group(function () {
        // Profil
        Route::get('/profile', function () {
            $user = Auth::user();
            $sessions = [
                'error' => session('error'),
                'success' => session('success'),
            ];
            return Inertia::render('profile/edit', compact('user', 'sessions'));
        })->name('profile.edit');
        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
});

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
    return view('home', [
        'heroText' => 'Males Ke Pasar Barang Antik? Pesan VINSTORE Aja!',
        'showSearch' => true,
        'products' => $products
    ]);
});

Route::get('/items', fn() => view('items', [
    'heroText' => 'Temukan Barang Favoritmu!',
    'showSearch' => true
]));

Route::get('/toko', [StoreController::class, 'index'])->name('toko.index');

Route::get('/order', fn() => view('order', [
    'heroText' => 'Udah Nyampe Mana Nih Pesananmu?'
]))->name('order');

Route::get('/contact', fn() => view('contact', [
    'heroText' => 'Butuh Sesuatu? Hubungi Kami!'
]));

Route::get('/products', [ProductController::class, 'index'])->name('products.index');

Route::get('/register', fn() => view('register', [
    'heroText' => 'Halo!, Selamat Datang di VINSTORE'
]))->name('register.form');

Route::post('/register', [RegisterController::class, 'store'])->name('register.submit');

Route::get('/login', fn() => view('login', [
    'heroText' => 'Selamat Datang Kembali!'
]))->name('login.form');

Route::post('/login', [LoginController::class, 'login'])->name('login.submit');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/inertia');
})->name('logout');

/*
|--------------------------------------------------------------------------
| Protected Routes (Login Required)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // Profil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Register Toko
    Route::get('/store/register', [StoreController::class, 'showRegisterForm'])->name('store.register');
    Route::post('/store/register', [StoreController::class, 'register'])->name('store.register.submit');

    // Dashboard Seller
    Route::get('/seller/dashboard', [SellerDashboardController::class, 'index'])->name('seller.dashboard');

    // Dashboard Admin
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    // Produk - CRUD
    Route::get('/products/create', fn() => view('seller.add_product'))->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{id}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{id}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('products.destroy');

    // Order Status & Delete
    Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->name('orders.destroy');
    Route::get('/order', [OrderController::class, 'userOrders'])->name('order');
    Route::delete('/order/{id}/cancel', [OrderController::class, 'cancel'])->name('order.cancel');

    // Tambah ke keranjang
    Route::post('/cart/add/{product}', [CartController::class, 'add'])->name('cart.add');

    // Untuk checkout satu produk (menampilkan halaman konfirmasi pembelian)
    Route::get('/checkout/show/{product}', [OrderController::class, 'showCheckout'])->name('checkout.show');


    // ✅ Checkout satu produk langsung dari detail produk
    Route::get('/checkout/product/{product}', [OrderController::class, 'showCheckout'])->name('checkout.product');
    Route::post('/checkout/product/{product}', [OrderController::class, 'processCheckout'])->name('checkout.process');

    // ✅ Checkout dari keranjang (semua item)
    Route::post('/checkout/cart', [OrderController::class, 'checkoutFromCart'])->name('checkout.fromCart');

    Route::delete('/order/{id}', [OrderController::class, 'cancelOrder'])->middleware('auth')->name('order.cancel');

    Route::get('/toko/{store}', [StoreController::class, 'show'])->name('store.show');

    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/add/{product}', [CartController::class, 'add'])->name('cart.add');
    Route::delete('/cart/{cart}', [CartController::class, 'remove'])->name('cart.remove');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
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
