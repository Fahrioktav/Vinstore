import { Head } from '@inertiajs/react';
import Navbar from '../components/navbar';
import Footer from '../components/footer';

export default function MainLayout({ children, title }) {
  return (
    <div className="flex min-h-dvh flex-col bg-gray-50 text-gray-800">
      <Head title={title} />
      <Navbar />
      <main className="grow">{children}</main>
      <Footer />
    </div>
  );
}
