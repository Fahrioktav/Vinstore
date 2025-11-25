import { Link, usePage } from '@inertiajs/react';
import { formatIDR } from '@/lib/utils';
import MainLayout from '@/layouts/main-layout';

export default function HomePage() {
  const {
    heroText,
    showSearch,
    products = [],
    categories = [],
    user,
  } = usePage().props;

  return (
    <div className="bg-white">
      {/* <!-- HERO SECTION -. */}
      <section className="font-poppins relative -mt-[80px] flex min-h-screen items-center bg-[#2F3E46] pt-24 text-white">
        <div className="mx-auto flex w-full max-w-7xl flex-col items-center justify-between px-6 md:flex-row md:px-10">
          {/* {-- LEFT TEXT --} */}
          <div className="md:w-1/2">
            <h1 className="text-4xl leading-tight font-extrabold md:text-6xl">
              Temukan Barang Antik Impianmu di{' '}
              <span className="text-[#E9E19E]">VINSTORE!</span>
            </h1>
            <p className="font-poppins mt-6 text-lg text-gray-200">
              Jelajahi koleksi barang antik eksklusif — dari kamera klasik
              hingga konsol legendaris.
            </p>
            <Link
              href="/toko"
              className="mt-8 inline-block rounded-lg bg-[#B77C4C] px-8 py-3 font-semibold text-white transition hover:bg-[#a16c3e]"
            >
              Jelajahi Sekarang
            </Link>
          </div>

          {/* {-- RIGHT IMAGE --} */}
          <div className="mt-10 flex flex-col items-center md:mt-0 md:w-1/2">
            <img
              src="/assets/hero-antique.jpg"
              alt="Vintage Collection"
              className="w-[400px] object-contain"
            />
            <p className="mt-4 text-lg font-semibold">Vintage Collection</p>
          </div>
        </div>
      </section>

      {/* {-- KATEGORI SECTION --} */}
      <section className="mt-20 px-6 md:px-16">
        <h2 className="mb-6 text-3xl font-bold text-[#3E2723]">
          Kategori Populer
        </h2>
        <div className="flex flex-wrap justify-center gap-8">
          {categories.length === 0 ? (
            <p className="text-gray-500">Belum ada kategori</p>
          ) : (
            categories.map((category) => (
              <div
                className="group flex w-32 flex-col items-center rounded-xl bg-white p-5 shadow-md transition-all duration-300 hover:shadow-xl md:w-36"
                key={category.id}
              >
                {category.image ? (
                  <img
                    src={`/storage/${category.image}`}
                    alt={category.name}
                    className="mb-3 h-16 w-16 rounded-full object-cover transition-transform group-hover:scale-110"
                  />
                ) : (
                  <div className="mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-[#53685B] to-[#B77C4C] transition-transform group-hover:scale-110">
                    <span className="text-2xl font-bold text-white">
                      {category.name.charAt(0).toUpperCase()}
                    </span>
                  </div>
                )}
                <span className="text-center font-semibold text-gray-800 group-hover:text-[#B77C4C]">
                  {category.name}
                </span>
              </div>
            ))
          )}
        </div>
      </section>

      {/* {-- PRODUK POPULER --} */}
      <section id="products" className="mt-20 px-6 md:px-16">
        <div className="mb-6 flex items-center justify-between">
          <h2 className="text-3xl font-bold text-[#3E2723]">
            Barang Paling Populer
          </h2>
          <Link href="/products" className="text-[#B77C4C] hover:underline">
            Lihat Semua
          </Link>
        </div>

        {products.length > 0 ? (
          <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            {products.map((product, i) => (
              <div
                className="flex flex-col rounded-xl bg-white p-4 shadow-md transition hover:shadow-lg"
                key={i}
              >
                <div className="relative">
                  <img
                    src={product.image}
                    className="mb-4 h-48 w-full rounded-md object-cover"
                  />
                  <span className="absolute top-2 left-2 rounded-md bg-[#B77C4C] px-2 py-1 text-xs text-white shadow">
                    Antik
                  </span>
                </div>

                <div className="flex-grow">
                  <h3 className="mb-1 text-lg font-semibold text-[#3E2723]">
                    {product.name}
                  </h3>
                  <p className="mb-3 line-clamp-3 text-sm text-gray-600">
                    {product.description}
                  </p>
                </div>

                <div className="mt-auto flex items-center justify-between border-t border-gray-200 pt-3">
                  <span className="font-bold text-[#B77C4C]">
                    {formatIDR(product.price)}
                  </span>

                  <Link href={user ? `/checkout/show/${product.id}` : `/login`}>
                    <button className="rounded-md bg-[#B77C4C] px-3 py-1 text-sm font-medium text-white transition hover:bg-[#a0683d]">
                      Order
                    </button>
                  </Link>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <p className="mt-6 text-gray-600">Belum ada produk yang tersedia.</p>
        )}
      </section>

      {/* {-- REKOMENDASI SECTION --} */}
      <section className="mt-20 bg-[#fdf8f3] py-12">
        <div className="mx-auto max-w-6xl px-6 text-center md:px-16">
          <h2 className="mb-6 text-3xl font-bold text-[#3E2723]">
            Rekomendasi Untukmu
          </h2>
          <p className="mb-10 text-gray-700">
            Berdasarkan minatmu, kami pilihkan produk antik yang mungkin kamu
            suka.
          </p>

          <div className="flex flex-wrap justify-center gap-6">
            {products.slice(0, 3).map((recommendation) => (
              <div
                key={recommendation.id}
                className="w-72 rounded-xl bg-white p-4 shadow-md transition hover:shadow-lg"
              >
                <img
                  src={recommendation.image}
                  className="mb-3 h-40 w-full rounded-md object-cover"
                />
                <h3 className="mb-1 text-lg font-semibold text-[#3E2723]">
                  {recommendation.name}
                </h3>
                <span className="font-bold text-[#B77C4C]">
                  {formatIDR(recommendation.price, 0, ',', '.')}
                </span>
              </div>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
}

HomePage.layout = (page) => <MainLayout title="Home">{page}</MainLayout>;
