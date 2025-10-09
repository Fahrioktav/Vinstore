@extends('layouts.form')

@section('title', 'Toko - VINSTORE')

@section('heroText')
    Jelajahi Toko Barang Antik Favoritmu 🕰️
@endsection

@section('showSearch')
@if ($showSearch)
    {{-- FORM SEARCH --}}
    <form action="{{ route('toko.index') }}" method="GET"
        class="flex items-center bg-white/90 backdrop-blur-md rounded-full shadow-lg w-full max-w-2xl mx-auto py-3 px-5 mt-8 border border-gray-200 transition focus-within:ring-2 focus-within:ring-[#B77C4C]">
        <input type="text" name="q" value="{{ request('q') }}"
            class="flex-grow bg-transparent outline-none text-gray-800 placeholder-gray-500"
            placeholder="Cari toko antik favoritmu...">
        <button type="submit"
            class="bg-[#B77C4C] hover:bg-[#9e6538] text-white font-semibold px-6 py-2 rounded-full transition-all duration-200">
            🔍 Cari
        </button>
    </form>
@endif
@endsection

@section('content')
@parent

<section class="min-h-screen w-full bg-gradient-to-br from-[#2F3E46] via-[#354F52] to-[#B77C4C] px-6 md:px-12 py-12 relative overflow-hidden">

    {{-- Judul --}}
    <h2 class="text-3xl md:text-4xl font-bold text-center text-[#E9E19E] mb-10 tracking-wide pt-32">
        Toko Paling Populer
    </h2>

    {{-- GRID TOKO --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 max-w-7xl mx-auto">

        @foreach ($stores as $store)
        <div class="bg-white/90 backdrop-blur-md rounded-2xl shadow-lg border border-gray-200 overflow-hidden hover:scale-105 transition-transform duration-300">

            {{-- GAMBAR TOKO --}}
            <img src="{{ asset('assets/store-placeholder.jpg') }}" alt="{{ $store->store_name }}"
                class="w-full h-48 object-cover">

            {{-- DETAIL TOKO --}}
            <div class="p-6">
                <h3 class="text-xl font-semibold text-[#2F3E46] capitalize mb-2">{{ $store->store_name }}</h3>
                <p class="text-gray-600 text-sm mb-4 flex items-center gap-2">
                    📍 {{ $store->location }}
                </p>
                <a href="{{ route('store.show', $store->id) }}"
                    class="block text-center bg-[#B77C4C] hover:bg-[#9e6538] text-white font-semibold py-2 rounded-lg transition-all duration-200">
                    Lihat Toko
                </a>
            </div>

        </div>
        @endforeach

        @if($stores->isEmpty())
        <div class="col-span-full text-center text-white text-lg italic">
            Tidak ada toko yang ditemukan 🕯️
        </div>
        @endif

    </div>

    {{-- DEKORASI LATAR --}}
    <div class="absolute top-0 left-0 w-64 h-64 bg-[#E9E19E]/20 rounded-full blur-3xl -z-10"></div>
    <div class="absolute bottom-0 right-0 w-96 h-96 bg-[#B77C4C]/20 rounded-full blur-3xl -z-10"></div>
</section>
@endsection
