import { usePage, Link, Head } from '@inertiajs/react';
import { formatIDR } from '@/lib/utils';
import React, { useRef } from 'react';

export default function InvoicePage() {
  const { order } = usePage().props;
  const invoiceRef = useRef();

  const handlePrint = () => {
    window.print();
  };

  const invoiceDate = new Date(order.created_at);
  const invoiceNumber = order.public_id;
  
  const statusBadge = {
    Waiting: 'bg-yellow-100 text-yellow-800',
    Processing: 'bg-blue-100 text-blue-800',
    'On The Way': 'bg-blue-100 text-blue-800',
    Delivered: 'bg-green-100 text-green-800',
    Completed: 'bg-green-100 text-green-800',
    Cancelled: 'bg-red-100 text-red-800',
  };

  return (
    <>
      <Head title="Invoice" />
      <div className="min-h-screen bg-gradient-to-br from-[#53685B] via-[#2d3a30] to-[#1a1f1c] py-12 px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-4xl">
        {/* Action Buttons - Hidden when printing */}
        <div className="mb-6 flex justify-between print:hidden">
          <Link
            href="/order"
            className="rounded-lg bg-white/10 px-6 py-2 text-white backdrop-blur-sm transition hover:bg-white/20"
          >
            ← Kembali ke Pesanan
          </Link>
          <button
            onClick={handlePrint}
            className="rounded-lg bg-[#B77C4C] px-6 py-2 font-semibold text-white transition hover:bg-[#8d5e39]"
          >
            🖨️ Print / Download PDF
          </button>
        </div>

        {/* Invoice Content */}
        <div
          ref={invoiceRef}
          className="rounded-2xl bg-white p-8 shadow-2xl md:p-12"
        >
          {/* Header */}
          <div className="mb-8 border-b-2 border-[#53685B] pb-6">
            <div className="flex items-start justify-between">
              <div>
                <h1 className="mb-2 text-4xl font-bold text-[#53685B]">
                  INVOICE
                </h1>
                <p className="text-lg text-gray-600">{invoiceNumber}</p>
              </div>
              <div className="text-right">
                <div className="mb-2 text-3xl font-bold text-[#B77C4C]">
                  VINSTORE
                </div>
                <p className="text-sm text-gray-600">
                  Platform E-Commerce Terpercaya
                </p>
              </div>
            </div>
          </div>

          {/* Invoice Details */}
          <div className="mb-8 grid gap-8 md:grid-cols-2">
            {/* From (Store) */}
            <div>
              <h3 className="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500">
                Dari Toko
              </h3>
              <div className="rounded-lg bg-gray-50 p-4">
                <p className="mb-1 text-xl font-bold text-[#53685B]">
                  {order.store?.store_name || 'Toko tidak ditemukan'}
                </p>
                <p className="text-sm text-gray-600">
                  {order.store?.description || '-'}
                </p>
                <p className="mt-2 text-sm text-gray-600">
                  📍 {order.store?.location || '-'}
                </p>
                <p className="text-sm text-gray-600">
                  📂 Kategori: {order.store?.category || '-'}
                </p>
              </div>
            </div>

            {/* To (Customer) */}
            <div>
              <h3 className="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500">
                Kepada
              </h3>
              <div className="rounded-lg bg-gray-50 p-4">
                <p className="mb-1 text-xl font-bold text-[#53685B]">
                  {order.user.first_name} {order.user.last_name}
                </p>
                <p className="text-sm text-gray-600">📧 {order.user.email}</p>
                <p className="mt-4 text-sm text-gray-600">
                  <span className="font-semibold">Tanggal:</span>{' '}
                  {invoiceDate.toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                  })}
                </p>
                <p className="text-sm text-gray-600">
                  <span className="font-semibold">Waktu:</span>{' '}
                  {invoiceDate.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                  })}
                </p>
              </div>
            </div>
          </div>

          {/* Status Badge */}
          <div className="mb-8">
            <span
              className={`inline-block rounded-full px-4 py-2 text-sm font-semibold ${statusBadge[order.status] || 'bg-gray-100 text-gray-800'}`}
            >
              Status: {order.status}
            </span>
          </div>

          {/* Order Items Table */}
          <div className="mb-8">
            <h3 className="mb-4 text-xl font-bold text-[#53685B]">
              Detail Pesanan
            </h3>
            <div className="overflow-hidden rounded-lg border border-gray-200">
              <table className="w-full">
                <thead className="bg-[#53685B] text-white">
                  <tr>
                    <th className="px-6 py-4 text-left text-sm font-semibold">
                      Produk
                    </th>
                    <th className="px-6 py-4 text-center text-sm font-semibold">
                      Jumlah
                    </th>
                    <th className="px-6 py-4 text-right text-sm font-semibold">
                      Harga Satuan
                    </th>
                    <th className="px-6 py-4 text-right text-sm font-semibold">
                      Total
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr className="border-b border-gray-200">
                    <td className="px-6 py-4">
                      <p className="font-semibold text-gray-900">
                        {order.product.name}
                      </p>
                      <p className="text-sm text-gray-500">
                        {order.product.description?.substring(0, 60)}...
                      </p>
                    </td>
                    <td className="px-6 py-4 text-center font-semibold">
                      {order.quantity}
                    </td>
                    <td className="px-6 py-4 text-right">
                      {formatIDR(order.price / order.quantity)}
                    </td>
                    <td className="px-6 py-4 text-right font-bold text-[#53685B]">
                      {formatIDR(order.price)}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          {/* Total */}
          <div className="mb-8 flex justify-end">
            <div className="w-full max-w-sm">
              <div className="rounded-lg bg-[#53685B] p-6 text-white">
                <div className="flex items-center justify-between">
                  <span className="text-xl font-semibold">TOTAL</span>
                  <span className="text-3xl font-bold">
                    {formatIDR(order.price)}
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Footer Notes */}
          <div className="border-t-2 border-gray-200 pt-6">
            <h4 className="mb-3 font-semibold text-gray-700">
              Informasi Penting:
            </h4>
            <ul className="space-y-2 text-sm text-gray-600">
              <li>
                ✓ Invoice ini adalah bukti transaksi yang sah di Vinstore
              </li>
              <li>
                ✓ Mohon simpan invoice ini untuk keperluan retur atau klaim
                garansi
              </li>
              <li>
                ✓ Untuk pertanyaan, hubungi toko atau customer service Vinstore
              </li>
              <li>
                ✓ Terima kasih telah berbelanja di Vinstore! 🛍️
              </li>
            </ul>
          </div>

          {/* Signature Section */}
          <div className="mt-12 grid gap-8 md:grid-cols-2">
            <div className="text-center">
              <p className="mb-12 text-sm text-gray-600">Penjual</p>
              <div className="border-t border-gray-400 pt-2">
                <p className="font-semibold text-gray-700">
                  {order.store?.store_name || '-'}
                </p>
              </div>
            </div>
            <div className="text-center">
              <p className="mb-12 text-sm text-gray-600">Pembeli</p>
              <div className="border-t border-gray-400 pt-2">
                <p className="font-semibold text-gray-700">
                  {order.user.first_name} {order.user.last_name}
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Print Styles */}
        <style>{`
          @media print {
            body {
              background: white !important;
            }
            .print\\:hidden {
              display: none !important;
            }
          }
        `}</style>
      </div>
      </div>
    </>
  );
}
