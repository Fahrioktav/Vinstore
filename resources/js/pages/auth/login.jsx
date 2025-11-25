import FormLayout from '@/layouts/form-layout';
import { Form, Link } from '@inertiajs/react';
// import FormLayout from '@/layouts/form-layout';

export default function LoginPage() {
  return (
    // {-- Background Full Layer --}
    <div className="relative flex min-h-screen w-full items-center justify-center overflow-hidden">
      {/* {-- Elemen Dekoratif --} */}
      <div className="absolute inset-0">
        <div className="absolute top-0 left-0 h-72 w-72 rounded-full bg-[#E9E19E]/20 blur-3xl"></div>
        <div className="absolute right-0 bottom-0 h-96 w-96 rounded-full bg-[#B77C4C]/25 blur-3xl"></div>
      </div>

      {/* {-- Card Login --} */}
      <div className="relative z-10 mx-4 w-full max-w-md rounded-2xl border border-gray-200 bg-white/95 p-8 shadow-2xl backdrop-blur-md md:p-10">
        {/* {-- Logo --} */}
        <div className="mb-6 flex flex-col items-center">
          <img
            src="/assets/Logo.png"
            alt="VINSTORE"
            className="mb-3 h-16 w-16 object-contain"
          />
          <h1 className="text-2xl font-bold text-[#3E2723]">
            Selamat Datang Kembali!
          </h1>
          <p className="text-sm text-gray-600">
            Masuk untuk melanjutkan ke dunia barang antik eksklusif ✨
          </p>
        </div>

        <Form
          method="POST"
          action="/login"
          className="space-y-5"
          disableWhileProcessing={true}
          options={{ preserveScroll: true }}
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

              {/* {-- Username / Email --} */}
              <div>
                <label
                  htmlFor="login"
                  className="mb-1 block text-sm font-semibold text-[#3E2723]"
                >
                  Username atau Email
                </label>
                <input
                  type="text"
                  name="login"
                  id="login"
                  className="w-full rounded-lg border border-gray-300 px-4 py-3 text-gray-700 focus:ring-2 focus:ring-[#B77C4C] focus:outline-none"
                  placeholder="Masukkan username atau email"
                  required
                />
              </div>

              {/* {-- Password --} */}
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
                  className="w-full rounded-lg border border-gray-300 px-4 py-3 text-gray-700 focus:ring-2 focus:ring-[#B77C4C] focus:outline-none"
                  placeholder="Masukkan password"
                  required
                />
              </div>

              {/* {-- Tombol Login --} */}
              <button
                type="submit"
                className="w-full rounded-lg bg-[#B77C4C] py-3 font-semibold text-white shadow-md transition-all duration-200 hover:cursor-pointer hover:bg-[#9e6538]"
              >
                Masuk Sekarang
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

        {/* {-- Register Prompt --} */}
        <div className="text-center">
          <p className="text-sm text-gray-600">
            Belum punya akun?{' '}
            <Link
              href="/register"
              className="font-semibold text-[#B77C4C] hover:underline"
            >
              Daftar Sekarang
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}

LoginPage.layout = (page) => <FormLayout title="Login">{page}</FormLayout>;
