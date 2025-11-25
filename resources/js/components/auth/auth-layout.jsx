import { cn } from '@/lib/utils';

export function AuthLayout({ children, className }) {
  return (
    <div
      className={cn(
        'relative flex min-h-screen w-full items-center justify-center overflow-hidden px-6 py-12',
        className
      )}
    >
      {children}
    </div>
  );
}

export function AuthLayoutHeader({ title, subtitle }) {
  return (
    <div className="mb-6 flex flex-col items-center">
      <img
        src="/assets/Logo.png"
        alt="VINSTORE"
        className="mb-3 h-16 w-16 object-contain"
      />
      <h1 className="text-2xl font-bold text-[#3E2723]">{title}</h1>
      <p className="text-sm text-gray-600">{subtitle}</p>
    </div>
  );
}

const DefaultCardStyles = {
  login:
    'relative z-10 mx-4 w-full max-w-md rounded-2xl border border-gray-200 bg-white/95 p-8 shadow-2xl backdrop-blur-md md:p-10',
  register:
    'relative w-full max-w-2xl rounded-2xl border border-gray-200 bg-white/95 p-10 shadow-2xl backdrop-blur-md',
};

export function AuthLayoutCard({ children, type }) {
  const style = DefaultCardStyles[type] ?? '';
  return <div className={style}>{children}</div>;
}

export function AuthLayoutDivider() {
  return (
    <div className="my-6 flex items-center">
      <hr className="flex-1 border-gray-300" />
      <span className="px-3 text-sm text-gray-500">atau</span>
      <hr className="flex-1 border-gray-300" />
    </div>
  );
}

export function AuthLabel({ htmlFor, children }) {
  return (
    <label
      htmlFor={htmlFor}
      className="mb-1 block text-sm font-semibold text-[#3E2723]"
    >
      {children}
    </label>
  );
}

export function AuthErrorMessage({ errors, hasErrors }) {
  return (
    hasErrors && (
      <div className="mb-6 rounded border border-red-400 bg-red-100 px-4 py-3 text-sm text-red-700">
        <ul className="list-inside list-disc">
          {Object.values(errors).map((error) => (
            <li key={error}>{error}</li>
          ))}
        </ul>
      </div>
    )
  );
}
