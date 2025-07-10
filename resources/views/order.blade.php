@extends('layouts.form')

@section('title', 'Track Order')

@section('heroText')
Udah Nyampe Mana Nih Pesananmu?
@endsection

@section('content')
@parent

<div class="px-4 md:px-10 mt-10">
    <h2 class="text-xl font-bold font-poppins mb-6">Pesananmu</h2>

    <div class="overflow-x-auto">
        <table class="min-w-full table-auto border border-gray-300">
            <thead>
                <tr class="bg-[#4a5b4d] text-white text-center">
                    <th class="px-6 py-3 font-poppins font-semibold">Your Items</th>
                    <th class="px-6 py-3 font-poppins font-semibold">Quantity</th>
                    <th class="px-6 py-3 font-poppins font-semibold">Price</th>
                    <th class="px-6 py-3 font-poppins font-semibold">Status</th>
                    <th class="px-6 py-3 font-poppins font-semibold">Date</th>
                    <th class="px-6 py-3 font-poppins font-semibold">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                <tr class="text-center border-b border-gray-200">
                    <td class="px-6 py-4">{{ $order->product->name ?? 'Produk tidak ditemukan' }}</td>
                    <td class="px-6 py-4">{{ $order->quantity }}</td>
                    <td class="px-6 py-4">Rp{{ number_format($order->price, 0, ',', '.') }}</td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 rounded-full bg-gray-200 text-sm">{{ $order->status }}</span>
                    </td>
                    <td class="px-6 py-4">{{ $order->created_at->format('d-m-Y H:i') }}</td>
                    <td class="px-6 py-4 mouse">
                        @if (in_array($order->status, ['Waiting', 'On The Way']))
                        <form action="{{ route('order.cancel', $order->id) }}" method="POST" onsubmit="return confirm('Yakin ingin membatalkan pesanan ini?');">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-500 hover:underline hover:cursor-pointer font-poppins">Batalkan</button>
                        </form>
                        @else
                        <span class="text-gray-400">-</span>
                        @endif
                    </td>

                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-6 text-gray-500 font-poppins">
                        Belum ada pesanan yang masuk.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection