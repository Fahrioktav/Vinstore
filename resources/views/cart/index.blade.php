@extends('layouts.form')

@section('title', 'Keranjang Belanja')

@section('content')
    <div class="max-w-4xl mx-auto py-8">
        <h2 class="text-2xl font-bold mb-6 text-center">🛒 Keranjang Belanja Kamu</h2>

        @if($cartItems->isEmpty())
            <div class="text-center text-gray-600">
                Keranjang kamu masih kosong.
            </div>
        @else
            <div class="space-y-6">
                @foreach ($cartItems as $item)
                    <div class="flex flex-col sm:flex-row items-center gap-6 border-b pb-4">
                        <!-- Gambar Produk -->
                        <img src="{{ asset($item->product->image) }}" alt="{{ $item->product->name }}"
                             class="w-28 h-28 object-cover rounded-md border">

                        <!-- Info Produk -->
                        <div class="flex-1 w-full">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-semibold text-lg">{{ $item->product->name }}</p>
                                    <p class="text-sm text-gray-600 mt-1">Jumlah: {{ $item->quantity }}</p>
                                    <p class="text-sm text-gray-600">Harga Satuan: Rp{{ number_format($item->product->price, 0, ',', '.') }}</p>
                                    <p class="text-sm text-gray-700 font-medium mt-1">
                                        Subtotal: <span class="text-green-600 font-semibold">Rp{{ number_format($item->product->price * $item->quantity, 0, ',', '.') }}</span>
                                    </p>
                                </div>

                                <!-- Tombol Hapus -->
                                <form method="POST" action="{{ route('cart.remove', $item->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-500 hover:text-red-700 text-sm font-medium mt-1">Hapus</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Tombol Checkout -->
            <div class="mt-10 text-right">
                <form action="{{ route('checkout.fromCart') }}" method="POST">
                    @csrf
                    <button type="submit"
                        class="bg-[#53685B] hover:bg-[#3c4a3e] text-white px-6 py-2 rounded font-semibold transition">
                        Lanjut ke Checkout
                    </button>
                </form>
            </div>
        @endif
    </div>
@endsection
