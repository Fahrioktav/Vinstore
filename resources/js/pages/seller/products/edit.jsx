import { Form, Link, usePage } from '@inertiajs/react';
import FormLayout from '@/layouts/form-layout';
import { Card, CardContent } from '@/components/ui/card';
import {
  AuthButton,
  AuthButtonLink,
  AuthInput,
  AuthLabel,
  AuthTextArea,
} from '@/components/auth/auth-layout';

export default function SellerEditProductPage() {
  const { product } = usePage().props;

  return (
    <section className="flex w-full max-w-2xl grow px-6 py-8">
      <Card className="my-auto w-full shadow-md transition hover:shadow-lg">
        <CardContent>
          <h2 className="font-poppins mb-6 text-2xl font-bold">Edit Produk</h2>

          <Form
            action={`/products/${product.id}`}
            method="PUT"
            encType="multipart/form-data"
            className="flex flex-col gap-4"
            disableWhileProcessing={true}
            options={{ preserveScroll: true }}
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

            <div className="grid gap-6 md:grid-cols-2">
              <div>
                <AuthLabel htmlFor="stock">Stok</AuthLabel>
                <AuthInput
                  id="stock"
                  type="number"
                  name="stock"
                  defaultValue={product.stock}
                  required
                />
              </div>

              <div>
                <AuthLabel htmlFor="price">Harga</AuthLabel>
                <AuthInput
                  id="price"
                  type="number"
                  name="price"
                  defaultValue={product.price}
                  required
                />
              </div>
            </div>

            <div>
              <AuthLabel htmlFor="category">Kategori</AuthLabel>
              <AuthInput
                id="category"
                type="text"
                name="category"
                defaultValue={product.category}
                required
              />
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
                <AuthInput id="image" type="file" name="image" />
                {product.image && (
                  <div className="">
                    <img
                      src={`/${product.image}`}
                      className="h-24 w-28 rounded-lg object-cover"
                    />
                  </div>
                )}
              </div>
            </div>

            <div>
              <AuthLabel htmlFor="certificate">
                📜 Sertifikat Keaslian (Opsional)
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
                    href={`/${product.certificate}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex items-center gap-1 text-blue-600 hover:underline"
                  >
                    <svg
                      className="h-4 w-4"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" />
                    </svg>
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
