<nav class="flex flex-col justify-between items-center bg-cover px-7 py-2" style="background-image: url('/assets/background.jpg'); background-position: center;">
    <div class="h-40 flex justify-between items-center w-full">
        {{-- Logo --}}
        <div>
            <img src="{{ asset('assets/Logo.png') }}" alt="Logo" class="w-full h-20">
        </div>

        {{-- Navigasi Menu --}}
        <div>
            <ul class="flex text-md gap-10">
                <li class="font-poppins font-semibold hover:cursor-pointer hover:bg-[#E9E19E] hover:text-black transition-all duration-200 p-2 rounded-3xl w-32 flex justify-center text-white">
                    <a href="/index">Home</a>
                </li>
                <li class="font-poppins font-semibold hover:cursor-pointer hover:bg-[#E9E19E] hover:text-black transition-all duration-200 p-2 rounded-3xl w-32 flex justify-center text-white">
                    <a href="/toko">Toko</a>
                </li>
                <li class="font-poppins font-semibold hover:cursor-pointer hover:bg-[#E9E19E] hover:text-black transition-all duration-200 p-2 rounded-3xl w-32 flex justify-center text-white">
                    <a href="/order">Order</a>
                </li>
                <li class="font-poppins font-semibold hover:cursor-pointer hover:bg-[#E9E19E] hover:text-black transition-all duration-200 p-2 rounded-3xl w-32 flex justify-center text-white">
                    <a href="/contact">Contact</a>
                </li>
            </ul>
        </div>

        {{-- Tombol Login --}}
        <div class="flex items-center">
            <a href="/login"
                class="flex items-center gap-2 border-2 border-white text-white px-4 py-2 rounded-full font-poppins hover:bg-[#E9E19E] hover:text-black transition-all duration-200">
                <x-icon name="user" class="w-4 h-4 text-white" />
                <span class="text-sm">Login/Signup</span>
            </a>
        </div>
    </div>

    {{-- Hero Section --}}
    <section class="pb-10">
        {{-- Hero Text --}}
        <div class="text-center text-white text-xl md:text-3xl font-readex">
            @yield('heroText', 'Selamat Datang di VINSTORE!')
        </div>

        {{-- Search Bar --}}
        @hasSection('showSearch')
        <div class="flex justify-center mt-6">
            <div class="flex w-full max-w-xl bg-white rounded-full overflow-hidden">
                <input type="text" class="font-poppins w-full px-4 py-3 focus:outline-none" placeholder="Cari barang yang kamu mau">
                <button class="flex items-center gap-2 rounded-4xl font-poppins font-bold bg-[#E9E19E] text-black px-10 py-2 hover:bg-[#e9e19e]/90 hover:cursor-pointer">
                    <x-icon name="search" class="w-5 h-5 text-black" />
                    Search
                </button>
            </div>
        </div>
        @endif
    </section>
</nav>