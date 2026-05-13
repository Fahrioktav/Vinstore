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

export function getCategoryImage(category) {
  return category.image
    ? storageUrl(category.image)
    : 'https://placehold.co/600x500/53685B/FFFFFF?text=Foto+Kategori';
}

export function getProductImage(product) {
  return product.image
    ? storageUrl(product.image)
    : 'https://placehold.co/600x500/53685B/FFFFFF?text=Foto+Produk';
}

export function getAuctionImage(auction) {
  return auction.image
    ? storageUrl(auction.image)
    : 'https://placehold.co/600x500/53685B/FFFFFF?text=Foto+Lelang';
}

export function getProductCertificate(product) {
  return product.certificate
    ? storageUrl(product.certificate)
    : 'https://placehold.co/600x500/53685B/FFFFFF?text=Foto+Sertifikat';
}

export function getStoreImage(store) {
  return store.photo
    ? storageUrl(store.photo)
    : 'https://placehold.co/600x400/53685B/FFFFFF?text=Foto+Toko';
}

export function getUserImage(user) {
  return user.photo
    ? storageUrl(user.photo)
    : `https://ui-avatars.com/api/?name=${user.username}&size=128`;
}

function storageUrl(path) {
  if (!path) {
    return null;
  }

  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
    return path;
  }

  if (path.startsWith('storage/') || path.startsWith('uploads/') || path.startsWith('assets/')) {
    return `/${path}`;
  }

  return `/storage/${path}`;
}
