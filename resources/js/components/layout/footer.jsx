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
          <a
            key={link.label}
            href={link.href}
            className="underline [:first-of-type]:border-l [&:not(:last-of-type)]:border-r"
          >
            {link.label}
          </a>
        ))}
      </>
    </footer>
  );
}
