@extends('layouts.app')

@section('heroText', 'Edit Produk')

@section('content')
<div class="max-w-xl mx-auto p-6 bg-white rounded shadow">
    <h2 class="text-xl font-bold mb-4">Edit Produk</h2>

    <form action="{{ route('products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="mb-4">
            <label class="block text-sm font-semibold mb-1">Nama Produk</label>
            <input type="text" name="name" value="{{ old('name', $product->name) }}" class="w-full border p-2 rounded" required>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-semibold mb-1">Stok</label>
            <input type="number" name="stock" value="{{ old('stock', $product->stock) }}" class="w-full border p-2 rounded" required>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-semibold mb-1">Harga</label>
            <input type="number" name="price" value="{{ old('price', $product->price) }}" class="w-full border p-2 rounded" required>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-semibold mb-1">Kategori</label>
            <input type="text" name="category" value="{{ old('category', $product->category) }}" class="w-full border p-2 rounded" required>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-semibold mb-1">Deskripsi</label>
            <textarea name="description" rows="4" class="w-full border p-2 rounded" required>{{ old('description', $product->description) }}</textarea>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-semibold mb-1">Gambar Produk (opsional)</label>
            <input type="file" name="image" class="w-full">
            @if ($product->image)
                <div class="mt-2">
                    <img src="{{ asset($product->image) }}" class="w-24 h-24 object-cover">
                </div>
            @endif
        </div>

        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            Simpan Perubahan
        </button>

        <a href="{{ route('seller.dashboard') }}" class="ml-4 text-gray-600 hover:underline">Kembali</a>
    </form>
</div>
@endsection
