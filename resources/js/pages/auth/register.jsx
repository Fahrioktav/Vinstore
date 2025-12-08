import { Form, Link } from '@inertiajs/react';
import FormLayout from '@/layouts/form-layout';
import {
  AuthButton,
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
          {({ errors }) => (
            <>
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {/* {-- Username --} */}
                <div className="md:col-span-2">
                  <AuthLabel variant="brown" htmlFor="username">
                    Username
                  </AuthLabel>
                  <AuthInput
                    variant="brown"
                    type="text"
                    name="username"
                    id="username"
                    placeholder="Masukkan username"
                    required
                  />
                  {errors.username && (
                    <p className="mt-1 text-xs text-red-500">
                      {errors.username}
                    </p>
                  )}
                </div>

                {/* {-- Nama Depan & Belakang --} */}
                <div>
                  <AuthLabel variant="brown" htmlFor="first_name">
                    Nama Depan
                  </AuthLabel>
                  <AuthInput
                    variant="brown"
                    type="text"
                    name="first_name"
                    id="first_name"
                    placeholder="Nama depan"
                    required
                  />
                  {errors.first_name && (
                    <p className="mt-1 text-xs text-red-500">
                      {errors.first_name}
                    </p>
                  )}
                </div>
                <div>
                  <AuthLabel variant="brown" htmlFor="last_name">
                    Nama Belakang
                  </AuthLabel>
                  <AuthInput
                    variant="brown"
                    type="text"
                    name="last_name"
                    id="last_name"
                    placeholder="Nama belakang"
                    required
                  />
                  {errors.last_name && (
                    <p className="mt-1 text-xs text-red-500">
                      {errors.last_name}
                    </p>
                  )}
                </div>

                {/* {-- Email & Telepon --} */}
                <div>
                  <AuthLabel variant="brown" htmlFor="email">
                    Email
                  </AuthLabel>
                  <AuthInput
                    variant="brown"
                    type="email"
                    name="email"
                    id="email"
                    placeholder="Masukkan email"
                    required
                  />
                  {errors.email && (
                    <p className="mt-1 text-xs text-red-500">{errors.email}</p>
                  )}
                </div>
                <div>
                  <AuthLabel variant="brown" htmlFor="phone">
                    Nomor Telepon
                  </AuthLabel>
                  <AuthInput
                    variant="brown"
                    type="tel"
                    name="phone"
                    id="phone"
                    placeholder="Masukkan nomor telepon"
                    required
                  />
                  {errors.phone && (
                    <p className="mt-1 text-xs text-red-500">{errors.phone}</p>
                  )}
                </div>

                {/* {-- Password & Konfirmasi --} */}
                <div>
                  <AuthLabel variant="brown" htmlFor="password">
                    Password
                  </AuthLabel>
                  <AuthInput
                    variant="brown"
                    type="password"
                    name="password"
                    id="password"
                    placeholder="Masukkan password"
                    required
                  />
                  {errors.password && (
                    <p className="mt-1 text-xs text-red-500">
                      {errors.password}
                    </p>
                  )}
                </div>
                <div>
                  <AuthLabel variant="brown" htmlFor="password_confirmation">
                    Konfirmasi Password
                  </AuthLabel>
                  <AuthInput
                    variant="brown"
                    type="password"
                    name="password_confirmation"
                    id="password_confirmation"
                    placeholder="Ulangi password"
                    required
                  />
                  {errors.password_confirmation && (
                    <p className="mt-1 text-xs text-red-500">
                      {errors.password_confirmation}
                    </p>
                  )}
                </div>

                {/* {-- Alamat --} */}
                <div className="md:col-span-2">
                  <AuthLabel variant="brown" htmlFor="address">
                    Alamat Lengkap
                  </AuthLabel>
                  <AuthTextArea
                    name="address"
                    id="address"
                    rows="3"
                    placeholder="Masukkan alamat lengkap"
                    required
                  />
                  {errors.address && (
                    <p className="mt-1 text-xs text-red-500">
                      {errors.address}
                    </p>
                  )}
                </div>
              </div>

              {/* {-- Tombol Register --} */}
              <AuthButton variant="brown" type="submit" className="w-full">
                Daftar Sekarang
              </AuthButton>
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
