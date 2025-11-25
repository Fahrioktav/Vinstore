import { Head, Link, useForm, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';

export default function EditUser() {
  const { user, errors: serverErrors } = usePage().props;
  const { data, setData, put, processing, errors } = useForm({
    username: user.username || '',
    first_name: user.first_name || '',
    last_name: user.last_name || '',
    email: user.email || '',
    phone: user.phone || '',
    address: user.address || '',
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    put(`/admin/users/${user.id}`, {
      preserveScroll: true,
    });
  };

  return (
    <>
      <Head title="Edit User" />
      <div className="mx-auto max-w-3xl px-6 py-8">
        <div className="rounded-2xl bg-white p-8 shadow-md shadow-[#53685B]/20">
          <h2 className="mb-6 text-3xl font-bold text-[#53685B]">✏️ Edit User</h2>

          <form onSubmit={handleSubmit}>
            <div className="mb-4">
              <label className="mb-2 block text-sm font-semibold">Username</label>
              <input
                type="text"
                value={data.username}
                onChange={(e) => setData('username', e.target.value)}
                className={`w-full rounded-lg border px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 ${errors.username ? 'border-red-500' : 'border-gray-300'}`}
                required
              />
              {errors.username && (
                <p className="mt-1 text-xs text-red-500">{errors.username}</p>
              )}
            </div>

            <div className="mb-4 grid grid-cols-2 gap-4">
              <div>
                <label className="mb-2 block text-sm font-semibold">Nama Depan</label>
                <input
                  type="text"
                  value={data.first_name}
                  onChange={(e) => setData('first_name', e.target.value)}
                  className={`w-full rounded-lg border px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 ${errors.first_name ? 'border-red-500' : 'border-gray-300'}`}
                />
                {errors.first_name && (
                  <p className="mt-1 text-xs text-red-500">{errors.first_name}</p>
                )}
              </div>

              <div>
                <label className="mb-2 block text-sm font-semibold">Nama Belakang</label>
                <input
                  type="text"
                  value={data.last_name}
                  onChange={(e) => setData('last_name', e.target.value)}
                  className={`w-full rounded-lg border px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 ${errors.last_name ? 'border-red-500' : 'border-gray-300'}`}
                />
                {errors.last_name && (
                  <p className="mt-1 text-xs text-red-500">{errors.last_name}</p>
                )}
              </div>
            </div>

            <div className="mb-4">
              <label className="mb-2 block text-sm font-semibold">Email</label>
              <input
                type="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                className={`w-full rounded-lg border px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 ${errors.email ? 'border-red-500' : 'border-gray-300'}`}
                required
              />
              {errors.email && (
                <p className="mt-1 text-xs text-red-500">{errors.email}</p>
              )}
            </div>

            <div className="mb-4">
              <label className="mb-2 block text-sm font-semibold">Telepon</label>
              <input
                type="text"
                value={data.phone}
                onChange={(e) => setData('phone', e.target.value)}
                className={`w-full rounded-lg border px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 ${errors.phone ? 'border-red-500' : 'border-gray-300'}`}
              />
              {errors.phone && (
                <p className="mt-1 text-xs text-red-500">{errors.phone}</p>
              )}
            </div>

            <div className="mb-6">
              <label className="mb-2 block text-sm font-semibold">Alamat</label>
              <textarea
                value={data.address}
                onChange={(e) => setData('address', e.target.value)}
                rows="3"
                className={`w-full rounded-lg border px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 ${errors.address ? 'border-red-500' : 'border-gray-300'}`}
              />
              {errors.address && (
                <p className="mt-1 text-xs text-red-500">{errors.address}</p>
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
                href="/admin/users"
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

EditUser.layout = (page) => (
  <MainLayout title="Edit User">{page}</MainLayout>
);
