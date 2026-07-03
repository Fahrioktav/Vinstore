import { Form, usePage } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import {
  formatIDR,
  getProductCertificate,
  getProductImage,
  storageMedia,
} from '@/lib/utils';
import { BadgeIcon, CertificateIcon } from '@/components/icons';

const guessStatusLabel = {
  scheduled: 'Akan Dimulai',
  active: 'Sedang Berlangsung',
  ended: 'Selesai - Prioritas Pemenang',
  public: 'Penjualan Biasa',
};

export default function CheckoutPage() {
  const { product, tebakHarga, flash } = usePage().props;

  // Gabungkan gambar utama + galeri foto tambahan
  const gallery = [
    product.image,
    ...(Array.isArray(product.images) ? product.images : []),
  ].filter(Boolean);

  const [activeImage, setActiveImage] = useState(gallery[0] ?? null);

  const isTebakHarga = product.sale_type === 'tebak_harga';
  // Harga asli disembunyikan -> backend tidak mengirim field price.
  const priceHidden = product.price === null || product.price === undefined;

  return (
    <div className="mx-auto mt-10 max-w-3xl rounded-md border bg-white p-6 shadow-md">
      <h2 className="mb-6 text-2xl font-bold">Detail Produk</h2>

      {flash?.success && (
        <div className="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
          {flash.success}
        </div>
      )}
      {flash?.error && (
        <div className="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {flash.error}
        </div>
      )}

      <div className="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
          <img
            src={
              activeImage
                ? getProductImage({ image: activeImage })
                : getProductImage(product)
            }
            className="h-64 w-full rounded-md object-contain"
            alt={product.name}
          />
          {gallery.length > 1 && (
            <div className="mt-3 flex flex-wrap gap-2">
              {gallery.map((img, i) => (
                <button
                  key={i}
                  type="button"
                  onClick={() => setActiveImage(img)}
                  className={`h-16 w-16 overflow-hidden rounded-md border-2 ${
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
            <div className="mt-4">
              <p className="mb-1 text-sm font-semibold text-gray-700">
                🎥 Video Produk
              </p>
              <video
                src={storageMedia(product.video)}
                controls
                className="w-full rounded-md"
              />
            </div>
          )}
        </div>
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-xl font-semibold">{product.name}</h3>
            {isTebakHarga && (
              <span className="rounded-md bg-[#53685B] px-2 py-1 text-xs font-semibold text-white">
                🎯 Tebak Harga
              </span>
            )}
          </div>
          {product.is_barterable && (
            <span className="mt-2 inline-block rounded-md bg-[#53685B] px-2 py-1 text-xs font-medium text-white">
              ⇄ Bisa Dibarter
            </span>
          )}
          <p className="mt-2 text-gray-600">{product.description}</p>
          <p className="mt-4 text-lg font-bold">
            Harga:{' '}
            {priceHidden ? (
              <span className="text-[#B77C4C]">??? (Tebak Harga)</span>
            ) : (
              formatIDR(product.price)
            )}
          </p>
          {product.certificate && (
            <div className="mt-4 rounded-lg border border-green-200 bg-green-50 p-3">
              <p className="mb-2 flex items-center gap-2 text-sm font-semibold text-green-800">
                <BadgeIcon className="h-5 w-5" />
                Produk Bersertifikat
              </p>
              <a
                href={getProductCertificate(product)}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-1 text-sm text-blue-600 hover:underline"
              >
                <CertificateIcon />
                Lihat Sertifikat Keaslian
              </a>
            </div>
          )}
        </div>
      </div>

      {isTebakHarga && tebakHarga ? (
        <TebakHargaSection product={product} tebakHarga={tebakHarga} />
      ) : (
        <NormalPurchaseActions product={product} />
      )}
    </div>
  );
}

/* Aksi pembelian produk biasa (atau tebak harga yang sudah jadi penjualan biasa). */
function NormalPurchaseActions({ product }) {
  return (
    <div className="flex flex-wrap gap-3">
      <Form method="POST" action={`/cart/add/${product.public_id}`}>
        <input type="hidden" name="quantity" defaultValue="1" />
        <button className="rounded-md bg-yellow-300 px-6 py-2 font-semibold text-black hover:cursor-pointer hover:bg-yellow-400">
          Masukkan Keranjang
        </button>
      </Form>

      <Form method="POST" action={`/checkout/product/${product.public_id}`}>
        <input type="hidden" name="quantity" defaultValue="1" />
        <button className="rounded-md bg-[#53685B] px-6 py-2 font-semibold text-white hover:cursor-pointer hover:bg-[#3c4a3e]">
          Beli Sekarang
        </button>
      </Form>
    </div>
  );
}

/* Bagian khusus alur Tebak Harga. */
function TebakHargaSection({ product, tebakHarga }) {
  const {
    guess_status: status,
    guess_starts_at,
    guess_ends_at,
    winner_priority_until,
    guesses_count,
    my_guess,
    is_winner,
    can_buy,
  } = tebakHarga;

  return (
    <div className="rounded-lg border border-[#53685B]/30 bg-[#53685B]/5 p-5">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
        <span className="rounded-full bg-[#53685B] px-3 py-1 text-xs font-semibold text-white">
          {guessStatusLabel[status] || status}
        </span>
        <span className="text-sm text-gray-500">
          {guesses_count} tebakan masuk
        </span>
      </div>

      <div className="grid grid-cols-2 gap-3 text-sm">
        <Info label="Mulai" value={formatDateTime(guess_starts_at)} />
        <Info label="Berakhir" value={formatDateTime(guess_ends_at)} />
      </div>

      {/* SCHEDULED */}
      {status === 'scheduled' && (
        <p className="mt-4 rounded-md bg-white p-4 text-sm text-gray-600">
          Periode tebak harga belum dimulai. Silakan kembali lagi pada{' '}
          <span className="font-semibold">{formatDateTime(guess_starts_at)}</span>.
        </p>
      )}

      {/* ACTIVE */}
      {status === 'active' && (
        <div className="mt-4">
          {my_guess ? (
            <div className="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800">
              <p className="font-semibold">Tebakan Anda sudah tersimpan.</p>
              <p className="mt-1">
                Nilai tebakan: {formatIDR(my_guess.amount)} (tidak dapat diubah)
              </p>
            </div>
          ) : (
            <Form
              method="POST"
              action={`/products/${product.public_id}/guess`}
              className="space-y-3"
              options={{ preserveScroll: true }}
            >
              {({ errors, processing }) => (
                <>
                  <label className="block text-sm font-semibold text-gray-700">
                    Tebak harga asli produk ini (hanya bisa sekali)
                  </label>
                  <input
                    type="number"
                    name="amount"
                    min="1"
                    placeholder="Masukkan tebakan harga Anda"
                    className="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-[#53685B] focus:ring-2 focus:ring-[#53685B]"
                    required
                  />
                  {errors.amount && (
                    <p className="text-xs text-red-600">{errors.amount}</p>
                  )}
                  <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded-lg bg-[#B77C4C] px-5 py-3 font-semibold text-white transition hover:bg-[#8d5e39] disabled:opacity-50"
                  >
                    Kirim Tebakan
                  </button>
                  <p className="text-xs text-gray-500">
                    Tebakan bersifat final dan tidak dapat diubah. Anda tidak
                    dapat melihat tebakan pengguna lain.
                  </p>
                </>
              )}
            </Form>
          )}
        </div>
      )}

      {/* ENDED - prioritas pemenang */}
      {status === 'ended' && (
        <div className="mt-4">
          {is_winner ? (
            <div className="rounded-md border border-green-300 bg-green-50 p-4">
              <p className="font-semibold text-green-800">
                🎉 Selamat! Tebakan Anda paling mendekati harga asli.
              </p>
              <p className="mt-1 text-sm text-green-700">
                Anda memiliki hak prioritas untuk membeli produk ini sampai{' '}
                <span className="font-semibold">
                  {formatDateTime(winner_priority_until)}
                </span>
                .
              </p>
              {can_buy && (
                <div className="mt-4 flex flex-wrap gap-3">
                  <Form
                    method="POST"
                    action={`/checkout/product/${product.public_id}`}
                  >
                    <input type="hidden" name="quantity" defaultValue="1" />
                    <button className="rounded-md bg-[#53685B] px-6 py-2 font-semibold text-white hover:bg-[#3c4a3e]">
                      Beli Sekarang
                    </button>
                  </Form>
                </div>
              )}
            </div>
          ) : (
            <div className="rounded-md bg-white p-4 text-sm text-gray-600">
              <p>
                Periode tebak harga telah berakhir. Pemenang sedang diberi hak
                prioritas pembelian sampai{' '}
                <span className="font-semibold">
                  {formatDateTime(winner_priority_until)}
                </span>
                .
              </p>
              {my_guess && (
                <p className="mt-2">
                  Tebakan Anda: {formatIDR(my_guess.amount)}
                </p>
              )}
              <p className="mt-2 text-xs text-gray-500">
                Jika pemenang tidak membeli hingga batas waktu, produk akan
                menjadi penjualan biasa dan dapat dibeli oleh siapa saja.
              </p>
            </div>
          )}
        </div>
      )}

      {/* PUBLIC - sudah jadi penjualan biasa */}
      {status === 'public' && (
        <div className="mt-4">
          <p className="mb-3 rounded-md bg-white p-3 text-sm text-gray-600">
            Hak prioritas pemenang telah berakhir. Produk kini dijual normal
            dengan harga asli.
          </p>
          <NormalPurchaseActions product={product} />
        </div>
      )}
    </div>
  );
}

function Info({ label, value }) {
  return (
    <div>
      <p className="text-xs text-gray-500">{label}</p>
      <p className="mt-1 font-semibold text-[#2F3E46]">{value}</p>
    </div>
  );
}

function formatDateTime(value) {
  if (!value) return '-';
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

CheckoutPage.layout = (page) => (
  <MainLayout title="Checkout" heroText="Konfirmasi Pembelianmu">
    {page}
  </MainLayout>
);
