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
/*
|--------------------------------------------------------------------------
| Public Routes (Tanpa Login)
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    $products = Product::latest()->take(6)->get(); // Ambil 6 produk terbaru dari database
    return view('index', [
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
    return redirect('/');
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

    // Produk - CRUD
    Route::get('/seller/products/create', fn() => view('seller.add_product'))->name('products.create');
    Route::post('/seller/products', [ProductController::class, 'store'])->name('products.store');
    Route::put('/seller/products/{id}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/seller/products/{id}', [ProductController::class, 'destroy'])->name('products.destroy');

    // Order Status
    Route::patch('/seller/orders/{id}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::get('/order', [\App\Http\Controllers\OrderController::class, 'userOrders'])->middleware('auth')->name('order');


    Route::get('/checkout/{id}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/checkout/{product}', [OrderController::class, 'showCheckout'])->name('checkout.show');
    Route::post('/checkout/{product}', [OrderController::class, 'processCheckout'])->name('checkout.process');

    Route::delete('/order/{id}', [\App\Http\Controllers\OrderController::class, 'cancelOrder'])->middleware('auth')->name('order.cancel');

});

