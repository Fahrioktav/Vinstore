@extends('layouts.app')

@section('heroText', 'Kelola Toko Mu!')

@section('content')
<div class="max-w-6xl mx-auto px-4">

    {{-- Section Customer Orders --}}
    <h2 class="text-xl font-bold mt-8 mb-4">Customer Orders</h2>

<table class="w-full text-sm border-collapse mb-8">
    <thead class="bg-gray-200">
        <tr class="text-center">
            <th>Customer</th>
            <th>Product</th>
            <th>Qty</th>
            <th>Total</th>
            <th>Status</th>
            <th>Date</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($orders as $order)
        <tr class="text-center">
            <td>{{ $order->user->first_name }} {{ $order->user->last_name }}</td>
            <td>{{ $order->product->name }}</td>
            <td>{{ $order->quantity }}</td>
            <td>Rp{{ number_format($order->price, 0, ',', '.') }}</td>
            <td>
                <form method="POST" action="{{ route('orders.updateStatus', $order->id) }}">
                    @csrf
                    @method('PATCH')
                    <select name="status" onchange="this.form.submit()">
                        @foreach (['Waiting', 'On The Way', 'Delivered', 'Cancelled'] as $status)
                        <option value="{{ $status }}" {{ $order->status == $status ? 'selected' : '' }}>
                            {{ $status }}
                        </option>
                        @endforeach
                    </select>
                </form>
            </td>
            <td>{{ $order->created_at->format('d-m-Y H:i') }}</td>
            <td>
                <form action="{{ route('orders.updateStatus', $order->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button class="text-red-600">🗑</button>
                </form>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="7" class="text-center text-gray-500 py-4">Belum ada pesanan.</td>
        </tr>
        @endforelse
    </tbody>
</table>


    {{-- Section Produk --}}
    <div class="flex justify-between items-center">
        <h2 class="text-xl font-bold mb-4">Daftar Barang</h2>
        <a href="{{ route('products.create') }}" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
            + Add Barang
        </a>
    </div>

    <table class="w-full text-sm border-collapse">
        <thead class="bg-gray-200">
            <tr>
                <th>Select</th>
                <th>Foto</th>
                <th>Nama</th>
                <th>Stok</th>
                <th>Harga</th>
                <th>Kategori</th>
                <th>Deskripsi</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
            <tr class="text-center">
                <td><input type="checkbox" /></td>
                <td>
                    @if($product->image)
                        <img src="{{ asset($product->image) }}" class="w-16 h-16 object-cover">
                    @else
                        <span class="text-gray-400">-</span>
                    @endif
                </td>
                <td>{{ $product->name }}</td>
                <td>{{ $product->stock }}</td>
                <td>Rp{{ number_format($product->price, 0, ',', '.') }}</td>
                <td>{{ $product->category }}</td>
                <td>{{ \Illuminate\Support\Str::limit($product->description, 40) }}</td>
                <td>
                    <a href="#" class="text-blue-600">✏️</a>
                    <form action="{{ route('products.destroy', $product->id) }}" method="POST" class="inline">
                        @csrf @method('DELETE')
                        <button class="text-red-600 ml-2">🗑</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center text-gray-500">Belum ada produk.</td></tr>
            @endforelse
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

