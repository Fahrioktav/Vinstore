import { useForm, usePage, Link } from '@inertiajs/react';
import { useState } from 'react';
import FormLayout from '@/layouts/form-layout';

export default function StoreEditPage() {
  const { store, errors } = usePage().props;
  
  const [photoPreview, setPhotoPreview] = useState(
    store.photo ? `/storage/${store.photo}` : 'https://placehold.co/600x400/53685B/FFFFFF?text=Foto+Toko'
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
    post('/store/update', {
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
    <div className="mx-auto max-w-4xl">
      {/* Header */}
      <form
        onSubmit={handleSubmit}
        className="bg-white rounded-3xl shadow-2xl overflow-hidden"
      >
          {/* Foto Toko Section */}
          <div className="relative h-72 md:h-80 bg-gradient-to-r from-[#53685B] to-[#3c4a3e] overflow-hidden">
            <img
              src={photoPreview}
              alt="Store Photo"
              className="w-full h-full object-cover opacity-90"
            />
            <div className="absolute inset-0 bg-black bg-opacity-30 flex items-center justify-center">
              <label
                htmlFor="photo-upload"
                className="cursor-pointer group"
              >
                <div className="bg-white/90 backdrop-blur-sm rounded-2xl px-8 py-4 shadow-lg transform transition-all hover:scale-105 hover:bg-white">
                  <div className="flex items-center gap-3">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      className="h-6 w-6 text-[#53685B] group-hover:text-[#3c4a3e] transition-colors"
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
                    <span className="font-semibold text-[#53685B] group-hover:text-[#3c4a3e] transition-colors">
                      Ubah Foto Toko
                    </span>
                  </div>
                </div>
              </label>
              <input
                id="photo-upload"
                type="file"
                accept="image/*"
                onChange={handlePhotoChange}
                className="hidden"
              />
            </div>
            {errors?.photo && (
              <div className="absolute bottom-4 left-1/2 transform -translate-x-1/2">
                <p className="bg-red-500 text-white px-4 py-2 rounded-lg text-sm shadow-lg">
                  {errors.photo}
                </p>
              </div>
            )}
          </div>

          {/* Form Fields */}
          <div className="p-8 md:p-12 space-y-8">

            {/* Nama Toko */}
            <div className="group">
              <label className="flex items-center gap-2 text-sm font-bold text-[#2F3E46] mb-2">
                <span className="text-xl">🏪</span>
                Nama Toko
              </label>
              <input
                type="text"
                name="store_name"
                value={data.store_name}
                onChange={(e) => setData('store_name', e.target.value)}
                className={`w-full rounded-xl border-2 px-4 py-3 transition-all focus:ring-4 focus:ring-[#E9E19E]/50 focus:border-[#53685B] focus:outline-none ${errors?.store_name ? 'border-red-500' : 'border-gray-200 hover:border-[#53685B]/30'}`}
                placeholder="Masukkan nama toko Anda"
                required
              />
              {errors?.store_name && (
                <p className="mt-2 flex items-center gap-1 text-xs text-red-500">
                  <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                  </svg>
                  {errors.store_name}
                </p>
              )}
            </div>

            <div className="grid md:grid-cols-2 gap-6">
              {/* Kategori Toko */}
              <div>
                <label className="flex items-center gap-2 text-sm font-bold text-[#2F3E46] mb-2">
                  <span className="text-xl">📂</span>
                  Kategori
                </label>
                <input
                  type="text"
                  name="category"
                  value={data.category}
                  onChange={(e) => setData('category', e.target.value)}
                  placeholder="Antik, Elektronik, Buku..."
                  className={`w-full rounded-xl border-2 px-4 py-3 transition-all focus:ring-4 focus:ring-[#E9E19E]/50 focus:border-[#53685B] focus:outline-none ${errors?.category ? 'border-red-500' : 'border-gray-200 hover:border-[#53685B]/30'}`}
                  required
                />
                {errors?.category && (
                  <p className="mt-2 flex items-center gap-1 text-xs text-red-500">
                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                    </svg>
                    {errors.category}
                  </p>
                )}
              </div>

              {/* Lokasi Toko */}
              <div>
                <label className="flex items-center gap-2 text-sm font-bold text-[#2F3E46] mb-2">
                  <span className="text-xl">📍</span>
                  Lokasi Toko
                </label>
                <input
                  type="text"
                  name="location"
                  value={data.location}
                  onChange={(e) => setData('location', e.target.value)}
                  placeholder="Alamat lengkap toko"
                  className={`w-full rounded-xl border-2 px-4 py-3 transition-all focus:ring-4 focus:ring-[#E9E19E]/50 focus:border-[#53685B] focus:outline-none ${errors?.location ? 'border-red-500' : 'border-gray-200 hover:border-[#53685B]/30'}`}
                  required
                />
                {errors?.location && (
                  <p className="mt-2 flex items-center gap-1 text-xs text-red-500">
                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                    </svg>
                    {errors.location}
                  </p>
                )}
              </div>
            </div>

            {/* Deskripsi */}
            <div>
              <label className="flex items-center gap-2 text-sm font-bold text-[#2F3E46] mb-2">
                <span className="text-xl">📝</span>
                Deskripsi Toko
              </label>
              <textarea
                name="description"
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                rows="4"
                placeholder="Ceritakan tentang toko Anda..."
                className={`w-full rounded-xl border-2 px-4 py-3 transition-all focus:ring-4 focus:ring-[#E9E19E]/50 focus:border-[#53685B] focus:outline-none resize-none ${errors?.description ? 'border-red-500' : 'border-gray-200 hover:border-[#53685B]/30'}`}
                required
              />
              {errors?.description && (
                <p className="mt-2 flex items-center gap-1 text-xs text-red-500">
                  <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                  </svg>
                  {errors.description}
                </p>
              )}
            </div>

            {/* Button Submit */}
            <div className="flex gap-4 pt-6 border-t border-gray-100">
              <Link
                href="/seller/dashboard"
                className="flex-1 text-center rounded-xl border-2 border-gray-300 px-6 py-3 font-semibold text-gray-700 hover:bg-gray-50 transition-all"
              >
                Batal
              </Link>
              <button
                type="submit"
                disabled={processing}
                className="flex-1 rounded-xl bg-gradient-to-r from-[#53685B] to-[#3c4a3e] px-6 py-3 font-semibold text-white hover:shadow-lg transform hover:-translate-y-0.5 transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
              >
                {processing ? (
                  <span className="flex items-center justify-center gap-2">
                    <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                    Menyimpan...
                  </span>
                ) : (
                  <span className="flex items-center justify-center gap-2">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Simpan Perubahan
                  </span>
                )}
              </button>
            </div>
          </div>
        </form>
    </div>
  );
}

StoreEditPage.layout = (page) => <FormLayout title="Edit Toko">{page}</FormLayout>;
