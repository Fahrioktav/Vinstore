@extends('layouts.app')

@section('heroText', 'Kelola Data Order')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-8">
    @if(session('success'))
    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-lg mb-6 shadow-sm">
        <p class="font-semibold">✓ {{ session('success') }}</p>
    </div>
    @endif

    <div class="bg-white rounded-2xl shadow-md shadow-[#53685B]/20 p-6">
        <h2 class="text-2xl font-bold mb-6 text-[#53685B]">📦 Daftar Order</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border border-gray-200">
            <thead class="bg-[#53685B] text-white">
                <tr>
                    <th class="px-4 py-3 text-left">ID Order</th>
                    <th class="px-4 py-3 text-left">Customer</th>
                    <th class="px-4 py-3 text-left">Produk</th>
                    <th class="px-4 py-3 text-left">Toko</th>
                    <th class="px-4 py-3 text-left">Qty</th>
                    <th class="px-4 py-3 text-left">Total Harga</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Tanggal</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                <tr class="border-t hover:bg-gray-50 transition">
                    <td class="px-4 py-3 font-semibold">#{{ $order->id }}</td>
                    <td class="px-4 py-3">
                        <p class="font-semibold">{{ $order->user->first_name }} {{ $order->user->last_name }}</p>
                        <p class="text-xs text-gray-500">{{ $order->user->email }}</p>
                    </td>
                    <td class="px-4 py-3">{{ $order->product->name ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $order->store->store_name ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $order->quantity }}</td>
                    <td class="px-4 py-3 font-bold text-[#53685B]">Rp{{ number_format($order->price, 0, ',', '.') }}</td>
                    <td class="px-4 py-3">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold
                            @if($order->status === 'Completed') bg-green-100 text-green-700
                            @elseif($order->status === 'Processing') bg-blue-100 text-blue-700
                            @elseif($order->status === 'Waiting') bg-yellow-100 text-yellow-700
                            @elseif($order->status === 'Cancelled') bg-red-100 text-red-700
                            @else bg-gray-100 text-gray-700
                            @endif">
                            {{ $order->status }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600">{{ $order->created_at->format('d M Y H:i') }}</td>
                    <td class="px-4 py-3">
                        <div class="flex gap-2 justify-center">
                            <a href="{{ route('admin.orders.edit', $order->id) }}" 
                                class="bg-[#53685B] hover:bg-[#3c4a3e] text-white px-4 py-2 rounded-lg text-xs font-semibold transition shadow-sm hover:shadow-md">
                                ✏️ Edit
                            </a>
                            <form action="{{ route('admin.orders.destroy', $order->id) }}" method="POST" 
                                onsubmit="return confirm('Yakin ingin menghapus order ini?')">
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
                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                        <p class="text-lg">📦 Belum ada order</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
