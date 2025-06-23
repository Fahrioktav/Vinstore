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
                <tr class="bg-[#4a5b4d] text-white text-left">
                    <th class="px-6 py-3 font-poppins font-semibold">Your Items</th>
                    <th class="px-6 py-3 font-poppins font-semibold">Quantity</th>
                    <th class="px-6 py-3 font-poppins font-semibold">Price</th>
                    <th class="px-6 py-3 font-poppins font-semibold">Status</th>
                    <th class="px-6 py-3 font-poppins font-semibold">Date</th>
                    <th class="px-6 py-3 font-poppins font-semibold">Action</th>
                </tr>
            </thead>
            <tbody>
                {{-- Data kosong karena belum ada isi --}}
                <tr>
                    <td colspan="6" class="text-center py-6 text-gray-500 font-poppins">
                        Belum ada pesanan yang masuk.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
