import { Form, usePage } from '@inertiajs/react';
import FormLayout from '@/layouts/form-layout';
import { cn } from '@/lib/utils';

const contacts = [
  {
    imgSrc: '/assets/icons/social-whatsapp.png',
    imgAlt: 'WhatsApp',
    label: '082113472156',
    href: 'https://wa.me',
  },
  {
    imgSrc: '/assets/icons/social-instagram.png',
    imgAlt: 'Instagram',
    label: 'Instagram',
    href: 'https://instagram.com/vinstore.id',
  },
  {
    imgSrc: '/assets/icons/social-twitter.png',
    imgAlt: 'Twitter',
    label: 'Twitter',
    href: 'https://twitter.com/vinstore.id',
  },
];

const inputClassName = cn(
  'w-full rounded-lg border border-gray-300 px-4 py-3 transition focus:ring-2 focus:ring-[#5A6E5A] focus:outline-none'
);

export default function ContactPage() {
  const { user } = usePage().props;

  return (
    <section className="relative my-auto flex w-full items-center justify-center overflow-hidden px-6 py-12">
      {/* <div className="my-auto w-full max-w-5xl rounded-2xl bg-white p-8 shadow-xl"></div> */}
      <div className="my-auto grid max-w-6xl items-start gap-12 rounded-2xl bg-gray-50 p-16 shadow-xl md:grid-cols-2">
        {/* {-- Kontak Kiri --} */}
        <div className="space-y-6">
          <h3 className="text-4xl font-bold text-[#4a5b4d]">Hubungi Kami</h3>
          <p className="text-lg text-gray-600">
            Punya pertanyaan, saran, atau kendala? Tim VINSTORE siap membantu
            kamu! Hubungi kami melalui media sosial atau kirim pesan melalui
            formulir di samping.
          </p>

          <div className="mt-8 space-y-4">
            {contacts.map((contact, idx) => (
              <a
                key={idx}
                href={contact.href}
                target="_blank"
                className="flex items-center gap-4 rounded-xl border border-gray-200 bg-white px-5 py-3 shadow-sm transition hover:shadow-md"
              >
                <img
                  src={contact.imgSrc}
                  alt={contact.imgAlt}
                  className="h-6 w-6"
                />
                <span className="text-gray-700">{contact.label}</span>
              </a>
            ))}
          </div>

          <div className="mt-8">
            <h4 className="text-xl font-semibold text-[#4a5b4d]">
              Lokasi Kami
            </h4>
            <p className="mt-2 text-gray-600">
              Institut Teknologi Indonesia,
              <br />
              Setu, Tangerang Selatan 12345, Indonesia
            </p>
          </div>
        </div>

        {/* {-- Formulir Kanan --} */}
        <Form className="space-y-5 rounded-2xl border border-gray-200 bg-white p-8 shadow-md md:mt-10">
          <h3 className="mb-3 text-2xl font-semibold text-[#4a5b4d]">
            Kirim Pesan
          </h3>

          <input
            type="text"
            defaultValue={user ? user?.first_name + ' ' + user?.last_name : ''}
            placeholder="Nama Lengkap"
            className={inputClassName}
            readOnly={!!user}
          />

          <input
            type="email"
            placeholder="Alamat Email"
            defaultValue={user?.email}
            className={inputClassName}
            readOnly={!!user}
          />

          <textarea
            rows="5"
            placeholder="Tulis pesanmu di sini..."
            className={cn(inputClassName, 'max-h-40')}
          />

          <button
            type="submit"
            className="w-full rounded-lg bg-[#5A6E5A] py-3 font-semibold tracking-wide text-white transition hover:bg-[#6d7f6d]"
          >
            Kirim Pesan
          </button>
        </Form>
      </div>
    </section>
  );
}

ContactPage.layout = (page) => <FormLayout title="Contact">{page}</FormLayout>;
