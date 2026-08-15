import { usePage, Link, Head } from '@inertiajs/react';
import { formatIDR } from '@/lib/utils';
import {
  CategoryFolderIcon,
  CheckIcon,
  LocationIcon,
  MailIcon,
  PrintIcon,
} from '@/components/icons';
import React, { useRef } from 'react';

// Label jenis pengemasan. Disalin ke sini karena invoice tidak menerima prop
// feeRates — tarifnya bisa berubah, tetapi nama jenisnya stabil.
const packagingLabels = {
  standard: 'Bubble Wrap + Kardus',
  kayu: 'Peti Kayu',
};

export default function InvoicePage() {
  const { order } = usePage().props;
  const invoiceRef = useRef();

  const handlePrint = () => {
    window.print();
  };

  const invoiceDate = new Date(order.created_at);
  const invoiceNumber = order.public_id;
  const item = order.product || order.auction;

  // order.price adalah TOTAL tagihan — sudah termasuk seluruh komponen biaya.
  // Harga satuan karena itu tidak boleh dihitung dari order.price / quantity;
  // pakai snapshot product_price bila tersedia.
  const shippingCost = Number(order.shipping_cost) || 0;
  const packagingFee = Number(order.packaging_fee) || 0;
  const weightFee = Number(order.weight_fee) || 0;
  const serviceFee = Number(order.service_fee) || 0;
  const depositCredit = Number(order.deposit_credit) || 0;

  const itemSubtotal =
    Number(order.price) - shippingCost - packagingFee - weightFee - serviceFee;
  const unitPrice = order.product_price
    ? Number(order.product_price)
    : itemSubtotal / (order.quantity || 1);

  const distanceKm = order.shipping_distance_km
    ? Number(order.shipping_distance_km)
    : null;

  // Baris biaya tambahan pada invoice. Yang bernilai nol tidak ditampilkan —
  // pesanan lelang, misalnya, tidak punya komponen biaya sama sekali.
  const feeRows = [
    {
      label: 'Biaya Pengiriman',
      note:
        (order.shipping_method || '-') +
        (distanceKm !== null
          ? ` · ${distanceKm.toLocaleString('id-ID', { maximumFractionDigits: 1 })} km`
          : ''),
      amount: shippingCost,
    },
    {
      label: 'Biaya Berat',
      note: order.weight_gram
        ? `${(Number(order.weight_gram) / 1000).toLocaleString('id-ID', {
            maximumFractionDigits: 2,
          })} kg` +
          (Number(order.volumetric_weight_gram) > 0 &&
          Number(order.volumetric_weight_gram) >= Number(order.weight_gram)
            ? ' (volumetrik)'
            : '')
        : '-',
      amount: weightFee,
    },
    {
      label: 'Biaya Pengemasan',
      note: packagingLabels[order.packaging_type] ?? 'Peti & pembungkus',
      amount: packagingFee,
    },
    { label: 'Biaya Layanan', note: 'Layanan marketplace', amount: serviceFee },
  ].filter((row) => row.amount > 0);

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
      <div className="min-h-screen bg-gradient-to-br from-[#53685B] via-[#2d3a30] to-[#1a1f1c] px-4 py-12 sm:px-6 lg:px-8">
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
              className="inline-flex items-center gap-2 rounded-lg bg-[#B77C4C] px-6 py-2 font-semibold text-white transition hover:bg-[#8d5e39]"
            >
              <PrintIcon className="h-5 w-5" />
              Print / Download PDF
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
                  <h1 className="mb-2 text-2xl font-bold text-[#53685B] sm:text-4xl">
                    INVOICE
                  </h1>
                  <p className="text-lg text-gray-600">{invoiceNumber}</p>
                </div>
                <div className="text-right">
                  <div className="mb-2 text-xl font-bold text-[#B77C4C] sm:text-3xl">
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
                <h3 className="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase">
                  Dari Toko
                </h3>
                <div className="rounded-lg bg-gray-50 p-4">
                  <p className="mb-1 text-xl font-bold text-[#53685B]">
                    {order.display_store_name}
                  </p>
                  <p className="text-sm text-gray-600">
                    {order.store?.description || '-'}
                  </p>
                  <p className="mt-2 flex items-center gap-1.5 text-sm text-gray-600">
                    <LocationIcon className="h-4 w-4 shrink-0" />
                    {order.store?.location || '-'}
                  </p>
                  <p className="flex items-center gap-1.5 text-sm text-gray-600">
                    <CategoryFolderIcon className="h-4 w-4 shrink-0" />
                    Kategori: {order.store?.category || '-'}
                  </p>
                </div>
              </div>

              {/* To (Customer) */}
              <div>
                <h3 className="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase">
                  Kepada
                </h3>
                <div className="rounded-lg bg-gray-50 p-4">
                  <p className="mb-1 text-xl font-bold text-[#53685B]">
                    {order.user.first_name} {order.user.last_name}
                  </p>
                  <p className="flex items-center gap-1.5 text-sm text-gray-600">
                    <MailIcon className="h-4 w-4 shrink-0" />
                    {order.user.email}
                  </p>
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

            {/* Alamat Pengiriman */}
            {order.shipping_address && (
              <div className="mb-8 rounded-lg bg-gray-50 p-4">
                <h4 className="mb-2 font-semibold text-[#53685B]">
                  Alamat Pengiriman
                </h4>
                {order.shipping_area && (
                  <p className="mb-1 text-sm font-semibold text-[#53685B]">
                    {order.shipping_area}
                  </p>
                )}
                <p className="text-sm whitespace-pre-line text-gray-700">
                  {order.shipping_address}
                </p>
                {order.notes && (
                  <p className="mt-2 text-sm text-gray-500 italic">
                    Catatan: {order.notes}
                  </p>
                )}
              </div>
            )}

            {/* Order Items Table */}
            <div className="mb-8">
              <h3 className="mb-4 text-xl font-bold text-[#53685B]">
                Detail Pesanan
              </h3>
              <div className="overflow-x-auto rounded-lg border border-gray-200">
                <table className="w-full min-w-[32rem]">
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
                          {order.display_item_name}
                        </p>
                        <p className="text-sm text-gray-500">
                          {item?.description
                            ? `${item.description.substring(0, 60)}...`
                            : '-'}
                        </p>
                      </td>
                      <td className="px-6 py-4 text-center font-semibold">
                        {order.quantity}
                      </td>
                      <td className="px-6 py-4 text-right">
                        {formatIDR(unitPrice)}
                      </td>
                      <td className="px-6 py-4 text-right font-bold text-[#53685B]">
                        {formatIDR(itemSubtotal)}
                      </td>
                    </tr>
                    {feeRows.map((row) => (
                      <tr key={row.label} className="border-b border-gray-200">
                        <td className="px-6 py-4">
                          <p className="font-semibold text-gray-900">
                            {row.label}
                          </p>
                          <p className="text-sm text-gray-500 capitalize">
                            {row.note}
                          </p>
                        </td>
                        <td className="px-6 py-4 text-center font-semibold">
                          1
                        </td>
                        <td className="px-6 py-4 text-right">
                          {formatIDR(row.amount)}
                        </td>
                        <td className="px-6 py-4 text-right font-bold text-[#53685B]">
                          {formatIDR(row.amount)}
                        </td>
                      </tr>
                    ))}
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
                    <span className="text-xl font-bold sm:text-3xl">
                      {formatIDR(order.price)}
                    </span>
                  </div>

                  {/* Deposit lelang sudah masuk lebih dulu, jadi yang ditagihkan
                      lewat Midtrans hanya sisanya. */}
                  {depositCredit > 0 && (
                    <div className="mt-4 space-y-2 border-t border-white/30 pt-4 text-sm">
                      <div className="flex items-center justify-between">
                        <span>Deposit lelang dibayar</span>
                        <span className="font-semibold">
                          − {formatIDR(depositCredit)}
                        </span>
                      </div>
                      <div className="flex items-center justify-between">
                        <span className="font-semibold">Sisa dibayar</span>
                        <span className="text-lg font-bold">
                          {formatIDR(Number(order.price) - depositCredit)}
                        </span>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Footer Notes */}
            <div className="border-t-2 border-gray-200 pt-6">
              <h4 className="mb-3 font-semibold text-gray-700">
                Informasi Penting:
              </h4>
              <ul className="space-y-2 text-sm text-gray-600">
                {[
                  'Invoice ini adalah bukti transaksi yang sah di Vinstore',
                  'Mohon simpan invoice ini untuk keperluan retur atau klaim garansi',
                  'Untuk pertanyaan, hubungi toko atau customer service Vinstore',
                  'Terima kasih telah berbelanja di Vinstore!',
                ].map((note) => (
                  <li key={note} className="flex items-start gap-2">
                    <CheckIcon className="mt-0.5 h-4 w-4 shrink-0 text-[#53685B]" />
                    <span>{note}</span>
                  </li>
                ))}
              </ul>
            </div>

            {/* Signature Section */}
            <div className="mt-12 grid gap-8 md:grid-cols-2">
              <div className="text-center">
                <p className="mb-12 text-sm text-gray-600">Penjual</p>
                <div className="border-t border-gray-400 pt-2">
                  <p className="font-semibold text-gray-700">
                    {order.display_store_name}
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
