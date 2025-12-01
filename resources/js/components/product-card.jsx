import { Link } from '@inertiajs/react';
import { Card, CardContent } from './ui/card';
import { formatIDR } from '@/lib/utils';

export default function ProductCard({ product }) {
  return (
    <Card className="gap-0 py-4 shadow-md transition hover:shadow-lg">
      <CardContent className="px-4">
        <div className="relative">
          <img
            src={
              product.image
                ? `/${product.image}`
                : 'https://placehold.co/600x400/53685B/FFFFFF?text=Foto+Produk'
            }
            className="mb-4 h-48 w-full rounded-md object-cover"
          />
          <span className="absolute top-2 left-2 rounded-md bg-[#B77C4C] px-2 py-1 text-xs text-white shadow">
            {product.category}
          </span>
          {product.certificate && (
            <span className="absolute top-2 right-2 flex items-center gap-1 rounded-md bg-green-600 px-2 py-1 text-xs text-white shadow">
              <svg className="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
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
