@extends('layouts.app')

@section('title', 'Detail Toko')

@section('heroText')
🛍️ Toko: {{ $store->user->first_name }} {{ $store->user->last_name }}
@endsection

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">

    <div class="mb-6">
        <h2 class="text-2xl font-bold">Toko: {{ $store->name }}</h2>
        <p class="text-gray-600">Pemilik: {{ $store->user->first_name }} {{ $store->user->last_name }}</p>
    </div>

    <div class="grid md:grid-cols-3 gap-6">
        @forelse($store->products as $product)
        <div class="bg-white p-4 rounded-xl shadow-md">
            <img src="{{ asset($product->image) }}" class="w-full h-48 object-cover rounded mb-3" alt="Produk">
            <h3 class="text-lg font-semibold">{{ $product->name }}</h3>
            <p class="text-sm text-gray-600 mb-1">Kategori: {{ $product->category }}</p>
            <p class="text-gray-800 font-bold mb-1">Rp{{ number_format($product->price, 0, ',', '.') }}</p>
            <p class="text-sm text-gray-600 mb-1">Stok: {{ $product->stock }}</p>
            <p class="text-sm text-gray-700 mb-2">{{ Str::limit($product->description, 100) }}</p>

            @if($product->stock > 0)
            <a href="{{ route('checkout.show', $product->id) }}"
                class="block text-center bg-[#53685B] hover:bg-[#3c4a3e] text-white px-4 py-2 rounded-md font-semibold">
                Pesan Sekarang
            </a>
            @else
            <div class="text-center text-red-500 font-semibold">Stok Habis</div>
            @endif
        </div>
        @empty
        <p>Toko ini belum memiliki produk.</p>
        @endforelse
    </div>
</div>
@endsection

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const userButton = document.getElementById('userButton');
        const dropdownMenu = document.getElementById('dropdownMenu');

        if (userButton && dropdownMenu) {
            userButton.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownMenu.classList.toggle('hidden');
            });

            document.addEventListener('click', function(e) {
                if (!dropdownMenu.contains(e.target) && !userButton.contains(e.target)) {
                    dropdownMenu.classList.add('hidden');
                }
            });
        }
    });
</script>
