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

export default function ForgotPasswordPage() {
  const { flash } = usePage().props;

  return (
    <AuthLayout>
      <AuthLayoutCard type="login">
        <AuthLayoutHeader
          title="Lupa Password?"
          subtitle="Masukkan email Anda dan kami akan mengirimkan link untuk reset password"
        />

        {/* Success Message */}
        {flash?.status && (
          <div className="mb-6 rounded border border-green-400 bg-green-100 px-4 py-3 text-sm text-green-700">
            {flash.status}
          </div>
        )}

        {flash?.reset_url && (
          <div className="mb-6 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p className="mb-2 font-semibold">Link reset untuk development:</p>
            <Link
              href={flash.reset_url}
              className="break-all font-semibold text-[#B77C4C] hover:underline"
            >
              {flash.reset_url}
            </Link>
          </div>
        )}

        {/* Form Forgot Password */}
        <Form
          method="POST"
          action="/forgot-password"
          className="space-y-5"
          disableWhileProcessing={true}
          options={{ preserveScroll: true }}
        >
          {({ errors, hasErrors }) => (
            <>
              {/* Error Message */}
              <AuthErrorMessage errors={errors} hasErrors={hasErrors} />

              {/* Email */}
              <div>
                <AuthLabel variant="brown" htmlFor="email">
                  Email
                </AuthLabel>
                <AuthInput
                  variant="brown"
                  type="email"
                  name="email"
                  id="email"
                  placeholder="Masukkan email Anda"
                  required
                />
              </div>

              {/* Tombol Kirim Link */}
              <AuthButton variant="brown" type="submit" className="w-full">
                Kirim Link Reset Password
              </AuthButton>
            </>
          )}
        </Form>

        <div className="mt-6 text-center">
          <p className="text-sm text-gray-600">
            Ingat password Anda?{' '}
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

ForgotPasswordPage.layout = (page) => (
  <FormLayout title="Lupa Password">{page}</FormLayout>
);
