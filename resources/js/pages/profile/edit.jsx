import { Form, Link, usePage } from '@inertiajs/react';
import FormLayout from '../../layouts/form-layout';

export default function EditProfilePage() {
  const { user } = usePage().props;

  console.log('rerendered');

  return (
    <div className="mx-auto mt-10 max-w-5xl px-4">
      <h2 className="font-poppins mb-6 text-2xl font-bold">Profile</h2>

      <div className="grid items-start gap-6 md:grid-cols-3">
        {/* <!-- Foto Profil -. */}
        <div className="text-center">
          <img
            src="https://via.placeholder.com/150"
            alt="Profile Picture"
            className="border-md mx-auto h-40 w-40 items-center rounded-full border object-cover"
          />
          <div className="mt-4">
            <Link
              href="#"
              className="flex items-center justify-center gap-1 font-semibold text-blue-600 hover:underline"
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
              Edit Profile
            </Link>
          </div>
          <div className="mt-6">
            <Link
              href="/store/register"
              className="inline-block rounded-md bg-[#53685B] px-6 py-2 text-center font-semibold text-white hover:bg-[#3c4a3e]"
            >
              🏪 Buka Toko
            </Link>
          </div>
        </div>

        {/* <!-- Form Edit -. */}
        <Form
          action="/inertia/profile"
          method="POST"
          className="space-y-4 md:col-span-2"
          options={{ preserveScroll: true }}
        >
          <div>
            <label className="block text-sm font-semibold">Username</label>
            <input
              type="text"
              name="username"
              defaultValue={user.username}
              className="w-full rounded-md border border-gray-400 px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]"
              required
            />
          </div>
          <div className="grid gap-4 md:grid-cols-2">
            <div>
              <label className="block text-sm font-semibold">First Name</label>
              <input
                type="text"
                name="first_name"
                defaultValue={user.first_name}
                className="w-full rounded-md border border-gray-400 px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-semibold">Last Name</label>
              <input
                type="text"
                name="last_name"
                defaultValue={user.last_name}
                className="w-full rounded-md border border-gray-400 px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]"
                required
              />
            </div>
          </div>
          <div className="grid gap-4 md:grid-cols-2">
            <div>
              <label className="block text-sm font-semibold">Your Email</label>
              <input
                type="email"
                name="email"
                defaultValue={user.email}
                className="w-full rounded-md border border-gray-400 px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-semibold">
                Phone Number
              </label>
              <input
                type="text"
                name="phone"
                defaultValue={user.phone}
                className="w-full rounded-md border border-gray-400 px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]"
              />
            </div>
          </div>
          <div>
            <label className="block text-sm font-semibold">Password</label>
            <input
              type="password"
              value="********"
              disabled
              className="w-full cursor-not-allowed rounded-md border border-gray-400 bg-gray-100 px-4 py-2"
            />
          </div>
          <div>
            <label className="block text-sm font-semibold">Your Address</label>
            <textarea
              name="address"
              rows="3"
              className="w-full rounded-md border border-gray-400 px-4 py-2 focus:ring-2 focus:ring-[#E9E19E]"
              defaultValue={user.address}
            />
          </div>
          <div className="pt-4 text-right">
            <button
              type="submit"
              className="rounded-md bg-[#53685B] px-6 py-2 font-semibold text-white hover:bg-[#3c4a3e]"
            >
              Simpan Perubahan
            </button>
          </div>
        </Form>
      </div>
    </div>
  );
}

EditProfilePage.layout = (page) => (
  <FormLayout title="Profile">{page}</FormLayout>
);
