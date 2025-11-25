import { Form, Link } from '@inertiajs/react';
import FormLayout from '@/layouts/form-layout';
import {
  AuthLabel,
  AuthLayout,
  AuthLayoutCard,
  AuthLayoutDivider,
  AuthLayoutHeader,
} from '@/components/auth/auth-layout';

export default function RegisterPage() {
  return (
    <AuthLayout>
      <AuthLayoutCard type="register">
        <AuthLayoutHeader
          title="Buat akun baru"
          subtitle="Daftar dan mulailah menjelajahi koleksi barang antik eksklusif ✨"
        />

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
              <AuthErrorMessage errors={errors} hasErrors={hasErrors} />

              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {/* {-- Username --} */}
                <div className="md:col-span-2">
                  <AuthLabel htmlFor="username">Username</AuthLabel>
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
                  <AuthLabel htmlFor="first_name">Nama Depan</AuthLabel>
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
                  <AuthLabel htmlFor="last_name">Nama Belakang</AuthLabel>
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
                  <AuthLabel htmlFor="email">Email</AuthLabel>
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
                  <AuthLabel htmlFor="phone">Nomor Telepon</AuthLabel>
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
                  <AuthLabel htmlFor="password">Password</AuthLabel>
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
                  <AuthLabel htmlFor="password_confirmation">
                    Konfirmasi Password
                  </AuthLabel>
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
                  <AuthLabel htmlFor="address">Alamat Lengkap</AuthLabel>
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
                className="w-full rounded-lg bg-[#B77C4C] py-3 font-semibold text-white shadow-md transition-all duration-200 hover:cursor-pointer hover:bg-[#9e6538]"
              >
                Daftar Sekarang
              </button>
            </>
          )}
        </Form>

        <AuthLayoutDivider />

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
      </AuthLayoutCard>
    </AuthLayout>
  );
}

RegisterPage.layout = (page) => (
  <FormLayout title="Register">{page}</FormLayout>
);
