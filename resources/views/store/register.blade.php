@extends('layouts.form')

@section('title', 'Register Toko')

@section('heroText')
    Daftarkan Toko Kamu Sekarang!
@endsection

@section('content')
@parent
<div class="flex justify-center mt-8 px-4">
    <form action="{{ route('store.register') }}" method="POST" class="w-full max-w-2xl space-y-4">
        @csrf

        <h2 class="text-center text-2xl font-bold font-poppins mb-4">Daftarkan Tokomu</h2>

        <!-- Nama Toko -->
        <input type="text" name="store_name" placeholder="Nama Toko"
            class="w-full border border-gray-400 rounded-md px-4 py-2 font-poppins focus:outline-none focus:ring-2 focus:ring-[#E9E19E]"
            required>

        <!-- Kategori Toko -->
        <input type="text" name="category" placeholder="Kategori (contoh: Antik, Elektronik, Buku)"
            class="w-full border border-gray-400 rounded-md px-4 py-2 font-poppins focus:outline-none focus:ring-2 focus:ring-[#E9E19E]"
            required>

        <!-- Deskripsi -->
        <textarea name="description" placeholder="Deskripsi Toko" rows="3"
            class="w-full border border-gray-400 rounded-md px-4 py-2 font-poppins focus:outline-none focus:ring-2 focus:ring-[#E9E19E]"
            required></textarea>

        <!-- Lokasi Toko -->
        <input type="text" name="location" placeholder="Alamat atau Lokasi Toko"
            class="w-full border border-gray-400 rounded-md px-4 py-2 font-poppins focus:outline-none focus:ring-2 focus:ring-[#E9E19E]"
            required>

        <!-- Tombol Submit -->
        <div class="text-center">
            <button type="submit"
                class="bg-[#4a5b4d] hover:bg-[#3c4a3e] text-white font-semibold font-poppins px-10 py-3 rounded-md transition-all">
                Daftarkan Toko
            </button>
        </div>
    </form>
</div>

@endsection
