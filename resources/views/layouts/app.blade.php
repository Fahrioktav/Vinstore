<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <title>@yield('title', 'VINSTORE')</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">


    {{-- Hanya panggil sekali --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>


<body class="relative bg-gradient-to-br from-[#2F3E46] via-[#354F52] to-[#B77C4C] text-gray-800 font-poppins min-h-screen">
    
    {{-- Elemen Dekoratif Background --}}
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-0 left-0 w-72 h-72 bg-[#E9E19E]/20 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-[#B77C4C]/25 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#52796F]/15 rounded-full blur-3xl"></div>
    </div>

    {{-- Navbar --}}
    <x-navbar />

    {{-- Hero Section --}}
    @hasSection('heroText')
    <section class="relative text-white pt-32 pb-20 -mt-[72px] z-10">
        <div class="relative max-w-7xl mx-auto px-6 md:px-12 text-center">
            <h1 class="text-4xl md:text-5xl font-bold font-playfair mb-4 drop-shadow-lg">
                @yield('heroText')
            </h1>
            <div class="w-24 h-1 bg-[#E9E19E] mx-auto rounded-full shadow-lg"></div>
        </div>
    </section>
    @endif

    {{-- Konten Utama --}}
    <main class="relative z-10 @hasSection('heroText') -mt-10 @endif">
        @yield('content')
    </main>

</body>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const userButton = document.getElementById('userButton');
        const dropdownMenu = document.getElementById('dropdownMenu');

        if (userButton && dropdownMenu) {
            userButton.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownMenu.classList.toggle('hidden');
            });

            // Tutup dropdown kalau klik di luar
            document.addEventListener('click', function(e) {
                if (!dropdownMenu.contains(e.target) && !userButton.contains(e.target)) {
                    dropdownMenu.classList.add('hidden');
                }
            });
        }
    });
</script>


</html>