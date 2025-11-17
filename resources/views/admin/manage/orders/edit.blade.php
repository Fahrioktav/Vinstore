@extends('layouts.app')

@section('heroText', 'Edit Status Order')

@section('content')
<div class="max-w-4xl mx-auto px-6 py-8">
    <div class="bg-white rounded-2xl shadow-md shadow-[#53685B]/20 p-8">
        <h2 class="text-2xl font-bold mb-6 text-[#53685B]">✏️ Edit Order #{{ $order->id }}</h2>

        <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 mb-6">
            <h3 class="font-semibold text-lg mb-4 text-[#53685B]">📦 Detail Order</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-gray-600">Customer</p>
                    <p class="font-semibold">{{ $order->user->first_name }} {{ $order->user->last_name }}</p>
                    <p class="text-xs text-gray-500">{{ $order->user->email }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Produk</p>
                    <p class="font-semibold">{{ $order->product->name }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Toko</p>
                    <p class="font-semibold">{{ $order->store->store_name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Quantity</p>
                    <p class="font-semibold">{{ $order->quantity }} pcs</p>
                </div>
                <div>
                    <p class="text-gray-600">Total Harga</p>
                    <p class="font-bold text-[#53685B]">Rp{{ number_format($order->price, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Tanggal Order</p>
                    <p class="font-semibold">{{ $order->created_at->format('d M Y H:i') }}</p>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.orders.update', $order->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-6">
                <label class="block text-sm font-bold mb-2 text-gray-700">Status Order</label>
                <select name="status" required
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-[#53685B] focus:border-[#53685B] transition">
                    <option value="Waiting" {{ $order->status === 'Waiting' ? 'selected' : '' }}>⏳ Waiting (Menunggu)</option>
                    <option value="Processing" {{ $order->status === 'Processing' ? 'selected' : '' }}>🔄 Processing (Diproses)</option>
                    <option value="Completed" {{ $order->status === 'Completed' ? 'selected' : '' }}>✅ Completed (Selesai)</option>
                    <option value="Cancelled" {{ $order->status === 'Cancelled' ? 'selected' : '' }}>❌ Cancelled (Dibatalkan)</option>
                </select>
                @error('status')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-4">
                <button type="submit" 
                    class="flex-1 bg-[#53685B] hover:bg-[#3c4a3e] text-white font-bold py-3 px-6 rounded-lg transition shadow-md hover:shadow-lg">
                    💾 Simpan Perubahan
                </button>
                <a href="{{ route('admin.orders.index') }}" 
                    class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-6 rounded-lg transition shadow-md hover:shadow-lg text-center">
                    ← Kembali
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
