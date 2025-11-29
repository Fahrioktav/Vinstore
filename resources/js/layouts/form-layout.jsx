import { Head, usePage } from '@inertiajs/react';
import Navbar from '@/components/layout/navbar';
import Footer from '@/components/layout/footer';
import { Toaster } from '@/components/ui/sonner';
import { ThemeProvider } from 'next-themes';

export default function FormLayout({ children, title }) {
  return (
    <ThemeProvider attribute="class" defaultTheme="light" enableSystem={false}>
      <div className="font-poppins flex min-h-screen flex-col bg-gray-50 text-gray-800">
        <Head title={title} />
        <Navbar />
        <Toaster />

        {/* {-- Konten Halaman Full Height --} */}
        <main className="flex grow flex-col items-center bg-gradient-to-br from-[#2F3E46] via-[#354F52] to-[#B77C4C] bg-cover bg-fixed bg-center">
          {children}
        </main>
        <Footer />
      </div>
    </ThemeProvider>
  );
}
