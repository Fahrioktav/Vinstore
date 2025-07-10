@extends('layouts.form')

@section('title', 'Checkout')

@section('heroText')
    Konfirmasi Pembelianmu
@endsection

@section('content')
@parent

<div class="max-w-3xl mx-auto mt-10 p-6 bg-white rounded-md shadow-md border">
    <h2 class="text-2xl font-bold mb-6">Detail Checkout</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div>
            <img src="{{ asset($product->image) }}" class="w-full h-48 object-contain rounded-md" alt="{{ $product->name }}">
        </div>
        <div>
            <h3 class="text-xl font-semibold">{{ $product->name }}</h3>
            <p class="text-gray-600 mt-2">{{ $product->description }}</p>
            <p class="mt-4 font-bold text-lg">Harga: Rp{{ number_format($product->price, 0, ',', '.') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('checkout.process', $product->id) }}">
        @csrf

        <div class="mb-4">
            <label for="quantity" class="block text-sm font-semibold">Jumlah</label>
            <input type="number" name="quantity" id="quantity" min="1" value="1" class="w-full border border-gray-400 rounded-md px-4 py-2" required>
        </div>

        <div class="mb-4">
            <label for="payment_method" class="block text-sm font-semibold">Metode Pembayaran</label>
            <select name="payment_method" id="payment_method" class="w-full border border-gray-400 rounded-md px-4 py-2" required>
                <option value="" disabled selected>-- Pilih Metode Pembayaran --</option>
                <option value="transfer">Transfer Bank</option>
                <option value="cod">Cash on Delivery</option>
                <option value="ewallet">E-Wallet (OVO, GoPay, DANA)</option>
            </select>
        </div>

        <div class="text-right">
            <button type="submit" class="bg-[#53685B] hover:bg-[#3c4a3e] text-white px-6 py-2 rounded-md font-semibold">
                Selesaikan Pesanan
            </button>
        </div>
    </form>
</div>
@endsection
