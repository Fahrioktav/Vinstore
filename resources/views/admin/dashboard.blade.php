@extends('layouts.app')

@section('heroText', 'Halo, Admin')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-8">

    {{-- Statistik --}}
    <h2 class="text-3xl font-bold mt-2 mb-6 text-[#53685B]">📊 Dashboard Admin</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-12 font-poppins">

        {{-- Total User --}}
        <a href="{{ route('admin.users.index') }}"
            class="group relative bg-gradient-to-br from-white to-gray-50 shadow-lg shadow-[#53685B]/10 border border-gray-100 p-6 rounded-2xl hover:shadow-xl hover:scale-105 transition-all duration-300 block">
            <div class="flex items-center gap-4">
                <div class="bg-[#53685B]/10 p-3 rounded-xl group-hover:bg-[#53685B]/20 transition">
                    <img src="{{ asset('assets/1.png') }}" class="w-8 h-8">
                </div>
                <div>
                    <p class="text-3xl font-bold text-[#53685B]">{{ $totalUsers }}</p>
                    <p class="text-sm text-gray-600 font-medium">Total User</p>
                </div>
            </div>
            <div class="absolute top-3 right-4 text-4xl text-[#53685B] opacity-20 group-hover:opacity-40 transition">
                →
            </div>
        </a>

        {{-- Total Seller --}}
        <a href="{{ route('admin.sellers.index') }}"
            class="group relative bg-gradient-to-br from-white to-gray-50 shadow-lg shadow-[#53685B]/10 border border-gray-100 p-6 rounded-2xl hover:shadow-xl hover:scale-105 transition-all duration-300 block">
            <div class="flex items-center gap-4">
                <div class="bg-[#53685B]/10 p-3 rounded-xl group-hover:bg-[#53685B]/20 transition">
                    <img src="{{ asset('assets/2.png') }}" class="w-8 h-8">
                </div>
                <div>
                    <p class="text-3xl font-bold text-[#53685B]">{{ $totalSellers }}</p>
                    <p class="text-sm text-gray-600 font-medium">Total Seller</p>
                </div>
            </div>
            <div class="absolute top-3 right-4 text-4xl text-[#53685B] opacity-20 group-hover:opacity-40 transition">
                →
            </div>
        </a>

        {{-- Total Toko --}}
        <a href="{{ route('admin.stores.index') }}"
            class="group relative bg-gradient-to-br from-white to-gray-50 shadow-lg shadow-[#53685B]/10 border border-gray-100 p-6 rounded-2xl hover:shadow-xl hover:scale-105 transition-all duration-300 block">
            <div class="flex items-center gap-4">
                <div class="bg-[#53685B]/10 p-3 rounded-xl group-hover:bg-[#53685B]/20 transition">
                    <img src="{{ asset('assets/3.png') }}" class="w-8 h-8">
                </div>
                <div>
                    <p class="text-3xl font-bold text-[#53685B]">{{ $totalStores }}</p>
                    <p class="text-sm text-gray-600 font-medium">Total Toko</p>
                </div>
            </div>
            <div class="absolute top-3 right-4 text-4xl text-[#53685B] opacity-20 group-hover:opacity-40 transition">
                →
            </div>
        </a>

        {{-- Total Produk --}}
        <a href="{{ route('admin.products.index') }}"
            class="group relative bg-gradient-to-br from-white to-gray-50 shadow-lg shadow-[#53685B]/10 border border-gray-100 p-6 rounded-2xl hover:shadow-xl hover:scale-105 transition-all duration-300 block">
            <div class="flex items-center gap-4">
                <div class="bg-[#53685B]/10 p-3 rounded-xl group-hover:bg-[#53685B]/20 transition">
                    <img src="{{ asset('assets/4.png') }}" class="w-8 h-8">
                </div>
                <div>
                    <p class="text-3xl font-bold text-[#53685B]">{{ $totalProducts }}</p>
                    <p class="text-sm text-gray-600 font-medium">Total Barang</p>
                </div>
            </div>
            <div class="absolute top-3 right-4 text-4xl text-[#53685B] opacity-20 group-hover:opacity-40 transition">
                →
            </div>
        </a>

        {{-- Total Order --}}
        <a href="{{ route('admin.orders.index') }}"
            class="group relative bg-gradient-to-br from-white to-gray-50 shadow-lg shadow-[#53685B]/10 border border-gray-100 p-6 rounded-2xl hover:shadow-xl hover:scale-105 transition-all duration-300 block">
            <div class="flex items-center gap-4">
                <div class="bg-[#53685B]/10 p-3 rounded-xl group-hover:bg-[#53685B]/20 transition">
                    <img src="{{ asset('assets/5.png') }}" class="w-8 h-8">
                </div>
                <div>
                    <p class="text-3xl font-bold text-[#53685B]">{{ $totalOrders }}</p>
                    <p class="text-sm text-gray-600 font-medium">Total Order</p>
                </div>
            </div>
            <div class="absolute top-3 right-4 text-4xl text-[#53685B] opacity-20 group-hover:opacity-40 transition">
                →
            </div>
        </a>

        {{-- Total Kategori --}}
        <a href="{{ route('admin.categories.index') }}"
            class="group relative bg-gradient-to-br from-white to-gray-50 shadow-lg shadow-[#53685B]/10 border border-gray-100 p-6 rounded-2xl hover:shadow-xl hover:scale-105 transition-all duration-300 block">
            <div class="flex items-center gap-4">
                <div class="bg-[#53685B]/10 p-3 rounded-xl group-hover:bg-[#53685B]/20 transition">
                    <img src="{{ asset('assets/6.png') }}" class="w-8 h-8">
                </div>
                <div>
                    <p class="text-3xl font-bold text-[#53685B]">{{ $totalCategories }}</p>
                    <p class="text-sm text-gray-600 font-medium">Category</p>
                </div>
            </div>
            <div class="absolute top-3 right-4 text-4xl text-[#53685B] opacity-20 group-hover:opacity-40 transition">
                →
            </div>
        </a>

    </div>

    {{-- Tabel Barang --}}
    <div class="bg-white rounded-2xl shadow-lg shadow-[#53685B]/10 p-6 mb-10">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-[#53685B]">📦 Daftar Produk Terbaru</h2>
            <a href="{{ route('admin.products.index') }}" class="text-[#53685B] hover:text-[#3c4a3e] font-semibold text-sm hover:underline transition">
                Lihat Semua →
            </a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#53685B] text-white">
                    <tr>
                        <th class="px-4 py-3 text-left rounded-tl-lg">Foto</th>
                        <th class="px-4 py-3 text-left">Nama Barang</th>
                        <th class="px-4 py-3 text-left">Toko</th>
                        <th class="px-4 py-3 text-left">Stok</th>
                        <th class="px-4 py-3 text-left">Harga</th>
                        <th class="px-4 py-3 text-left">Kategori</th>
                        <th class="px-4 py-3 text-center rounded-tr-lg">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                    <tr class="border-t border-gray-100 hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <img src="{{ asset($product->image) }}" class="w-16 h-16 object-cover rounded-lg shadow-sm" alt="{{ $product->name }}">
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-semibold text-gray-800">{{ $product->name }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ \Illuminate\Support\Str::limit($product->description, 30) }}</p>
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $product->store->store_name ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                {{ $product->stock > 10 ? 'bg-green-100 text-green-700' : ($product->stock > 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                {{ $product->stock }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-bold text-[#53685B]">Rp{{ number_format($product->price, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded-full text-xs">{{ $product->category }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2 justify-center">
                                <a href="{{ route('admin.products.edit', $product->id) }}" 
                                    class="bg-[#53685B] hover:bg-[#3c4a3e] text-white px-3 py-1 rounded-lg text-xs transition">
                                    ✏️
                                </a>
                                <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST" 
                                    onsubmit="return confirm('Yakin ingin menghapus produk ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-xs transition">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            <p class="text-lg">📦 Belum ada produk</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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