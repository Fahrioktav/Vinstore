import { Form, usePage } from '@inertiajs/react';
import { useState } from 'react';
import FormLayout from '@/layouts/form-layout';
import { Card, CardContent } from '@/components/ui/card';
import {
  AuthButton,
  AuthButtonLink,
  AuthInput,
  AuthLabel,
  AuthTextArea,
} from '@/components/auth/auth-layout';
import CurrencyInput from '@/components/currency-input';
import { CertificateIcon, VideoIcon } from '@/components/icons';
import { getProductCertificate, getProductImage } from '@/lib/utils';

export default function SellerEditProductPage() {
  const { product, categories = [] } = usePage().props;
  const [saleType, setSaleType] = useState(product.sale_type || 'normal');
  const isTebakHarga = saleType === 'tebak_harga';
  // Tebak harga yang sedang berjalan / selesai tidak boleh diubah.
  const guessLocked =
    product.sale_type === 'tebak_harga' &&
    ['active', 'ended'].includes(product.guess_status);

  return (
    <section className="flex w-full max-w-2xl grow px-6 py-8">
      <Card className="my-auto w-full shadow-md transition hover:shadow-lg">
        <CardContent>
          <h2 className="font-poppins mb-6 text-2xl font-bold">Edit Produk</h2>

          <Form
            action={`/seller/products/${product.public_id}`}
            method="POST"
            encType="multipart/form-data"
            className="flex flex-col gap-4"
            disableWhileProcessing={true}
            options={{ preserveScroll: true }}
            transform={(data) => ({ ...data, _method: 'PUT' })}
          >
            <div>
              <AuthLabel htmlFor="name">Nama Produk</AuthLabel>
              <AuthInput
                id="name"
                type="text"
                name="name"
                defaultValue={product.name}
                required
              />
            </div>

            {guessLocked && (
              <div className="rounded border border-yellow-400 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                Produk Tebak Harga ini sedang berjalan atau sudah selesai,
                sehingga perubahan tidak akan disimpan.
              </div>
            )}

            <div>
              <AuthLabel htmlFor="sale_type">Jenis Penjualan</AuthLabel>
              <select
                id="sale_type"
                name="sale_type"
                value={saleType}
                onChange={(e) => setSaleType(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-[#53685B] focus:outline-none"
              >
                <option value="normal">Penjualan Biasa</option>
                <option value="tebak_harga">Tebak Harga</option>
              </select>
              {isTebakHarga && (
                <p className="mt-1 text-xs text-gray-500">
                  Produk ditawarkan dengan harga diskon yang harus ditebak.
                  Harga normal tetap terlihat sebagai patokan; harga diskonnya
                  disembunyikan (ditampilkan ???). Pembeli mengirim satu
                  tebakan.
                </p>
              )}
            </div>

            <div className="grid gap-6 md:grid-cols-2">
              <div>
                <AuthLabel htmlFor="stock">Stok</AuthLabel>
                <AuthInput
                  id="stock"
                  type="number"
                  name="stock"
                  defaultValue={product.stock}
                  min="0"
                  required
                />
              </div>

              <div>
                <AuthLabel htmlFor="price">
                  {isTebakHarga ? 'Harga Normal (tetap terlihat)' : 'Harga'}
                </AuthLabel>
                <CurrencyInput
                  id="price"
                  name="price"
                  defaultValue={product.price}
                  min={1}
                  required
                />
              </div>
            </div>

            <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
              <p className="mb-4 text-sm font-semibold text-[#2F3E46]">
                Berat & Dimensi Paket
              </p>

              <div className="grid gap-4 md:grid-cols-4">
                <div>
                  <AuthLabel htmlFor="weight">Berat (gram)</AuthLabel>
                  <AuthInput
                    id="weight"
                    type="number"
                    name="weight"
                    defaultValue={product.weight ?? 1000}
                    min="1"
                    required
                  />
                </div>
                <div>
                  <AuthLabel htmlFor="length">Panjang (cm)</AuthLabel>
                  <AuthInput
                    id="length"
                    type="number"
                    name="length"
                    defaultValue={product.length ?? ''}
                    min="1"
                    placeholder="opsional"
                  />
                </div>
                <div>
                  <AuthLabel htmlFor="width">Lebar (cm)</AuthLabel>
                  <AuthInput
                    id="width"
                    type="number"
                    name="width"
                    defaultValue={product.width ?? ''}
                    min="1"
                    placeholder="opsional"
                  />
                </div>
                <div>
                  <AuthLabel htmlFor="height">Tinggi (cm)</AuthLabel>
                  <AuthInput
                    id="height"
                    type="number"
                    name="height"
                    defaultValue={product.height ?? ''}
                    min="1"
                    placeholder="opsional"
                  />
                </div>
              </div>
            </div>

            {isTebakHarga && (
              <div className="grid gap-6 rounded-lg border border-[#53685B]/30 bg-[#53685B]/5 p-4 md:grid-cols-2">
                <div className="md:col-span-2">
                  <p className="text-sm font-semibold text-[#2F3E46]">
                    Pengaturan Tebak Harga
                  </p>
                  <p className="text-xs text-gray-500">
                    Harga di atas menjadi harga normal dan tetap terlihat
                    pembeli. Yang ditebak adalah harga diskonnya.
                  </p>
                </div>
                <div className="md:col-span-2">
                  <AuthLabel htmlFor="guess_discount_price">
                    Harga Diskon (yang ditebak)
                  </AuthLabel>
                  <CurrencyInput
                    id="guess_discount_price"
                    name="guess_discount_price"
                    defaultValue={product.guess_discount_price ?? ''}
                    min={1}
                    required={isTebakHarga}
                  />
                  <p className="mt-1 text-xs text-gray-500">
                    Harus lebih kecil dari harga normal. Pembeli yang menebak
                    paling dekat — meleset maksimal 5% — menang dan berhak
                    membelinya di harga ini selama 24 jam. Bila tidak ada yang
                    cukup dekat, produk dijual di harga normal.
                  </p>
                </div>
                <div>
                  <AuthLabel htmlFor="guess_starts_at">Tanggal Mulai</AuthLabel>
                  <AuthInput
                    id="guess_starts_at"
                    type="datetime-local"
                    name="guess_starts_at"
                    defaultValue={
                      product.guess_starts_at
                        ? toDateTimeLocal(product.guess_starts_at)
                        : ''
                    }
                    required={isTebakHarga}
                  />
                </div>
                <div>
                  <AuthLabel htmlFor="guess_ends_at">
                    Tanggal Berakhir
                  </AuthLabel>
                  <AuthInput
                    id="guess_ends_at"
                    type="datetime-local"
                    name="guess_ends_at"
                    defaultValue={
                      product.guess_ends_at
                        ? toDateTimeLocal(product.guess_ends_at)
                        : ''
                    }
                    required={isTebakHarga}
                  />
                </div>
              </div>
            )}

            <div>
              <AuthLabel htmlFor="category">Kategori</AuthLabel>
              <select
                id="category"
                name="category"
                defaultValue={product.category || ''}
                className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-[#53685B] focus:outline-none"
                required
              >
                <option value="">Pilih kategori</option>
                {categories.map((category) => (
                  <option key={category.name} value={category.name}>
                    {category.name}
                  </option>
                ))}
              </select>
              {categories.length === 0 && (
                <p className="mt-1 text-xs text-red-500">
                  Belum ada kategori. Hubungi admin untuk membuat kategori.
                </p>
              )}
            </div>

            <div>
              <AuthLabel htmlFor="description">Deskripsi</AuthLabel>
              <AuthTextArea
                id="description"
                name="description"
                defaultValue={product.description}
                rows="4"
                required
              />
            </div>

            <div>
              <AuthLabel htmlFor="image">Gambar Produk (Opsional)</AuthLabel>
              <div className="flex flex-row-reverse items-start gap-4">
                <AuthInput
                  id="image"
                  type="file"
                  name="image"
                  accept="image/*"
                />
                {product.image && (
                  <div className="">
                    <img
                      src={getProductImage(product)}
                      className="h-24 w-28 rounded-lg object-cover"
                    />
                  </div>
                )}
              </div>
            </div>

            <div>
              <AuthLabel htmlFor="images">
                Foto Tambahan (tampak depan, samping, kondisi)
              </AuthLabel>
              <AuthInput
                id="images"
                type="file"
                name="images[]"
                accept="image/*"
                multiple
              />
              <p className="mt-1 text-xs text-gray-500">
                Mengunggah foto baru akan mengganti seluruh foto tambahan
                sebelumnya (maks. 5).
              </p>
              {Array.isArray(product.images) && product.images.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-2">
                  {product.images.map((img, i) => (
                    <img
                      key={i}
                      src={getProductImage({ image: img })}
                      className="h-16 w-16 rounded-md object-cover"
                    />
                  ))}
                </div>
              )}
            </div>

            <div>
              <AuthLabel htmlFor="video">
                <VideoIcon className="mr-1 inline h-4 w-4 align-text-bottom" />
                Video Produk (Opsional)
              </AuthLabel>
              <AuthInput
                id="video"
                type="file"
                name="video"
                accept="video/mp4,video/webm,video/quicktime"
              />
              <p className="mt-1 text-xs text-gray-500">
                Format: MP4, WebM, MOV (Max 20MB).
              </p>
              {product.video && (
                <video
                  src={getProductImage({ image: product.video })}
                  controls
                  className="mt-2 h-32 rounded-md"
                />
              )}
            </div>

            {!isTebakHarga && (
              <label className="flex items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                <input
                  id="is_trade_in_enabled"
                  type="checkbox"
                  name="is_trade_in_enabled"
                  value="1"
                  defaultChecked={!!product.is_trade_in_enabled}
                  className="mt-1 h-4 w-4"
                />
                <span>
                  <span className="block text-sm font-semibold text-[#2F3E46]">
                    Produk ini bisa ditukar tambah
                  </span>
                  <span className="block text-xs text-gray-500">
                    Jika dicentang, seller lain dapat mengajukan tukar tambah
                    untuk produk ini (selama stok masih ada).
                  </span>
                </span>
              </label>
            )}

            <div>
              <AuthLabel htmlFor="certificate">
                <CertificateIcon className="mr-1 inline h-4 w-4 align-text-bottom" />
                Sertifikat Keaslian (Opsional)
              </AuthLabel>
              <div>
                <AuthInput
                  id="certificate"
                  type="file"
                  name="certificate"
                  accept=".pdf,.jpg,.jpeg,.png"
                />
                <p className="mt-1 text-xs text-gray-500">
                  Format: PDF, JPG, PNG (Max 5MB)
                </p>
              </div>
              {product.certificate && (
                <div className="mt-2">
                  <a
                    href={getProductCertificate(product)}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex items-center gap-1 text-blue-600 hover:underline"
                  >
                    <CertificateIcon className="h-5 w-5 shrink-0" />
                    Lihat Sertifikat Saat Ini
                  </a>
                </div>
              )}
            </div>

            <div className="flex justify-end gap-4">
              <AuthButtonLink href="/seller/dashboard">Kembali</AuthButtonLink>
              <AuthButton type="submit">Simpan Perubahan</AuthButton>
            </div>
          </Form>
        </CardContent>
      </Card>
    </section>
  );
}

SellerEditProductPage.layout = (page) => (
  <FormLayout title="Seller Dashboard" heroText="Edit Produk">
    {page}
  </FormLayout>
);

function toDateTimeLocal(value) {
  const date = new Date(value);
  const offset = date.getTimezoneOffset();
  const localDate = new Date(date.getTime() - offset * 60000);

  return localDate.toISOString().slice(0, 16);
}
