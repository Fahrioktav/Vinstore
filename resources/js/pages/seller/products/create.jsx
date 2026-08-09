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
import { CertificateIcon, VideoIcon } from '@/components/icons';

export default function SellerCreateProductPage() {
  const { categories = [] } = usePage().props;
  const [saleType, setSaleType] = useState('normal');
  const isTebakHarga = saleType === 'tebak_harga';

  return (
    <section className="flex w-full max-w-2xl grow px-6 py-8">
      <Card className="my-auto w-full shadow-md transition hover:shadow-lg">
        <CardContent>
          <h2 className="font-poppins mb-6 text-2xl font-bold">
            Tambah Produk
          </h2>

          <Form
            action="/seller/products"
            method="POST"
            encType="multipart/form-data"
            className="flex flex-col gap-4"
            disableWhileProcessing={true}
            options={{ preserveScroll: true }}
          >
            {({ errors, hasErrors }) => (
              <>
                {/* {-- Error Validasi --} */}
                {hasErrors && (
                  <div
                    className="relative mb-6 rounded border border-red-400 bg-red-100 px-4 py-3 text-red-700"
                    role="alert"
                  >
                    <strong className="font-bold">Oops!</strong>
                    <ul className="ml-5 list-disc">
                      {Object.values(errors).map((error) => (
                        <li key={error}>{error}</li>
                      ))}
                    </ul>
                  </div>
                )}

                <div>
                  <AuthLabel htmlFor="name">Nama Produk</AuthLabel>
                  <AuthInput id="name" type="text" name="name" required />
                </div>

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
                      Harga normal tetap terlihat sebagai patokan; harga
                      diskonnya disembunyikan (ditampilkan ???). Pembeli
                      mengirim satu tebakan.
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
                      required
                      min="0"
                      defaultValue="0"
                    />
                  </div>
                  <div>
                    <AuthLabel htmlFor="price">
                      {isTebakHarga ? 'Harga Normal (tetap terlihat)' : 'Harga'}
                    </AuthLabel>
                    <AuthInput
                      id="price"
                      type="number"
                      // step="0.01"
                      name="price"
                      required
                      min="0"
                      defaultValue="0"
                    />
                  </div>
                </div>

                <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                  <p className="text-sm font-semibold text-[#2F3E46]">
                    Berat & Dimensi Paket
                  </p>
                  <p className="mt-1 mb-4 text-xs text-gray-500">
                    Dipakai menghitung biaya pengiriman. Yang ditagih adalah
                    berat yang lebih besar antara berat asli dan berat
                    volumetrik (P × L × T ÷ 6000), sama seperti cara kurir
                    menghitung — barang besar tapi ringan tetap memakan ruang.
                  </p>

                  <div className="grid gap-4 md:grid-cols-4">
                    <div>
                      <AuthLabel htmlFor="weight">Berat (gram)</AuthLabel>
                      <AuthInput
                        id="weight"
                        type="number"
                        name="weight"
                        required
                        min="1"
                        defaultValue="1000"
                      />
                    </div>
                    <div>
                      <AuthLabel htmlFor="length">Panjang (cm)</AuthLabel>
                      <AuthInput
                        id="length"
                        type="number"
                        name="length"
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
                        min="1"
                        placeholder="opsional"
                      />
                    </div>
                  </div>

                  <p className="mt-2 text-xs text-gray-400">
                    Berat dibulatkan ke atas ke kilogram penuh — 1.200 gram
                    ditagih 2 kg. Dimensi boleh dikosongkan; bila kosong,
                    biayanya murni dari berat asli.
                  </p>
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
                      <AuthInput
                        id="guess_discount_price"
                        type="number"
                        name="guess_discount_price"
                        placeholder="Contoh: 800000"
                        min="1"
                        required={isTebakHarga}
                      />
                      <p className="mt-1 text-xs text-gray-500">
                        Harus lebih kecil dari harga normal. Pembeli yang
                        menebak paling dekat — meleset maksimal 5% — menang dan
                        berhak membelinya di harga ini selama 24 jam. Bila tidak
                        ada yang cukup dekat, produk dijual di harga normal.
                      </p>
                    </div>
                    <div>
                      <AuthLabel htmlFor="guess_starts_at">
                        Tanggal Mulai
                      </AuthLabel>
                      <AuthInput
                        id="guess_starts_at"
                        type="datetime-local"
                        name="guess_starts_at"
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
                    rows="4"
                    required
                  />
                </div>
                <div>
                  <AuthLabel htmlFor="image">Gambar Produk</AuthLabel>
                  <AuthInput
                    id="image"
                    type="file"
                    name="image"
                    accept="image/*"
                    required
                  />
                  <p className="mt-1 text-xs text-gray-500">
                    Gambar utama produk. Format: JPG, PNG (Max 2MB).
                  </p>
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
                    Bisa pilih beberapa foto sekaligus (maks. 5). Akan tampil di
                    detail produk untuk buyer. Format: JPG, PNG (Max 2MB /
                    foto).
                  </p>
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
                    Video singkat kondisi barang. Format: MP4, WebM, MOV (Max
                    20MB).
                  </p>
                </div>
                {!isTebakHarga && (
                  <label className="flex items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <input
                      id="is_barterable"
                      type="checkbox"
                      name="is_barterable"
                      value="1"
                      className="mt-1 h-4 w-4"
                    />
                    <span>
                      <span className="block text-sm font-semibold text-[#2F3E46]">
                        Produk ini bisa dibarter
                      </span>
                      <span className="block text-xs text-gray-500">
                        Jika dicentang, seller lain dapat mengajukan barter
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
                <div className="flex justify-end gap-4">
                  <AuthButtonLink href="/seller/dashboard">
                    Kembali
                  </AuthButtonLink>
                  <AuthButton type="submit">Simpan Produk</AuthButton>
                </div>
              </>
            )}
          </Form>
        </CardContent>
      </Card>
    </section>
  );
}

SellerCreateProductPage.layout = (page) => (
  <FormLayout title="Seller Dashboard">{page}</FormLayout>
);
