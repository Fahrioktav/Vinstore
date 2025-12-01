import { useForm, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import FormLayout from '@/layouts/form-layout';
import { EditPhotoIcon } from '@/components/icons';

export default function EditProfilePage() {
  const { user, errors } = usePage().props;

  const [photoPreview, setPhotoPreview] = useState(
    user.photo ? `/storage/${user.photo}` : 'https://via.placeholder.com/150'
  );

  const { data, setData, post, processing } = useForm({
    username: user.username || '',
    first_name: user.first_name || '',
    last_name: user.last_name || '',
    email: user.email || '',
    phone: user.phone || '',
    address: user.address || '',
    photo: null,
    password: '',
    password_confirmation: '',
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
    post('/profile', {
      forceFormData: true,
      preserveScroll: true,
    });
  };

  return (
    <section className="relative my-auto flex w-full items-center justify-center overflow-hidden px-6 py-12">
      <div className="my-auto w-full max-w-5xl rounded-2xl bg-gray-50 p-8 shadow-xl">
        <h2 className="font-poppins mb-6 text-2xl font-bold text-[#2F3E46]">
          👤 Edit Profile
        </h2>

        <div className="grid items-start gap-6 md:grid-cols-3">
          {/* <!-- Foto Profil --> */}
          <div className="text-center">
            <img
              src={photoPreview}
              alt="Profile Picture"
              className="border-md mx-auto h-40 w-40 items-center rounded-full border object-cover"
            />
            <div className="mt-4">
              <label
                htmlFor="photo-upload"
                className="flex cursor-pointer items-center justify-center gap-1 font-semibold text-blue-600 hover:underline"
              >
                <EditPhotoIcon />
                Edit Photo
              </label>
              <input
                id="photo-upload"
                type="file"
                accept="image/*"
                onChange={handlePhotoChange}
                className="hidden"
              />
              {errors?.photo && (
                <p className="mt-1 text-xs text-red-500">{errors.photo}</p>
              )}
            </div>

            {/* Informasi Toko (for seller) */}
            {user.role === 'seller' && user.store && (
              <div className="mt-6 border-t pt-6">
                <h3 className="mb-3 text-sm font-bold text-[#2F3E46]">
                  🏪 Informasi Toko
                </h3>
                <div className="space-y-3">
                  <div className="overflow-hidden rounded-lg border border-gray-200">
                    <img
                      src={
                        user.store.photo
                          ? `/storage/${user.store.photo}`
                          : 'https://placehold.co/400x200/53685B/FFFFFF?text=Foto+Toko'
                      }
                      alt={user.store.store_name}
                      className="h-32 w-full object-cover"
                    />
                  </div>
                  <div className="text-sm">
                    <p className="font-semibold text-[#2F3E46]">
                      {user.store.store_name}
                    </p>
                    <p className="text-xs text-gray-600">
                      📂 {user.store.category}
                    </p>
                    <p className="text-xs text-gray-600">
                      📍 {user.store.location}
                    </p>
                  </div>
                </div>
              </div>
            )}

            <div className="mt-6 space-y-3">
              {user.role === 'seller' && (
                <Link
                  href="/seller/store/edit"
                  className="block rounded-md bg-[#B77C4C] px-6 py-2 text-center font-semibold text-white hover:bg-[#a0683d]"
                >
                  ✏️ Edit Toko
                </Link>
              )}
              {user.role !== 'seller' && user.role !== 'admin' && (
                <Link
                  href="/seller/store/register"
                  className="block rounded-md bg-[#53685B] px-6 py-2 text-center font-semibold text-white hover:bg-[#3c4a3e]"
                >
                  🏪 Buka Toko
                </Link>
              )}
            </div>
          </div>

          {/* <!-- Form Edit --> */}
          <form onSubmit={handleSubmit} className="space-y-4 md:col-span-2">
            <div>
              <label className="block text-sm font-semibold">Username</label>
              <input
                type="text"
                name="username"
                value={data.username}
                onChange={(e) => setData('username', e.target.value)}
                className={`w-full rounded-md border px-4 py-2 focus:ring-2 focus:ring-[#E9E19E] ${errors?.username ? 'border-red-500' : 'border-gray-400'}`}
                required
              />
              {errors?.username && (
                <p className="mt-1 text-xs text-red-500">{errors.username}</p>
              )}
            </div>
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <label className="block text-sm font-semibold">
                  First Name
                </label>
                <input
                  type="text"
                  name="first_name"
                  value={data.first_name}
                  onChange={(e) => setData('first_name', e.target.value)}
                  className={`w-full rounded-md border px-4 py-2 focus:ring-2 focus:ring-[#E9E19E] ${errors?.first_name ? 'border-red-500' : 'border-gray-400'}`}
                />
                {errors?.first_name && (
                  <p className="mt-1 text-xs text-red-500">
                    {errors.first_name}
                  </p>
                )}
              </div>
              <div>
                <label className="block text-sm font-semibold">Last Name</label>
                <input
                  type="text"
                  name="last_name"
                  value={data.last_name}
                  onChange={(e) => setData('last_name', e.target.value)}
                  className={`w-full rounded-md border px-4 py-2 focus:ring-2 focus:ring-[#E9E19E] ${errors?.last_name ? 'border-red-500' : 'border-gray-400'}`}
                />
                {errors?.last_name && (
                  <p className="mt-1 text-xs text-red-500">
                    {errors.last_name}
                  </p>
                )}
              </div>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <label className="block text-sm font-semibold">
                  Your Email
                </label>
                <input
                  type="email"
                  name="email"
                  value={data.email}
                  onChange={(e) => setData('email', e.target.value)}
                  className={`w-full rounded-md border px-4 py-2 focus:ring-2 focus:ring-[#E9E19E] ${errors?.email ? 'border-red-500' : 'border-gray-400'}`}
                  required
                />
                {errors?.email && (
                  <p className="mt-1 text-xs text-red-500">{errors.email}</p>
                )}
              </div>
              <div>
                <label className="block text-sm font-semibold">
                  Phone Number
                </label>
                <input
                  type="text"
                  name="phone"
                  value={data.phone}
                  onChange={(e) => setData('phone', e.target.value)}
                  className={`w-full rounded-md border px-4 py-2 focus:ring-2 focus:ring-[#E9E19E] ${errors?.phone ? 'border-red-500' : 'border-gray-400'}`}
                />
                {errors?.phone && (
                  <p className="mt-1 text-xs text-red-500">{errors.phone}</p>
                )}
              </div>
            </div>
            <div>
              <label className="block text-sm font-semibold">Password</label>
              <input
                type="password"
                name="password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                className="w-full rounded-md border border-gray-400 px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]"
                placeholder="Kosongkan jika tidak ingin mengubah"
              />
              {errors?.password && (
                <p className="mt-1 text-xs text-red-500">{errors.password}</p>
              )}
            </div>
            <div>
              <label className="block text-sm font-semibold">
                Confirm Password
              </label>
              <input
                type="password"
                name="password_confirmation"
                value={data.password_confirmation}
                onChange={(e) =>
                  setData('password_confirmation', e.target.value)
                }
                className="w-full rounded-md border border-gray-400 px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]"
                placeholder="Konfirmasi password baru"
              />
            </div>
            <div>
              <label className="block text-sm font-semibold">
                Your Address
              </label>
              <textarea
                name="address"
                rows="3"
                value={data.address}
                onChange={(e) => setData('address', e.target.value)}
                className={`w-full rounded-md border px-4 py-2 focus:ring-2 focus:ring-[#E9E19E] ${errors?.address ? 'border-red-500' : 'border-gray-400'}`}
              />
              {errors?.address && (
                <p className="mt-1 text-xs text-red-500">{errors.address}</p>
              )}
            </div>
            <div className="pt-4 text-right">
              <button
                type="submit"
                disabled={processing}
                className="rounded-md bg-[#53685B] px-6 py-2 font-semibold text-white hover:bg-[#3c4a3e] disabled:opacity-50"
              >
                {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </section>
  );
}

EditProfilePage.layout = (page) => (
  <FormLayout title="Profile">{page}</FormLayout>
);
