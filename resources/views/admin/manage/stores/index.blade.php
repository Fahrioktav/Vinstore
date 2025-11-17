@extends('layouts.app')

@section('heroText', 'Kelola Data Toko')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-8">
    @if(session('success'))
    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-lg mb-6 shadow-sm">
        <p class="font-semibold">✓ {{ session('success') }}</p>
    </div>
    @endif

    <div class="bg-white rounded-2xl shadow-md shadow-[#53685B]/20 p-6">
        <h2 class="text-2xl font-bold mb-6 text-[#53685B]">Daftar Toko</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border border-gray-200">
            <thead class="bg-[#53685B] text-white">
                <tr>
                    <th class="px-4 py-3 text-left">ID</th>
                    <th class="px-4 py-3 text-left">Nama Toko</th>
                    <th class="px-4 py-3 text-left">Pemilik</th>
                    <th class="px-4 py-3 text-left">Kategori</th>
                    <th class="px-4 py-3 text-left">Lokasi</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stores as $store)
                <tr class="border-t hover:bg-gray-50 transition">
                    <td class="px-4 py-3">{{ $store->id }}</td>
                    <td class="px-4 py-3 font-semibold">{{ $store->store_name }}</td>
                    <td class="px-4 py-3">{{ $store->user->first_name }} {{ $store->user->last_name }}</td>
                    <td class="px-4 py-3">{{ $store->category }}</td>
                    <td class="px-4 py-3">{{ $store->location ?? '-' }}</td>
                    <td class="px-4 py-3">
                        <div class="flex gap-2 justify-center">
                            <a href="{{ route('admin.stores.edit', $store->id) }}" 
                                class="bg-[#53685B] hover:bg-[#3c4a3e] text-white px-4 py-2 rounded-lg text-xs font-semibold transition shadow-sm hover:shadow-md">
                                ✏️ Edit
                            </a>
                            <form action="{{ route('admin.stores.destroy', $store->id) }}" method="POST" 
                                onsubmit="return confirm('Yakin ingin menghapus toko ini? Semua produknya juga akan terhapus!')">
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
                        <p class="text-lg">🟪 Tidak ada data toko</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
