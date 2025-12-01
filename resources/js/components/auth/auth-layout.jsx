import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { cva } from 'class-variance-authority';

export function AuthLayout({ children, className }) {
  return (
    <div
      className={cn(
        'relative my-auto flex w-full items-center justify-center overflow-hidden px-6 py-12',
        className
      )}
    >
      {children}
    </div>
  );
}

export function AuthLayoutHeader({ title, subtitle }) {
  return (
    <div className="mb-6 flex flex-col items-center text-center">
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

const authLayoutVariants = cva(
  'relative w-full rounded-2xl border border-gray-200 bg-white/95 p-8 shadow-2xl',
  {
    variants: {
      variant: {
        login: 'max-w-lg',
        register: 'max-w-2xl',
      },
    },
    defaultVariants: {
      variant: 'login',
    },
  }
);

export function AuthLayoutCard({
  children,
  type: variant,
  className,
  ...props
}) {
  return (
    <div className={cn(authLayoutVariants({ variant }), className)} {...props}>
      {children}
    </div>
  );
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

const labelVariants = cva('mb-1 block text-sm font-semibold', {
  variants: {
    variant: {
      brown: 'text-[#3E2723]',
      green: 'text-[#2F3E46]',
    },
  },
  defaultVariants: {
    variant: 'green',
  },
});

export function AuthLabel({ htmlFor, children, className, variant, ...props }) {
  return (
    <label
      htmlFor={htmlFor}
      className={cn(labelVariants({ variant, className }))}
      {...props}
    >
      {children}
    </label>
  );
}

const inputVariants = cva(
  'w-full rounded-lg border border-gray-300 px-4 text-gray-700 focus:ring-2 focus:outline-none',
  {
    variants: {
      variant: {
        brown: 'focus:ring-[#B77C4C]',
        green: 'focus:ring-[#E9E19E]',
      },
      size: {
        sm: 'py-2',
        md: 'py-3',
      },
    },
    defaultVariants: {
      variant: 'green',
      size: 'md',
    },
  }
);

export function AuthInput({
  id,
  type,
  name,
  value,
  defaultValue,
  className,
  placeholder,
  required = false,
  min,
  max,
  step,
  variant,
  size,
  ...props
}) {
  return (
    <input
      id={id}
      type={type}
      name={name}
      value={value}
      defaultValue={defaultValue}
      placeholder={placeholder}
      className={cn(inputVariants({ variant, size, className }))}
      required={required}
      min={min}
      step={step}
      max={max}
      {...props}
    />
  );
}

export function AuthTextArea({
  id,
  name,
  rows,
  placeholder,
  required = false,
  className,
  variant,
  ...props
}) {
  return (
    <textarea
      id={id}
      name={name}
      rows={rows}
      placeholder={placeholder}
      className={cn('max-h-40', inputVariants({ variant, className }))}
      required={required}
      {...props}
    />
  );
}

// TODO: TERAKHIR SAMPE SINI
const buttonVariants = cva(
  'rounded-lg px-6 py-3 font-semibold text-white shadow-md transition-all duration-200 hover:cursor-pointer',
  {
    variants: {
      variant: {
        brown: 'bg-[#B77C4C] hover:bg-[#9e6538]',
        green: 'bg-green-600 hover:bg-green-700',
        link: 'bg-muted text-muted-foreground hover:underline',
      },
      size: {
        sm: 'py-2',
        md: 'py-3',
      },
    },
    defaultVariants: {
      variant: 'green',
      size: 'md',
    },
  }
);

export function AuthButton({
  type = 'submit',
  className,
  children,
  variant,
  size,
  ...props
}) {
  return (
    <button
      type={type}
      className={cn(buttonVariants({ variant, size }), className)}
      {...props}
    >
      {children}
    </button>
  );
}

export function AuthButtonLink({ children, href = '#', ...props }) {
  return (
    <Link
      href={href}
      className="bg-muted text-muted-foreground rounded-lg px-6 py-3 font-semibold shadow-md transition-all duration-200 hover:cursor-pointer hover:underline"
      {...props}
    >
      {children}
    </Link>
  );
}
