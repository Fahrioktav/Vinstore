import { Form } from '@inertiajs/react';
import FormLayout from '@/layouts/form-layout';
import { Card, CardContent } from '@/components/ui/card';
import {
  AuthButton,
  AuthButtonLink,
  AuthInput,
  AuthLabel,
  AuthTextArea,
} from '@/components/auth/auth-layout';

export default function SellerCreateAuctionPage() {
  return (
    <section className="flex w-full max-w-2xl grow px-6 py-8">
      <Card className="my-auto w-full shadow-md">
        <CardContent>
          <h2 className="font-poppins mb-6 text-2xl font-bold">
            Tambah Barang Lelang
          </h2>

          <Form
            action="/seller/auctions"
            method="POST"
            encType="multipart/form-data"
            className="flex flex-col gap-4"
            disableWhileProcessing={true}
            options={{ preserveScroll: true }}
          >
            {({ errors, hasErrors }) => (
              <>
                {hasErrors && (
                  <div className="rounded border border-red-400 bg-red-100 px-4 py-3 text-red-700">
                    <strong className="font-bold">Oops!</strong>
                    <ul className="ml-5 list-disc">
                      {Object.values(errors).map((error) => (
                        <li key={error}>{error}</li>
                      ))}
                    </ul>
                  </div>
                )}

                <div>
                  <AuthLabel htmlFor="name">Nama Barang Antik</AuthLabel>
                  <AuthInput id="name" type="text" name="name" required />
                </div>

                <div>
                  <AuthLabel htmlFor="description">Deskripsi</AuthLabel>
                  <AuthTextArea id="description" name="description" rows="4" required />
                </div>

                <div>
                  <AuthLabel htmlFor="image">Foto Barang</AuthLabel>
                  <AuthInput id="image" type="file" name="image" accept="image/*" required />
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                  <div>
                    <AuthLabel htmlFor="starting_price">Harga Awal</AuthLabel>
                    <AuthInput
                      id="starting_price"
                      type="number"
                      name="starting_price"
                      min="1000"
                      required
                    />
                  </div>
                  <div>
                    <AuthLabel htmlFor="min_increment">Minimal Kenaikan Bid</AuthLabel>
                    <AuthInput
                      id="min_increment"
                      type="number"
                      name="min_increment"
                      min="1000"
                      required
                    />
                  </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                  <div>
                    <AuthLabel htmlFor="starts_at">Tanggal Mulai</AuthLabel>
                    <AuthInput id="starts_at" type="datetime-local" name="starts_at" required />
                  </div>
                  <div>
                    <AuthLabel htmlFor="ends_at">Tanggal Selesai</AuthLabel>
                    <AuthInput id="ends_at" type="datetime-local" name="ends_at" required />
                  </div>
                </div>

                <div className="flex justify-end gap-4">
                  <AuthButtonLink href="/seller/dashboard">Kembali</AuthButtonLink>
                  <AuthButton type="submit">Ajukan Lelang</AuthButton>
                </div>
              </>
            )}
          </Form>
        </CardContent>
      </Card>
    </section>
  );
}

SellerCreateAuctionPage.layout = (page) => (
  <FormLayout title="Tambah Lelang">{page}</FormLayout>
);
