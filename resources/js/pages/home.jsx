import { Link, usePage } from '@inertiajs/react';
import { formatIDR } from '@/lib/utils';
import MainLayout from '@/layouts/main-layout';
import { Card, CardContent, CardFooter } from '@/components/ui/card';

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
      <section className="font-poppins relative -mt-20 flex min-h-screen items-center bg-[#2F3E46] p-20 text-white">
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
      <section className="px-6 py-20 md:px-16">
        <h2 className="mb-6 text-3xl font-bold text-[#3E2723]">
          Kategori Populer
        </h2>
        <div className="flex flex-wrap justify-center gap-8">
          {categories.length === 0 ? (
            <p className="text-gray-500">Belum ada kategori</p>
          ) : (
            categories.slice(0, 5).map((category) => (
              <div
                className="group flex w-32 flex-col items-center rounded-xl bg-white p-5 shadow-md transition-all duration-300 hover:shadow-xl md:w-36"
                key={category.id}
              >
                {category.image ? (
                  <img
                    src={`/${category.image}`}
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
      <section id="products" className="px-6 py-20 md:px-16">
        <div className="mb-6 flex items-center justify-between">
          <h2 className="text-3xl font-bold text-[#3E2723]">
            Barang Paling Populer
          </h2>
          <Link
            href="/products"
            className="text-right text-[#B77C4C] hover:underline"
          >
            Lihat Semua
          </Link>
        </div>

        {products.length > 0 ? (
          <div className="grid grid-cols-1 gap-8 pt-5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            {products.slice(0, 4).map((product, i) => (
              <Card
                key={i}
                className="gap-0 py-4 shadow-md transition hover:shadow-lg"
              >
                <CardContent className="px-4">
                  <div className="relative">
                    <img
                      src={product.image}
                      className="mb-4 h-48 w-full rounded-md object-cover"
                    />
                    <span className="absolute top-2 left-2 rounded-md bg-[#B77C4C] px-2 py-1 text-xs text-white shadow">
                      Antik
                    </span>
                    {product.certificate && (
                      <span className="absolute top-2 right-2 flex items-center gap-1 rounded-md bg-green-600 px-2 py-1 text-xs text-white shadow">
                        <svg
                          className="h-3 w-3"
                          fill="currentColor"
                          viewBox="0 0 20 20"
                        >
                          <path
                            fillRule="evenodd"
                            d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clipRule="evenodd"
                          />
                        </svg>
                        Bersertifikat
                      </span>
                    )}
                  </div>
                  <div className="flex-grow">
                    <h3 className="mb-1 text-lg font-semibold text-[#3E2723]">
                      {product.name}
                    </h3>
                    <p className="mb-3 line-clamp-3 text-sm text-gray-600">
                      {product.description}
                    </p>
                  </div>

                  <div className="mt-auto border-t border-gray-200 pt-3">
                    <div className="mb-2 flex items-center justify-between">
                      <span className="font-bold text-[#B77C4C]">
                        {formatIDR(product.price)}
                      </span>
                      <span
                        className={`text-xs font-medium ${product.stock > 10 ? 'text-green-600' : product.stock > 0 ? 'text-orange-600' : 'text-red-600'}`}
                      >
                        {product.stock > 0 ? `Stok: ${product.stock}` : 'Habis'}
                      </span>
                    </div>

                    {product.stock > 0 ? (
                      <Link href={`/checkout/show/${product.id}`}>
                        <button className="w-full rounded-md bg-[#B77C4C] px-3 py-3 text-sm font-medium text-white transition hover:bg-[#a0683d]">
                          Order
                        </button>
                      </Link>
                    ) : (
                      <button
                        disabled
                        className="w-full cursor-not-allowed rounded-md bg-gray-400 px-3 py-3 text-sm font-medium text-white"
                      >
                        Stok Habis
                      </button>
                    )}
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        ) : (
          <p className="mt-6 text-gray-600">Belum ada produk yang tersedia.</p>
        )}
      </section>

      {/* {-- REKOMENDASI SECTION --} */}
      <section className="bg-[#fdf8f3] py-20">
        <div className="mx-auto max-w-6xl px-6 text-center md:px-16">
          <h2 className="mb-6 text-3xl font-bold text-[#3E2723]">
            Rekomendasi Untukmu
          </h2>
          <p className="mb-10 text-gray-700">
            Berdasarkan minatmu, kami pilihkan produk antik yang mungkin kamu
            suka.
          </p>

          <div className="flex flex-wrap justify-center gap-6">
            {products.slice(0, 2).map((recommendation) => (
              <Card
                key={recommendation.id}
                className="w-72 rounded-xl bg-white py-4 shadow-md transition hover:shadow-lg"
              >
                <CardContent className="px-4">
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
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
}

HomePage.layout = (page) => <MainLayout title="Home">{page}</MainLayout>;
