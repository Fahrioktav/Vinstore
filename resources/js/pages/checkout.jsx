import { Form, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR, getProductCertificate, getProductImage } from '@/lib/utils';
import { BadgeIcon, CertificateIcon } from '@/components/icons';

export default function CheckoutPage() {
  const { product } = usePage().props;

  return (
    <div className="mx-auto mt-10 max-w-3xl rounded-md border bg-white p-6 shadow-md">
      <h2 className="mb-6 text-2xl font-bold">Detail Checkout</h2>

      <div className="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
          <img
            src={getProductImage(product)}
            className="h-48 w-full rounded-md object-contain"
            alt={product.name}
          />
        </div>
        <div>
          <h3 className="text-xl font-semibold">{product.name}</h3>
          <p className="mt-2 text-gray-600">{product.description}</p>
          <p className="mt-4 text-lg font-bold">
            Harga: {formatIDR(product.price)}
          </p>
          {product.certificate && (
            <div className="mt-4 rounded-lg border border-green-200 bg-green-50 p-3">
              <p className="mb-2 flex items-center gap-2 text-sm font-semibold text-green-800">
                <BadgeIcon className="h-5 w-5" />
                Produk Bersertifikat
              </p>
              <a
                href={getProductCertificate(product)}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-1 text-sm text-blue-600 hover:underline"
              >
                <CertificateIcon />
                Lihat Sertifikat Keaslian
              </a>
            </div>
          )}
        </div>
      </div>

      <div className="flex flex-wrap gap-3">
        <Form method="POST" action={`/cart/add/${product.public_id}`}>
          <input type="hidden" name="quantity" defaultValue="1" />
          <button className="rounded-md bg-yellow-300 px-6 py-2 font-semibold text-black hover:cursor-pointer hover:bg-yellow-400">
            Masukkan Keranjang
          </button>
        </Form>

        <Form method="POST" action={`/checkout/product/${product.public_id}`}>
          <input type="hidden" name="quantity" defaultValue="1" />
          <button className="rounded-md bg-[#53685B] px-6 py-2 font-semibold text-white hover:cursor-pointer hover:bg-[#3c4a3e]">
            Bayar dengan Midtrans Sandbox
          </button>
        </Form>
      </div>
    </div>
  );
}

CheckoutPage.layout = (page) => (
  <MainLayout title="Checkout" heroText="Konfirmasi Pembelianmu">
    {page}
  </MainLayout>
);
