@section('heroText')
    Need Something? Contact Us!
@endsection

@extends('layouts.form')
@section('title', 'Contact')

@section('content')
@parent

{{-- Konten Utama --}}
<section class="px-6 md:px-20 py-10 grid md:grid-cols-2 gap-10">
    {{-- Kontak Kiri --}}
    <div class="space-y-5">
        <h3 class="text-3xl font-bold">GET IN TOUCH</h3>

        <h4 class="text-xl font-semibold mt-5">Our Contact</h4>

        <div class="flex items-center gap-4 bg-[#5A6E5A] text-white px-4 py-2 rounded">
            <img src="/icons/whatsapp.svg" alt="WhatsApp" class="w-6 h-6">
            <span>082113472156</span>
        </div>

        <div class="flex items-center gap-4 bg-[#5A6E5A] text-white px-4 py-2 rounded">
            <img src="/icons/instagram.svg" alt="Instagram" class="w-6 h-6">
            <span>VINSTORE</span>
        </div>

        <div class="flex items-center gap-4 bg-[#5A6E5A] text-white px-4 py-2 rounded">
            <img src="/icons/twitter.svg" alt="Twitter" class="w-6 h-6">
            <span>VINSTORE</span>
        </div>

        <div class="mt-6">
            <h4 class="font-semibold">Location</h4>
            <p>777 Pamulang Ave, Thackerville, OK 73459,<br>United States</p>
        </div>
    </div>

    {{-- Formulir Kanan --}}
    <form class="space-y-5">
        <input type="text" placeholder="Your Name" class="w-full border border-gray-400 px-4 py-3 rounded">
        <input type="email" placeholder="Your Email" class="w-full border border-gray-400 px-4 py-3 rounded">
        <textarea rows="5" placeholder="Your Message" class="w-full border border-gray-400 px-4 py-3 rounded"></textarea>
        <button type="submit" class="bg-[#5A6E5A] text-white px-6 py-2 rounded hover:bg-opacity-90 transition">Submit</button>
    </form>
</section>

@endsection
