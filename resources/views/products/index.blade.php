@extends('layouts.form')

@section('title', 'Semua Produk')

@section('content')
<div class="px-6 md:px-16 py-10">
    <h2 class="text-3xl font-bold text-[#3E2723] mb-6">Semua Produk</h2>

    @if ($products->count() > 0)
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
        @foreach ($products as $product)
        <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition p-4 flex flex-col">
            <img src="{{ asset($product->image) }}" class="w-full h-48 object-contain rounded-md mb-4">
            <h3 class="font-semibold text-lg text-[#3E2723] mb-1">{{ $product->name }}</h3>
            <p class="text-sm text-gray-600 mb-3 line-clamp-3">{{ $product->description }}</p>
            <div class="flex justify-between items-center mt-auto">
                <span class="font-bold text-[#B77C4C]">Rp{{ number_format($product->price, 0, ',', '.') }}</span>
                <form action="{{ route('checkout.show', $product->id) }}" method="GET">
                    <button type="submit" class="bg-[#B77C4C] hover:bg-[#a0683d] text-white px-3 py-1 rounded-md text-sm">
                        Order Now
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-10">
        {{ $products->links() }}
    </div>
    @else
    <p class="text-gray-600">Belum ada produk yang tersedia.</p>
    @endif
</div>
@endsection
