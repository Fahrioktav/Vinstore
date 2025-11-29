import { Head, usePage } from '@inertiajs/react';
import Navbar from '@/components/layout/navbar';
import Footer from '@/components/layout/footer';
import { Toaster } from '@/components/ui/sonner';
import { ThemeProvider } from 'next-themes';

export default function MainLayout({ children, title, heroText }) {
  return (
    <ThemeProvider attribute="class" defaultTheme="light" enableSystem={false}>
      <div className="font-poppins relative flex min-h-screen flex-col bg-gradient-to-br from-[#2F3E46] via-[#354F52] to-[#B77C4C] text-gray-800">
        <Head title={title} />
        <Navbar />
        <Toaster position="top-center" richColors />

        {/* {-- Hero Section --} */}
        {/* {heroText && (
          <section className="relative z-10 -mt-[72px] pt-32 pb-20 text-white">
            <div className="relative mx-auto max-w-7xl px-6 text-center md:px-12">
              <h1 className="font-playfair mb-4 text-4xl font-bold drop-shadow-lg md:text-5xl">
                {heroText}
              </h1>
              <div className="mx-auto h-1 w-24 rounded-full bg-[#E9E19E] shadow-lg"></div>
            </div>
          </section>
        )} */}

        <main className="grow">{children}</main>
        <Footer />
      </div>
    </ThemeProvider>
  );
}
