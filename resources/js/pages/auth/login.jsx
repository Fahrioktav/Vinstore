import FormLayout from '@/layouts/form-layout';
import { Form, Link } from '@inertiajs/react';
import {
  AuthErrorMessage,
  AuthLabel,
  AuthLayout,
  AuthLayoutCard,
  AuthLayoutDivider,
  AuthLayoutHeader,
} from '@/components/auth/auth-layout';

export default function LoginPage() {
  return (
    <AuthLayout>
      <AuthLayoutCard type="login">
        <AuthLayoutHeader
          title="Selamat Datang Kembali!"
          subtitle="Masuk untuk melanjutkan ke dunia barang antik eksklusif ✨"
        />

        {/* {-- FORM LOGIN --} */}
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
              <AuthErrorMessage errors={errors} hasErrors={hasErrors} />

              {/* {-- Username / Email --} */}
              <div>
                <AuthLabel htmlFor="login">Username atau Email</AuthLabel>
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
                <AuthLabel htmlFor="password">Password</AuthLabel>
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

        <AuthLayoutDivider />

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
      </AuthLayoutCard>
    </AuthLayout>
  );
}

LoginPage.layout = (page) => <FormLayout title="Login">{page}</FormLayout>;
