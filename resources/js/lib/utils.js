import { usePage } from '@inertiajs/react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs) {
  return twMerge(clsx(inputs));
}

// Format angka ke Rupiah
const rupiah = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  minimumFractionDigits: 0, // biar nggak ada ,00
});

export function formatIDR(number) {
  return rupiah.format(number);
}

export function useParams() {
  const { url } = usePage();
  const params = new URLSearchParams(url.split('?')[1]);

  return params;
}
