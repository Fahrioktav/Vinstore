import { Link } from '@inertiajs/react';
import { Card, CardContent } from './ui/card';
import { formatIDR, getProductImage } from '@/lib/utils';
import { BadgeIcon } from './icons';

export default function ProductCard({ product }) {
  return (
    <Card className="gap-0 py-4 shadow-md transition hover:shadow-lg">
      <CardContent className="px-4">
        <div className="relative">
          <img
            src={getProductImage(product)}
            className="mb-4 h-48 w-full rounded-md object-cover"
          />
          <span className="absolute top-2 left-2 rounded-md bg-[#B77C4C] px-2 py-1 text-xs text-white shadow">
            {product.category}
          </span>
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
              <button className="w-full rounded-md bg-[#B77C4C] px-3 py-3 text-sm font-medium text-white transition hover:cursor-pointer hover:bg-[#a0683d]">
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
  );
}
