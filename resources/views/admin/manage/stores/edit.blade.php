@extends('layouts.app')

@section('heroText', 'Edit Data Toko')

@section('content')
<div class="max-w-3xl mx-auto px-6 py-8">
    <div class="bg-white p-8 rounded-2xl shadow-md shadow-[#53685B]/20">
        <h2 class="text-3xl font-bold mb-6 text-[#53685B]">🏪 Edit Toko</h2>

        @if(session('error'))
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded-lg mb-4">
            <p class="font-semibold">{{ session('error') }}</p>
        </div>
        @endif

        <form action="{{ route('admin.stores.update', $store->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Pemilik Toko</label>
                <input type="text" value="{{ $store->user->first_name }} {{ $store->user->last_name }}" 
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 bg-gray-100" 
                    readonly>
            </div>

            <div class="mb-4">
                <label for="store_name" class="block text-sm font-semibold mb-2">Nama Toko</label>
                <input type="text" name="store_name" id="store_name" 
                    value="{{ old('store_name', $store->store_name) }}"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('store_name') border-red-500 @enderror" 
                    required>
                @error('store_name')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="category" class="block text-sm font-semibold mb-2">Kategori</label>
                <input type="text" name="category" id="category" 
                    value="{{ old('category', $store->category) }}"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('category') border-red-500 @enderror" 
                    required>
                @error('category')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="location" class="block text-sm font-semibold mb-2">Lokasi</label>
                <input type="text" name="location" id="location" 
                    value="{{ old('location', $store->location) }}"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('location') border-red-500 @enderror">
                @error('location')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="description" class="block text-sm font-semibold mb-2">Deskripsi</label>
                <textarea name="description" id="description" rows="4"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror">{{ old('description', $store->description) }}</textarea>
                @error('description')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3">
                <button type="submit" 
                    class="bg-[#53685B] hover:bg-[#3c4a3e] text-white px-8 py-3 rounded-lg font-semibold transition shadow-md hover:shadow-lg">
                    ✓ Simpan Perubahan
                </button>
                <a href="{{ route('admin.stores.index') }}" 
                    class="bg-gray-500 hover:bg-gray-600 text-white px-8 py-3 rounded-lg font-semibold transition shadow-md hover:shadow-lg">
                    ← Batal
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
