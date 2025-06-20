<?php

use Illuminate\Support\Facades\Route;

Route::get('/index', function () {
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
        'heroText' => 'Need Something? Contact Us!'
        // tanpa 'showSearch', maka search tidak tampil
    ]);
});
