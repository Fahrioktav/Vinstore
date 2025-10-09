<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'VINSTORE')</title>

    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    {{-- Alpine.js untuk interaksi ringan --}}
    <script src="//unpkg.com/alpinejs" defer></script>

    {{-- Vite CSS (Tailwind) --}}
    @vite('resources/css/app.css')

    {{-- Style tambahan --}}
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="min-h-screen flex flex-col bg-gray-50 font-poppins">

    {{-- Navbar --}}
    <header class="w-full">
        @include('components.navbar')
    </header>

    {{-- Flash Messages --}}
    @if(session('success') || session('error'))
    <div class="max-w-3xl mx-auto w-full px-4 mt-4">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-sm">
                {{ session('error') }}
            </div>
        @endif
    </div>
    @endif

    {{-- Konten Halaman Full Height --}}
    <main class="flex-1 flex flex-col justify-center items-center w-full min-h-screen bg-gradient-to-br from-[#2F3E46] via-[#354F52] to-[#B77C4C]">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="w-full bg-black text-white text-center py-6 text-sm">
        VINSTORE.id © 2025 — All Rights Reserved |
        <a href="#" class="underline">Privacy Policy</a> |
        <a href="#" class="underline">Terms</a> |
        <a href="#" class="underline">Pricing</a> |
        <a href="#" class="underline">Do not sell or share my personal info</a>
    </footer>

</body>
</html>
