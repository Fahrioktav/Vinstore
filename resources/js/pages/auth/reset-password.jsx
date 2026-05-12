import FormLayout from '@/layouts/form-layout';
import { Form, Link, usePage } from '@inertiajs/react';
import {
  AuthButton,
  AuthErrorMessage,
  AuthInput,
  AuthLabel,
  AuthLayout,
  AuthLayoutCard,
  AuthLayoutHeader,
} from '@/components/auth/auth-layout';

export default function ResetPasswordPage({ token, email }) {
  const { flash } = usePage().props;

  return (
    <AuthLayout>
      <AuthLayoutCard type="login">
        <AuthLayoutHeader
          title="Reset Password"
          subtitle="Masukkan password baru Anda"
        />

        {/* Success Message */}
        {flash?.status && (
          <div className="mb-6 rounded border border-green-400 bg-green-100 px-4 py-3 text-sm text-green-700">
            {flash.status}
          </div>
        )}

        {/* Form Reset Password */}
        <Form
          method="POST"
          action="/reset-password"
          className="space-y-5"
          disableWhileProcessing={true}
          options={{ preserveScroll: true }}
        >
          {({ errors, hasErrors }) => (
            <>
              {/* Error Message */}
              <AuthErrorMessage errors={errors} hasErrors={hasErrors} />

              {/* Hidden Token & Email */}
              <input type="hidden" name="token" value={token} />
              <input type="hidden" name="email" value={email} />

              {/* Email (Read Only) */}
              <div>
                <AuthLabel variant="brown" htmlFor="email_display">
                  Email
                </AuthLabel>
                <AuthInput
                  variant="brown"
                  type="email"
                  id="email_display"
                  value={email}
                  disabled
                  className="bg-gray-100"
                />
              </div>

              {/* Password Baru */}
              <div>
                <AuthLabel variant="brown" htmlFor="password">
                  Password Baru
                </AuthLabel>
                <AuthInput
                  variant="brown"
                  type="password"
                  name="password"
                  id="password"
                  placeholder="Masukkan password baru (min. 6 karakter)"
                  required
                />
              </div>

              {/* Konfirmasi Password */}
              <div>
                <AuthLabel variant="brown" htmlFor="password_confirmation">
                  Konfirmasi Password
                </AuthLabel>
                <AuthInput
                  variant="brown"
                  type="password"
                  name="password_confirmation"
                  id="password_confirmation"
                  placeholder="Ulangi password baru"
                  required
                />
              </div>

              {/* Tombol Reset Password */}
              <AuthButton variant="brown" type="submit" className="w-full">
                Reset Password
              </AuthButton>
            </>
          )}
        </Form>

        <div className="mt-6 text-center">
          <p className="text-sm text-gray-600">
            <Link
              href="/login"
              className="font-semibold text-[#B77C4C] hover:underline"
            >
              Kembali ke Login
            </Link>
          </p>
        </div>
      </AuthLayoutCard>
    </AuthLayout>
  );
}

ResetPasswordPage.layout = (page) => (
  <FormLayout title="Reset Password">{page}</FormLayout>
);
