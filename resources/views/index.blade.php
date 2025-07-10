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

    @if ($products->count() > 0)
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach ($products as $product)
        <div class="rounded-lg p-2">
            <img src="{{ asset($product->image) }}" class="w-full h-48 object-contain rounded-md mb-4 relative">
            <div class="bg-cover z-10 p-2" style="background-image:url('assets/vector.png')">
                <h3 class="font-semibold text-lg">{{ $product->name }}</h3>
                <p class="text-sm text-gray-600 mb-2">{{ $product->description }}</p>
                <div class="flex justify-between items-center mt-4">
                    <span class="font-bold">Rp.{{ number_format($product->price, 0, ',', '.') }}</span>

                    @auth
                    <form action="{{ route('checkout.show', $product->id) }}" method="GET">
                        <button type="submit"
                            class="border border-dashed border-red-400 text-red-600 px-3 py-1 rounded hover:bg-red-100 transition-all duration-200">
                            Order Now
                        </button>
                    </form>
                    @else
                    <a href="{{ route('login.form') }}"
                        class="border border-dashed border-red-400 text-red-600 px-3 py-1 rounded hover:bg-red-100 transition-all duration-200">
                        Order Now
                    </a>
                    @endauth

                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <p class="text-gray-600">Belum ada produk yang tersedia.</p>
    @endif
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
