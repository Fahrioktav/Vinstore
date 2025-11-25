import { Form, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { UserIcon } from './icons';
import { cn } from '@/lib/utils';

const links = [
  { label: 'Home', href: '/', requireAuth: false },
  { label: 'Toko', href: '/toko', requireAuth: false },
  { label: 'Order', href: '/order', requireAuth: true },
  { label: 'Contact', href: '/contact', requireAuth: false },
];

const userMenus = [
  { label: '👤 Profil', href: '/profile' },
  { label: '🛒 Keranjang', href: '/cart' },
];

export default function Navbar() {
  const { user } = usePage().props;
  const url = usePage().url;

  const [open, setOpen] = useState(false);
  const [userMenu, setUserMenu] = useState(false);
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    window.addEventListener('scroll', () => {
      setScrolled(window.scrollY > 10);
    });
  }, []);

  return (
    <nav
      className={cn(
        'sticky top-0 left-0 z-50 w-full transition-all duration-500',
        scrolled ? 'bg-[#2F3E46]/95 shadow-lg backdrop-blur-md' : 'bg-[#2F3E46]'
      )}
    >
      {/* {-- WRAPPER TANPA MAX-W --}  */}
      <div className="flex w-full items-center justify-between px-6 py-4 md:px-12 lg:px-20 xl:px-32">
        {/* {-- LOGO --} */}
        <Link
          href={
            user?.role === 'admin'
              ? '/admin/dashboard'
              : user?.role === 'seller'
                ? '/seller/dashboard'
                : '/'
          }
          className="flex items-center gap-2"
        >
          <img
            src="/assets/Logo.png"
            alt="VINSTORE"
            className="h-12 w-12 object-contain"
          />
          <span className="font-playfair text-2xl font-bold tracking-wide text-[#E9E19E]">
            VINSTORE
          </span>
        </Link>

        {/* {-- MENU UTAMA --} */}
        <ul className="hidden items-center gap-10 text-sm font-semibold text-white md:flex">
          {links.map((link) => (
            <li key={link.label}>
              <Link
                href={link.requireAuth && !user ? '/login' : link.href}
                className="transition hover:text-[#E9E19E]"
                preserveScroll={url === link.href}
              >
                {link.label}
              </Link>
            </li>
          ))}
        </ul>

        {/* {-- MENU KANAN --} */}
        <div className="flex items-center gap-4">
          {/* {-- USER LOGIN --} */}
          {user ? (
            <div
              className="relative"
              // @click.away="userMenu = false"
            >
              <button
                onClick={() => setUserMenu((prev) => !prev)}
                className="flex items-center gap-2 rounded-full bg-[#B77C4C]/70 px-4 py-2 font-semibold text-white transition hover:bg-[#B77C4C]"
              >
                <UserIcon className="h-5 w-5" />
                {user.username}
                {/* <x-icon name="chevron-down" className="w-4 h-4" /> */}
              </button>

              {/* {-- DROPDOWN USER --} */}
              {userMenu && (
                <div className="absolute right-0 z-50 mt-3 w-44 overflow-hidden rounded-lg bg-white shadow-lg">
                  {userMenus.map((menu) => (
                    <Link
                      key={menu.label}
                      href={menu.href}
                      className="block px-4 py-2 text-gray-700 hover:bg-[#E9E19E] hover:text-black"
                    >
                      {menu.label}
                    </Link>
                  ))}
                  <Form action="/logout" method="POST">
                    <button
                      type="submit"
                      className="w-full px-4 py-2 text-left text-gray-700 hover:cursor-pointer hover:bg-[#E9E19E] hover:text-black"
                    >
                      🚪 Logout
                    </button>
                  </Form>
                </div>
              )}
            </div>
          ) : (
            <Link
              href="/login"
              className="rounded-full bg-[#E9E19E] px-5 py-2 text-sm font-semibold text-[#2F3E46] transition hover:bg-[#dcd58c]"
            >
              Login / Signup
            </Link>
          )}

          {/* {-- TOMBOL MENU MOBILE --} */}
          <button
            onClick={() => setOpen((prev) => !prev)}
            className="text-3xl text-white focus:outline-none md:hidden"
          >
            <span>{open ? '✕' : '☰'}</span>
          </button>
        </div>
      </div>

      {/* // {-- MENU MOBILE --} */}
      {open && (
        <div
          className={cn(
            'space-y-3 py-4 text-center text-white md:hidden',
            !scrolled ? 'bg-[#2F3E46]' : 'bg-transparent'
          )}
        >
          {links.map((link) => (
            <Link
              key={link.label}
              href={link.href}
              className="block hover:text-[#E9E19E]"
            >
              {link.label}
            </Link>
          ))}
        </div>
      )}
    </nav>
  );
}
