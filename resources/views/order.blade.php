@extends('layouts.form')

@section('title', 'Pesananmu - VINSTORE')

@section('content')
@parent

<section class="min-h-screen w-full bg-gradient-to-br from-[#2F3E46] via-[#354F52] to-[#B77C4C] text-[#E9E19E] px-6 md:px-12 pt-32 pb-20">
    <div class="max-w-6xl mx-auto">
        <h2 class="text-4xl font-bold text-center mb-10">📦 Pesananmu</h2>

        @if ($orders->isEmpty())
            <div class="text-center text-[#E9E19E]/90 italic text-lg py-20">
                Belum ada pesanan yang masuk 🕯️
            </div>
        @else
            <div class="overflow-x-auto bg-white/10 backdrop-blur-md rounded-2xl shadow-xl border border-white/20">
                <table class="min-w-full text-left text-[#E9E19E]">
                    <thead>
                        <tr class="bg-[#E9E19E]/10 uppercase text-sm tracking-wider">
                            <th class="px-6 py-4">Item</th>
                            <th class="px-6 py-4">Jumlah</th>
                            <th class="px-6 py-4">Harga</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Tanggal</th>
                            <th class="px-6 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                        <tr class="border-b border-white/10 hover:bg-white/10 transition">
                            <td class="px-6 py-4 font-semibold">
                                {{ $order->product->name ?? 'Produk tidak ditemukan' }}
                            </td>
                            <td class="px-6 py-4">{{ $order->quantity }}</td>
                            <td class="px-6 py-4">Rp{{ number_format($order->price, 0, ',', '.') }}</td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-sm 
                                    @if($order->status === 'Waiting') bg-yellow-500/30 text-yellow-200
                                    @elseif($order->status === 'On The Way') bg-blue-500/30 text-blue-200
                                    @elseif($order->status === 'Delivered') bg-green-500/30 text-green-200
                                    @elseif($order->status === 'Cancelled') bg-red-500/30 text-red-200
                                    @else bg-gray-500/30 text-gray-200 @endif">
                                    {{ $order->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4">{{ $order->created_at->format('d M Y, H:i') }}</td>
                            <td class="px-6 py-4 text-center">
                                @if (in_array($order->status, ['Waiting', 'On The Way']))
                                    <form action="{{ route('order.cancel', $order->id) }}" method="POST"
                                          onsubmit="return confirm('Yakin ingin membatalkan pesanan ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-red-400 hover:text-red-300 font-semibold transition">
                                            Batalkan
                                        </button>
                                    </form>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
@endsection
