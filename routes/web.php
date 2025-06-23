<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index', [
        'heroText' => 'Males Ke Pasar Barang Antik? Pesan VINSTORE Aja!',
        'showSearch' => true
    ]);
});

Route::get('/items', function () {
    return view('items', [
        'heroText' => 'Temukan Barang Favoritmu!',
        'showSearch' => true
    ]);
});

Route::get('/toko', function () {
    return view('toko', [
        'heroText' => 'Males Ke Pasar Barang Antik? Pesan VINSTORE Aja!',
        'showSearch' => true
    ]);
});

Route::get('/login', function () {
    return view('login', [
        'heroText' => 'Selamat Datang Kembali!'
        // tanpa 'showSearch', maka search tidak tampil
    ]);
});

Route::get('/contact', function () {
    return view('contact', [
        'heroText' => 'Butuh Sesuatu? Hubungi Kami!'
        // tanpa 'showSearch', maka search tidak tampil
    ]);
});

Route::get('/register', function () {
    return view('register', [
        'heroText' => 'Halo!, Selamat Datang di VINSTORE'
    ]);
})->name('register');


Route::get('/order', function () {
    return view('order', [
        'heroText' => 'Udah Nyampe Mana Nih Pesananmu?'
    ]);
})->name('order');



