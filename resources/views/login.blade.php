@extends('layouts.form')

@section('title', 'login')

@section('heroText')
    Selamat Datang Kembali!
@endsection

@section('content')
@parent
<div class="flex justify-center items-center w-full py-10">
    <div class="w-full max-w-md px-6 py-8 bg-white rounded-lg">

        {{-- Notifikasi Sukses (contoh: dari register) --}}
        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded mb-4 text-sm">
                {{ session('success') }}
            </div>
        @endif

        {{-- Notifikasi Error Login --}}
        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4 text-sm">
                {{ session('error') }}
            </div>
        @endif

        <h2 class="text-center text-2xl font-bold mb-8">Login Ke Akunmu!</h2>

        {{-- FORM LOGIN --}}
        <form method="POST" action="{{ route('login.submit') }}">
            @csrf

            {{-- Username / Email --}}
            <div class="mb-4">
                <input type="text" name="login" placeholder="Username atau Email"
                    class="w-full border-2 border-[#53685B] rounded-md px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#E9E19E] placeholder-gray-400" required>
            </div>

            {{-- Password --}}
            <div class="mb-6">
                <input type="password" name="password" placeholder="Password"
                    class="w-full border-2 border-[#53685B] rounded-md px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#E9E19E] placeholder-gray-400" required>
            </div>

            {{-- Tombol Login --}}
            <div class="mb-4">
                <button type="submit"
                    class="w-full bg-[#53685B] text-white font-semibold py-3 rounded-md hover:bg-[#3b5144] transition-all duration-200">
                    Login
                </button>
            </div>
        </form>

        {{-- Registrasi --}}
        <div class="text-center">
            <p class="text-sm text-gray-600">
                Belum Punya Akun? 
                <a href="/register" class="text-[#53685B] font-semibold hover:underline">Registrasi</a>
            </p>
        </div>
    </div>
</div>

{{-- Footer --}}
<footer class="bg-black text-white text-center py-6 mt-12 text-sm">
    VINSTORE.id Copyright 2025, All Rights Reserved. |
    <a href="#" class="underline">Privacy Policy</a> |
    <a href="#" class="underline">Terms</a> |
    <a href="#" class="underline">Pricing</a> |
    <a href="#" class="underline">Do not sell or share my personal info</a>
</footer>
@endsection
