@extends('layouts.app')

@section('heroText', 'Halo, Admin')

@section('content')
<div class="max-w-7xl mx-auto px-6">

    {{-- Statistik --}}
    <h2 class="text-xl font-bold mt-8 mb-4">Dashboard</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-10 font-poppins text-[#53685B]">

        {{-- Total User --}}
        <a href="{{ route('admin.users.index') }}"
            class="relative bg-white shadow-md shadow-[#53685B] border p-4 rounded-2xl hover:shadow-lg transition-all block">
            <div class="flex items-center gap-3">
                <img src="{{ asset('assets/1.png') }}" class="w-10 h-10">
                <div>
                    <p class="text-2xl font-bold">{{ $totalUsers }}</p>
                    <p class="text-sm">Total User</p>
                </div>
            </div>
            <div class="absolute top-2 right-3 text-5xl text-[#53685B]">
                &rsaquo;
            </div>
        </a>

        {{-- Total Seller --}}
        <a href="{{ route('admin.sellers.index') }}"
            class="relative bg-white shadow-md shadow-[#53685B] border p-4 rounded-2xl hover:shadow-lg transition-all block">
            <div class="flex items-center gap-3">
                <img src="{{ asset('assets/2.png') }}" class="w-10 h-10">
                <div>
                    <p class="text-2xl font-bold">{{ $totalSellers }}</p>
                    <p class="text-sm">Total Seller</p>
                </div>
            </div>
            <div class="absolute top-2 right-3 text-5xl text-[#53685B]">
                &rsaquo;
            </div>
        </a>

        {{-- Total Toko --}}
        <a href="{{ route('admin.stores.index') }}"
            class="relative bg-white shadow-md shadow-[#53685B] border p-4 rounded-2xl hover:shadow-lg transition-all block">
            <div class="flex items-center gap-3">
                <img src="{{ asset('assets/3.png') }}" class="w-10 h-10">
                <div>
                    <p class="text-2xl font-bold">{{ $totalStores }}</p>
                    <p class="text-sm">Total Toko</p>
                </div>
            </div>
            <div class="absolute top-2 right-3 text-5xl text-[#53685B]">
                &rsaquo;
            </div>
        </a>

        {{-- Total Produk --}}
        <a href="{{ route('admin.products.index') }}"
            class="relative bg-white shadow-md shadow-[#53685B] border p-4 rounded-2xl hover:shadow-lg transition-all block">
            <div class="flex items-center gap-3">
                <img src="{{ asset('assets/4.png') }}" class="w-10 h-10">
                <div>
                    <p class="text-2xl font-bold">{{ $totalProducts }}</p>
                    <p class="text-sm">Total Barang</p>
                </div>
            </div>
            <div class="absolute top-2 right-3 text-5xl text-[#53685B]">
                &rsaquo;
            </div>
        </a>

        {{-- Total Order --}}
        <a href="{{ route('admin.orders.index') }}"
            class="relative bg-white shadow-md shadow-[#53685B] border p-4 rounded-2xl hover:shadow-lg transition-all block">
            <div class="flex items-center gap-3">
                <img src="{{ asset('assets/5.png') }}" class="w-10 h-10">
                <div>
                    <p class="text-2xl font-bold">{{ $totalOrders }}</p>
                    <p class="text-sm">Total Order</p>
                </div>
            </div>
            <div class="absolute top-2 right-3 text-5xl text-[#53685B]">
                &rsaquo;
            </div>
        </a>

        {{-- Total Kategori --}}
        <a href="{{ route('admin.categories.index') }}"
            class="relative bg-white shadow-md shadow-[#53685B] border p-4 rounded-2xl hover:shadow-lg transition-all block">
            <div class="flex items-center gap-3">
                <img src="{{ asset('assets/6.png') }}" class="w-10 h-10">
                <div>
                    <p class="text-2xl font-bold">{{ $totalCategories }}</p>
                    <p class="text-sm">Category</p>
                </div>
            </div>
            <div class="absolute top-2 right-3 text-5xl text-[#53685B]">
                &rsaquo;
            </div>
        </a>

    </div>

    {{-- Tabel Barang --}}
    <h2 class="text-xl font-bold mb-4">Daftar Barang</h2>
    <table class="w-full text-sm border border-gray-200 mb-10">
        <thead class="bg-gray-100">
            <tr class="text-left">
                <th class="px-4 py-2">Select</th>
                <th class="px-4 py-2">Foto Barang</th>
                <th class="px-4 py-2">Nama Barang</th>
                <th class="px-4 py-2">Stock</th>
                <th class="px-4 py-2">Harga</th>
                <th class="px-4 py-2">Category</th>
                <th class="px-4 py-2">Description</th>
                <th class="px-4 py-2">Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $product)
            <tr class="border-t text-sm">
                <td class="px-4 py-2"><input type="checkbox"></td>
                <td class="px-4 py-2">
                    <img src="{{ asset($product->image) }}" class="w-16 h-16 object-cover">
                </td>
                <td class="px-4 py-2">{{ $product->name }}</td>
                <td class="px-4 py-2">{{ $product->stock }}</td>
                <td class="px-4 py-2">Rp{{ number_format($product->price, 0, ',', '.') }}</td>
                <td class="px-4 py-2">{{ $product->category }}</td>
                <td class="px-4 py-2">{{ \Illuminate\Support\Str::limit($product->description, 40) }}</td>
                <td class="px-4 py-2">
                    <a href="#" class="text-blue-600">✏️</a>
                    <button class="text-red-600 ml-2">🗑</button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

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