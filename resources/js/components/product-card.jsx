import { Link } from '@inertiajs/react';
import { Card, CardContent } from './ui/card';
import { formatIDR, getProductImage } from '@/lib/utils';
import { BadgeIcon } from './icons';

export default function ProductCard({ product }) {
  const isTebakHarga = product.sale_type === 'tebak_harga';
  // Pada tebak harga, yang disembunyikan adalah harga diskonnya. Harga normal
  // tetap ditampilkan sebagai patokan pembeli menebak.
  const isDiscountHidden =
    product.guess_discount_price === null ||
    product.guess_discount_price === undefined;
  // Setelah status 'public' yang berlaku adalah harga normal, jadi harga diskon
  // tidak lagi ditampilkan agar tidak menyesatkan.
  const showDiscount =
    isTebakHarga &&
    ['scheduled', 'active', 'ended'].includes(product.guess_status);

  return (
    <Link
      href={`/products/${product.public_id}`}
      className="group block h-full"
    >
      <Card className="h-full gap-0 py-4 shadow-md transition hover:shadow-lg">
        <CardContent className="flex grow flex-col justify-between px-4">
          <div>
            <div className="relative">
              <img
                src={getProductImage(product)}
                className="mb-4 h-48 w-full rounded-md object-cover"
              />
              <span className="absolute top-2 left-2 rounded-md bg-[#B77C4C] px-2 py-1 text-xs text-white shadow">
                {product.category}
              </span>
              {isTebakHarga && (
                <span className="absolute bottom-2 left-2 rounded-md bg-[#53685B] px-2 py-1 text-xs font-semibold text-white shadow">
                  🎯 Tebak Harga
                </span>
              )}
              {product.certificate && (
                <span className="absolute top-2 right-2 flex items-center gap-1 rounded-md bg-green-600 px-2 py-1 text-xs text-white shadow">
                  <BadgeIcon />
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
          </div>

          <div className="mt-auto border-t border-gray-200 pt-3">
            <div className="mb-2 flex items-center justify-between">
              <span className="font-bold text-[#B77C4C]">
                {showDiscount ? (
                  <span className="flex items-baseline gap-1.5">
                    <span className="text-xs text-gray-400 line-through">
                      {formatIDR(product.price)}
                    </span>
                    {isDiscountHidden ? (
                      <span title="Tebak harga diskonnya">???</span>
                    ) : (
                      formatIDR(product.guess_discount_price)
                    )}
                  </span>
                ) : (
                  formatIDR(product.price)
                )}
              </span>
              <span
                className={`text-xs font-medium ${product.stock > 10 ? 'text-green-600' : product.stock > 0 ? 'text-orange-600' : 'text-red-600'}`}
              >
                {product.stock > 0 ? `Stok: ${product.stock}` : 'Habis'}
              </span>
            </div>

            <div className="w-full rounded-md bg-[#B77C4C] px-3 py-3 text-center text-sm font-medium text-white transition group-hover:bg-[#a0683d]">
              {showDiscount && isDiscountHidden
                ? 'Ikuti Tebak Harga'
                : 'Lihat Detail'}
            </div>
          </div>
        </CardContent>
      </Card>
    </Link>
  );
}
