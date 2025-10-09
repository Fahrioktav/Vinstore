@extends('layouts.form')

@section('title', 'Login - VINSTORE')

@section('heroText')
    Login ke Akunmu dan Mulai Berbelanja!
@endsection

@section('content')
@parent

{{-- Background Full Layer --}}
<section class="relative flex items-center justify-center w-full min-h-screen bg-gradient-to-br from-[#2F3E46] via-[#354F52] to-[#B77C4C] overflow-hidden">

    {{-- Elemen Dekoratif --}}
    <div class="absolute inset-0">
        <div class="absolute top-0 left-0 w-72 h-72 bg-[#E9E19E]/20 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-[#B77C4C]/25 rounded-full blur-3xl"></div>
    </div>

    {{-- Card Login --}}
    <div class="relative z-10 w-full max-w-md bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl p-8 md:p-10 border border-gray-200 mx-4">

        {{-- Logo --}}
        <div class="flex flex-col items-center mb-6">
            <img src="{{ asset('assets/Logo.png') }}" alt="VINSTORE" class="w-16 h-16 object-contain mb-3">
            <h1 class="text-2xl font-bold text-[#3E2723]">Selamat Datang Kembali!</h1>
            <p class="text-gray-600 text-sm">Masuk untuk melanjutkan ke dunia barang antik eksklusif ✨</p>
        </div>

        {{-- Notifikasi Error --}}
        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4 text-sm text-center">
                {{ session('error') }}
            </div>
        @endif

        {{-- Form Login --}}
        <form method="POST" action="{{ route('login.submit') }}" class="space-y-5">
            @csrf

            {{-- Username / Email --}}
            <div>
                <label for="login" class="block text-sm font-semibold text-[#3E2723] mb-1">Username atau Email</label>
                <input type="text" name="login" id="login"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#B77C4C] text-gray-700"
                    placeholder="Masukkan username atau email" required>
            </div>

            {{-- Password --}}
            <div>
                <label for="password" class="block text-sm font-semibold text-[#3E2723] mb-1">Password</label>
                <input type="password" name="password" id="password"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#B77C4C] text-gray-700"
                    placeholder="Masukkan password" required>
            </div>

            {{-- Tombol Login --}}
            <button type="submit"
                class="w-full bg-[#B77C4C] hover:bg-[#9e6538] text-white font-semibold py-3 rounded-lg shadow-md transition-all duration-200">
                Masuk Sekarang
            </button>
        </form>

        {{-- Divider --}}
        <div class="flex items-center my-6">
            <hr class="flex-1 border-gray-300">
            <span class="px-3 text-gray-500 text-sm">atau</span>
            <hr class="flex-1 border-gray-300">
        </div>

        {{-- Register Prompt --}}
        <div class="text-center">
            <p class="text-sm text-gray-600">
                Belum punya akun?
                <a href="{{ route('register.form') }}" class="text-[#B77C4C] font-semibold hover:underline">
                    Daftar Sekarang
                </a>
            </p>
        </div>
    </div>
</section>
@endsection
