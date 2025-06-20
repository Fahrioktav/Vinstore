<nav class="flex flex-col justify-between items-center bg-cover h-full px-7 py-2" style="background-image: url('/assets/background.jpg');">
    <div class="h-40 flex justify-between items-center w-full">
        <div>
            <img src="{{ asset('assets/Logo.png') }}" alt="Logo" class="w-full h-20">
        </div>
        <div>
            <ul class="flex text-md gap-10">
                <li class="font-poppins hover:cursor-pointer hover:bg-[#E9E19E] hover:text-black transition-all duration-200 p-2 rounded-3xl w-32 flex justify-center text-white">
                    <a href="">Home</a>
                </li>
                <li class="font-poppins hover:cursor-pointer hover:bg-[#E9E19E] hover:text-black transition-all duration-200 p-2 rounded-3xl w-32 flex justify-center text-white">
                    <a href="">Toko</a>
                </li>
                <li class="font-poppins hover:cursor-pointer hover:bg-[#E9E19E] hover:text-black transition-all duration-200 p-2 rounded-3xl w-32 flex justify-center text-white">
                    <a href="">Order</a>
                </li>
                <li class="font-poppins hover:cursor-pointer hover:bg-[#E9E19E] hover:text-black transition-all duration-200 p-2 rounded-3xl w-32 flex justify-center text-white">
                    <a href="/contact">Contact</a>
                </li>
            </ul>
        </div>
        <div>
            <a class="border p-2 rounded-3xl font-poppins hover:cursor-pointer hover:bg-[#E9E19E] hover:text-black transition-all duration-200 w-32 flex justify-center text-white" href="/login">
                <p class="text-md">Login</p>
            </a>
        </div>
    </div>    
    {{-- Hero Section --}}
    <section class="pb-10">
        <div class="text-center text-white text-xl md:text-3xl font-readex">
            Males Ke Pasar Barang Antik? Pesan VINSTORE Aja!
        </div>
    </section>

</nav>
