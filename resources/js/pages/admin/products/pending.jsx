import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import {
  formatIDR,
  getProductCertificate,
  getProductImage,
  storageMedia,
} from '@/lib/utils';
import {
  BadgeIcon,
  CheckIcon,
  CloseIcon,
  GuessIcon,
  LocationIcon,
  PendingIcon,
  WarningIcon,
} from '@/components/icons';
import { confirmDialog, promptDialog } from '@/lib/dialog';

export default function AdminPendingProducts() {
  const { products = [] } = usePage().props;

  return (
    <>
      <Head title="Produk Menunggu Persetujuan" />
      <div className="mx-auto max-w-6xl px-6 py-8">
        <div className="mb-6 flex items-center justify-between">
          <Link
            href="/admin/dashboard"
            className="text-sm font-semibold text-[#E9E19E] transition hover:underline"
          >
            ← Kembali ke Dashboard
          </Link>
          <Link
            href="/admin/products"
            className="text-sm font-semibold text-[#E9E19E] transition hover:underline"
          >
            Semua Produk →
          </Link>
        </div>

        <div className="mb-6 rounded-2xl bg-white p-6 shadow-md shadow-[#53685B]/20">
          <h1 className="text-2xl font-bold text-[#53685B]">
            <PendingIcon className="mr-2 inline h-6 w-6 align-text-bottom" />
            Produk Menunggu Persetujuan
          </h1>
          <p className="mt-1 text-sm text-gray-600">
            {products.length > 0
              ? `Ada ${products.length} produk yang sudah divalidasi validator dan menunggu persetujuan Anda.`
              : 'Tidak ada produk yang menunggu persetujuan saat ini.'}
          </p>
        </div>

        <div className="flex flex-col gap-6">
          {products.map((product) => (
            <PendingProductCard key={product.public_id} product={product} />
          ))}
        </div>
      </div>
    </>
  );
}

