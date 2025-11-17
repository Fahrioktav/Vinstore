@extends('layouts.app')

@section('heroText', 'Edit Data User')

@section('content')
<div class="max-w-3xl mx-auto px-6 py-8">
    <div class="bg-white p-8 rounded-2xl shadow-md shadow-[#53685B]/20">
        <h2 class="text-3xl font-bold mb-6 text-[#53685B]">✏️ Edit User</h2>

        @if(session('error'))
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded-lg mb-4">
            <p class="font-semibold">{{ session('error') }}</p>
        </div>
        @endif

        <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="username" class="block text-sm font-semibold mb-2">Username</label>
                <input type="text" name="username" id="username" 
                    value="{{ old('username', $user->username) }}"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('username') border-red-500 @enderror" 
                    required>
                @error('username')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="first_name" class="block text-sm font-semibold mb-2">Nama Depan</label>
                    <input type="text" name="first_name" id="first_name" 
                        value="{{ old('first_name', $user->first_name) }}"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('first_name') border-red-500 @enderror" 
                        required>
                    @error('first_name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="last_name" class="block text-sm font-semibold mb-2">Nama Belakang</label>
                    <input type="text" name="last_name" id="last_name" 
                        value="{{ old('last_name', $user->last_name) }}"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('last_name') border-red-500 @enderror" 
                        required>
                    @error('last_name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <label for="email" class="block text-sm font-semibold mb-2">Email</label>
                <input type="email" name="email" id="email" 
                    value="{{ old('email', $user->email) }}"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('email') border-red-500 @enderror" 
                    required>
                @error('email')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="phone" class="block text-sm font-semibold mb-2">Telepon</label>
                <input type="text" name="phone" id="phone" 
                    value="{{ old('phone', $user->phone) }}"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('phone') border-red-500 @enderror">
                @error('phone')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="address" class="block text-sm font-semibold mb-2">Alamat</label>
                <textarea name="address" id="address" rows="3"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('address') border-red-500 @enderror">{{ old('address', $user->address) }}</textarea>
                @error('address')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3">
                <button type="submit" 
                    class="bg-[#53685B] hover:bg-[#3c4a3e] text-white px-8 py-3 rounded-lg font-semibold transition shadow-md hover:shadow-lg">
                    ✓ Simpan Perubahan
                </button>
                <a href="{{ route('admin.users.index') }}" 
                    class="bg-gray-500 hover:bg-gray-600 text-white px-8 py-3 rounded-lg font-semibold transition shadow-md hover:shadow-lg">
                    ← Batal
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
