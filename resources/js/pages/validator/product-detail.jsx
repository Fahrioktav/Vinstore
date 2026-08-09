import { Head, Link, router, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import {
  cn,
  formatIDR,
  getProductCertificate,
  getProductImage,
  getUserImage,
} from '@/lib/utils';
import {
  BadgeIcon,
  CheckIcon,
  CloseIcon,
  GuessIcon,
  LocationIcon,
  WarningIcon,
} from '@/components/icons';
import { confirmDialog, promptDialog } from '@/lib/dialog';

const statusLabels = {
  pending_validator: 'Menunggu Validasi',
  pending_admin: 'Menunggu Admin',
  approved: 'Disetujui',
  rejected: 'Ditolak',
};

const statusColors = {
  pending_validator: 'bg-yellow-100 text-yellow-700',
  pending_admin: 'bg-blue-100 text-blue-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
};

export default function ValidatorProductDetail() {
  const { product } = usePage().props;
  const isPending = product.approval_status === 'pending_validator';

  const handleApprove = async () => {
    if (
      await confirmDialog({
        title: 'Validasi produk ini?',
        description:
          'Produk diteruskan ke admin untuk persetujuan akhir sebelum tampil ke pembeli.',
        confirmLabel: 'Ya, validasi',
      })
    ) {
      router.post(
        `/validator/products/${product.public_id}/approve`,
        {},
        {
          preserveScroll: true,
        }
      );
    }
  };

  const handleReject = async () => {
    const rejectionReason = await promptDialog({
      title: 'Tolak produk ini?',
      description:
        'Alasannya akan ditampilkan kepada seller agar bisa diperbaiki.',
      label: 'Alasan penolakan',
      placeholder: 'Contoh: keaslian barang tidak dapat diverifikasi...',
      confirmLabel: 'Tolak produk',
      variant: 'destructive',
    });

    if (rejectionReason === null) return;
    router.post(
      `/validator/products/${product.public_id}/reject`,
      { rejection_reason: rejectionReason },
      { preserveScroll: true }
    );
  };

  const store = product.store || {};
  const seller = store.user || {};

  return (
    <>
      <Head title={`Detail Produk - ${product.name}`} />
      <div className="mx-auto max-w-6xl px-6 py-8">
        <div className="mb-6 flex items-center justify-between">
          <Link
            href="/validator/dashboard"
            className="text-sm font-semibold text-[#E9E19E] transition hover:underline"
          >
            ← Kembali ke Dashboard
          </Link>
          <span
            className={cn(
              'rounded-full px-4 py-1.5 text-sm font-semibold',
              statusColors[product.approval_status] ||
                'bg-gray-100 text-gray-700'
            )}
          >
            {statusLabels[product.approval_status] || product.approval_status}
          </span>
        </div>

        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          {/* Gambar & sertifikat */}
          <div className="lg:col-span-1">
            <div className="rounded-2xl bg-white p-4 shadow-md shadow-[#53685B]/20">
              <img
                src={getProductImage(product)}
                alt={product.name}
                className="aspect-square w-full rounded-xl object-cover"
              />
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
          </div>

          {/* Detail produk */}
          <div className="lg:col-span-2">
            <div className="rounded-2xl bg-white p-6 shadow-md shadow-[#53685B]/20">
              <h1 className="text-2xl font-bold text-[#53685B]">
                {product.name}
              </h1>
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

              <div className="mt-6 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <Info label="Kategori" value={product.category} />
                <Info label="Stok" value={product.stock} />
                <Info label="ID Produk" value={product.public_id} />
                <Info
                  label="Tanggal Diajukan"
                  value={
                    product.created_at
                      ? new Date(product.created_at).toLocaleDateString(
                          'id-ID',
                          {
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric',
                          }
                        )
                      : '-'
                  }
                />
              </div>

              <div className="mt-6">
                <p className="mb-1 text-sm font-semibold text-gray-500">
                  Deskripsi
                </p>
                <p className="text-sm leading-relaxed whitespace-pre-line text-gray-700">
                  {product.description || 'Tidak ada deskripsi.'}
                </p>
              </div>

              {product.rejection_reason && (
                <div className="mt-6 rounded-lg border-l-4 border-red-500 bg-red-50 px-4 py-3">
                  <p className="text-sm font-semibold text-red-700">
                    Alasan Penolakan
                  </p>
                  <p className="text-sm text-red-600">
                    {product.rejection_reason}
                  </p>
                </div>
              )}
            </div>

            {/* Info penjual / toko */}
            <div className="mt-6 rounded-2xl bg-white p-6 shadow-md shadow-[#53685B]/20">
              <h2 className="mb-4 text-lg font-bold text-[#53685B]">
                Informasi Penjual
              </h2>
              <div className="flex items-center gap-4">
                {seller.username && (
                  <img
                    src={getUserImage(seller)}
                    alt={seller.username}
                    className="h-14 w-14 rounded-full object-cover"
                  />
                )}
                <div className="text-sm">
                  <p className="font-semibold text-gray-800">
                    {store.store_name || '-'}
                  </p>
                  <p className="text-gray-500">
                    {seller.first_name || ''} {seller.last_name || ''}
                    {seller.username ? ` (@${seller.username})` : ''}
                  </p>
                  {store.location && (
                    <p className="flex items-center gap-1 text-gray-500">
                      <LocationIcon className="h-4 w-4 shrink-0" />
                      {store.location}
                    </p>
                  )}
                </div>
              </div>
              {store.description && (
                <p className="mt-4 text-sm text-gray-600">
                  {store.description}
                </p>
              )}
            </div>

            {/* Aksi validasi */}
            {isPending && (
              <div className="mt-6 flex gap-3">
                <button
                  onClick={handleApprove}
                  className="flex-1 rounded-lg bg-green-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700"
                >
                  <CheckIcon className="mr-1 inline h-5 w-5 align-text-bottom" />
                  Validasi & Teruskan ke Admin
                </button>
                <button
                  onClick={handleReject}
                  className="flex-1 rounded-lg bg-red-500 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-600"
                >
                  <CloseIcon className="mr-1 inline h-5 w-5 align-text-bottom" />
                  Tolak Produk
                </button>
              </div>
            )}
          </div>
        </div>
      </div>
    </>
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

ValidatorProductDetail.layout = (page) => (
  <MainLayout title="Detail Produk">{page}</MainLayout>
);
