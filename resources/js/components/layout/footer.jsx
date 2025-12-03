const links = [
  { label: 'Privacy Policy', href: '#' },
  { label: 'Terms', href: '#' },
  // { label: 'Pricing', href: '#' },
  // { label: 'Do not sell or share my personal info', href: '#' },
];

export default function Footer() {
  return (
    <footer className="w-full bg-black py-3 text-center text-xs text-white [&>*]:px-[1ch]">
      <span>VINSTORE.id © 2025 — All Rights Reserved</span>
      <>
        {links.map((link) => (
          <span key={link.label} className="border-l">
            <a href={link.href} className="hover:underline">
              {link.label}
            </a>
          </span>
        ))}
      </>
    </footer>
  );
}
