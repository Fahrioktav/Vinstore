import FormLayout from '@/layouts/form-layout';
import { Form, Link } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import {
  AuthButton,
  AuthInput,
  AuthLabel,
  AuthLayout,
  AuthLayoutCard,
  AuthLayoutDivider,
  AuthLayoutHeader,
} from '@/components/auth/auth-layout';

export default function LoginPage() {
  const [showPassword, setShowPassword] = useState(false);

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
                <div className="mb-1 flex items-center justify-between">
                  <AuthLabel variant="brown" htmlFor="password">
                    Password
                  </AuthLabel>
                  <Link
                    href="/forgot-password"
                    className="text-xs text-[#B77C4C] hover:underline"
                  >
                    Lupa Password?
                  </Link>
                </div>
                <div className="relative">
                  <AuthInput
                    variant="brown"
                    type={showPassword ? 'text' : 'password'}
                    name="password"
                    id="password"
                    placeholder="Masukkan password"
                    required
                    className="pr-12"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword((value) => !value)}
                    className="absolute top-1/2 right-3 -translate-y-1/2 rounded-md p-1 text-gray-500 transition hover:bg-gray-100 hover:text-[#B77C4C]"
                    aria-label={
                      showPassword ? 'Sembunyikan password' : 'Tampilkan password'
                    }
                  >
                    {showPassword ? (
                      <EyeOff className="h-5 w-5" />
                    ) : (
                      <Eye className="h-5 w-5" />
                    )}
                  </button>
                </div>
                {errors.login && (
                  <p className="mt-1 text-xs text-red-500">{errors.login}</p>
                )}
              </div>

              {/* {-- Tombol Login --} */}
              <AuthButton variant="brown" type="submit" className="w-full">
                Masuk Sekarang
              </AuthButton>
            </>
          )}
        </Form>

        <AuthLayoutDivider />

        {/* Tombol Google Login */}
        <a
          href="/auth/google"
          className="flex w-full items-center justify-center gap-3 rounded-lg border-2 border-gray-300 bg-white px-6 py-3 font-semibold text-gray-700 shadow-sm transition-all duration-200 hover:bg-gray-50 hover:shadow-md"
        >
          <svg className="h-5 w-5" viewBox="0 0 24 24">
            <path
              fill="#4285F4"
              d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
            />
            <path
              fill="#34A853"
              d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
            />
            <path
              fill="#FBBC05"
              d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
            />
            <path
              fill="#EA4335"
              d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
            />
          </svg>
          Masuk dengan Google
        </a>

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
