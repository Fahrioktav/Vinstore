import { Head, Link, useForm, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';

export default function EditStore() {
  const { store, errors: serverErrors } = usePage().props;
  const { data, setData, put, processing, errors } = useForm({
    store_name: store.store_name || '',
    category: store.category || '',
    location: store.location || '',
    description: store.description || '',
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    console.log(data);
    put(`/admin/stores/${store.id}`, {
      preserveScroll: true,
    });
  };

  return (
    <>
      <Head title="Edit Toko" />
      <div className="mx-auto max-w-3xl px-6 py-8">
        <div className="rounded-2xl bg-white p-8 shadow-md shadow-[#53685B]/20">
          <h2 className="mb-6 text-3xl font-bold text-[#53685B]">
            ✏️ Edit Toko
          </h2>

          <div className="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <p className="text-sm text-gray-600">
              <span className="font-semibold">Owner:</span>{' '}
              {store.user?.username || '-'}
            </p>
            <p className="text-sm text-gray-600">
              <span className="font-semibold">Email:</span>{' '}
              {store.user?.email || '-'}
            </p>
          </div>

          <form onSubmit={handleSubmit}>
            <div className="mb-4">
              <label className="mb-2 block text-sm font-semibold">
                Nama Toko
              </label>
              <input
                type="text"
                value={data.store_name}
                onChange={(e) => setData('store_name', e.target.value)}
                className={`w-full rounded-lg border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none ${errors.store_name ? 'border-red-500' : 'border-gray-300'}`}
                required
              />
              {errors.store_name && (
                <p className="mt-1 text-xs text-red-500">{errors.store_name}</p>
              )}
            </div>

            <div className="mb-4">
              <label className="mb-2 block text-sm font-semibold">
                Kategori
              </label>
              <input
                type="text"
                value={data.category}
                onChange={(e) => setData('category', e.target.value)}
                className={`w-full rounded-lg border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none ${errors.store_name ? 'border-red-500' : 'border-gray-300'}`}
                required
              />
              {errors.category && (
                <p className="mt-1 text-xs text-red-500">{errors.category}</p>
              )}
            </div>

            <div className="mb-4">
              <label className="mb-2 block text-sm font-semibold">Lokasi</label>
              <input
                type="text"
                value={data.location}
                onChange={(e) => setData('location', e.target.value)}
                className={`w-full rounded-lg border px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none ${errors.location ? 'border-red-500' : 'border-gray-300'}`}
              />
              {errors.location && (
                <p className="mt-1 text-xs text-red-500">{errors.location}</p>
              )}
            </div>

            <div className="mb-6">
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

            <div className="flex gap-4">
              <button
                type="submit"
                disabled={processing}
                className="flex-1 rounded-lg bg-[#53685B] px-6 py-3 font-bold text-white shadow-md transition hover:bg-[#3c4a3e] hover:shadow-lg disabled:opacity-50"
              >
                💾 Simpan Perubahan
              </button>
              <Link
                href="/admin/stores"
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

EditStore.layout = (page) => <MainLayout title="Edit Toko">{page}</MainLayout>;
