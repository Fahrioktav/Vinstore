@extends('layouts.app')

@section('title', 'Home')

@section('content')
<!-- HERO SECTION -->
<section class="relative bg-[#2F3E46] text-white min-h-screen flex items-center pt-24 -mt-[72px] font-poppins">
    <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center px-6 md:px-10 w-full">
        {{-- LEFT TEXT --}}
        <div class="md:w-1/2">
            <h1 class="text-4xl md:text-6xl font-extrabold leading-tight">
                Temukan Barang Antik Impianmu di
                <span class="text-[#E9E19E]">VINSTORE!</span>
            </h1>
            <p class="mt-6 text-lg text-gray-200 font-poppins">
                Jelajahi koleksi barang antik eksklusif — dari kamera klasik hingga konsol legendaris.
            </p>
            <a href="{{ route('toko.index') }}"
               class="inline-block mt-8 bg-[#B77C4C] hover:bg-[#a16c3e] text-white font-semibold px-8 py-3 rounded-lg transition">
                Jelajahi Sekarang
            </a>
        </div>
        {{-- RIGHT IMAGE --}}
        <div class="md:w-1/2 flex flex-col items-center mt-10 md:mt-0">
            <img src="{{ asset('assets/hero-antique.jpg') }}" alt="Vintage Collection" class="w-[400px] object-contain">
            <p class="mt-4 text-lg font-semibold">Vintage Collection</p>
        </div>
    </div>
</section>

{{-- KATEGORI SECTION --}}
<section class="px-6 md:px-16 mt-20">
    <h2 class="text-3xl font-bold mb-6 text-[#3E2723]">Kategori Populer</h2>
    <div class="flex flex-wrap justify-center gap-8">
        @foreach (['Kamera', 'MesinTik', 'TasKlasik', 'VasAntik', 'Gramofon', 'KonsolGame', 'PiringanHitam'] as $item)
        <div class="group flex flex-col items-center bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-5 w-32 md:w-36">
            <img src="/assets/icons/{{ Str::slug($item) }}.png" class="w-16 h-16 mb-3 group-hover:scale-110 transition-transform">
            <span class="text-center font-semibold text-gray-800 group-hover:text-[#B77C4C]">{{ $item }}</span>
        </div>
        @endforeach
    </div>
</section>

{{-- PRODUK POPULER --}}
<section id="products" class="px-6 md:px-16 mt-20">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-[#3E2723]">Barang Paling Populer</h2>
        <a href="{{ route('products.index') }}" class="text-[#B77C4C] hover:underline">Lihat Semua</a>
    </div>

    @if ($products->count() > 0)
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
        @foreach ($products as $product)
        <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition p-4 flex flex-col">
            <div class="relative">
                <img src="{{ asset($product->image) }}" class="w-full h-48 object-contain rounded-md mb-4">
                <span class="absolute top-2 left-2 bg-[#B77C4C] text-white text-xs px-2 py-1 rounded-md shadow">Antik</span>
            </div>

            <div class="flex-grow">
                <h3 class="font-semibold text-lg text-[#3E2723] mb-1">{{ $product->name }}</h3>
                <p class="text-sm text-gray-600 mb-3 line-clamp-3">{{ $product->description }}</p>
            </div>

            <div class="flex justify-between items-center mt-auto pt-3 border-t border-gray-200">
                <span class="font-bold text-[#B77C4C]">Rp{{ number_format($product->price, 0, ',', '.') }}</span>
                @auth
                <form action="{{ route('checkout.show', $product->id) }}" method="GET">
                    <button type="submit"
                        class="bg-[#B77C4C] hover:bg-[#a0683d] text-white px-3 py-1 rounded-md text-sm font-medium transition">
                        Order Now
                    </button>
                </form>
                @else
                <a href="{{ route('login.form') }}"
                    class="bg-[#B77C4C] hover:bg-[#a0683d] text-white px-3 py-1 rounded-md text-sm font-medium transition">
                    Order Now
                </a>
                @endauth
            </div>
        </div>
        @endforeach
    </div>
    @else
    <p class="text-gray-600 mt-6">Belum ada produk yang tersedia.</p>
    @endif
</section>

{{-- REKOMENDASI SECTION --}}
<section class="bg-[#fdf8f3] mt-20 py-12">
    <div class="max-w-6xl mx-auto px-6 md:px-16 text-center">
        <h2 class="text-3xl font-bold text-[#3E2723] mb-6">Rekomendasi Untukmu</h2>
        <p class="text-gray-700 mb-10">Berdasarkan minatmu, kami pilihkan produk antik yang mungkin kamu suka.</p>

        <div class="flex flex-wrap justify-center gap-6">
            @foreach ($products->take(3) as $recommendation)
            <div class="bg-white rounded-xl shadow-md p-4 w-72 hover:shadow-lg transition">
                <img src="{{ asset($recommendation->image) }}" class="w-full h-40 object-contain rounded-md mb-3">
                <h3 class="font-semibold text-lg text-[#3E2723] mb-1">{{ $recommendation->name }}</h3>
                <span class="font-bold text-[#B77C4C]">Rp{{ number_format($recommendation->price, 0, ',', '.') }}</span>
            </div>
            @endforeach
        </div>
    </div>
</section>

@endsection