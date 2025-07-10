@extends('layouts.form')

@section('title', 'register')

@section('heroText')
Halo!, Selamat Datang Di VINSTORE
@endsection

@section('content')
@parent
<div class="flex justify-center mt-8 px-4">
    <form action="{{ route('register.submit') }}" method="POST" class="w-full max-w-2xl space-y-4">
        @csrf

        @if ($errors->any())
        <div class="bg-red-100 text-red-700 border border-red-400 p-3 rounded mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif


        <h2 class="text-center text-2xl font-bold font-poppins mb-4">Daftarkan Dirimu</h2>

        <!-- Username -->
        <input type="text" name="username" placeholder="Username"
            class="w-full border border-gray-400 rounded-md px-4 py-2 font-poppins focus:outline-none focus:ring-2 focus:ring-[#E9E19E]" required>

        <!-- First & Last Name -->
        <div class="flex flex-col md:flex-row gap-4">
            <input type="text" name="first_name" placeholder="First Name"
                class="w-full border border-gray-400 rounded-md px-4 py-2 font-poppins focus:outline-none focus:ring-2 focus:ring-[#E9E19E]" required>
            <input type="text" name="last_name" placeholder="Last Name"
                class="w-full border border-gray-400 rounded-md px-4 py-2 font-poppins focus:outline-none focus:ring-2 focus:ring-[#E9E19E]" required>
        </div>

        <!-- Email & Phone -->
        <div class="flex flex-col md:flex-row gap-4">
            <input type="email" name="email" placeholder="Email Address"
                class="w-full border border-gray-400 rounded-md px-4 py-2 font-poppins focus:outline-none focus:ring-2 focus:ring-[#E9E19E]" required>
            <input type="tel" name="phone" placeholder="Phone Number"
                class="w-full border border-gray-400 rounded-md px-4 py-2 font-poppins focus:outline-none focus:ring-2 focus:ring-[#E9E19E]" required>
        </div>

        <!-- Password & Confirm -->
        <div class="flex flex-col md:flex-row gap-4">
            <input type="password" name="password" placeholder="Password"
                class="w-full border border-gray-400 rounded-md px-4 py-2 font-poppins focus:outline-none focus:ring-2 focus:ring-[#E9E19E]" required>
            <input type="password" name="password_confirmation" placeholder="Confirm Password"
                class="w-full border border-gray-400 rounded-md px-4 py-2 font-poppins focus:outline-none focus:ring-2 focus:ring-[#E9E19E]" required>
        </div>

        <!-- Address -->
        <textarea name="address" placeholder="Your Address" rows="3"
            class="w-full border border-gray-400 rounded-md px-4 py-2 font-poppins focus:outline-none focus:ring-2 focus:ring-[#E9E19E]" required></textarea>

        <!-- Submit Button -->
        <div class="text-center">
            <button type="submit"
                class="bg-[#4a5b4d] hover:bg-[#3c4a3e] text-white font-semibold font-poppins px-30 py-2 rounded-md transition-all">
                Register
            </button>
        </div>
    </form>
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