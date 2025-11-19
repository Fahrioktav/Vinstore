import { Form, usePage } from '@inertiajs/react';
import FormLayout from '../../../layouts/form-layout';

export default function SellerCreateProductPage() {
  const { sessions } = usePage().props;

  return (
    <div className="mx-auto mt-10 max-w-4xl px-4">
      <h2 className="font-poppins mb-6 text-2xl font-bold">Tambah Produk</h2>

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
              <label className="block text-sm font-semibold">Nama Produk</label>
              <input
                type="text"
                name="name"
                className="w-full rounded-md border border-gray-400 px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]"
                required
              />
            </div>

            <div className="grid gap-6 md:grid-cols-2">
              <div>
                <label className="block text-sm font-semibold">Stok</label>
                <input
                  type="number"
                  name="stock"
                  className="w-full rounded-md border border-gray-400 px-4 py-2"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-semibold">Harga</label>
                <input
                  type="number"
                  step="0.01"
                  name="price"
                  className="w-full rounded-md border border-gray-400 px-4 py-2"
                  required
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-semibold">Kategori</label>
              <input
                type="text"
                name="category"
                className="w-full rounded-md border border-gray-400 px-4 py-2"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-semibold">Deskripsi</label>
              <textarea
                name="description"
                rows="4"
                className="w-full rounded-md border border-gray-400 px-4 py-2"
                required
              ></textarea>
            </div>
            <div>
              <label className="block text-sm font-semibold">
                Gambar Produk
              </label>
              <input
                type="file"
                name="image"
                className="w-full rounded-md border border-gray-400 px-4 py-2"
              />
            </div>
            <div className="text-right">
              <button
                type="submit"
                className="rounded-md bg-[#53685B] px-6 py-2 font-semibold text-white hover:bg-[#3c4a3e]"
              >
                Simpan Produk
              </button>
            </div>
          </>
        )}
      </Form>
    </div>
  );
}

SellerCreateProductPage.layout = (page) => (
  <FormLayout title="Seller Dashboard">{page}</FormLayout>
);
