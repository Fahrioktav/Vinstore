@extends('layouts.form')

@section('title', 'Profile')

@section('heroText')
👋 Halo, {{ $user->first_name }}
@endsection

@section('content')
@parent

<div class="max-w-5xl mx-auto mt-10 px-4">
    <h2 class="text-2xl font-bold font-poppins mb-6">Profile</h2>


    <div class="grid md:grid-cols-3 gap-6 items-start">
        <!-- Foto Profil -->
        <div class="text-center">
            <img src="https://via.placeholder.com/150" alt="Profile Picture" class="rounded-full w-40 h-40 mx-auto object-cover">
            <div class="mt-4">
                <a href="#" class="text-blue-600 font-semibold hover:underline flex justify-center items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15.232 5.232l3.536 3.536M9 13l6-6m2 2l-6 6M13 7H7v6M6 6h.01" />
                    </svg>
                    Edit Profile
                </a>
            </div>
            <div class="mt-6">
                <a href="{{ route('store.register') }}"
                    class="bg-[#53685B] hover:bg-[#3c4a3e] text-white px-6 py-2 rounded-md font-semibold inline-block text-center">
                    🏪 Buka Toko
                </a>
            </div>

        </div>

        <!-- Form Edit -->
        <form action="{{ route('profile.update') }}" method="POST" class="md:col-span-2 space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-semibold">Username</label>
                <input type="text" name="username" value="{{ old('username', $user->username) }}"
                    class="w-full border border-gray-400 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]" required>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold">First Name</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}"
                        class="w-full border border-gray-400 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}"
                        class="w-full border border-gray-400 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]" required>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold">Your Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}"
                        class="w-full border border-gray-400 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold">Phone Number</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                        class="w-full border border-gray-400 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold">Password</label>
                <input type="password" value="********" disabled
                    class="w-full border border-gray-400 rounded-md px-4 py-2 bg-gray-100 cursor-not-allowed">
            </div>

            <div>
                <label class="block text-sm font-semibold">Your Address</label>
                <textarea name="address" rows="3"
                    class="w-full border border-gray-400 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]">{{ old('address', $user->address) }}</textarea>
            </div>

            <div class="text-right pt-4">
                <button type="submit"
                    class="bg-[#53685B] hover:bg-[#3c4a3e] text-white px-6 py-2 rounded-md font-semibold">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<footer class="bg-black text-white text-center py-6 mt-12 text-sm">
    VINSTORE.id Copyright 2025, All Rights Reserved. |
    <a href="#" class="underline">Privacy Policy</a> |
    <a href="#" class="underline">Terms</a> |
    <a href="#" class="underline">Pricing</a> |
    <a href="#" class="underline">Do not sell or share my personal info</a>
</footer>
@endsection