function PendingProductCard({ product }) {
  const store = product.store || {};
  const seller = store.user || {};

  const gallery = [
    product.image,
    ...(Array.isArray(product.images) ? product.images : []),
  ].filter(Boolean);
  const [activeImage, setActiveImage] = useState(gallery[0] ?? null);

  const handleApprove = async () => {
    if (
      await confirmDialog({
        title: 'Setujui produk ini?',
        description: 'Produk akan langsung tampil dan bisa dibeli pembeli.',
        confirmLabel: 'Ya, setujui',
      })
    ) {
      router.post(
        `/admin/products/${product.public_id}/approve`,
        {},
        { preserveScroll: true }
      );
    }
  };

  const handleReject = async () => {
    const rejectionReason = await promptDialog({
      title: 'Tolak produk ini?',
      description:
        'Alasannya akan ditampilkan kepada seller agar bisa diperbaiki.',
      label: 'Alasan penolakan',
      placeholder: 'Contoh: foto kurang jelas, sertifikat tidak terbaca...',
      confirmLabel: 'Tolak produk',
      variant: 'destructive',
    });

    if (rejectionReason === null) return;
    router.post(
      `/admin/products/${product.public_id}/reject`,
      { rejection_reason: rejectionReason },
      { preserveScroll: true }
    );
  };

  return (
    <div className="grid grid-cols-1 gap-6 rounded-2xl bg-white p-6 shadow-md shadow-[#53685B]/20 lg:grid-cols-3">
      {/* Media */}
      <div className="lg:col-span-1">
        <img
          src={
            activeImage
              ? getProductImage({ image: activeImage })
              : getProductImage(product)
          }
          alt={product.name}
          className="aspect-square w-full rounded-xl object-cover"
        />
        {gallery.length > 1 && (
          <div className="mt-3 flex flex-wrap gap-2">
            {gallery.map((img, i) => (
              <button
                key={i}
                type="button"
                onClick={() => setActiveImage(img)}
                className={`h-14 w-14 overflow-hidden rounded-md border-2 ${
                  activeImage === img
                    ? 'border-[#B77C4C]'
                    : 'border-transparent'
                }`}
              >
                <img
                  src={getProductImage({ image: img })}
                  className="h-full w-full object-cover"
                  alt={`${product.name} ${i + 1}`}
                />
              </button>
            ))}
          </div>
        )}
        {product.video && (
          <video
            src={storageMedia(product.video)}
            controls
            className="mt-3 w-full rounded-md"
          />
        )}
        <div className="mt-4">
          {product.certificate ? (
            <a
              href={getProductCertificate(product)}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-green-100 px-4 py-2.5 text-sm font-semibold text-green-700 transition hover:bg-green-200"
            >
              <BadgeIcon className="h-5 w-5" />
              Lihat Sertifikat Keaslian
            </a>
          ) : (
            <p className="rounded-lg bg-red-50 px-4 py-2.5 text-center text-sm font-medium text-red-600">
              <WarningIcon className="mr-1 inline h-4 w-4 align-text-bottom" />
              Produk tidak menyertakan sertifikat
            </p>
          )}
        </div>
      </div>

      {/* Detail */}
      <div className="lg:col-span-2">
        <div className="flex items-start justify-between">
          <h2 className="text-2xl font-bold text-[#53685B]">{product.name}</h2>
          <span className="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
            Menunggu Admin
          </span>
        </div>
        {product.sale_type === 'tebak_harga' && (
          <span className="mt-2 inline-block rounded-md bg-[#53685B] px-2 py-1 text-xs font-semibold text-white">
            <GuessIcon className="mr-1 inline h-3.5 w-3.5 align-text-bottom" />
            Tebak Harga
          </span>
        )}
        <p className="mt-2 text-3xl font-bold text-[#B77C4C]">
          {formatIDR(product.price)}
          {product.sale_type === 'tebak_harga' && (
            <span className="ml-2 align-middle text-sm font-normal text-gray-500">
              (harga asli - disembunyikan dari pembeli)
            </span>
          )}
        </p>

        {product.sale_type === 'tebak_harga' && (
          <div className="mt-4 grid grid-cols-1 gap-4 rounded-lg bg-[#53685B]/5 p-4 text-sm sm:grid-cols-2">
            <Info
              label="Periode Mulai"
              value={formatTebakDate(product.guess_starts_at)}
            />
            <Info
              label="Periode Berakhir"
              value={formatTebakDate(product.guess_ends_at)}
            />
          </div>
        )}

        <div className="mt-5 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
          <Info label="Kategori" value={product.category} />
          <Info label="Stok" value={product.stock} />
          <Info label="ID Produk" value={product.public_id} />
          <Info
            label="Bisa Dibarter"
            value={product.is_barterable ? 'Ya' : 'Tidak'}
          />
          <Info
            label="Tanggal Diajukan"
            value={
              product.created_at
                ? new Date(product.created_at).toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                  })
                : '-'
            }
          />
          <Info
            label="Divalidasi"
            value={
              product.validated_at
                ? new Date(product.validated_at).toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                  })
                : '-'
            }
          />
        </div>

        <div className="mt-5">
          <p className="mb-1 text-sm font-semibold text-gray-500">Deskripsi</p>
          <p className="text-sm leading-relaxed whitespace-pre-line text-gray-700">
            {product.description || 'Tidak ada deskripsi.'}
          </p>
        </div>

        <div className="mt-5 rounded-xl bg-gray-50 p-4">
          <p className="mb-1 text-sm font-semibold text-gray-500">
            Informasi Penjual
          </p>
          <p className="font-semibold text-gray-800">
            {store.store_name || '-'}
          </p>
          <p className="text-sm text-gray-500">
            {seller.first_name || ''} {seller.last_name || ''}
            {seller.username ? ` (@${seller.username})` : ''}
          </p>
          {store.location && (
            <p className="flex items-center gap-1 text-sm text-gray-500">
              <LocationIcon className="h-4 w-4 shrink-0" />
              {store.location}
            </p>
          )}
        </div>

        <div className="mt-6 flex gap-3">
          <button
            onClick={handleApprove}
            className="flex-1 rounded-lg bg-green-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700"
          >
            <CheckIcon className="mr-1 inline h-5 w-5 align-text-bottom" />
            Setujui Produk
          </button>
          <button
            onClick={handleReject}
            className="flex-1 rounded-lg bg-red-500 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-600"
          >
            <CloseIcon className="mr-1 inline h-5 w-5 align-text-bottom" />
            Tolak Produk
          </button>
        </div>
      </div>
    </div>
  );
}

function Info({ label, value }) {
  return (
    <div>
      <p className="text-xs font-semibold text-gray-500">{label}</p>
      <p className="font-medium text-gray-800">{value ?? '-'}</p>
    </div>
  );
}

function formatTebakDate(value) {
  if (!value) return '-';
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

AdminPendingProducts.layout = (page) => (
  <MainLayout title="Produk Menunggu Persetujuan">{page}</MainLayout>
);
