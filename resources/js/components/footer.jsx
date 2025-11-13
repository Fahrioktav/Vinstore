import { Link } from '@inertiajs/react';

const links = [
  { label: 'Privacy Policy', href: '#' },
  { label: 'Terms', href: '#' },
  { label: 'Pricing', href: '#' },
  { label: 'Do not sell or share my personal info', href: '#' },
];

export default function Footer() {
  return (
    <footer className="w-full bg-black py-6 text-center text-sm text-white">
      <span>VINSTORE.id © 2025 — All Rights Reserved</span> |{' '}
      {links.map((link, idx) => (
        <span key={link.label}>
          <Link href={link.href} className="underline">
            {link.label}
          </Link>
          {idx < links.length - 1 && ' | '}
        </span>
      ))}
    </footer>
  );
}
