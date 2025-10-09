@extends('layouts.form')

@section('title', 'Contact')

@section('heroText')
    Butuh Sesuatu? Hubungi Kami!
@endsection

@section('content')
@parent

<section class="px-6 md:px-20 py-16 bg-gray-50 text-gray-800">
    <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-12 items-start">
        
        {{-- Kontak Kiri --}}
        <div class="space-y-6">
            <h3 class="text-4xl font-bold text-[#4a5b4d]">Hubungi Kami</h3>
            <p class="text-gray-600 text-lg">
                Punya pertanyaan, saran, atau kendala? Tim VINSTORE siap membantu kamu!
                Hubungi kami melalui media sosial atau kirim pesan melalui formulir di samping.
            </p>

            <div class="space-y-4 mt-8">
                <div class="flex items-center gap-4 bg-white shadow-sm px-5 py-3 rounded-xl border border-gray-200 hover:shadow-md transition">
                    <img src="/icons/whatsapp.svg" alt="WhatsApp" class="w-6 h-6">
                    <span class="text-gray-700">082113472156</span>
                </div>

                <div class="flex items-center gap-4 bg-white shadow-sm px-5 py-3 rounded-xl border border-gray-200 hover:shadow-md transition">
                    <img src="/icons/instagram.svg" alt="Instagram" class="w-6 h-6">
                    <span class="text-gray-700">@VINSTORE</span>
                </div>

                <div class="flex items-center gap-4 bg-white shadow-sm px-5 py-3 rounded-xl border border-gray-200 hover:shadow-md transition">
                    <img src="/icons/twitter.svg" alt="Twitter" class="w-6 h-6">
                    <span class="text-gray-700">@VINSTORE</span>
                </div>
            </div>

            <div class="mt-8">
                <h4 class="text-xl font-semibold text-[#4a5b4d]">Lokasi Kami</h4>
                <p class="text-gray-600 mt-2">
                    Institut Teknologi Indonesia,<br>
                    Setu, Tangerang Selatan 12345, Indonesia
                </p>
            </div>
        </div>

        {{-- Formulir Kanan --}}
        <form class="bg-white p-8 rounded-2xl shadow-md border border-gray-200 space-y-5">
            <h3 class="text-2xl font-semibold text-[#4a5b4d] mb-3">Kirim Pesan</h3>

            <input 
                type="text" 
                placeholder="Nama Lengkap" 
                class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#5A6E5A] transition"
            >

            <input 
                type="email" 
                placeholder="Alamat Email" 
                class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#5A6E5A] transition"
            >

            <textarea 
                rows="5" 
                placeholder="Tulis pesanmu di sini..." 
                class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#5A6E5A] transition"
            ></textarea>

            <button 
                type="submit" 
                class="w-full bg-[#5A6E5A] hover:bg-[#6d7f6d] text-white py-3 rounded-lg font-semibold tracking-wide transition"
            >
                Kirim Pesan
            </button>
        </form>

    </div>
</section>

@endsection
