import { useForm, usePage } from '@inertiajs/react';
import FormLayout from '@/layouts/form-layout';
import { Card, CardContent } from '@/components/ui/card';
import {
  AuthButton,
  AuthButtonLink,
  AuthInput,
  AuthLabel,
  AuthTextArea,
} from '@/components/auth/auth-layout';
import { getAuctionImage } from '@/lib/utils';

export default function SellerEditAuctionPage() {
  const { auction } = usePage().props;
  const { data, setData, post, processing, errors } = useForm({
    name: auction.name || '',
    description: auction.description || '',
    image: null,
    starting_price: auction.starting_price || '',
    min_increment: auction.min_increment || '',
    starts_at: toDateTimeLocal(auction.starts_at),
    ends_at: toDateTimeLocal(auction.ends_at),
    _method: 'PUT',
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    post(`/seller/auctions/${auction.public_id}`, {
      preserveScroll: true,
      forceFormData: true,
    });
  };

  return (
    <section className="flex w-full max-w-2xl grow px-6 py-8">
      <Card className="my-auto w-full shadow-md">
        <CardContent>
          <h2 className="font-poppins mb-6 text-2xl font-bold">
            Edit Barang Lelang
          </h2>

          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            {Object.keys(errors).length > 0 && (
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
              <AuthInput
                id="name"
                type="text"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                required
              />
            </div>

            <div>
              <AuthLabel htmlFor="description">Deskripsi</AuthLabel>
              <AuthTextArea
                id="description"
                rows="4"
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                required
              />
            </div>

            <div>
              <AuthLabel htmlFor="image">Foto Barang</AuthLabel>
              <img
                src={getAuctionImage(auction)}
                alt={auction.name}
                className="mb-3 h-32 w-32 rounded-lg object-cover"
              />
              <AuthInput
                id="image"
                type="file"
                accept="image/*"
                onChange={(e) => setData('image', e.target.files[0])}
              />
              <p className="mt-1 text-xs text-gray-500">
                Kosongkan jika tidak ingin mengganti foto.
              </p>
            </div>

            <div className="grid gap-6 md:grid-cols-2">
              <div>
                <AuthLabel htmlFor="starting_price">Harga Awal</AuthLabel>
                <AuthInput
                  id="starting_price"
                  type="number"
                  min="1000"
                  value={data.starting_price}
                  onChange={(e) => setData('starting_price', e.target.value)}
                  required
                />
              </div>
              <div>
                <AuthLabel htmlFor="min_increment">Minimal Kenaikan Bid</AuthLabel>
                <AuthInput
                  id="min_increment"
                  type="number"
                  min="1000"
                  value={data.min_increment}
                  onChange={(e) => setData('min_increment', e.target.value)}
                  required
                />
              </div>
            </div>

            <div className="grid gap-6 md:grid-cols-2">
              <div>
                <AuthLabel htmlFor="starts_at">Tanggal Mulai</AuthLabel>
                <AuthInput
                  id="starts_at"
                  type="datetime-local"
                  value={data.starts_at}
                  onChange={(e) => setData('starts_at', e.target.value)}
                  required
                />
              </div>
              <div>
                <AuthLabel htmlFor="ends_at">Tanggal Selesai</AuthLabel>
                <AuthInput
                  id="ends_at"
                  type="datetime-local"
                  value={data.ends_at}
                  onChange={(e) => setData('ends_at', e.target.value)}
                  required
                />
              </div>
            </div>

            <div className="flex justify-end gap-4">
              <AuthButtonLink href="/seller/dashboard">Kembali</AuthButtonLink>
              <AuthButton type="submit" disabled={processing}>
                Simpan Perubahan
              </AuthButton>
            </div>
          </form>
        </CardContent>
      </Card>
    </section>
  );
}

SellerEditAuctionPage.layout = (page) => (
  <FormLayout title="Edit Lelang">{page}</FormLayout>
);

function toDateTimeLocal(value) {
  const date = new Date(value);
  const offset = date.getTimezoneOffset();
  const localDate = new Date(date.getTime() - offset * 60000);

  return localDate.toISOString().slice(0, 16);
}
