import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';

export default function EditProduct() {
  const { product, errors: serverErrors } = usePage().props;
  const [imagePreview, setImagePreview] = useState(
    product.image ? `/${product.image}` : null
  );

  const { data, setData, post, processing, errors } = useForm({
    name: product.name || '',
    price: product.price || '',
    stock: product.stock || '',
    category: product.category || '',
    description: product.description || '',
    image: null,
    _method: 'PUT',
  });

  const handleImageChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      setData('image', file);
      const reader = new FileReader();
      reader.onloadend = () => {
        setImagePreview(reader.result);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    post(`/admin/products/${product.id}`, {
      preserveScroll: true,
      forceFormData: true,
    });
  };

  return (
    <>
      <Head title="Edit Produk" />
      <div className="mx-auto max-w-3xl px-6 py-8">
        <div className="rounded-2xl bg-white p-8 shadow-md shadow-[#53685B]/20">
          <h2 className="mb-6 text-3xl font-bold text-[#53685B]">
            📦 Edit Produk
          </h2>

          <div className="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <p className="text-sm text-gray-600">
              <span className="font-semibold">Toko:</span>{' '}
              {product.store?.store_name || '-'}
            </p>
          </div>

          <form onSubmit={handleSubmit}>
            <div className="mb-4">
              <label className="mb-2 block text-sm font-semibold">
                Nama Produk
              </label>
              <input
                type="text"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                className={`w-full rounded-lg border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none ${errors.name ? 'border-red-500' : 'border-gray-300'}`}
                required
              />
              {errors.name && (
                <p className="mt-1 text-xs text-red-500">{errors.name}</p>
              )}
            </div>

            <div className="mb-4 grid grid-cols-2 gap-4">
              <div>
                <label className="mb-2 block text-sm font-semibold">
                  Harga
                </label>
                <input
                  type="number"
                  value={data.price}
                  onChange={(e) => setData('price', e.target.value)}
                  className={`w-full rounded-lg border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none ${errors.price ? 'border-red-500' : 'border-gray-300'}`}
                  required
                  min="0"
                />
                {errors.price && (
                  <p className="mt-1 text-xs text-red-500">{errors.price}</p>
                )}
              </div>

              <div>
                <label className="mb-2 block text-sm font-semibold">Stok</label>
                <input
                  type="number"
                  value={data.stock}
                  onChange={(e) => setData('stock', e.target.value)}
                  className={`w-full rounded-lg border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none ${errors.stock ? 'border-red-500' : 'border-gray-300'}`}
                  required
                  min="0"
                />
                {errors.stock && (
                  <p className="mt-1 text-xs text-red-500">{errors.stock}</p>
                )}
              </div>
            </div>

            <div className="mb-4">
              <label className="mb-2 block text-sm font-semibold">
                Kategori
              </label>
              <input
                type="text"
                value={data.category}
                onChange={(e) => setData('category', e.target.value)}
                className={`w-full rounded-lg border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none ${errors.category ? 'border-red-500' : 'border-gray-300'}`}
                required
              />
              {errors.category && (
                <p className="mt-1 text-xs text-red-500">{errors.category}</p>
              )}
            </div>

            <div className="mb-4">
              <label className="mb-2 block text-sm font-semibold">
                Deskripsi
              </label>
              <textarea
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                rows="4"
                className={`w-full rounded-lg border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none ${errors.description ? 'border-red-500' : 'border-gray-300'}`}
              />
              {errors.description && (
                <p className="mt-1 text-xs text-red-500">
                  {errors.description}
                </p>
              )}
            </div>

            <div className="mb-6">
              <label className="mb-2 block text-sm font-semibold">
                Foto Produk
              </label>
              {imagePreview && (
                <div className="mb-4">
                  <img
                    src={imagePreview}
                    alt="Preview"
                    className="h-40 w-40 rounded-lg object-cover shadow-md"
                  />
                </div>
              )}
              <input
                type="file"
                onChange={handleImageChange}
                accept="image/*"
                className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
              />
              <p className="mt-1 text-xs text-gray-500">
                Kosongkan jika tidak ingin mengubah gambar
              </p>
              {errors.image && (
                <p className="mt-1 text-xs text-red-500">{errors.image}</p>
              )}
            </div>

            <div className="flex gap-4">
              <button
                type="submit"
                disabled={processing}
                className="flex-1 rounded-lg bg-[#53685B] px-6 py-3 font-bold text-white shadow-md transition hover:bg-[#3c4a3e] hover:shadow-lg disabled:opacity-50"
              >
                💾 Simpan Perubahan
              </button>
              <Link
                href="/admin/products"
                className="flex-1 rounded-lg bg-gray-300 px-6 py-3 text-center font-bold text-gray-800 shadow-md transition hover:bg-gray-400 hover:shadow-lg"
              >
                ← Kembali
              </Link>
            </div>
          </form>
        </div>
      </div>
    </>
  );
}

EditProduct.layout = (page) => (
  <MainLayout title="Edit Produk">{page}</MainLayout>
);
