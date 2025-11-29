import FormLayout from '@/layouts/form-layout';
import { Form, Link } from '@inertiajs/react';
import {
  AuthButton,
  AuthErrorMessage,
  AuthInput,
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
                <AuthLabel variant="brown" htmlFor="login">
                  Username atau Email
                </AuthLabel>
                <AuthInput
                  variant="brown"
                  type="text"
                  name="login"
                  id="login"
                  placeholder="Masukkan username atau email"
                  required
                />
              </div>

              {/* {-- Password --} */}
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
              </div>

              {/* {-- Tombol Login --} */}
              <AuthButton variant="brown" type="submit" className="w-full">
                Masuk Sekarang
              </AuthButton>
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
