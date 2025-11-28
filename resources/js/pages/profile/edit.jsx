import { useForm, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import FormLayout from '../../layouts/form-layout';

export default function EditProfilePage() {
  const { user, sessions, errors } = usePage().props;
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
    _method: 'POST',
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
    post('/inertia/profile', {
      forceFormData: true,
      preserveScroll: true,
    });
  };

  console.log('rerendered');

  return (
    <div className="mx-auto w-full max-w-5xl rounded-2xl bg-white p-8 shadow-xl">
      <h2 className="font-poppins mb-6 text-2xl font-bold text-[#2F3E46]">
        👤 Edit Profile
      </h2>

      {/* Success Message */}
      {sessions?.success && (
        <div className="mb-6 rounded-lg border-l-4 border-green-500 bg-green-50 px-4 py-3 text-green-700 shadow-sm">
          <p className="font-semibold">✓ {sessions.success}</p>
        </div>
      )}

      {/* Error Message */}
      {sessions?.error && (
        <div className="mb-6 rounded-lg border-l-4 border-red-500 bg-red-50 px-4 py-3 text-red-700 shadow-sm">
          <p className="font-semibold">✗ {sessions.error}</p>
        </div>
      )}

      <div className="grid items-start gap-6 md:grid-cols-3">
        {/* Foto Profil */}
        <div className="text-center">
          <img
            src={photoPreview}
            alt="Profile Picture"
            className="border-md mx-auto h-40 w-40 items-center rounded-full border object-cover"
          />
          <div className="mt-4">
            <label
              htmlFor="photo-upload"
              className="flex items-center justify-center gap-1 font-semibold text-blue-600 hover:underline cursor-pointer"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                className="h-5 w-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  d="M15.232 5.232l3.536 3.536M9 13l6-6m2 2l-6 6M13 7H7v6M6 6h.01"
                />
              </svg>
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
        </div>
          
        {/* Form Fields */}
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
              <label className="block text-sm font-semibold">First Name</label>
              <input
                type="text"
                name="first_name"
                value={data.first_name}
                onChange={(e) => setData('first_name', e.target.value)}
                className={`w-full rounded-md border px-4 py-2 focus:ring-2 focus:ring-[#E9E19E] ${errors?.first_name ? 'border-red-500' : 'border-gray-400'}`}
              />
              {errors?.first_name && (
                <p className="mt-1 text-xs text-red-500">{errors.first_name}</p>
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
                <p className="mt-1 text-xs text-red-500">{errors.last_name}</p>
              )}
            </div>
          </div>
          <div className="grid gap-4 md:grid-cols-2">
            <div>
              <label className="block text-sm font-semibold">Your Email</label>
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
              <label className="block text-sm font-semibold">Phone Number</label>
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
            <label className="block text-sm font-semibold">Confirm Password</label>
            <input
              type="password"
              name="password_confirmation"
              value={data.password_confirmation}
              onChange={(e) => setData('password_confirmation', e.target.value)}
              className="w-full rounded-md border border-gray-400 px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]"
              placeholder="Konfirmasi password baru"
            />
          </div>
          <div>
            <label className="block text-sm font-semibold">Your Address</label>
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
        
        <div className="mt-6">
          <Link
            href="/store/register"
            className="inline-block rounded-md bg-[#53685B] px-6 py-2 text-center font-semibold text-white hover:bg-[#3c4a3e]"
          >
            🏪 Buka Toko
          </Link>
        </div>
      </div>
    </div>
  );
}

EditProfilePage.layout = (page) => (
  <FormLayout title="Profile">{page}</FormLayout>
);
