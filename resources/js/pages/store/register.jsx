import { useForm, usePage } from '@inertiajs/react';
import FormLayout from '@/layouts/form-layout';
import { useState } from 'react';

export default function StoreRegisterPage() {
  const [photoPreview, setPhotoPreview] = useState(null);

  const { data, setData, post, processing, errors } = useForm({
    store_name: '',
    category: '',
    description: '',
    location: '',
    photo: null,
  });

  const handlePhotoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      setData('photo', file);
      const reader = new FileReader();
      reader.onloadend = () => {
        setPhotoPreview(reader.result);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    post('/store/register', {
      forceFormData: true,
    });
  };

  return (
    <section className="relative my-auto flex w-full items-center justify-center overflow-hidden px-6 py-12">
      <div className="my-auto w-full max-w-2xl rounded-2xl bg-white p-8 shadow-xl">
        <h2 className="font-poppins mb-6 text-center text-3xl font-bold text-[#2F3E46]">
          Daftar Toko Baru
        </h2>

        <p className="mb-6 text-center text-gray-600">
          Lengkapi formulir di bawah untuk mendaftarkan toko Anda
        </p>

        <form onSubmit={handleSubmit} className="flex flex-col gap-5">
          {/* Nama Toko */}
          <div>
            <label className="mb-2 block text-sm font-semibold text-gray-700">
              Nama Toko <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={data.store_name}
              onChange={(e) => setData('store_name', e.target.value)}
              placeholder="Contoh: Toko Antik Jaya"
              className="w-full rounded-lg border border-gray-300 px-4 py-3 transition focus:border-[#4a5b4d] focus:outline-none focus:ring-2 focus:ring-[#4a5b4d]/20"
              required
            />
            {errors.store_name && (
              <p className="mt-1 text-xs text-red-500">{errors.store_name}</p>
            )}
          </div>

          {/* Kategori Toko */}
          <div>
            <label className="mb-2 block text-sm font-semibold text-gray-700">
              Kategori Toko <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={data.category}
              onChange={(e) => setData('category', e.target.value)}
              placeholder="Contoh: Barang Antik"
              className="w-full rounded-lg border border-gray-300 px-4 py-3 transition focus:border-[#4a5b4d] focus:outline-none focus:ring-2 focus:ring-[#4a5b4d]/20"
              required
            />
            {errors.category && (
              <p className="mt-1 text-xs text-red-500">{errors.category}</p>
            )}
          </div>

          {/* Deskripsi */}
          <div>
            <label className="mb-2 block text-sm font-semibold text-gray-700">
              Deskripsi Toko <span className="text-red-500">*</span>
            </label>
            <textarea
              value={data.description}
              onChange={(e) => setData('description', e.target.value)}
              placeholder="Ceritakan tentang toko Anda..."
              rows="4"
              className="w-full rounded-lg border border-gray-300 px-4 py-3 transition focus:border-[#4a5b4d] focus:outline-none focus:ring-2 focus:ring-[#4a5b4d]/20"
              required
            />
            {errors.description && (
              <p className="mt-1 text-xs text-red-500">{errors.description}</p>
            )}
          </div>

          {/* Lokasi Toko */}
          <div>
            <label className="mb-2 block text-sm font-semibold text-gray-700">
              Lokasi Toko <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={data.location}
              onChange={(e) => setData('location', e.target.value)}
              placeholder="Contoh: Jakarta Selatan"
              className="w-full rounded-lg border border-gray-300 px-4 py-3 transition focus:border-[#4a5b4d] focus:outline-none focus:ring-2 focus:ring-[#4a5b4d]/20"
              required
            />
            {errors.location && (
              <p className="mt-1 text-xs text-red-500">{errors.location}</p>
            )}
          </div>

          {/* Foto Toko */}
          <div>
            <label className="mb-2 block text-sm font-semibold text-gray-700">
              Foto Toko (Opsional)
            </label>
            <input
              type="file"
              onChange={handlePhotoChange}
              accept="image/*"
              className="w-full rounded-lg border border-gray-300 px-4 py-3 file:mr-4 file:rounded-full file:border-0 file:bg-[#4a5b4d] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-[#3c4a3e]"
            />
            {errors.photo && (
              <p className="mt-1 text-xs text-red-500">{errors.photo}</p>
            )}
            {photoPreview && (
              <div className="mt-3">
                <img
                  src={photoPreview}
                  alt="Preview"
                  className="h-48 w-full rounded-lg object-cover"
                />
              </div>
            )}
          </div>

          {/* Tombol Submit */}
          <div className="mt-4">
            <button
              type="submit"
              disabled={processing}
              className="w-full rounded-lg bg-[#4a5b4d] px-6 py-3 font-semibold text-white transition-all hover:bg-[#3c4a3e] disabled:cursor-not-allowed disabled:opacity-50"
            >
              {processing ? 'Mendaftarkan...' : 'Daftarkan Toko'}
            </button>
          </div>
        </form>
      </div>
    </section>
  );
}

StoreRegisterPage.layout = (page) => (
  <FormLayout title="Register Toko">{page}</FormLayout>
);
