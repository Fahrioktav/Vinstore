import { Link, useForm, usePage, router } from '@inertiajs/react';
import React, { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR, getProductCertificate, getProductImage } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import {
  BadgeIcon,
  DeleteIcon,
  EditIcon,
  EmptyStateIcon,
  ImageIcon,
  LocationIcon,
  MoneyIcon,
  OrderIcon,
  ProductIcon,
  RefreshIcon,
  ShippingIcon,
  StatsIcon,
  ViewIcon,
} from '@/components/icons';
import ActionMenu from '@/components/ui/action-menu';
import { toast } from 'sonner';
import { confirmDestructive, confirmDialog } from '@/lib/dialog';

// Harus sama dengan Auction::EDITABLE_APPROVAL_STATUSES di backend.
// Nilai 'pending' TIDAK pernah ada di kolom approval_status — memakainya di
// sini dulu membuat menu Edit tidak pernah muncul (temuan T-07).
const EDITABLE_APPROVAL_STATUSES = [
  'draft',
  'pending_validator',
  'pending_admin',
  'rejected',
];

const payoutStatusColors = {
  pending: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
};

// Label persetujuan dipakai bersama oleh tabel produk DAN tabel lelang.
// Sebelumnya hanya tabel produk yang memakainya, sehingga tabel lelang
// menampilkan nilai mentah dari basis data — dua gaya penulisan dalam satu
// layar (temuan V6-07).
const approvalColors = {
  approved: 'bg-green-100 text-green-700',
  draft: 'bg-gray-100 text-gray-700',
  pending_validator: 'bg-yellow-100 text-yellow-700',
  pending_admin: 'bg-blue-100 text-blue-700',
  pending: 'bg-yellow-100 text-yellow-700',
  rejected: 'bg-red-100 text-red-700',
};

const approvalLabels = {
  approved: 'Disetujui',
  draft: 'Draf',
  pending_validator: 'Menunggu Validator',
  pending_admin: 'Menunggu Admin',
  pending: 'Menunggu',
  rejected: 'Ditolak',
};

// Tahapan hidup lelang, terpisah dari status persetujuannya.
const auctionStatusLabels = {
  pending: 'Belum Dijadwalkan',
  scheduled: 'Terjadwal',
  active: 'Berlangsung',
  ended: 'Selesai',
  cancelled: 'Dibatalkan',
};

const auctionStatusColors = {
  pending: 'bg-gray-100 text-gray-700',
  scheduled: 'bg-blue-100 text-blue-700',
  active: 'bg-green-100 text-green-700',
  ended: 'bg-gray-200 text-gray-700',
  cancelled: 'bg-red-100 text-red-700',
};

