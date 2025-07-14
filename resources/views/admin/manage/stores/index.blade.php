@extends('layouts.app')

@section('heroText', 'Kelola Data Toko')

@section('content')
<div class="max-w-6xl mx-auto px-4 mt-10">
    <h2 class="text-xl font-bold mb-4">Daftar Toko</h2>
    <table class="w-full border text-sm">
        <thead class="bg-gray-200">
            <tr>
                <th class="p-2 border">ID</th>
                <th class="p-2 border">Username</th>
                <th class="p-2 border">Email</th>
                <th class="p-2 border">Role</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
            <tr class="text-center">
                <td class="p-2 border">{{ $user->id }}</td>
                <td class="p-2 border">{{ $user->username }}</td>
                <td class="p-2 border">{{ $user->email }}</td>
                <td class="p-2 border">{{ $user->role }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
