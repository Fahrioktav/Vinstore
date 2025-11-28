import { Form, Link } from '@inertiajs/react';
import FormLayout from '@/layouts/form-layout';
import {
  AuthButton,
  AuthErrorMessage,
  AuthInput,
  AuthLabel,
  AuthLayout,
  AuthLayoutCard,
  AuthLayoutDivider,
  AuthLayoutHeader,
  AuthTextArea,
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
                  <AuthInput
                    type="text"
                    name="username"
                    id="username"
                    placeholder="Masukkan username"
                    required
                  />
                </div>

                {/* {-- Nama Depan & Belakang --} */}
                <div>
                  <AuthLabel htmlFor="first_name">Nama Depan</AuthLabel>
                  <AuthInput
                    type="text"
                    name="first_name"
                    id="first_name"
                    placeholder="Nama depan"
                    required
                  />
                </div>
                <div>
                  <AuthLabel htmlFor="last_name">Nama Belakang</AuthLabel>
                  <AuthInput
                    type="text"
                    name="last_name"
                    id="last_name"
                    placeholder="Nama belakang"
                    required
                  />
                </div>

                {/* {-- Email & Telepon --} */}
                <div>
                  <AuthLabel htmlFor="email">Email</AuthLabel>
                  <AuthInput
                    type="email"
                    name="email"
                    id="email"
                    placeholder="Masukkan email"
                    required
                  />
                </div>
                <div>
                  <AuthLabel htmlFor="phone">Nomor Telepon</AuthLabel>
                  <AuthInput
                    type="tel"
                    name="phone"
                    id="phone"
                    placeholder="Masukkan nomor telepon"
                    required
                  />
                </div>

                {/* {-- Password & Konfirmasi --} */}
                <div>
                  <AuthLabel htmlFor="password">Password</AuthLabel>
                  <AuthInput
                    type="password"
                    name="password"
                    id="password"
                    placeholder="Masukkan password"
                    required
                  />
                </div>
                <div>
                  <AuthLabel htmlFor="password_confirmation">
                    Konfirmasi Password
                  </AuthLabel>
                  <AuthInput
                    type="password"
                    name="password_confirmation"
                    id="password_confirmation"
                    placeholder="Ulangi password"
                    required
                  />
                </div>

                {/* {-- Alamat --} */}
                <div className="md:col-span-2">
                  <AuthLabel htmlFor="address">Alamat Lengkap</AuthLabel>
                  <AuthTextArea
                    name="address"
                    id="address"
                    rows="3"
                    placeholder="Masukkan alamat lengkap"
                    required
                  />
                </div>
              </div>

              {/* {-- Tombol Register --} */}
              <AuthButton type="submit">Daftar Sekarang</AuthButton>
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
