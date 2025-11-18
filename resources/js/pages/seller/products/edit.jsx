import { Form, Link, usePage } from '@inertiajs/react';
import MainLayout from '../../../layouts/main-layout';

export default function SellerEditProductPage() {
  const { product } = usePage().props;

  return (
    <div className="mx-auto max-w-xl rounded bg-white p-6 shadow">
      <h2 className="mb-4 text-xl font-bold">Edit Produk</h2>

      <Form
        action={`/products/${product.id}`}
        method="PUT"
        encType="multipart/form-data"
        disableWhileProcessing={true}
        options={{ preserveScroll: true }}
      >
        <div className="mb-4">
          <label className="mb-1 block text-sm font-semibold">
            Nama Produk
          </label>
          <input
            type="text"
            name="name"
            defaultValue={product.name}
            className="w-full rounded border p-2"
            required
          />
        </div>
        <div className="mb-4">
          <label className="mb-1 block text-sm font-semibold">Stok</label>
          <input
            type="number"
            name="stock"
            defaultValue={product.stock}
            className="w-full rounded border p-2"
            required
          />
        </div>
        <div className="mb-4">
          <label className="mb-1 block text-sm font-semibold">Harga</label>
          <input
            type="number"
            name="price"
            defaultValue={product.price}
            className="w-full rounded border p-2"
            required
          />
        </div>
        <div className="mb-4">
          <label className="mb-1 block text-sm font-semibold">Kategori</label>
          <input
            type="text"
            name="category"
            defaultValue={product.category}
            className="w-full rounded border p-2"
            required
          />
        </div>
        <div className="mb-4">
          <label className="mb-1 block text-sm font-semibold">Deskripsi</label>
          <textarea
            name="description"
            rows="4"
            className="w-full rounded border p-2"
            required
          >
            {product.description}
          </textarea>
        </div>
        <div className="mb-4">
          <label className="mb-1 block text-sm font-semibold">
            Gambar Produk (opsional)
          </label>
          <input type="file" name="image" className="w-full" />
          {product.image && (
            <div className="mt-2">
              <img
                src={`/${product.image}`}
                className="h-24 w-24 object-cover"
              />
            </div>
          )}
        </div>
        <button
          type="submit"
          className="rounded bg-green-600 px-4 py-2 text-white hover:bg-green-700"
        >
          Simpan Perubahan
        </button>
        <Link
          href="/seller/dashboard"
          className="ml-4 text-gray-600 hover:underline"
        >
          Kembali
        </Link>
      </Form>
    </div>
  );
}

SellerEditProductPage.layout = (page) => (
  <MainLayout title="Seller Dashboard" heroText="Edit Produk">
    {page}
  </MainLayout>
);
