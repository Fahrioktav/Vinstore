<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">


    {{-- Hanya panggil sekali --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>


<body class="bg-gray-50 text-gray-800">

    {{-- Navbar --}}
    <x-navbar />


    {{-- Konten Utama --}}
    <main>
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