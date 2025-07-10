@extends('layouts.form')

@section('title', 'Tambah Produk')

@section('heroText')
Tambah Produk Baru
@endsection

@section('content')
@parent

<div class="max-w-4xl mx-auto mt-10 px-4">

    {{-- Notifikasi --}}
    @if (session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
        <strong class="font-bold">Berhasil!</strong>
        <span class="block sm:inline">{{ session('success') }}</span>
    </div>
    @endif

    {{-- Error Validasi --}}
    @if ($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
        <strong class="font-bold">Oops!</strong>
        <ul class="list-disc ml-5">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif


    <div class="max-w-4xl mx-auto mt-10 px-4">
        <h2 class="text-2xl font-bold font-poppins mb-6">Tambah Produk</h2>

        <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-semibold">Nama Produk</label>
                <input type="text" name="name" class="w-full border border-gray-400 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]" required>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold">Stok</label>
                    <input type="number" name="stock" class="w-full border border-gray-400 rounded-md px-4 py-2" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold">Harga</label>
                    <input type="number" step="0.01" name="price" class="w-full border border-gray-400 rounded-md px-4 py-2" required>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold">Kategori</label>
                <input type="text" name="category" class="w-full border border-gray-400 rounded-md px-4 py-2" required>
            </div>

            <div>
                <label class="block text-sm font-semibold">Deskripsi</label>
                <textarea name="description" rows="4" class="w-full border border-gray-400 rounded-md px-4 py-2" required></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold">Gambar Produk</label>
                <input type="file" name="image" class="w-full border border-gray-400 rounded-md px-4 py-2">
            </div>

            <div class="text-right">
                <button type="submit" class="bg-[#53685B] hover:bg-[#3c4a3e] text-white px-6 py-2 rounded-md font-semibold">
                    Simpan Produk
                </button>
            </div>
        </form>
    </div>
    @endsection