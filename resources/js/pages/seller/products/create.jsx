import { Form, usePage } from '@inertiajs/react';
import FormLayout from '@/layouts/form-layout';
import { Card, CardContent } from '@/components/ui/card';
import {
  AuthButton,
  AuthInput,
  AuthLabel,
  AuthTextArea,
} from '@/components/auth/auth-layout';

export default function SellerCreateProductPage() {
  const { sessions } = usePage().props;

  return (
    <section className="flex max-w-4xl grow px-6 py-8">
      <Card className="my-auto shadow-md transition hover:shadow-lg">
        <CardContent>
          <h2 className="font-poppins mb-6 text-2xl font-bold">
            Tambah Produk
          </h2>

          {sessions ? (
            <>
              <p>{sessions.success}</p>
              <p>{sessions.error}</p>
            </>
          ) : (
            'Sessions kosong'
          )}

          <Form
            action="/products"
            method="POST"
            encType="multipart/form-data"
            className="space-y-6"
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

                <div className="grid gap-6 md:grid-cols-2">
                  <div>
                    <AuthLabel htmlFor="stock">Stok</AuthLabel>
                    <AuthInput
                      id="stock"
                      type="number"
                      name="stock"
                      required
                      min="0"
                    />
                  </div>
                  <div>
                    <AuthLabel htmlFor="price">Harga</AuthLabel>
                    <AuthInput
                      id="price"
                      type="number"
                      step="0.01"
                      name="price"
                      required
                      min="0"
                    />
                  </div>
                </div>

                <div>
                  <AuthLabel htmlFor="category">Kategori</AuthLabel>
                  <AuthInput
                    id="category"
                    type="text"
                    name="category"
                    required
                  />
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
                  />
                </div>
                <div className="text-right">
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
