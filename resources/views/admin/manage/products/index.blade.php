@extends('layouts.app')

@section('heroText', 'Kelola Data Produk')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-8">
    @if(session('success'))
    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-lg mb-6 shadow-sm">
        <p class="font-semibold">✓ {{ session('success') }}</p>
    </div>
    @endif

    <div class="bg-white rounded-2xl shadow-md shadow-[#53685B]/20 p-6">
        <h2 class="text-2xl font-bold mb-6 text-[#53685B]">Daftar Produk</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border border-gray-200">
            <thead class="bg-[#53685B] text-white">
                <tr>
                    <th class="px-4 py-3 text-left">ID</th>
                    <th class="px-4 py-3 text-left">Gambar</th>
                    <th class="px-4 py-3 text-left">Nama Produk</th>
                    <th class="px-4 py-3 text-left">Toko</th>
                    <th class="px-4 py-3 text-left">Harga</th>
                    <th class="px-4 py-3 text-left">Stok</th>
                    <th class="px-4 py-3 text-left">Kategori</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                <tr class="border-t hover:bg-gray-50 transition">
                    <td class="px-4 py-3">{{ $product->id }}</td>
                    <td class="px-4 py-3">
                        @if($product->image)
                        <img src="{{ asset($product->image) }}" alt="{{ $product->name }}" class="w-16 h-16 object-cover rounded-lg shadow-sm">
                        @else
                        <span class="text-gray-400 text-xs">🖼️ No Image</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 font-semibold">{{ $product->name }}</td>
                    <td class="px-4 py-3">{{ $product->store->store_name ?? '-' }}</td>
                    <td class="px-4 py-3 text-[#53685B] font-semibold">Rp{{ number_format($product->price, 0, ',', '.') }}</td>
                    <td class="px-4 py-3">{{ $product->stock }}</td>
                    <td class="px-4 py-3">
                        <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded-full text-xs">{{ $product->category }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex gap-2 justify-center">
                            <a href="{{ route('admin.products.edit', $product->id) }}" 
                                class="bg-[#53685B] hover:bg-[#3c4a3e] text-white px-4 py-2 rounded-lg text-xs font-semibold transition shadow-sm hover:shadow-md">
                                ✏️ Edit
                            </a>
                            <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST" 
                                onsubmit="return confirm('Yakin ingin menghapus produk ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                    class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-xs font-semibold transition shadow-sm hover:shadow-md">
                                    🗑️ Hapus
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                        <p class="text-lg">📦 Tidak ada data produk</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
