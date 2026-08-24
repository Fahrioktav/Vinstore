import { Form, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import {
  formatIDR,
  getProductCertificate,
  getProductImage,
  storageMedia,
} from '@/lib/utils';
import CurrencyInput from '@/components/currency-input';
import {
  BadgeIcon,
  TradeInIcon,
  CategoryIcon,
  CelebrateIcon,
  CertificateIcon,
  FailIcon,
  GuessIcon,
  LocationIcon,
  PendingIcon,
  SuccessIcon,
  VideoIcon,
} from '@/components/icons';
import GuessLeaderboard from '@/components/guess-leaderboard';

const guessStatusLabel = {
  scheduled: 'Akan Dimulai',
  active: 'Sedang Berlangsung',
  ended: 'Selesai - Prioritas Pemenang',
  public: 'Penjualan Biasa',
};

export default function ProductDetailPage() {
  const { product, tebakHarga, flash } = usePage().props;

  // Gabungkan gambar utama + galeri foto tambahan
  const gallery = [
    product.image,
    ...(Array.isArray(product.images) ? product.images : []),
  ].filter(Boolean);

  const [activeImage, setActiveImage] = useState(gallery[0] ?? null);

  const isTebakHarga = product.sale_type === 'tebak_harga';
  // Yang disembunyikan pada tebak harga adalah HARGA DISKON — itulah angka
  // yang sedang ditebak. Harga normal (product.price) justru tetap dikirim
  // backend sebagai patokan pembeli menebak.
  const discountHidden =
    product.guess_discount_price === null ||
    product.guess_discount_price === undefined;
  // Harga diskon hanya relevan selama diskonnya masih bisa diperoleh: saat
  // periode tebak berjalan, dan saat pemenang masih memegang prioritas.
  // Begitu status 'public', yang berlaku adalah harga normal — memajang harga
  // diskon di situ membuat angka paling menonjol bukan angka yang ditagih.
  const showDiscount =
    isTebakHarga &&
    ['scheduled', 'active', 'ended'].includes(product.guess_status);

  return (
    <div className="mx-auto mt-6 max-w-5xl rounded-md border bg-white p-4 shadow-md sm:mt-10 sm:p-6">
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

      <div className="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Galeri Gambar */}
        <div className="space-y-4">
          <div className="overflow-hidden rounded-lg border-2 border-gray-200">
            <img
              src={
                activeImage
                  ? getProductImage({ image: activeImage })
                  : getProductImage(product)
              }
              className="h-80 w-full bg-gray-50 object-contain"
              alt={product.name}
            />
          </div>

          {/* Thumbnail Gallery */}
          {gallery.length > 1 && (
            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
              {gallery.map((img, i) => (
                <button
                  key={i}
                  type="button"
                  onClick={() => setActiveImage(img)}
                  className={`aspect-square overflow-hidden rounded-lg border-2 transition-all hover:scale-105 ${
                    activeImage === img
                      ? 'border-[#B77C4C] ring-2 ring-[#B77C4C] ring-offset-2'
                      : 'border-gray-200 hover:border-gray-300'
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

          {/* Video Produk */}
          {product.video && (
            <div className="rounded-lg border border-gray-200 p-4">
              <p className="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-700">
                <VideoIcon className="h-4 w-4" />
                Video Produk
              </p>
              <video
                src={storageMedia(product.video)}
                controls
                className="w-full rounded-md"
              />
            </div>
          )}
        </div>

        {/* Detail Produk */}
        <div className="space-y-4">
          {/* Nama Produk & Badges */}
          <div>
            <div className="mb-2 flex flex-wrap items-center gap-2">
              <h3 className="text-2xl font-bold text-[#2F3E46]">
                {product.name}
              </h3>
            </div>
            <div className="flex flex-wrap gap-2">
              {isTebakHarga && (
                <span className="inline-flex items-center gap-1 rounded-full bg-[#53685B] px-3 py-1 text-xs font-semibold text-white">
                  <GuessIcon className="h-3.5 w-3.5" />
                  Tebak Harga
                </span>
              )}
              {product.is_trade_in_enabled && (
                <span className="inline-flex items-center gap-1 rounded-full bg-[#B77C4C] px-3 py-1 text-xs font-semibold text-white">
                  <TradeInIcon className="h-3.5 w-3.5" />
                  Bisa Ditukar Tambah
                </span>
              )}
              {product.category && (
                <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                  <CategoryIcon className="h-3.5 w-3.5" />
                  {product.category}
                </span>
              )}
            </div>
          </div>

          {/* Informasi Toko */}
          {product.store && (
            <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
              <p className="mb-1 text-xs font-semibold text-gray-500 uppercase">
                Penjual
              </p>
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[#53685B] font-bold text-white">
                  {product.store.store_name?.[0]?.toUpperCase() || 'S'}
                </div>
                <div>
                  <p className="font-semibold text-gray-900">
                    {product.store.store_name || 'Toko'}
                  </p>
                  {product.store.address && (
                    <p className="flex items-center gap-1 text-xs text-gray-500">
                      <LocationIcon className="h-3 w-3 shrink-0" />
                      {product.store.address}
                    </p>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Harga & Stok */}
          <div className="rounded-lg border-2 border-[#B77C4C] bg-[#B77C4C]/5 p-4">
            <div className="space-y-2">
              <div>
                <p className="mb-1 text-xs font-semibold text-gray-500 uppercase">
                  {showDiscount ? 'Harga Normal' : 'Harga'}
                </p>
                <p
                  className={`font-bold text-[#B77C4C] ${
                    showDiscount
                      ? 'text-2xl line-through decoration-gray-400'
                      : 'text-3xl'
                  }`}
                >
                  {formatIDR(product.price)}
                </p>

                {showDiscount && (
                  <div className="mt-2">
                    <p className="mb-1 text-xs font-semibold text-gray-500 uppercase">
                      Harga Diskon
                    </p>
                    <p className="text-xl font-bold text-[#B77C4C] sm:text-3xl">
                      {discountHidden ? (
                        <span>??? (Tebak Harga)</span>
                      ) : (
                        formatIDR(product.guess_discount_price)
                      )}
                    </p>
                    {discountHidden && (
                      <p className="mt-1 text-xs text-gray-500">
                        Tebak harga diskonnya. Yang paling dekat — meleset
                        maksimal 5% — berhak membelinya di harga itu. Bila tidak
                        ada yang cukup dekat, produk dijual di harga normal.
                      </p>
                    )}
                  </div>
                )}
              </div>
              <div className="border-t border-gray-200 pt-2">
                <p className="mb-1 text-xs font-semibold text-gray-500 uppercase">
                  Stok Tersedia
                </p>
                <p className="text-lg font-bold text-gray-900">
                  {product.available_stock > 0 ? (
                    <span className="inline-flex items-center gap-1 text-green-600">
                      <SuccessIcon className="h-5 w-5" />
                      {product.available_stock} unit
                    </span>
                  ) : product.is_fully_reserved ? (
                    <span className="inline-flex items-center gap-1 text-amber-600">
                      <PendingIcon className="h-5 w-5" />
                      Sedang dipesan
                    </span>
                  ) : (
                    <span className="inline-flex items-center gap-1 text-red-600">
                      <FailIcon className="h-5 w-5" />
                      Habis
                    </span>
                  )}
                  {product.is_fully_reserved && (
                    <span className="mt-1 block text-xs font-normal text-gray-500">
                      Seluruh sisa stok sedang ditahan pesanan yang belum
                      dibayar. Barang ini bisa dibeli lagi bila pembayaran itu
                      batal atau kedaluwarsa.
                    </span>
                  )}
                </p>
              </div>
            </div>
          </div>

          {/* Deskripsi */}
          <div className="rounded-lg border border-gray-200 p-4">
            <p className="mb-2 text-xs font-semibold text-gray-500 uppercase">
              Deskripsi Produk
            </p>
            <p className="text-sm leading-relaxed whitespace-pre-line text-gray-700">
              {product.description || 'Tidak ada deskripsi'}
            </p>
          </div>

          {/* Sertifikat */}
          {product.certificate && (
            <div className="rounded-lg border-2 border-green-200 bg-green-50 p-4">
              <div className="flex items-start gap-3">
                <BadgeIcon className="mt-1 h-6 w-6 flex-shrink-0 text-green-600" />
                <div>
                  <p className="mb-1 font-semibold text-green-800">
                    Produk Bersertifikat
                  </p>
                  <p className="mb-2 text-sm text-green-700">
                    Produk ini dilengkapi dengan sertifikat keaslian resmi
                  </p>
                  <a
                    href={getProductCertificate(product)}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline"
                  >
                    <CertificateIcon className="h-4 w-4" />
                    Lihat Sertifikat
                  </a>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Action Buttons */}
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

      <Link
        href={`/checkout/product/${product.public_id}`}
        className="inline-block rounded-md bg-[#53685B] px-6 py-2 font-semibold text-white hover:cursor-pointer hover:bg-[#3c4a3e]"
      >
        Beli Sekarang
      </Link>
    </div>
  );
}

/* Bagian khusus alur Tebak Harga. */
function TebakHargaSection({ product, tebakHarga }) {
  const {
    guess_status: status,
    guess_starts_at,
    guess_ends_at,
    guess_finished_reason,
    winner_priority_until,
    guesses_count,
    my_guess,
    is_winner,
    winner_username,
    can_buy,
    leaderboard = [],
    tolerance_percent,
    winner_priority_hours,
  } = tebakHarga;

  const endedByExactGuess = guess_finished_reason === 'exact_guess';

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

      <div className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
        <Info label="Mulai" value={formatDateTime(guess_starts_at)} />
        <Info label="Berakhir" value={formatDateTime(guess_ends_at)} />
      </div>

      {/* Aturan main. Ditampilkan terus-menerus supaya tidak ada yang merasa
          dicurangi setelah hasilnya keluar. */}
      <details className="mt-4 rounded-md bg-white p-3 text-sm text-gray-600">
        <summary className="cursor-pointer font-semibold text-[#53685B]">
          Bagaimana pemenangnya ditentukan?
        </summary>
        <ol className="mt-2 list-decimal space-y-1 pl-5 text-xs">
          <li>
            Setiap orang hanya boleh mengirim <strong>satu tebakan</strong>, dan
            tebakan itu final.
          </li>
          <li>
            Tebakan yang meleset lebih dari{' '}
            <strong>{tolerance_percent}%</strong> dari harga diskon tidak ikut
            dinilai. Kalau tidak ada satu pun yang cukup dekat,{' '}
            <strong>tidak ada pemenang</strong> dan produk dijual di harga
            normal.
          </li>
          <li>
            Pemenangnya adalah tebakan dengan{' '}
            <strong>selisih paling kecil</strong> terhadap harga diskon.
          </li>
          <li>
            Bila dua orang sama-sama dekat — termasuk saat menebak angka yang
            persis sama — <strong>yang menebak lebih dulu yang menang</strong>.
          </li>
          <li>
            Begitu ada yang menebak <strong>persis tepat</strong>, sesi langsung
            ditutup saat itu juga dan pemenangnya diumumkan tanpa menunggu waktu
            berakhir.
          </li>
          <li>
            Pemenang berhak membeli di harga diskon selama{' '}
            <strong>{winner_priority_hours} jam</strong>. Lewat dari itu produk
            dijual biasa kepada siapa saja.
          </li>
        </ol>
      </details>

      {/* SCHEDULED */}
      {status === 'scheduled' && (
        <p className="mt-4 rounded-md bg-white p-4 text-sm text-gray-600">
          Periode tebak harga belum dimulai. Silakan kembali lagi pada{' '}
          <span className="font-semibold">
            {formatDateTime(guess_starts_at)}
          </span>
          .
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
                    Tebak harga diskon produk ini (hanya bisa sekali)
                  </label>
                  <CurrencyInput
                    id="amount"
                    name="amount"
                    min={1}
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
                    Tebakan bersifat final dan tidak dapat diubah. Menebak lebih
                    dulu menguntungkan: bila nanti ada yang menebak angka yang
                    sama, yang lebih dulu yang menang.
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
          {endedByExactGuess && (
            <p className="mb-3 flex items-start gap-2 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
              <GuessIcon className="mt-0.5 h-4 w-4 shrink-0" />
              <span>
                Sesi ini ditutup lebih cepat karena{' '}
                <strong>
                  {is_winner ? 'Anda' : (winner_username ?? 'seseorang')}
                </strong>{' '}
                menebak persis pada harga diskonnya. Tebakan setepat itu tidak
                mungkin dikalahkan, jadi menunggu waktu berakhir tidak ada
                gunanya.
              </span>
            </p>
          )}
          {is_winner ? (
            <div className="rounded-md border border-green-300 bg-green-50 p-4">
              <p className="flex items-center gap-2 font-semibold text-green-800">
                <CelebrateIcon className="h-5 w-5 shrink-0" />
                Selamat! Tebakan Anda paling mendekati harga diskon.
              </p>
              <p className="mt-1 text-sm text-green-700">
                Anda memiliki hak prioritas untuk membeli produk ini sampai{' '}
                <span className="font-semibold">
                  {formatDateTime(winner_priority_until)}
                </span>
                .
              </p>
              {can_buy && (
                <div className="mt-4">
                  <Link
                    href={`/checkout/product/${product.public_id}`}
                    className="inline-block rounded-md bg-[#53685B] px-6 py-2 font-semibold text-white hover:bg-[#3c4a3e]"
                  >
                    Beli Sekarang
                  </Link>
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
            Produk kini dijual biasa dengan harga normal — entah karena tidak
            ada tebakan yang cukup dekat, atau hak prioritas pemenang sudah
            berakhir.
          </p>
          <NormalPurchaseActions product={product} />
        </div>
      )}

      {/* Papan tebakan — versi tebak harga dari "Riwayat Bid" pada lelang. */}
      {status !== 'scheduled' && (
        <GuessLeaderboard entries={leaderboard} status={status} />
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

ProductDetailPage.layout = (page) => (
  <MainLayout title="Detail Produk" heroText="Detail Produk">
    {page}
  </MainLayout>
);
