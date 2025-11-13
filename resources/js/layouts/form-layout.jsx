import { Head, usePage } from '@inertiajs/react';
import Navbar from '../components/navbar';
import Footer from '../components/footer';

export default function FormLayout({ children, title }) {
  const { sessions } = usePage().props;

  return (
    <div className="flex min-h-dvh flex-col bg-gray-50 text-gray-800">
      <Head title={title} />
      <Navbar />

      {/* {-- Konten Halaman Full Height --} */}
      <main className="flex min-h-screen grow flex-col items-center justify-center bg-gradient-to-br from-[#2F3E46] via-[#354F52] to-[#B77C4C] bg-cover bg-fixed bg-center">
        {/* {-- Flash Messages --} */}
        {(sessions.success || sessions.error) && (
          <div className="mx-auto mt-4 w-full max-w-3xl px-4">
            {sessions.success && (
              <div className="mb-4 rounded border border-green-400 bg-green-100 px-4 py-3 text-sm text-green-700">
                {sessions.success}
              </div>
            )}

            {sessions.error && (
              <div className="mb-4 rounded border border-red-400 bg-red-100 px-4 py-3 text-sm text-red-700">
                {sessions.error}
              </div>
            )}
          </div>
        )}
        {children}
      </main>
      <Footer />
    </div>
  );
}
