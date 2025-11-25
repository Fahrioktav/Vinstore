import { Form, Link, useForm } from '@inertiajs/react';
import FormLayout from '@/layouts/main-layout';

export default function RegisterPage() {
  const {} = useForm();

  return (
    // {-- Section Background --}
    <section className="relative flex min-h-screen w-full items-center justify-center overflow-hidden bg-gradient-to-br from-[#2F3E46] via-[#354F52] to-[#B77C4C] px-6 py-12">
      {/* {-- CARD REGISTER --} */}
      <div className="relative w-full max-w-2xl rounded-2xl border border-gray-200 bg-white/95 p-10 shadow-2xl backdrop-blur-md">
        {/* {-- Header --} */}
        <div className="mb-8 flex flex-col items-center">
          <img
            src="/assets/Logo.png"
            alt="VINSTORE"
            className="mb-3 h-16 w-16 object-contain"
          />
          <h1 className="text-center text-2xl font-bold text-[#3E2723] md:text-3xl">
            Buat Akun Baru
          </h1>
          <p className="text-center text-sm text-gray-600">
            Daftar dan mulailah menjelajahi koleksi barang antik eksklusif ✨
          </p>
        </div>

        {/* {-- FORM REGISTER --} */}
        <Form
          action="/register"
          method="POST"
          className="space-y-5"
          options={{ preserveScroll: true }}
          disableWhileProcessing={true}
        >
          {({ errors, hasErrors }) => (
            <>
              {/* {-- ERROR MESSAGE --} */}
              {hasErrors && (
                <div className="mb-6 rounded border border-red-400 bg-red-100 px-4 py-3 text-sm text-red-700">
                  <ul className="list-inside list-disc">
                    {Object.values(errors).map((error) => (
                      <li key={error}>{error}</li>
                    ))}
                  </ul>
                </div>
              )}

              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {/* {-- Username --} */}
                <div className="md:col-span-2">
                  <label
                    htmlFor="username"
                    className="mb-1 block text-sm font-semibold text-[#3E2723]"
                  >
                    Username
                  </label>
                  <input
                    type="text"
                    name="username"
                    id="username"
                    placeholder="Masukkan username"
                    className="w-full rounded-lg border border-gray-300 px-4 py-3 text-gray-700 focus:ring-2 focus:ring-[#B77C4C] focus:outline-none"
                    required
                  />
                </div>

                {/* {-- Nama Depan & Belakang --} */}
                <div>
                  <label
                    htmlFor="first_name"
                    className="mb-1 block text-sm font-semibold text-[#3E2723]"
                  >
                    Nama Depan
                  </label>
                  <input
                    type="text"
                    name="first_name"
                    id="first_name"
                    placeholder="Nama depan"
                    className="w-full rounded-lg border border-gray-300 px-4 py-3 text-gray-700 focus:ring-2 focus:ring-[#B77C4C] focus:outline-none"
                    required
                  />
                </div>
                <div>
                  <label
                    htmlFor="last_name"
                    className="mb-1 block text-sm font-semibold text-[#3E2723]"
                  >
                    Nama Belakang
                  </label>
                  <input
                    type="text"
                    name="last_name"
                    id="last_name"
                    placeholder="Nama belakang"
                    className="w-full rounded-lg border border-gray-300 px-4 py-3 text-gray-700 focus:ring-2 focus:ring-[#B77C4C] focus:outline-none"
                    required
                  />
                </div>

                {/* {-- Email & Telepon --} */}
                <div>
                  <label
                    htmlFor="email"
                    className="mb-1 block text-sm font-semibold text-[#3E2723]"
                  >
                    Email
                  </label>
                  <input
                    type="email"
                    name="email"
                    id="email"
                    placeholder="Masukkan email"
                    className="w-full rounded-lg border border-gray-300 px-4 py-3 text-gray-700 focus:ring-2 focus:ring-[#B77C4C] focus:outline-none"
                    required
                  />
                </div>
                <div>
                  <label
                    htmlFor="phone"
                    className="mb-1 block text-sm font-semibold text-[#3E2723]"
                  >
                    Nomor Telepon
                  </label>
                  <input
                    type="tel"
                    name="phone"
                    id="phone"
                    placeholder="Masukkan nomor telepon"
                    className="w-full rounded-lg border border-gray-300 px-4 py-3 text-gray-700 focus:ring-2 focus:ring-[#B77C4C] focus:outline-none"
                    required
                  />
                </div>

                {/* {-- Password & Konfirmasi --} */}
                <div>
                  <label
                    htmlFor="password"
                    className="mb-1 block text-sm font-semibold text-[#3E2723]"
                  >
                    Password
                  </label>
                  <input
                    type="password"
                    name="password"
                    id="password"
                    placeholder="Masukkan password"
                    className="w-full rounded-lg border border-gray-300 px-4 py-3 text-gray-700 focus:ring-2 focus:ring-[#B77C4C] focus:outline-none"
                    required
                  />
                </div>
                <div>
                  <label
                    htmlFor="password_confirmation"
                    className="mb-1 block text-sm font-semibold text-[#3E2723]"
                  >
                    Konfirmasi Password
                  </label>
                  <input
                    type="password"
                    name="password_confirmation"
                    id="password_confirmation"
                    placeholder="Ulangi password"
                    className="w-full rounded-lg border border-gray-300 px-4 py-3 text-gray-700 focus:ring-2 focus:ring-[#B77C4C] focus:outline-none"
                    required
                  />
                </div>

                {/* {-- Alamat --} */}
                <div className="md:col-span-2">
                  <label
                    htmlFor="address"
                    className="mb-1 block text-sm font-semibold text-[#3E2723]"
                  >
                    Alamat Lengkap
                  </label>
                  <textarea
                    name="address"
                    id="address"
                    rows="3"
                    placeholder="Masukkan alamat lengkap"
                    className="w-full rounded-lg border border-gray-300 px-4 py-3 text-gray-700 focus:ring-2 focus:ring-[#B77C4C] focus:outline-none"
                    required
                  ></textarea>
                </div>
              </div>

              {/* {-- Tombol Register --} */}
              <button
                type="submit"
                className="w-full rounded-lg bg-[#B77C4C] py-3 font-semibold text-white shadow-md transition-all duration-200 hover:bg-[#9e6538]"
              >
                Daftar Sekarang
              </button>
            </>
          )}
        </Form>

        {/* {-- Divider --} */}
        <div className="my-6 flex items-center">
          <hr className="flex-1 border-gray-300" />
          <span className="px-3 text-sm text-gray-500">atau</span>
          <hr className="flex-1 border-gray-300" />
        </div>

        {/* {-- Redirect ke Login --} */}
        <div className="text-center">
          <p className="text-sm text-gray-600">
            Sudah punya akun?{' '}
            <Link
              href="/login"
              className="font-semibold text-[#B77C4C] hover:underline"
            >
              Login Sekarang
            </Link>
          </p>
        </div>
      </div>

      {/* {-- Dekorasi Background --} */}
      <div className="absolute top-0 left-0 -z-10 h-72 w-72 rounded-full bg-[#E9E19E]/20 blur-3xl"></div>
      <div className="absolute right-0 bottom-0 -z-10 h-96 w-96 rounded-full bg-[#B77C4C]/25 blur-3xl"></div>
    </section>
  );
}

RegisterPage.layout = (page) => (
  <FormLayout title="Register">{page}</FormLayout>
);
