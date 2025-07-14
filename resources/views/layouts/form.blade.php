<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite('resources/css/app.css')

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="flex flex-col min-h-screen bg-gray-100 font-poppins">

    {{-- Navbar --}}
    @include('components.navbar')

    {{-- Flash Messages --}}
    <div class="max-w-7xl mx-auto px-4 py-4">
        @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            {{ session('error') }}
        </div>
        @endif
    </div>

    {{-- Konten Halaman --}}
    <main class="flex-1">
        <div class="max-w-7xl mx-auto px-4 py-6">
            @yield('content')
        </div>
    </main>

    {{-- Footer Full Width --}}
    <footer class="w-full bg-black text-white text-center py-6 text-sm">
        VINSTORE.id Copyright 2025, All Rights Reserved. |
        <a href="#" class="underline">Privacy Policy</a> |
        <a href="#" class="underline">Terms</a> |
        <a href="#" class="underline">Pricing</a> |
        <a href="#" class="underline">Do not sell or share my personal info</a>
    </footer>

</body>

</html>
