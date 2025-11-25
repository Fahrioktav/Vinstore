import { Head, usePage } from '@inertiajs/react';
import Navbar from '@/components/navbar';
import Footer from '@/components/footer';

export default function FormLayout({ children, title }) {
  return (
    <div className="font-poppins flex min-h-dvh flex-col bg-gray-50 text-gray-800">
      <Head title={title} />
      <Navbar />

      {/* {-- Konten Halaman Full Height --} */}
      <main className="flex min-h-screen grow flex-col items-center justify-center bg-gradient-to-br from-[#2F3E46] via-[#354F52] to-[#B77C4C] bg-cover bg-fixed bg-center">
        {children}
      </main>
      <Footer />
    </div>
  );
}
