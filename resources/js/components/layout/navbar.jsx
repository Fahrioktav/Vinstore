import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
  CartIcon,
  CloseIcon,
  HelpIcon,
  LogoutIcon,
  MenuIcon,
  MoneyIcon,
  ProfileIcon,
} from '@/components/icons';
import { cn, getUserImage } from '@/lib/utils';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
} from '../ui/dropdown-menu';
import { DropdownMenuTrigger } from '@radix-ui/react-dropdown-menu';

const links = {
  default: [
    { label: 'Home', href: '/' },
    { label: 'Toko', href: '/toko' },
    { label: 'Produk', href: '/products' },
    { label: 'Lelang', href: '/auctions' },
    { label: 'Riwayat Order', href: '/order' },
  ],
  seller: [
    { label: 'Home', href: '/' },
    { label: 'Tukar Tambah', href: '/seller/tukar-tambah' },
    { label: 'Lelang', href: '/auctions' },
  ],
  admin: [
    { label: 'Dashboard', href: '/admin/dashboard' },
    { label: 'Lelang', href: '/admin/auctions' },
    { label: 'Refund', href: '/admin/refunds' },
    { label: 'Deposit', href: '/admin/deposit-lelang' },
    { label: 'Pencairan', href: '/admin/payouts' },
    { label: 'Pendapatan', href: '/admin/pendapatan' },
  ],
  validator: [{ label: 'Dashboard', href: '/validator/dashboard' }],
};

// Ikon disimpan sebagai komponen, bukan string emoji, agar ukuran dan warnanya
// mengikuti kelas Tailwind di tempat ia dirender.
const baseMenus = [{ label: 'Profil', href: '/profile', icon: ProfileIcon }];
const bantuanUser = { label: 'Bantuan', href: '/bantuan', icon: HelpIcon };
const menus = {
  default: [
    ...baseMenus,
    bantuanUser,
    { label: 'Keranjang', href: '/cart', icon: CartIcon },
    // Deposit lelang jarang dibuka — hanya saat pembeli mengikuti lelang
    // bernilai tinggi — jadi tempatnya di dropdown profil, bukan navbar utama.
    { label: 'Deposit Lelang', href: '/deposit-lelang', icon: MoneyIcon },
  ],
  seller: [...baseMenus, bantuanUser],
  admin: baseMenus,
  validator: [...baseMenus, bantuanUser],
};

