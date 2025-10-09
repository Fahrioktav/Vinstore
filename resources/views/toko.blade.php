@extends('layouts.form')

@section('title', 'Home')

@section('heroText')
{{ $heroText }}
@endsection

@section('showSearch')
@if ($showSearch)
<form action="{{ route('toko.index') }}" method="GET" class="flex items-center bg-white rounded-full shadow-md w-full max-w-xl mx-auto py-2 px-4 mt-6">
    <input type="text" name="q" class="flex-grow px-4 py-2 outline-none text-gray-800" placeholder="Cari Toko favorit mu">
    <button type="submit" class="bg-yellow-300 hover:bg-yellow-400 text-black font-semibold px-4 py-2 rounded-full">
        🔍 Search
    </button>
</form>
@endif
@endsection

@section('content')
@parent

<div class="max-w-6xl mx-auto px-4 py-10">
    <h2 class="text-xl font-bold mb-6">Toko Paling Populer</h2>

    @foreach ($stores as $store)
    <div class="flex items-center justify-between mb-6 border-b pb-4">
        <div class="flex items-center space-x-4">
            <img src="{{ asset('assets/store-placeholder.jpg') }}" alt="{{ $store->store_name }}" class="w-28 h-20 object-cover rounded-md">
            <div>
                <h3 class="font-semibold text-lg capitalize">{{ $store->store_name }}</h3>
                <p class="text-sm text-gray-700">{{ $store->location }}</p>
            </div>
        </div>
        <a href="{{ route('store.show', $store->id) }}"
            class="bg-[#E9E19E] text-black font-bold px-4 py-2 rounded-md hover:bg-yellow-400 transition">
            Lihat Toko
        </a>

    </div>
    @endforeach
</div>
@endsection