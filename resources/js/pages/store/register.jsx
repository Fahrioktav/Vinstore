import { Form } from '@inertiajs/react';
import FormLayout from '@/layouts/form-layout';

export default function StoreRegisterPage() {
  return (
    <div className="mt-8 flex justify-center px-4">
      <Form
        action="/store/register"
        method="POST"
        className="w-full max-w-2xl space-y-4"
      >
        <h2 className="font-poppins mb-4 text-center text-2xl font-bold">
          Form Registrasi Toko
        </h2>

        {/* <!-- Nama Toko --> */}
        <input
          type="text"
          name="store_name"
          placeholder="Nama Toko"
          className="font-poppins w-full rounded-md border border-gray-400 px-4 py-2 focus:ring-2 focus:ring-[#E9E19E] focus:outline-none"
          required
        />

        {/* <!-- Kategori Toko --> */}
        <input
          type="text"
          name="category"
          placeholder="Kategori (contoh: Antik, Elektronik, Buku)"
          className="font-poppins w-full rounded-md border border-gray-400 px-4 py-2 focus:ring-2 focus:ring-[#E9E19E] focus:outline-none"
          required
        />

        {/* <!-- Deskripsi --> */}
        <textarea
          name="description"
          placeholder="Deskripsi Toko"
          rows="3"
          className="font-poppins w-full rounded-md border border-gray-400 px-4 py-2 focus:ring-2 focus:ring-[#E9E19E] focus:outline-none"
          required
        ></textarea>

        {/* <!-- Lokasi Toko --> */}
        <input
          type="text"
          name="location"
          placeholder="Alamat atau Lokasi Toko"
          className="font-poppins w-full rounded-md border border-gray-400 px-4 py-2 focus:ring-2 focus:ring-[#E9E19E] focus:outline-none"
          required
        />

        {/* <!-- Tombol Submit --> */}
        <div className="text-center">
          <button
            type="submit"
            className="font-poppins rounded-md bg-[#4a5b4d] px-10 py-3 font-semibold text-white transition-all hover:bg-[#3c4a3e]"
          >
            Daftarkan Toko
          </button>
        </div>
      </Form>
    </div>
  );
}

StoreRegisterPage.layout = (page) => (
  <FormLayout title="Register Toko">{page}</FormLayout>
);
