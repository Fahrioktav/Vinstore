import { useForm, usePage, Link } from '@inertiajs/react';
import { useState } from 'react';
import FormLayout from '@/layouts/form-layout';
import { AuthInput, AuthTextArea } from '@/components/auth/auth-layout';

export default function StoreEditPage() {
  const { store, errors } = usePage().props;

  const [photoPreview, setPhotoPreview] = useState(
    store.photo
      ? `/storage/${store.photo}`
      : 'https://placehold.co/600x400/53685B/FFFFFF?text=Foto+Toko'
  );

  const { data, setData, post, processing } = useForm({
    store_name: store.store_name || '',
    category: store.category || '',
    description: store.description || '',
    location: store.location || '',
    photo: null,
  });

  const handlePhotoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      console.log('File selected:', file.name, 'Size:', file.size);
      setData('photo', file);
      const reader = new FileReader();
      reader.onloadend = () => {
        setPhotoPreview(reader.result);
        console.log('Preview updated');
      };
      reader.readAsDataURL(file);
    } else {
      console.log('No file selected');
    }
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    post('/seller/store/update', {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        console.log('Update berhasil');
      },
      onError: (errors) => {
        console.log('Errors:', errors);
      },
    });
  };

  return (
    <section className="relative my-auto flex w-full items-center justify-center overflow-hidden px-6 py-12">
      <div className="mx-auto w-full max-w-2xl">
        {/* Header */}
        <form
          onSubmit={handleSubmit}
          className="overflow-hidden rounded-3xl bg-white shadow-2xl"
        >
          {/* Foto Toko Section */}
          <div className="relative h-72 overflow-hidden bg-gradient-to-r from-[#53685B] to-[#3c4a3e] md:h-80">
            <img
              src={photoPreview}
              alt="Store Photo"
              className="h-full w-full object-cover opacity-90"
            />
            <div className="bg-opacity-30 absolute inset-0 flex items-center justify-center bg-black/20">
              <label htmlFor="photo-upload" className="group cursor-pointer">
                <div className="transform rounded-2xl bg-white/90 px-8 py-4 shadow-lg backdrop-blur-sm transition-all hover:scale-105">
                  <div className="flex items-center gap-3">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      className="h-6 w-6 text-[#53685B] transition-colors group-hover:text-[#3c4a3e]"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                      />
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"
                      />
                    </svg>
                    <span className="font-semibold text-[#53685B] transition-colors group-hover:text-[#3c4a3e]">
                      Ubah Foto Toko
                    </span>
                  </div>
                </div>
              </label>
              <AuthInput
                id="photo-upload"
                type="file"
                accept="image/*"
                onChange={handlePhotoChange}
                className="hidden"
              />
            </div>
            {errors?.photo && (
              <div className="absolute bottom-4 left-1/2 -translate-x-1/2 transform">
                <p className="rounded-lg bg-red-500 px-4 py-2 text-sm text-white shadow-lg">
                  {errors.photo}
                </p>
              </div>
            )}
          </div>

          {/* Form Fields */}
          <div className="flex flex-col gap-4 p-8 md:p-12">
            {/* Nama Toko */}
            <div className="group">
              <label className="mb-2 flex items-center gap-2 text-sm font-bold text-[#2F3E46]">
                <span className="text-xl">🏪</span>
                Nama Toko
              </label>
              <AuthInput
                type="text"
                name="store_name"
                value={data.store_name}
                onChange={(e) => setData('store_name', e.target.value)}
                className={errors?.store_name ? 'border-red-500' : ''}
                placeholder="Masukkan nama toko Anda"
                required
              />
              {errors?.store_name && (
                <p className="mt-2 flex items-center gap-1 text-xs text-red-500">
                  <svg
                    className="h-4 w-4"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fillRule="evenodd"
                      d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                      clipRule="evenodd"
                    />
                  </svg>
                  {errors.store_name}
                </p>
              )}
            </div>

            <div className="grid gap-6 md:grid-cols-2">
              {/* Kategori Toko */}
              <div>
                <label className="mb-2 flex items-center gap-2 text-sm font-bold text-[#2F3E46]">
                  <span className="text-xl">📂</span>
                  Kategori
                </label>
                <AuthInput
                  type="text"
                  name="category"
                  value={data.category}
                  onChange={(e) => setData('category', e.target.value)}
                  placeholder="Antik, Elektronik, Buku..."
                  className={errors?.category ? 'border-red-500' : ''}
                  required
                />
                {errors?.category && (
                  <p className="mt-2 flex items-center gap-1 text-xs text-red-500">
                    <svg
                      className="h-4 w-4"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path
                        fillRule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                        clipRule="evenodd"
                      />
                    </svg>
                    {errors.category}
                  </p>
                )}
              </div>

              {/* Lokasi Toko */}
              <div>
                <label className="mb-2 flex items-center gap-2 text-sm font-bold text-[#2F3E46]">
                  <span className="text-xl">📍</span>
                  Lokasi Toko
                </label>
                <AuthInput
                  type="text"
                  name="location"
                  value={data.location}
                  onChange={(e) => setData('location', e.target.value)}
                  placeholder="Alamat lengkap toko"
                  className={errors?.location ? 'border-red-500' : ''}
                  required
                />
                {errors?.location && (
                  <p className="mt-2 flex items-center gap-1 text-xs text-red-500">
                    <svg
                      className="h-4 w-4"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path
                        fillRule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                        clipRule="evenodd"
                      />
                    </svg>
                    {errors.location}
                  </p>
                )}
              </div>
            </div>

            {/* Deskripsi */}
            <div>
              <label className="mb-2 flex items-center gap-2 text-sm font-bold text-[#2F3E46]">
                <span className="text-xl">📝</span>
                Deskripsi Toko
              </label>
              <AuthTextArea
                name="description"
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                rows="4"
                placeholder="Ceritakan tentang toko Anda..."
                className={errors?.description ? 'border-red-500' : ''}
                required
              />
              {errors?.description && (
                <p className="mt-2 flex items-center gap-1 text-xs text-red-500">
                  <svg
                    className="h-4 w-4"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fillRule="evenodd"
                      d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                      clipRule="evenodd"
                    />
                  </svg>
                  {errors.description}
                </p>
              )}
            </div>

            {/* Button Submit */}
            <div className="flex gap-4 border-t border-gray-100 pt-6">
              <Link
                href="/seller/dashboard"
                className="flex-1 rounded-xl border-2 border-gray-300 px-6 py-3 text-center font-semibold text-gray-700 transition-all hover:bg-gray-50"
              >
                Batal
              </Link>
              <button
                type="submit"
                disabled={processing}
                className="flex-1 transform rounded-xl bg-gradient-to-r from-[#53685B] to-[#3c4a3e] px-6 py-3 font-semibold text-white transition-all hover:-translate-y-0.5 hover:shadow-lg disabled:transform-none disabled:cursor-not-allowed disabled:opacity-50"
              >
                {processing ? (
                  <span className="flex items-center justify-center gap-2">
                    <svg className="h-5 w-5 animate-spin" viewBox="0 0 24 24">
                      <circle
                        className="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        strokeWidth="4"
                        fill="none"
                      />
                      <path
                        className="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                      />
                    </svg>
                    Menyimpan...
                  </span>
                ) : (
                  <span className="flex items-center justify-center gap-2">
                    <svg
                      className="h-5 w-5"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        d="M5 13l4 4L19 7"
                      />
                    </svg>
                    Simpan Perubahan
                  </span>
                )}
              </button>
            </div>
          </div>
        </form>
      </div>
    </section>
  );
}

StoreEditPage.layout = (page) => (
  <FormLayout title="Edit Toko">{page}</FormLayout>
);
