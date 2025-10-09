@extends('layouts.form')

@section('title', 'Register - VINSTORE')

@section('heroText')
    Halo! Selamat Datang di VINSTORE 🎉
@endsection

@section('content')
@parent

{{-- Section Background --}}
<section class="flex items-center justify-center min-h-screen w-full bg-gradient-to-br from-[#2F3E46] via-[#354F52] to-[#B77C4C] px-6 py-12 relative overflow-hidden">

    {{-- CARD REGISTER --}}
    <div class="relative w-full max-w-2xl bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl mt-30 md:p-10 border border-gray-200">

        {{-- Header --}}
        <div class="flex flex-col items-center mb-8">
            <img src="{{ asset('assets/Logo.png') }}" alt="VINSTORE" class="w-16 h-16 object-contain mb-3">
            <h1 class="text-2xl md:text-3xl font-bold text-[#3E2723] text-center">Buat Akun Baru</h1>
            <p class="text-gray-600 text-sm text-center">Daftar dan mulailah menjelajahi koleksi barang antik eksklusif ✨</p>
        </div>

        {{-- ERROR MESSAGE --}}
        @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 text-sm">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- FORM REGISTER --}}
        <form action="{{ route('register.submit') }}" method="POST" class="space-y-5">
            @csrf

            {{-- Username --}}
            <div>
                <label for="username" class="block text-sm font-semibold text-[#3E2723] mb-1">Username</label>
                <input type="text" name="username" id="username" placeholder="Masukkan username"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#B77C4C]" required>
            </div>

            {{-- Nama Depan & Belakang --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="block text-sm font-semibold text-[#3E2723] mb-1">Nama Depan</label>
                    <input type="text" name="first_name" id="first_name" placeholder="Nama depan"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#B77C4C]" required>
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-semibold text-[#3E2723] mb-1">Nama Belakang</label>
                    <input type="text" name="last_name" id="last_name" placeholder="Nama belakang"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#B77C4C]" required>
                </div>
            </div>

            {{-- Email & Telepon --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="email" class="block text-sm font-semibold text-[#3E2723] mb-1">Email</label>
                    <input type="email" name="email" id="email" placeholder="Masukkan email"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#B77C4C]" required>
                </div>
                <div>
                    <label for="phone" class="block text-sm font-semibold text-[#3E2723] mb-1">Nomor Telepon</label>
                    <input type="tel" name="phone" id="phone" placeholder="Masukkan nomor telepon"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#B77C4C]" required>
                </div>
            </div>

            {{-- Password & Konfirmasi --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-sm font-semibold text-[#3E2723] mb-1">Password</label>
                    <input type="password" name="password" id="password" placeholder="Masukkan password"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#B77C4C]" required>
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-semibold text-[#3E2723] mb-1">Konfirmasi Password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Ulangi password"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#B77C4C]" required>
                </div>
            </div>

            {{-- Alamat --}}
            <div>
                <label for="address" class="block text-sm font-semibold text-[#3E2723] mb-1">Alamat Lengkap</label>
                <textarea name="address" id="address" rows="3" placeholder="Masukkan alamat lengkap"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#B77C4C]" required></textarea>
            </div>

            {{-- Tombol Register --}}
            <button type="submit"
                class="w-full bg-[#B77C4C] hover:bg-[#9e6538] text-white font-semibold py-3 rounded-lg shadow-md transition-all duration-200">
                Daftar Sekarang
            </button>
        </form>

        {{-- Divider --}}
        <div class="flex items-center my-6">
            <hr class="flex-1 border-gray-300">
            <span class="px-3 text-gray-500 text-sm">atau</span>
            <hr class="flex-1 border-gray-300">
        </div>

        {{-- Redirect ke Login --}}
        <div class="text-center">
            <p class="text-sm text-gray-600">
                Sudah punya akun?
                <a href="{{ route('login.form') }}" class="text-[#B77C4C] font-semibold hover:underline">
                    Login Sekarang
                </a>
            </p>
        </div>
    </div>

    {{-- Dekorasi Background --}}
    <div class="absolute top-0 left-0 w-72 h-72 bg-[#E9E19E]/20 rounded-full blur-3xl -z-10"></div>
    <div class="absolute bottom-0 right-0 w-96 h-96 bg-[#B77C4C]/25 rounded-full blur-3xl -z-10"></div>
</section>
@endsection
