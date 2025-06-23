@extends('layouts.form')

@section('title', 'Home')

@section('heroText')
    Mau Cari Barang Antik? Di VINSTORE Aja!
@endsection

@section('showSearch')
    <span></span>
@endsection

@section('content')
@parent

{{-- Footer --}}
<footer class="bg-black text-white text-center py-6 mt-12 text-sm">
    VINSTORE.id Copyright 2025, All Rights Reserved. |
    <a href="#" class="underline">Privacy Policy</a> |
    <a href="#" class="underline">Terms</a> |
    <a href="#" class="underline">Pricing</a> |
    <a href="#" class="underline">Do not sell or share my personal info</a>
</footer

@endsection