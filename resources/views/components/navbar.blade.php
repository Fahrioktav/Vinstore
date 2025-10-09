<nav x-data="{ open: false, userMenu: false, scrolled: false }"
     x-init="window.addEventListener('scroll', () => scrolled = window.scrollY > 10)"
     :class="scrolled ? 'bg-[#2F3E46]/95 shadow-lg backdrop-blur-md' : 'bg-transparent'"
     class="fixed w-screen top-0 left-0 z-50 transition-all duration-500">

    {{-- WRAPPER TANPA MAX-W --}} 
    <div class="w-full flex justify-between items-center px-6 md:px-12 lg:px-20 xl:px-32 py-4">

        {{-- LOGO --}}
        <a href="{{ url('/') }}" class="flex items-center gap-2">
            <img src="{{ asset('assets/Logo.png') }}" alt="VINSTORE" class="w-12 h-12 object-contain">
            <span class="text-2xl font-playfair font-bold tracking-wide text-[#E9E19E]">VINSTORE</span>
        </a>

        {{-- MENU UTAMA --}}
        <ul class="hidden md:flex items-center gap-10 font-semibold text-sm text-white">
            <li><a href="/" class="hover:text-[#E9E19E] transition">Home</a></li>
            <li><a href="{{ route('toko.index') }}" class="hover:text-[#E9E19E] transition">Toko</a></li>
            <li><a href="{{ route('order') }}" class="hover:text-[#E9E19E] transition">Order</a></li>
            <li><a href="/contact" class="hover:text-[#E9E19E] transition">Contact</a></li>
        </ul>

        {{-- MENU KANAN --}}
        <div class="flex items-center gap-4">

            {{-- USER LOGIN --}}
            @auth
            <div class="relative" @click.away="userMenu = false">
                <button @click="userMenu = !userMenu"
                    class="flex items-center gap-2 text-white font-semibold bg-[#B77C4C]/70 hover:bg-[#B77C4C] px-4 py-2 rounded-full transition">
                    <x-icon name="user" class="w-5 h-5" />
                    {{ Auth::user()->username }}
                    <x-icon name="chevron-down" class="w-4 h-4" />
                </button>

                {{-- DROPDOWN USER --}}
                <div x-show="userMenu" x-transition
                    class="absolute right-0 mt-3 w-44 bg-white rounded-lg shadow-lg overflow-hidden z-50">
                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-gray-700 hover:bg-[#E9E19E] hover:text-black">👤 Profil</a>
                    <a href="{{ route('cart.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-[#E9E19E] hover:text-black">🛒 Keranjang</a>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-gray-700 hover:bg-[#E9E19E] hover:text-black">
                            🚪 Logout
                        </button>
                    </form>
                </div>
            </div>
            @else
            <a href="{{ route('login.form') }}"
                class="text-sm font-semibold text-[#2F3E46] bg-[#E9E19E] hover:bg-[#dcd58c] px-5 py-2 rounded-full transition">
                Login / Signup
            </a>
            @endauth

            {{-- TOMBOL MENU MOBILE --}}
            <button @click="open = !open" class="md:hidden text-white text-3xl focus:outline-none">
                <template x-if="!open">☰</template>
                <template x-if="open">✕</template>
            </button>
        </div>
    </div>

    {{-- MENU MOBILE --}}
    <div x-show="open" x-transition
        class="md:hidden bg-[#2F3E46]/95 backdrop-blur-md text-white py-4 space-y-3 text-center">
        <a href="/" class="block hover:text-[#E9E19E]">Home</a>
        <a href="{{ route('toko.index') }}" class="block hover:text-[#E9E19E]">Toko</a>
        <a href="{{ route('order') }}" class="block hover:text-[#E9E19E]">Order</a>
        <a href="/contact" class="block hover:text-[#E9E19E]">Contact</a>
    </div>
</nav>
