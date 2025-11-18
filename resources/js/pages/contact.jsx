import { Form, usePage } from '@inertiajs/react';
import FormLayout from '../layouts/form-layout';

const contacts = [
  { imgSrc: '/icons/whatsapp.svg', imgAlt: 'WhatsApp', label: '082113472156' },
  { imgSrc: '/icons/instagram.svg', imgAlt: 'Instagram', label: '@VINSTORE' },
  { imgSrc: '/icons/twitter.svg', imgAlt: 'Twitter', label: '@VINSTORE' },
];

const inputClassName =
  'w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#5A6E5A] transition';

export default function ContactPage() {
  const { auth } = usePage().props;

  return (
    <section className="bg-gray-50 px-6 py-16 text-gray-800 md:px-20">
      <div className="mx-auto grid max-w-6xl items-start gap-12 md:grid-cols-2">
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
              <div
                key={idx}
                className="flex items-center gap-4 rounded-xl border border-gray-200 bg-white px-5 py-3 shadow-sm transition hover:shadow-md"
              >
                <img
                  src={contact.imgSrc}
                  alt={contact.imgAlt}
                  className="h-6 w-6"
                />
                <span className="text-gray-700">{contact.label}</span>
              </div>
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
        <Form className="space-y-5 rounded-2xl border border-gray-200 bg-white p-8 shadow-md">
          <h3 className="mb-3 text-2xl font-semibold text-[#4a5b4d]">
            Kirim Pesan
          </h3>

          <input
            type="text"
            placeholder="Nama Lengkap"
            className={inputClassName}
          />

          <input
            type="email"
            placeholder="Alamat Email"
            className={inputClassName}
          />

          <textarea
            rows="5"
            placeholder="Tulis pesanmu di sini..."
            className={inputClassName}
          ></textarea>

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