export default function Navbar() {
  const { user } = usePage().props;
  const url = usePage().url;

  const displayMenus = menus[user?.role] ?? menus['default'];

  const roleLinks = links[user?.role] ?? links['default'];
  const displayLinks = roleLinks;

  const [open, setOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    window.addEventListener('scroll', () => {
      setScrolled(window.scrollY > 10);
    });
  }, []);

  const handleLogout = () => {
    // Kosongkan cache service worker supaya sisa data pengguna ini tidak
    // tersaji ke pengguna berikutnya di perangkat yang sama.
    navigator.serviceWorker?.controller?.postMessage({ type: 'CLEAR_CACHE' });

    router.post('/logout');
  };

  return (
    <nav
      className={cn(
        'sticky top-0 left-0 z-50 w-full transition-all duration-500',
        scrolled ? 'bg-[#2F3E46]/95 shadow-lg backdrop-blur-md' : 'bg-[#2F3E46]'
      )}
    >
      {/* {-- WRAPPER TANPA MAX-W --}  */}
      <div className="flex w-full items-center justify-between gap-2 px-4 py-3 sm:px-6 sm:py-4 md:px-16">
        {/* {-- LOGO --} */}
        <Link
          href={
            user?.role === 'admin'
              ? '/admin/dashboard'
              : user?.role === 'seller'
                ? '/seller/dashboard'
                : user?.role === 'validator'
                  ? '/validator/dashboard'
                  : '/'
          }
          className="flex items-center gap-2"
        >
          <img
            src="/assets/Logo.png"
            alt="VINSTORE"
            className="h-9 w-9 object-contain sm:h-12 sm:w-12"
          />
          <span className="font-playfair text-lg font-bold tracking-wide text-[#E9E19E] sm:text-2xl">
            VINSTORE
          </span>
        </Link>

        {/* {-- MENU UTAMA --} */}
        <ul className="hidden items-center gap-5 text-sm font-semibold text-white transition-all md:flex lg:gap-10">
          {displayLinks.map((link) => (
            <li key={link.label}>
              <Link
                href={link.href}
                className="transition hover:text-[#E9E19E]"
                preserveScroll={url === link.href}
              >
                {link.label}
              </Link>
            </li>
          ))}
        </ul>

        {/* {-- MENU KANAN --} */}
        <div className="flex items-center gap-2 sm:gap-4">
          {/* {-- USER LOGIN --} */}
          {user ? (
            // DROPDOWN USER
            <DropdownMenu modal={false}>
              <DropdownMenuTrigger asChild>
                <button className="flex shrink-0 items-center gap-2 rounded-full bg-[#B77C4C]/70 px-2 py-1.5 font-semibold text-white transition hover:bg-[#B77C4C] sm:px-3 sm:py-2">
                  <img
                    src={getUserImage(user)}
                    alt={user.username}
                    className="h-8 w-8 rounded-full border-2 border-white/50 object-cover"
                  />
                  <span className="hidden sm:inline">{user.username}</span>
                  {/* <x-icon name="chevron-down" className="w-4 h-4" /> */}
                </button>
              </DropdownMenuTrigger>
              <DropdownMenuContent className="shadow-lgshadow-lg mt-2 w-44 border-0 p-0">
                {displayMenus.map((menu, i) => (
                  <DropdownMenuItem
                    key={i}
                    className={DropdownMenuItemStyle}
                    asChild
                  >
                    <Link href={menu.href} className="flex items-center gap-2">
                      <menu.icon className="h-4 w-4" />
                      {menu.label}
                    </Link>
                  </DropdownMenuItem>
                ))}
                <DropdownMenuItem className={DropdownMenuItemStyle} asChild>
                  <button
                    type="submit"
                    className="flex w-full items-center gap-2"
                    onClick={handleLogout}
                  >
                    <LogoutIcon className="h-4 w-4" />
                    Logout
                  </button>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          ) : (
            <Link
              href="/login"
              className="rounded-full bg-[#E9E19E] px-3 py-1.5 text-xs font-semibold whitespace-nowrap text-[#2F3E46] transition hover:bg-[#dcd58c] sm:px-5 sm:py-2 sm:text-sm"
            >
              Login / Signup
            </Link>
          )}

          {/* {-- TOMBOL MENU MOBILE --} */}
          <button
            onClick={() => setOpen((prev) => !prev)}
            className="text-white focus:outline-none md:hidden"
            aria-label={open ? 'Tutup menu' : 'Buka menu'}
          >
            {open ? (
              <CloseIcon className="h-7 w-7" />
            ) : (
              <MenuIcon className="h-7 w-7" />
            )}
          </button>
        </div>
      </div>

      {/* // {-- MENU MOBILE --} */}
      {open && (
        <div className="max-h-[calc(100vh-4rem)] overflow-y-auto border-t border-white/10 bg-[#2F3E46] py-3 text-white md:hidden">
          {displayLinks.map((link) => (
            <Link
              key={link.label}
              href={link.href}
              onClick={() => setOpen(false)}
              className="block px-6 py-2.5 text-sm font-semibold hover:bg-white/10 hover:text-[#E9E19E]"
            >
              {link.label}
            </Link>
          ))}

          {/* Menu pengguna ikut ditampilkan di sini. Dropdown-nya sendiri
              terlalu sempit dan mudah terlewat di layar kecil. */}
          {user && (
            <>
              <div className="my-2 border-t border-white/10" />
              {displayMenus.map((menu) => (
                <Link
                  key={menu.href}
                  href={menu.href}
                  onClick={() => setOpen(false)}
                  className="flex items-center gap-2 px-6 py-2.5 text-sm font-semibold hover:bg-white/10 hover:text-[#E9E19E]"
                >
                  <menu.icon className="h-4 w-4" />
                  {menu.label}
                </Link>
              ))}
              <button
                type="button"
                onClick={handleLogout}
                className="flex w-full items-center gap-2 px-6 py-2.5 text-left text-sm font-semibold text-red-300 hover:bg-white/10"
              >
                <LogoutIcon className="h-4 w-4" />
                Logout
              </button>
            </>
          )}
        </div>
      )}
    </nav>
  );
}

const DropdownMenuItemStyle = cn(
  'rounded-none px-4 py-2 text-gray-700 focus:cursor-pointer focus:bg-[#E9E19E] focus:text-black'
);
