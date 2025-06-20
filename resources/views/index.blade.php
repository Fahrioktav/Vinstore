@extends('layouts.form')

@section('title', 'Home')

@section('heroText')
    Mau Cari Barang Antik? Di VINSTORE Aja!
@endsection

@section('showSearch')
    <span></span>
@endsection

@section('content')
@parent


{{-- Kategori Section --}}
<section class="px-6 md:px-16 mt-20">
    <h2 class="text-3xl font-bold mb-4">Lagi Pengen Beli Apa Nih...</h2>
    <div class="font-poppins font-bold flex gap-7 items-center justify-center space-x-10">
        @foreach (['Kamera', 'MesinTik', 'TasKlasik', 'VasAntik', 'Gramofon', 'KonsolGame', 'PiringanHitam'] as $item)
        <div class="flex flex-col items-center min-w-[100px]">
            <img src="/assets/icons/{{ Str::slug($item) }}.png" class="w-20 h-20 object-cover rounded-full">
            <span class="mt-2 text-xl font-semibold">{{ $item }}</span>
        </div>
        @endforeach
    </div>
</section>

{{-- Produk Populer --}}
<section class="px-6 md:px-16 mt-10">
    <h2 class="text-2xl font-bold mb-4">Barang Paling Populer</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @php
        $products = [
        ['title' => 'Kamera Analog', 'price' => '175.000', 'img' => 'kameraproducts.jpg', 'desc' => 'Kamera analog klasik dengan desain retro, cocok untuk penggemar fotografi film.'],
        ['title' => 'Gramofon', 'price' => '10.000.000', 'img' => 'gramofon.jpg', 'desc' => 'Pemutar piringan hitam bergaya antik dengan corong logam besar.'],
        ['title' => 'Pemutar Kaset', 'price' => '200.000', 'img' => 'kaset.jpg', 'desc' => 'Walkman vintage dari era 90-an. Cocok bagi pecinta musik kaset.'],
        ['title' => 'Jam Dinding Antik', 'price' => '75.000', 'img' => 'jam.jpg', 'desc' => 'Jam dinding klasik dengan angka romawi.'],
        ['title' => 'Radio', 'price' => '135.000', 'img' => 'radio.jpg', 'desc' => 'Radio lawas bergaya retro.'],
        ['title' => 'Konsol Game SEGA', 'price' => '450.000', 'img' => 'sega.jpg', 'desc' => 'Konsol game klasik SEGA lengkap dengan stik dan cartridge.'],
        ];
        @endphp

        @foreach ($products as $product)
        <div class="bg-white shadow rounded-lg p-4">
            <img src="/assets/products/{{ $product['img'] }}" class="w-full h-48 object-cover rounded-md mb-4 relative">
            <div class="bg-cover z-10 p-2" style="background-image:url('assets/vector.png')">
                <h3 class="font-semibold text-lg">{{ $product['title'] }}</h3>
                <p class="text-sm text-gray-600 mb-2">{{ $product['desc'] }}</p>
                <div class="flex justify-between items-center mt-4">
                    <span class="font-bold">Rp.{{ $product['price'] }}</span>
                    <button class="border border-dashed border-red-400 text-red-600 px-3 py-1 rounded hover:bg-red-100 hover:cursor-pointer transition-all duration-200">Order Now</button>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</section>

{{-- Footer --}}
<footer class="bg-black text-white text-center py-6 mt-12 text-sm">
    VINSTORE.id Copyright 2025, All Rights Reserved. |
    <a href="#" class="underline">Privacy Policy</a> |
    <a href="#" class="underline">Terms</a> |
    <a href="#" class="underline">Pricing</a> |
    <a href="#" class="underline">Do not sell or share my personal info</a>
</footer>
@endsection