export default function SellerDashboard() {
  const {
    products,
    orders,
    productCount,
    orderCount,
    totalIncome,
    monthlyIncome,
    auctions,
    store,
    payouts,
    bankPrefill,
    readyToRequest,
    pendingPayoutAmount,
    paidOutAmount,
  } = usePage().props;
  const [selectedProducts, setSelectedProducts] = React.useState([]);

  // Tarik kembali pengajuan lelang dari antrean validator agar bisa diperbaiki.
  const withdrawSubmission = async (publicId) => {
    const ok = await confirmDialog({
      title: 'Tarik kembali pengajuan lelang?',
      description:
        'Lelang keluar dari antrean validator dan dapat Anda perbaiki sebelum diajukan ulang.',
      confirmLabel: 'Ya, tarik kembali',
    });

    if (!ok) return;

    router.post(
      `/seller/auctions/${publicId}/withdraw`,
      {},
      { preserveScroll: true }
    );
  };

  const handleSelectProduct = (productId) => {
    setSelectedProducts((prev) =>
      prev.includes(productId)
        ? prev.filter((id) => id !== productId)
        : [...prev, productId]
    );
  };

  const handleSelectAll = (e) => {
    if (e.target.checked) {
      setSelectedProducts(products.map((p) => p.public_id));
    } else {
      setSelectedProducts([]);
    }
  };

  return (
    <>
      <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        {/* Header Stats */}
        <div className="mb-8 grid gap-6 md:grid-cols-4">
          <div className="rounded-2xl bg-gradient-to-br from-[#53685B] to-[#3c4a3e] p-6 text-white shadow-lg">
            <h3 className="mb-2 text-sm font-semibold opacity-90">
              Total Produk
            </h3>
            <p className="text-2xl font-bold sm:text-4xl">{productCount}</p>
          </div>
          <div className="rounded-2xl bg-gradient-to-br from-[#B77C4C] to-[#8d5e39] p-6 text-white shadow-lg">
            <h3 className="mb-2 text-sm font-semibold opacity-90">
              Total Order
            </h3>
            <p className="text-2xl font-bold sm:text-4xl">{orderCount}</p>
          </div>
          <div className="rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 p-6 text-white shadow-lg">
            <h3 className="mb-2 text-sm font-semibold opacity-90">
              <MoneyIcon className="mr-1 inline h-4 w-4 align-text-bottom" />
              Total Pendapatan
            </h3>
            <p className="text-2xl font-bold">{formatIDR(totalIncome || 0)}</p>
          </div>
          <div className="rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 p-6 text-white shadow-lg">
            <h3 className="mb-2 text-sm font-semibold opacity-90">
              <StatsIcon className="mr-1 inline h-4 w-4 align-text-bottom" />
              Pendapatan Bulan Ini
            </h3>
            <p className="text-2xl font-bold">
              {formatIDR(monthlyIncome || 0)}
            </p>
          </div>
        </div>

        {/* Pencairan Dana */}
        <div className="mb-8 grid gap-6 lg:grid-cols-[1fr_1.4fr]">
          <div className="rounded-2xl bg-white p-6 shadow-md">
            <h2 className="mb-4 text-xl font-bold text-[#53685B]">
              Pencairan Dana
            </h2>
            <div className="space-y-4">
              <BalanceLine
                label="Siap diajukan"
                value={formatIDR(readyToRequest || 0)}
              />
              <BalanceLine
                label="Menunggu persetujuan"
                value={formatIDR(pendingPayoutAmount || 0)}
              />
              <BalanceLine
                label="Sudah ditransfer"
                value={formatIDR(paidOutAmount || 0)}
              />
            </div>
          </div>

          <div className="rounded-2xl bg-white p-6 shadow-md">
            <h2 className="mb-4 text-xl font-bold text-[#53685B]">
              Riwayat Pengajuan Pencairan
            </h2>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-100">
                  <tr>
                    <th className="px-3 py-2 text-left">Pesanan</th>
                    <th className="px-3 py-2 text-left">Nominal</th>
                    <th className="px-3 py-2 text-left">Bank</th>
                    <th className="px-3 py-2 text-left">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {payouts?.length > 0 ? (
                    payouts.map((payout) => (
                      <tr key={payout.public_id} className="border-t">
                        <td className="px-3 py-2 text-xs text-gray-600">
                          {payout.order?.public_id || '-'}
                        </td>
                        <td className="px-3 py-2 font-semibold">
                          {formatIDR(payout.amount)}
                        </td>
                        <td className="px-3 py-2">
                          {payout.bank_name} - {payout.account_number}
                        </td>
                        <td className="px-3 py-2">
                          <span
                            className={`rounded-full px-2 py-1 text-xs font-semibold capitalize ${payoutStatusColors[payout.status] || 'bg-gray-100 text-gray-700'}`}
                          >
                            {payout.status}
                          </span>
                          {payout.admin_note && (
                            <p className="mt-1 text-xs text-gray-500 italic">
                              {payout.admin_note}
                            </p>
                          )}
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td
                        colSpan="4"
                        className="px-3 py-4 text-center text-gray-500"
                      >
                        Belum ada pengajuan pencairan.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        {/* Customer Orders */}
        <div className="mb-8 rounded-2xl bg-white p-6 shadow-md">
          <h2 className="mb-6 text-2xl font-bold text-[#53685B]">
            <OrderIcon className="mr-2 inline h-6 w-6 align-text-bottom" />
            Customer Orders
          </h2>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="px-4 py-3 text-left">Customer</th>
                  <th className="px-4 py-3 text-left">Pengiriman</th>
                  <th className="px-4 py-3 text-left">Product</th>
                  <th className="px-4 py-3 text-center">Qty</th>
                  <th className="px-4 py-3 text-left">Total</th>
                  <th className="px-4 py-3 text-left">Payment</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-left">Date</th>
                  <th className="px-4 py-3 text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                {orders.length > 0 ? (
                  orders.map((order) => (
                    <OrderRow
                      key={order.public_id}
                      order={order}
                      bankPrefill={bankPrefill}
                    />
                  ))
                ) : (
                  <tr>
                    <td colSpan="9" className="py-8 text-center text-gray-500">
                      <EmptyStateIcon className="mx-auto mb-2 h-10 w-10 text-gray-300" />
                      Belum ada pesanan
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Products Section */}
        <div className="mb-8 rounded-2xl bg-white p-6 shadow-md">
          <div className="mb-6 flex items-center justify-between">
            <h2 className="text-2xl font-bold text-[#53685B]">
              <ProductIcon className="mr-2 inline h-6 w-6 align-text-bottom" />
              Daftar Barang
            </h2>
            <button>
              <Link
                href="/seller/products/create"
                className="rounded-lg bg-[#53685B] px-6 py-2 font-semibold text-white transition hover:bg-[#3c4a3e]"
              >
                + Add Barang
              </Link>
            </button>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-200">
                <tr>
                  <th className="px-4 py-3">
                    <input
                      type="checkbox"
                      onChange={handleSelectAll}
                      checked={
                        products.length > 0 &&
                        selectedProducts.length === products.length
                      }
                    />
                  </th>
                  <th className="px-4 py-3 text-left">Foto</th>
                  <th className="px-4 py-3 text-left">Nama</th>
                  <th className="px-4 py-3 text-center">Stok</th>
                  <th className="px-4 py-3 text-left">Harga</th>
                  <th className="px-4 py-3 text-left">Approval</th>
                  <th className="px-4 py-3 text-left">Kategori</th>
                  <th className="px-4 py-3 text-left">Deskripsi</th>
                  <th className="px-4 py-3 text-center">Sertifikat</th>
                  <th className="px-4 py-3 text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                {products.length > 0 ? (
                  products.map((product) => (
                    <ProductRow
                      key={product.public_id}
                      product={product}
                      isSelected={selectedProducts.includes(product.public_id)}
                      onSelect={handleSelectProduct}
                    />
                  ))
                ) : (
                  <tr>
                    <td colSpan="9" className="py-8 text-center text-gray-500">
                      <EmptyStateIcon className="mx-auto mb-2 h-10 w-10 text-gray-300" />
                      Belum ada produk
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Auctions Section */}
        <div className="rounded-2xl bg-white p-6 shadow-md">
          {/* Tanpa tombol tambah sendiri: barang lelang kini diajukan lewat
              form Tambah Produk dengan memilih jenis penjualan "Lelang". */}
          <div className="mb-6">
            <h2 className="text-2xl font-bold text-[#53685B]">
              Daftar Barang Lelang
            </h2>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-200">
                <tr>
                  <th className="px-4 py-3 text-left">Nama</th>
                  <th className="px-4 py-3 text-left">Harga Awal</th>
                  <th className="px-4 py-3 text-left">Harga Tertinggi</th>
                  <th className="px-4 py-3 text-left">Bid</th>
                  <th className="px-4 py-3 text-left">Approval</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-left">Pemenang</th>
                  <th className="px-4 py-3 text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                {auctions?.length > 0 ? (
                  auctions.map((auction) => (
                    <tr
                      key={auction.public_id}
                      className="border-t hover:bg-gray-50"
                    >
                      <td className="px-4 py-3 font-semibold">
                        {auction.name}
                      </td>
                      <td className="px-4 py-3">
                        {formatIDR(auction.starting_price)}
                      </td>
                      <td className="px-4 py-3 font-semibold text-[#53685B]">
                        {formatIDR(auction.current_price)}
                      </td>
                      <td className="px-4 py-3">{auction.bids_count}</td>
                      <td className="px-4 py-3">
                        <span
                          className={`rounded-full px-3 py-1 text-xs font-semibold ${approvalColors[auction.approval_status] || 'bg-gray-100 text-gray-700'}`}
                        >
                          {approvalLabels[auction.approval_status] ||
                            auction.approval_status}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`rounded-full px-3 py-1 text-xs font-semibold ${auctionStatusColors[auction.status] || 'bg-gray-100 text-gray-700'}`}
                        >
                          {auctionStatusLabels[auction.status] ||
                            auction.status}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        {auction.winner?.username || '-'}
                      </td>
                      <td className="px-4 py-3 text-center">
                        <div className="flex justify-center">
                          <ActionMenu
                            items={[
                              {
                                label: 'Detail',
                                icon: <ViewIcon />,
                                href: `/auctions/${auction.public_id}`,
                              },
                              auction.bids_count === 0 &&
                                ['pending', 'scheduled'].includes(
                                  auction.status
                                ) &&
                                EDITABLE_APPROVAL_STATUSES.includes(
                                  auction.approval_status
                                ) && {
                                  label: 'Edit',
                                  icon: <EditIcon />,
                                  href: `/seller/auctions/${auction.public_id}/edit`,
                                },
                              auction.bids_count === 0 &&
                                ['pending_validator', 'pending_admin'].includes(
                                  auction.approval_status
                                ) && {
                                  label: 'Tarik Pengajuan',
                                  icon: '↩️',
                                  onClick: () =>
                                    withdrawSubmission(auction.public_id),
                                },
                              auction.bids_count === 0 &&
                                auction.status === 'ended' && {
                                  label: 'Ajukan Ulang',
                                  icon: <RefreshIcon />,
                                  href: `/seller/auctions/${auction.public_id}/relist`,
                                },
                            ]}
                          />
                        </div>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="8" className="py-8 text-center text-gray-500">
                      Belum ada barang lelang
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </>
  );
}

function BalanceLine({ label, value }) {
  return (
    <div className="flex items-center justify-between border-b border-gray-100 pb-3">
      <span className="text-sm text-gray-600">{label}</span>
      <span className="font-bold text-[#53685B]">{value}</span>
    </div>
  );
}

function InputField({ label, type = 'text', value, onChange, error }) {
  return (
    <label className="block">
      <span className="mb-1 block text-sm font-semibold text-gray-700">
        {label}
      </span>
      <input
        type={type}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-[#53685B] focus:ring-2 focus:ring-[#53685B] focus:outline-none"
        required
      />
      {error && (
        <span className="mt-1 block text-xs text-red-600">{error}</span>
      )}
    </label>
  );
}

function ProductRow({ product, isSelected, onSelect }) {
  const {
    patch,
    delete: destroy,
    data,
    setData,
    processing,
  } = useForm({
    ...product,
  });

  const handleStockUpdate = (e) => {
    e.preventDefault();
    patch(`/seller/products/${product.public_id}`, {
      preserveScroll: true,
    });
  };

  const handleDelete = async () => {
    const ok = await confirmDestructive({
      title: 'Hapus produk ini?',
      description: 'Produk yang dihapus tidak dapat dikembalikan.',
    });

    if (ok) {
      destroy(`/seller/products/${product.public_id}`, {
        preserveScroll: true,
      });
    }
  };

  return (
    <tr className="border-t hover:bg-gray-50">
      <td className="px-4 py-3 text-center">
        <input
          type="checkbox"
          checked={isSelected}
          onChange={() => onSelect(product.public_id)}
        />
      </td>
      <td className="px-4 py-3">
        {product.image ? (
          <img
            src={getProductImage(product)}
            alt={product.name}
            className="h-16 w-16 rounded-lg object-cover"
          />
        ) : (
          <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-gray-200 text-gray-400">
            <ImageIcon className="h-7 w-7" />
          </div>
        )}
      </td>
      <td className="px-4 py-3 font-semibold">{product.name}</td>
      <td className="px-4 py-3">
        <div className="flex items-center justify-center">
          <span className="rounded border border-gray-300 bg-gray-50 px-3 py-1 text-center text-sm font-semibold text-gray-700">
            {product.stock}
          </span>
        </div>
      </td>
      <td className="px-4 py-3 font-semibold text-[#53685B]">
        {formatIDR(product.price)}
      </td>
      <td className="px-4 py-3">
        <span
          className={`rounded-full px-3 py-1 text-xs font-semibold ${approvalColors[product.approval_status] || 'bg-gray-100 text-gray-700'}`}
        >
          {approvalLabels[product.approval_status] ||
            product.approval_status ||
            'Menunggu'}
        </span>
        {product.rejection_reason && (
          <p className="mt-1 max-w-40 text-xs text-red-600">
            {product.rejection_reason}
          </p>
        )}
      </td>
      <td className="px-4 py-3">
        <span className="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold">
          {product.category}
        </span>
      </td>
      <td className="px-4 py-3 text-xs text-gray-600">
        {product.description?.substring(0, 40)}
        {product.description?.length > 40 ? '...' : ''}
      </td>
      <td className="px-4 py-3 text-center">
        {product.certificate ? (
          <a
            href={getProductCertificate(product)}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1 text-green-600 hover:text-green-800"
            title="Lihat Sertifikat"
          >
            <BadgeIcon className="h-5 w-5" />
          </a>
        ) : (
          <span className="text-xs text-gray-400">-</span>
        )}
      </td>
      <td className="px-4 py-3">
        <div className="flex items-center justify-center">
          <ActionMenu
            items={[
              {
                label: 'Edit',
                icon: <EditIcon />,
                href: `/seller/products/${product.public_id}/edit`,
              },
              {
                label: 'Hapus',
                icon: <DeleteIcon />,
                variant: 'destructive',
                onClick: processing ? undefined : handleDelete,
              },
            ]}
          />
        </div>
      </td>
    </tr>
  );
}

function OrderRow({ order, bankPrefill }) {
  const [currentStatus, setCurrentStatus] = useState(order.status);
  const [isUpdating, setIsUpdating] = useState(false);
  const [showTrackingModal, setShowTrackingModal] = useState(false);
  const [showEditTrackingModal, setShowEditTrackingModal] = useState(false);
  const [showPayoutModal, setShowPayoutModal] = useState(false);
  const [pendingStatus, setPendingStatus] = useState(null);
  const [trackingNumber, setTrackingNumber] = useState(
    order.tracking_number || ''
  );

  // Data bank diisi otomatis dari pengajuan terakhir, tapi tetap bisa diubah
  // seller di form ini — misal ganti rekening tujuan.
  const payoutForm = useForm({
    bank_name: bankPrefill?.bank_name || '',
    account_number: bankPrefill?.account_number || '',
    account_holder: bankPrefill?.account_holder || '',
  });

  const submitPayout = (e) => {
    e.preventDefault();
    payoutForm.post(`/seller/orders/${order.public_id}/payout`, {
      preserveScroll: true,
      onSuccess: () => setShowPayoutModal(false),
    });
  };

  const handleStatusChange = (e) => {
    const newStatus = e.target.value;

    // Status pengiriman apa pun menuntut nomor resi — termasuk "Delivered",
    // yang dulu justru satu-satunya yang melewatinya. Lihat aturan yang sama di
    // Order::STATUSES_REQUIRING_TRACKING (temuan V9-01).
    if (
      ['Processing', 'On The Way', 'Delivered'].includes(newStatus) &&
      !order.tracking_number
    ) {
      setPendingStatus(newStatus);
      setShowTrackingModal(true);
      return;
    }

    // Jika sudah ada tracking number atau status lain, langsung update
    updateStatus(newStatus, order.tracking_number);
  };

  const handleTrackingSubmit = (e) => {
    e.preventDefault();
    if (!trackingNumber.trim()) {
      toast.error('Nomor resi harus diisi.');
      return;
    }
    updateStatus(pendingStatus, trackingNumber);
    setShowTrackingModal(false);
  };

  const handleEditTrackingSubmit = (e) => {
    e.preventDefault();
    if (!trackingNumber.trim()) {
      toast.error('Nomor resi harus diisi.');
      return;
    }
    updateStatus(currentStatus, trackingNumber);
    setShowEditTrackingModal(false);
  };

  const openEditTracking = () => {
    setTrackingNumber(order.tracking_number || '');
    setShowEditTrackingModal(true);
  };

  const updateStatus = (newStatus, tracking = null) => {
    setCurrentStatus(newStatus); // Update UI immediately
    setIsUpdating(true);

    router.post(
      `/seller/orders/${order.public_id}/status`,
      {
        status: newStatus,
        tracking_number: tracking,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setIsUpdating(false);
        },
        onError: (errors) => {
          // Revert jika error
          setCurrentStatus(order.status);
          setIsUpdating(false);
          toast.error(
            'Gagal mengupdate status: ' +
              (errors.tracking_number || 'Terjadi kesalahan.')
          );
        },
      }
    );
  };

  const handleDelete = async () => {
    const ok = await confirmDestructive({
      title: 'Hapus order ini?',
      description:
        'Order adalah bukti transaksi pembeli. Pesanan yang sudah dibayar tidak dapat dihapus.',
    });

    if (ok) {
      router.delete(`/seller/orders/${order.public_id}`, {
        preserveScroll: true,
      });
    }
  };

  const statusColors = {
    Waiting: 'bg-yellow-100 text-yellow-700',
    'On The Way': 'bg-blue-100 text-blue-700',
    Processing: 'bg-blue-100 text-blue-700',
    Delivered: 'bg-green-100 text-green-700',
    Completed: 'bg-green-100 text-green-700',
    Cancelled: 'bg-red-100 text-red-700',
  };

  const paymentColors = {
    paid: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    unpaid: 'bg-gray-100 text-gray-700',
    cancelled: 'bg-red-100 text-red-700',
    denied: 'bg-red-100 text-red-700',
    expired: 'bg-red-100 text-red-700',
    refunded: 'bg-blue-100 text-blue-700',
  };

  return (
    <>
      {/* Modal Input Nomor Resi untuk Status Baru */}
      {showTrackingModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4 backdrop-blur-sm">
          <div className="animate-in fade-in zoom-in-95 max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white p-5 shadow-2xl duration-200 sm:p-6">
            <div className="mb-4 flex items-center gap-3">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-[#53685B]">
                <ShippingIcon className="h-6 w-6 text-white" />
              </div>
              <h3 className="text-xl font-bold text-gray-900">
                Masukkan Nomor Resi
              </h3>
            </div>
            <form onSubmit={handleTrackingSubmit}>
              <div className="mb-5">
                <label className="mb-2 block text-sm font-semibold text-gray-700">
                  Nomor Resi Pengiriman <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  value={trackingNumber}
                  onChange={(e) => setTrackingNumber(e.target.value)}
                  placeholder="Contoh: JNE1234567890"
                  className="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-[#53685B] focus:ring-2 focus:ring-[#53685B] focus:outline-none"
                  required
                  autoFocus
                />
              </div>
              <div className="flex gap-3">
                <button
                  type="submit"
                  disabled={isUpdating}
                  className="flex-1 rounded-lg bg-[#53685B] px-4 py-3 font-semibold text-white transition hover:bg-[#3c4a3e] disabled:opacity-50"
                >
                  {isUpdating ? 'Menyimpan...' : 'Simpan & Update Status'}
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setShowTrackingModal(false);
                    setPendingStatus(null);
                    setTrackingNumber(order.tracking_number || '');
                  }}
                  className="flex-1 rounded-lg bg-gray-200 px-4 py-3 font-semibold text-gray-700 transition hover:bg-gray-300"
                >
                  Batal
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Pengajuan Pencairan Dana */}
      {showPayoutModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4 backdrop-blur-sm">
          <div className="animate-in fade-in zoom-in-95 max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white p-5 shadow-2xl duration-200 sm:p-6">
            <div className="mb-4 flex items-center gap-3">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-[#53685B]">
                <MoneyIcon className="h-6 w-6 text-white" />
              </div>
              <div>
                <h3 className="text-xl font-bold text-gray-900">
                  Ajukan Pencairan Dana
                </h3>
                <p className="text-sm text-gray-600">
                  Pesanan {order.public_id}
                </p>
              </div>
            </div>

            <div className="mb-5 rounded-lg bg-gray-50 p-4">
              <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">
                  Nominal yang dicairkan
                </span>
                <span className="text-xl font-bold text-[#53685B]">
                  {formatIDR(order.price)}
                </span>
              </div>
            </div>

            <form onSubmit={submitPayout} className="space-y-4">
              <InputField
                label="Nama Bank"
                value={payoutForm.data.bank_name}
                onChange={(value) => payoutForm.setData('bank_name', value)}
                error={payoutForm.errors.bank_name}
              />
              <InputField
                label="Nomor Rekening"
                value={payoutForm.data.account_number}
                onChange={(value) =>
                  payoutForm.setData('account_number', value)
                }
                error={payoutForm.errors.account_number}
              />
              <InputField
                label="Nama Pemilik Rekening"
                value={payoutForm.data.account_holder}
                onChange={(value) =>
                  payoutForm.setData('account_holder', value)
                }
                error={payoutForm.errors.account_holder}
              />

              <div className="flex gap-3 pt-2">
                <button
                  type="submit"
                  disabled={payoutForm.processing}
                  className="flex-1 rounded-lg bg-[#53685B] px-4 py-3 font-semibold text-white transition hover:bg-[#3c4a3e] disabled:opacity-50"
                >
                  {payoutForm.processing ? 'Mengirim...' : 'Ajukan'}
                </button>
                <button
                  type="button"
                  onClick={() => setShowPayoutModal(false)}
                  className="flex-1 rounded-lg bg-gray-200 px-4 py-3 font-semibold text-gray-700 transition hover:bg-gray-300"
                >
                  Batal
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Edit Nomor Resi */}
      {showEditTrackingModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4 backdrop-blur-sm">
          <div className="animate-in fade-in zoom-in-95 max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white p-5 shadow-2xl duration-200 sm:p-6">
            <div className="mb-4 flex items-center gap-3">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-blue-500">
                <EditIcon className="h-6 w-6 text-white" />
              </div>
              <h3 className="text-xl font-bold text-gray-900">
                Edit Nomor Resi
              </h3>
            </div>
            <form onSubmit={handleEditTrackingSubmit}>
              <div className="mb-5">
                <label className="mb-2 block text-sm font-semibold text-gray-700">
                  Nomor Resi Pengiriman <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  value={trackingNumber}
                  onChange={(e) => setTrackingNumber(e.target.value)}
                  placeholder="Contoh: JNE1234567890"
                  className="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                  required
                  autoFocus
                />
              </div>
              <div className="flex gap-3">
                <button
                  type="submit"
                  disabled={isUpdating}
                  className="flex-1 rounded-lg bg-blue-500 px-4 py-3 font-semibold text-white transition hover:bg-blue-600 disabled:opacity-50"
                >
                  {isUpdating ? 'Menyimpan...' : 'Simpan Perubahan'}
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setShowEditTrackingModal(false);
                    setTrackingNumber(order.tracking_number || '');
                  }}
                  className="flex-1 rounded-lg bg-gray-200 px-4 py-3 font-semibold text-gray-700 transition hover:bg-gray-300"
                >
                  Batal
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <tr className="border-t hover:bg-gray-50">
        <td className="px-4 py-3">
          <p className="font-semibold">
            {order.user.first_name} {order.user.last_name}
          </p>
          <p className="text-xs text-gray-500">{order.user.email}</p>
        </td>
        <td className="max-w-xs px-4 py-3 align-top">
          {order.shipping_address ? (
            <>
              {/* Wilayah hasil pembacaan titik peta didahulukan: inilah tujuan
                  yang dipakai menghitung ongkir. Teks di bawahnya adalah detail
                  yang diketik pembeli (temuan V4-01). */}
              {order.shipping_area && (
                <p className="flex items-start gap-1 text-xs font-semibold text-[#53685B]">
                  <LocationIcon className="mt-0.5 h-3 w-3 shrink-0" />
                  {order.shipping_area}
                </p>
              )}
              <p className="mt-1 text-xs whitespace-pre-line text-gray-700">
                {order.shipping_address}
              </p>
              {order.shipping_method && (
                <p className="mt-1 text-xs text-gray-500 capitalize">
                  {order.shipping_method} ·{' '}
                  {formatIDR(order.shipping_cost || 0)}
                  {order.shipping_distance_km != null && (
                    <span className="normal-case">
                      {' '}
                      ·{' '}
                      {Number(order.shipping_distance_km).toLocaleString(
                        'id-ID',
                        { maximumFractionDigits: 1 }
                      )}{' '}
                      km
                    </span>
                  )}
                </p>
              )}
              {order.notes && (
                <p className="mt-1 text-xs text-amber-700 italic">
                  Catatan: {order.notes}
                </p>
              )}
            </>
          ) : (
            <p className="text-xs text-gray-400 italic">
              Alamat belum diisi pembeli
            </p>
          )}
        </td>
        <td className="px-4 py-3">{order.display_item_name}</td>
        <td className="px-4 py-3 text-center font-semibold">
          {order.quantity}
        </td>
        <td className="px-4 py-3 font-bold text-[#53685B]">
          {formatIDR(order.price)}
          {/* Total di atas adalah tagihan pembeli. Yang menjadi hak seller
              lebih kecil: ongkir, biaya berat, dan biaya layanan bukan
              haknya. */}
          <span className="block text-xs font-normal text-gray-500">
            Anda terima: {formatIDR(order.seller_payout_amount ?? order.price)}
          </span>
        </td>
        <td className="px-4 py-3">
          <span
            className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${paymentColors[order.payment_status] || 'bg-gray-100 text-gray-700'}`}
          >
            {order.payment_status || 'unpaid'}
          </span>
        </td>
        <td className="px-4 py-3">
          <select
            value={currentStatus}
            onChange={handleStatusChange}
            disabled={isUpdating}
            className={`rounded-full px-3 py-1 text-xs font-semibold ${statusColors[currentStatus] || 'bg-gray-100 text-gray-700'} ${isUpdating ? 'opacity-50' : ''}`}
          >
            {/* Status sekarang selalu ada sebagai nilai terpilih; sisanya
                hanya perpindahan yang memang sah dari keadaan ini. Daftarnya
                datang dari server (Order::SELLER_STATUS_TRANSITIONS) supaya
                aturannya tidak ditulis dua kali. */}
            <option value={currentStatus}>{currentStatus}</option>
            {(order.allowed_statuses ?? []).map((status) => (
              <option key={status} value={status}>
                {status}
              </option>
            ))}
          </select>
          {order.tracking_number && (
            <div className="mt-2 flex items-center gap-2">
              <p className="text-xs text-gray-600">
                Resi:{' '}
                <span className="font-semibold text-gray-800">
                  {order.tracking_number}
                </span>
              </p>
              <button
                onClick={openEditTracking}
                className="text-blue-600 hover:text-blue-800"
                title="Edit nomor resi"
                aria-label="Edit nomor resi"
              >
                <EditIcon className="h-4 w-4" />
              </button>
            </div>
          )}
        </td>
        <td className="px-4 py-3 text-xs text-gray-600">
          {new Date(order.created_at).toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
          })}
        </td>
        <td className="px-4 py-3 text-center">
          <div className="flex justify-center">
            <ActionMenu
              items={[
                ...(order.can_request_payout
                  ? [
                      {
                        label: 'Ajukan Pencairan',
                        icon: <MoneyIcon />,
                        onClick: () => setShowPayoutModal(true),
                      },
                    ]
                  : []),
                {
                  label: 'Hapus',
                  icon: <DeleteIcon />,
                  variant: 'destructive',
                  onClick: isUpdating ? undefined : handleDelete,
                },
              ]}
            />
          </div>
          {/* Status pengajuan pencairan, supaya seller tahu posisinya tanpa
              harus menggulir ke tabel riwayat di atas. */}
          {order.latest_payout && (
            <div className="mt-2">
              <span
                className={`rounded-full px-2 py-1 text-xs font-semibold capitalize ${payoutStatusColors[order.latest_payout.status] || 'bg-gray-100 text-gray-700'}`}
              >
                Pencairan: {order.latest_payout.status}
              </span>
            </div>
          )}
          {!order.can_request_payout &&
            !order.latest_payout &&
            order.payout_block_reason && (
              <p className="mt-2 text-xs text-gray-400 italic">
                {order.payout_block_reason}
              </p>
            )}
        </td>
      </tr>
    </>
  );
}

SellerDashboard.layout = (page) => (
  <MainLayout title="Seller Dashboard">{page}</MainLayout>
);
