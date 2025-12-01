import { Form } from '@inertiajs/react';
import FormLayout from '@/layouts/form-layout';
import {
  AuthInput,
  AuthLabel,
  AuthTextArea,
} from '@/components/auth/auth-layout';

export default function StoreRegisterPage() {
  return (
    <section className="relative my-auto flex w-full items-center justify-center overflow-hidden px-6 py-12">
      <div className="my-auto w-full max-w-2xl rounded-2xl bg-white p-8 shadow-xl">
        <Form
          action="/store/register"
          method="POST"
          className="flex flex-col gap-4"
          options={{ forceFormData: true }}
        >
          <h2 className="font-poppins mb-4 text-center text-2xl font-bold">
            Form Registrasi Toko
          </h2>

          {/* <!-- Nama Toko --> */}
          <AuthInput
            size="sm"
            type="text"
            name="store_name"
            placeholder="Nama Toko"
            required
          />

          {/* <!-- Kategori Toko --> */}
          <AuthInput
            size="sm"
            type="text"
            name="category"
            placeholder="Kategori (contoh: Antik, Elektronik, Buku)"
            required
          />

          {/* <!-- Deskripsi --> */}
          <AuthTextArea
            name="description"
            placeholder="Deskripsi Toko"
            rows="3"
            required
          />

          {/* <!-- Lokasi Toko --> */}
          <AuthInput
            size="sm"
            type="text"
            name="location"
            placeholder="Alamat atau Lokasi Toko"
            required
          />

          {/* <!-- Foto Toko --> */}
          <div>
            <AuthLabel htmlFor="photo">Foto Toko (Opsional)</AuthLabel>
            <AuthInput
              id="photo"
              size="sm"
              type="file"
              name="photo"
              accept="image/*"
            />
          </div>

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
    </section>
  );
}

StoreRegisterPage.layout = (page) => (
  <FormLayout title="Register Toko">{page}</FormLayout>
);
