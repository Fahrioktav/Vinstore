@extends('layouts.app')

@section('heroText', 'Kelola Data Seller')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-8">
    @if(session('success'))
    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-lg mb-6 shadow-sm">
        <p class="font-semibold">✓ {{ session('success') }}</p>
    </div>
    @endif

    <div class="bg-white rounded-2xl shadow-md shadow-[#53685B]/20 p-6">
        <h2 class="text-2xl font-bold mb-6 text-[#53685B]">Daftar Seller</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border border-gray-200">
            <thead class="bg-[#53685B] text-white">
                <tr>
                    <th class="px-4 py-3 text-left">ID</th>
                    <th class="px-4 py-3 text-left">Username</th>
                    <th class="px-4 py-3 text-left">Nama</th>
                    <th class="px-4 py-3 text-left">Email</th>
                    <th class="px-4 py-3 text-left">Nama Toko</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sellers as $seller)
                <tr class="border-t hover:bg-gray-50 transition">
                    <td class="px-4 py-3">{{ $seller->id }}</td>
                    <td class="px-4 py-3">{{ $seller->username }}</td>
                    <td class="px-4 py-3">{{ $seller->first_name }} {{ $seller->last_name }}</td>
                    <td class="px-4 py-3">{{ $seller->email }}</td>
                    <td class="px-4 py-3">{{ $seller->store->store_name ?? '-' }}</td>
                    <td class="px-4 py-3">
                        <div class="flex gap-2 justify-center">
                            <a href="{{ route('admin.sellers.edit', $seller->id) }}" 
                                class="bg-[#53685B] hover:bg-[#3c4a3e] text-white px-4 py-2 rounded-lg text-xs font-semibold transition shadow-sm hover:shadow-md">
                                ✏️ Edit
                            </a>
                            <form action="{{ route('admin.sellers.destroy', $seller->id) }}" method="POST" 
                                onsubmit="return confirm('Yakin ingin menghapus seller ini? Toko dan produknya juga akan terhapus!')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                    class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-xs font-semibold transition shadow-sm hover:shadow-md">
                                    🗑️ Hapus
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                        <p class="text-lg">📭 Tidak ada data seller</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
