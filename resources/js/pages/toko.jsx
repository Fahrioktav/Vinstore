import { Form, Link, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { getStoreImage, useParams } from '@/lib/utils';
import SearchInput from '@/components/search-input';

export default function TokoPage() {
  const { showSearch, stores } = usePage().props;

  // Keyword pencarian nama, deskripsi, lokasi, atau kategori toko
  const q = useParams().get('q');

  return (
    <>
      {showSearch && (
        <SearchInput
          defaultValue={q}
          placeholder="Cari toko antik favoritmu..."
        />
      )}

      <section className="relative w-full overflow-hidden px-6 py-12 md:px-12">
        {/* {-- Judul --} */}
        <h2 className="mb-10 pt-10 text-center text-3xl font-bold tracking-wide text-[#E9E19E] md:text-4xl">
          Semua Toko
        </h2>

        {/* {-- GRID TOKO --} */}
        <div className="mx-auto grid max-w-7xl grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
          {stores.map((store) => (
            <div
              className="overflow-hidden rounded-2xl border border-gray-200 bg-white/90 shadow-lg backdrop-blur-md transition-transform duration-300 hover:scale-105"
              key={store.id}
            >
              {/* {-- GAMBAR TOKO --} */}
              <img
                src={getStoreImage(store)}
                alt={store.store_name}
                className="h-48 w-full object-cover"
              />

              {/* {-- DETAIL TOKO --} */}
              <div className="p-6">
                <h3 className="mb-2 text-xl font-semibold text-[#2F3E46] capitalize">
                  {store.store_name}
                </h3>
                <p className="mb-4 flex items-center gap-2 text-sm text-gray-600">
                  📍 {store.location}
                </p>
                <Link
                  href={`/toko/${store.id}`}
                  className="block rounded-lg bg-[#B77C4C] py-2 text-center font-semibold text-white transition-all duration-200 hover:bg-[#9e6538]"
                >
                  Lihat Toko
                </Link>
              </div>
            </div>
          ))}

          {stores.length === 0 && (
            <div className="col-span-full text-center text-lg text-white italic">
              Tidak ada toko yang ditemukan 🕯️
            </div>
          )}
        </div>

        {/* {-- DEKORASI LATAR --} */}
        <div className="absolute top-0 left-0 -z-10 h-64 w-64 rounded-full bg-[#E9E19E]/20 blur-3xl"></div>
        <div className="absolute right-0 bottom-0 -z-10 h-96 w-96 rounded-full bg-[#B77C4C]/20 blur-3xl"></div>
      </section>
    </>
  );
}

TokoPage.layout = (page) => <MainLayout title="Toko">{page}</MainLayout>;
