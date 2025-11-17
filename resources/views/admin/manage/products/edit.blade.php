@extends('layouts.app')

@section('heroText', 'Edit Data Produk')

@section('content')
<div class="max-w-3xl mx-auto px-6 py-8">
    <div class="bg-white p-8 rounded-2xl shadow-md shadow-[#53685B]/20">
        <h2 class="text-3xl font-bold mb-6 text-[#53685B]">📦 Edit Produk</h2>

        @if(session('error'))
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded-lg mb-4">
            <p class="font-semibold">{{ session('error') }}</p>
        </div>
        @endif

        <form action="{{ route('admin.products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Toko</label>
                <input type="text" value="{{ $product->store->store_name }}" 
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 bg-gray-100" 
                    readonly>
            </div>

            <div class="mb-4">
                <label for="name" class="block text-sm font-semibold mb-2">Nama Produk</label>
                <input type="text" name="name" id="name" 
                    value="{{ old('name', $product->name) }}"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror" 
                    required>
                @error('name')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="price" class="block text-sm font-semibold mb-2">Harga</label>
                    <input type="number" name="price" id="price" 
                        value="{{ old('price', $product->price) }}"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('price') border-red-500 @enderror" 
                        required min="0">
                    @error('price')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="stock" class="block text-sm font-semibold mb-2">Stok</label>
                    <input type="number" name="stock" id="stock" 
                        value="{{ old('stock', $product->stock) }}"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('stock') border-red-500 @enderror" 
                        required min="0">
                    @error('stock')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <label for="category" class="block text-sm font-semibold mb-2">Kategori</label>
                <input type="text" name="category" id="category" 
                    value="{{ old('category', $product->category) }}"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('category') border-red-500 @enderror" 
                    required>
                @error('category')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="description" class="block text-sm font-semibold mb-2">Deskripsi</label>
                <textarea name="description" id="description" rows="4"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror">{{ old('description', $product->description) }}</textarea>
                @error('description')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Gambar Saat Ini</label>
                @if($product->image)
                <img src="{{ asset($product->image) }}" alt="{{ $product->name }}" class="w-32 h-32 object-cover rounded mb-2">
                @else
                <p class="text-gray-500 text-sm">Tidak ada gambar</p>
                @endif
            </div>

            <div class="mb-6">
                <label for="image" class="block text-sm font-semibold mb-2">Upload Gambar Baru (Opsional)</label>
                <input type="file" name="image" id="image" accept="image/*"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('image') border-red-500 @enderror">
                @error('image')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <p class="text-xs text-gray-500 mt-1">Format: JPEG, PNG, JPG, GIF. Max: 2MB</p>
            </div>

            <div class="flex gap-3">
                <button type="submit" 
                    class="bg-[#53685B] hover:bg-[#3c4a3e] text-white px-8 py-3 rounded-lg font-semibold transition shadow-md hover:shadow-lg">
                    ✓ Simpan Perubahan
                </button>
                <a href="{{ route('admin.products.index') }}" 
                    class="bg-gray-500 hover:bg-gray-600 text-white px-8 py-3 rounded-lg font-semibold transition shadow-md hover:shadow-lg">
                    ← Batal
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
