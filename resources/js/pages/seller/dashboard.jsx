import { Link, useForm, usePage, router } from '@inertiajs/react';
import React, { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR, getProductCertificate, getProductImage } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { BadgeIcon } from '@/components/icons';
import ActionMenu from '@/components/ui/action-menu';
import {
  AreaChart,
  Area,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
} from 'recharts';

export default function SellerDashboard() {
  const {
    products,
    orders,
    productCount,
    orderCount,
    totalIncome,
    monthlyIncome,
    monthlyIncomeData,
    productIncome,
    auctions,
    store,
    withdrawals,
    pendingWithdrawalAmount,
  } = usePage().props;
  const [selectedProducts, setSelectedProducts] = React.useState([]);
  const withdrawalForm = useForm({
    amount: '',
    bank_name: '',
    account_number: '',
    account_holder: '',
  });
  const withdrawableBalance =
    Number(store?.available_balance || 0) - Number(pendingWithdrawalAmount || 0);

  const submitWithdrawal = (e) => {
    e.preventDefault();
    withdrawalForm.post('/seller/withdrawals', {
      preserveScroll: true,
      onSuccess: () => withdrawalForm.reset(),
    });
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
      <div className="mx-auto max-w-7xl px-6 py-8">
        {/* Header Stats */}
        <div className="mb-8 grid gap-6 md:grid-cols-4">
          <div className="rounded-2xl bg-gradient-to-br from-[#53685B] to-[#3c4a3e] p-6 text-white shadow-lg">
            <h3 className="mb-2 text-sm font-semibold opacity-90">
              Total Produk
            </h3>
            <p className="text-4xl font-bold">{productCount}</p>
          </div>
          <div className="rounded-2xl bg-gradient-to-br from-[#B77C4C] to-[#8d5e39] p-6 text-white shadow-lg">
            <h3 className="mb-2 text-sm font-semibold opacity-90">
              Total Order
            </h3>
            <p className="text-4xl font-bold">{orderCount}</p>
          </div>
          <div className="rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 p-6 text-white shadow-lg">
            <h3 className="mb-2 text-sm font-semibold opacity-90">
              💰 Total Pendapatan
            </h3>
            <p className="text-2xl font-bold">{formatIDR(totalIncome || 0)}</p>
          </div>
          <div className="rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 p-6 text-white shadow-lg">
            <h3 className="mb-2 text-sm font-semibold opacity-90">
              📊 Pendapatan Bulan Ini
            </h3>
            <p className="text-2xl font-bold">
              {formatIDR(monthlyIncome || 0)}
            </p>
          </div>
        </div>

        {/* Income Charts */}
        <div className="mb-8 grid gap-6 md:grid-cols-2">
          {/* Monthly Income Chart */}
          <div className="rounded-2xl bg-white p-6 shadow-md">
            <h2 className="mb-6 text-xl font-bold text-[#53685B]">
              Grafik Pendapatan Bulanan
            </h2>
            <ResponsiveContainer width="100%" height={300}>
              <AreaChart data={monthlyIncomeData}>
                <defs>
                  <linearGradient id="colorIncome" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#10b981" stopOpacity={0.8} />
                    <stop offset="95%" stopColor="#10b981" stopOpacity={0.1} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis
                  dataKey="month"
                  style={{ fontSize: '12px' }}
                  angle={-15}
                  textAnchor="end"
                  height={60}
                />
                <YAxis
                  style={{ fontSize: '12px' }}
                  tickFormatter={(value) =>
                    `Rp ${(value / 1000000).toFixed(1)}M`
                  }
                />
                <Tooltip
                  formatter={(value) => formatIDR(value)}
                  contentStyle={{
                    backgroundColor: '#fff',
                    border: '1px solid #ccc',
                    borderRadius: '8px',
                  }}
                />
                <Area
                  type="monotone"
                  dataKey="income"
                  stroke="#10b981"
                  fillOpacity={1}
                  fill="url(#colorIncome)"
                  name="Pendapatan"
                />
              </AreaChart>
            </ResponsiveContainer>
          </div>

          {/* Top Products Income */}
          <div className="rounded-2xl bg-white p-6 shadow-md">
            <h2 className="mb-6 text-xl font-bold text-[#53685B]">
              Top 5 Produk Terlaris
            </h2>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={productIncome}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis
                  dataKey="name"
                  style={{ fontSize: '11px' }}
                  angle={-15}
                  textAnchor="end"
                  height={80}
                  interval={0}
                />
                <YAxis
                  style={{ fontSize: '12px' }}
                  tickFormatter={(value) =>
                    `Rp ${(value / 1000000).toFixed(1)}M`
                  }
                />
                <Tooltip
                  formatter={(value) => formatIDR(value)}
                  contentStyle={{
                    backgroundColor: '#fff',
                    border: '1px solid #ccc',
                    borderRadius: '8px',
                  }}
                />
                <Bar dataKey="income" fill="#B77C4C" name="Pendapatan" />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Seller Balance */}
        <div className="mb-8 grid gap-6 lg:grid-cols-[1fr_1.4fr]">
          <div className="rounded-2xl bg-white p-6 shadow-md">
            <h2 className="mb-4 text-xl font-bold text-[#53685B]">
              Saldo Seller
            </h2>
            <div className="space-y-4">
              <BalanceLine
                label="Saldo tersedia"
                value={formatIDR(store?.available_balance || 0)}
              />
              <BalanceLine
                label="Menunggu pencairan"
                value={formatIDR(pendingWithdrawalAmount || 0)}
              />
              <BalanceLine
                label="Bisa dicairkan"
                value={formatIDR(Math.max(withdrawableBalance, 0))}
              />
              <BalanceLine
                label="Total sudah dicairkan"
                value={formatIDR(store?.withdrawn_balance || 0)}
              />
            </div>
          </div>

          <div className="rounded-2xl bg-white p-6 shadow-md">
            <h2 className="mb-4 text-xl font-bold text-[#53685B]">
              Ajukan Pencairan
            </h2>
            <form onSubmit={submitWithdrawal} className="grid gap-4 md:grid-cols-2">
              <InputField
                label="Nominal"
                type="number"
                value={withdrawalForm.data.amount}
                onChange={(value) => withdrawalForm.setData('amount', value)}
                error={withdrawalForm.errors.amount}
              />
              <InputField
                label="Nama Bank"
                value={withdrawalForm.data.bank_name}
                onChange={(value) => withdrawalForm.setData('bank_name', value)}
                error={withdrawalForm.errors.bank_name}
              />
              <InputField
                label="Nomor Rekening"
                value={withdrawalForm.data.account_number}
                onChange={(value) => withdrawalForm.setData('account_number', value)}
                error={withdrawalForm.errors.account_number}
              />
              <InputField
                label="Nama Pemilik Rekening"
                value={withdrawalForm.data.account_holder}
                onChange={(value) => withdrawalForm.setData('account_holder', value)}
                error={withdrawalForm.errors.account_holder}
              />
              <div className="md:col-span-2">
                <button
                  type="submit"
                  disabled={withdrawalForm.processing || withdrawableBalance <= 0}
                  className="rounded-lg bg-[#53685B] px-6 py-3 font-semibold text-white transition hover:bg-[#3c4a3e] disabled:opacity-50"
                >
                  Ajukan Pencairan
                </button>
              </div>
            </form>

            <div className="mt-6 overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-100">
                  <tr>
                    <th className="px-3 py-2 text-left">Nominal</th>
                    <th className="px-3 py-2 text-left">Bank</th>
                    <th className="px-3 py-2 text-left">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {withdrawals?.length > 0 ? (
                    withdrawals.map((withdrawal) => (
                      <tr key={withdrawal.public_id} className="border-t">
                        <td className="px-3 py-2 font-semibold">
                          {formatIDR(withdrawal.amount)}
                        </td>
                        <td className="px-3 py-2">
                          {withdrawal.bank_name} - {withdrawal.account_number}
                        </td>
                        <td className="px-3 py-2 capitalize">{withdrawal.status}</td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan="3" className="px-3 py-4 text-center text-gray-500">
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
            📦 Customer Orders
          </h2>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="px-4 py-3 text-left">Customer</th>
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
                    <OrderRow key={order.public_id} order={order} />
                  ))
                ) : (
                  <tr>
                    <td colSpan="8" className="py-8 text-center text-gray-500">
                      📭 Belum ada pesanan
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
              🛍️ Daftar Barang
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
                      📦 Belum ada produk
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Auctions Section */}
        <div className="rounded-2xl bg-white p-6 shadow-md">
          <div className="mb-6 flex items-center justify-between">
            <h2 className="text-2xl font-bold text-[#53685B]">
              Daftar Barang Lelang
            </h2>
            <Link
              href="/seller/auctions/create"
              className="rounded-lg bg-[#B77C4C] px-6 py-2 font-semibold text-white transition hover:bg-[#8d5e39]"
            >
              + Add Lelang
            </Link>
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
                    <tr key={auction.public_id} className="border-t hover:bg-gray-50">
                      <td className="px-4 py-3 font-semibold">{auction.name}</td>
                      <td className="px-4 py-3">{formatIDR(auction.starting_price)}</td>
                      <td className="px-4 py-3 font-semibold text-[#53685B]">
                        {formatIDR(auction.current_price)}
                      </td>
                      <td className="px-4 py-3">{auction.bids_count}</td>
                      <td className="px-4 py-3 capitalize">{auction.approval_status}</td>
                      <td className="px-4 py-3 capitalize">{auction.status}</td>
                      <td className="px-4 py-3">
                        {auction.winner?.username || '-'}
                      </td>
                      <td className="px-4 py-3 text-center">
                        <div className="flex justify-center">
                          <ActionMenu
                            items={[
                              {
                                label: 'Detail',
                                icon: '🔍',
                                href: `/auctions/${auction.public_id}`,
                              },
                              auction.bids_count === 0 &&
                                ['pending', 'scheduled'].includes(auction.status) &&
                                ['pending', 'approved', 'rejected'].includes(
                                  auction.approval_status
                                ) && {
                                  label: 'Edit',
                                  icon: '✏️',
                                  href: `/seller/auctions/${auction.public_id}/edit`,
                                },
                              auction.bids_count === 0 &&
                                auction.status === 'ended' && {
                                  label: 'Ajukan Ulang',
                                  icon: '🔁',
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
      {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
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

  const handleDelete = () => {
    if (confirm('Yakin ingin menghapus produk ini?')) {
      destroy(`/seller/products/${product.public_id}`, {
        preserveScroll: true,
      });
    }
  };

  const approvalColors = {
    approved: 'bg-green-100 text-green-700',
    pending_validator: 'bg-yellow-100 text-yellow-700',
    pending_admin: 'bg-blue-100 text-blue-700',
    pending: 'bg-yellow-100 text-yellow-700',
    rejected: 'bg-red-100 text-red-700',
  };

  const approvalLabels = {
    approved: 'Disetujui',
    pending_validator: 'Menunggu Validator',
    pending_admin: 'Menunggu Admin',
    pending: 'Menunggu',
    rejected: 'Ditolak',
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
            📷
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
          {approvalLabels[product.approval_status] || product.approval_status || 'Menunggu'}
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
                icon: '✏️',
                href: `/seller/products/${product.public_id}/edit`,
              },
              {
                label: 'Hapus',
                icon: '🗑️',
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

function OrderRow({ order }) {
  const [currentStatus, setCurrentStatus] = useState(order.status);
  const [isUpdating, setIsUpdating] = useState(false);
  const [showTrackingModal, setShowTrackingModal] = useState(false);
  const [showEditTrackingModal, setShowEditTrackingModal] = useState(false);
  const [pendingStatus, setPendingStatus] = useState(null);
  const [trackingNumber, setTrackingNumber] = useState(order.tracking_number || '');

  const handleStatusChange = (e) => {
    const newStatus = e.target.value;
    
    // Jika status diubah ke Processing atau On The Way, pastikan ada nomor resi
    if ((newStatus === 'Processing' || newStatus === 'On The Way') && !order.tracking_number) {
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
      alert('Nomor resi harus diisi!');
      return;
    }
    updateStatus(pendingStatus, trackingNumber);
    setShowTrackingModal(false);
  };

  const handleEditTrackingSubmit = (e) => {
    e.preventDefault();
    if (!trackingNumber.trim()) {
      alert('Nomor resi harus diisi!');
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
        tracking_number: tracking
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
          alert('Gagal mengupdate status: ' + (errors.tracking_number || 'Terjadi kesalahan'));
        },
      }
    );
  };

  const handleDelete = () => {
    if (confirm('Yakin ingin menghapus order ini?')) {
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
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-sm p-4">
          <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-200">
            <div className="mb-4 flex items-center gap-3">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-[#53685B] text-2xl">
                📦
              </div>
              <div>
                <h3 className="text-xl font-bold text-gray-900">
                  Masukkan Nomor Resi
                </h3>
                <p className="text-sm text-gray-600">
                  Diperlukan untuk memproses pesanan
                </p>
              </div>
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
                  className="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-[#53685B] focus:outline-none focus:ring-2 focus:ring-[#53685B]"
                  required
                  autoFocus
                />
                <p className="mt-1 text-xs text-gray-500">
                  Masukkan nomor resi dari ekspedisi pengiriman
                </p>
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

      {/* Modal Edit Nomor Resi */}
      {showEditTrackingModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-sm p-4">
          <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-200">
            <div className="mb-4 flex items-center gap-3">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-blue-500 text-2xl">
                ✏️
              </div>
              <div>
                <h3 className="text-xl font-bold text-gray-900">
                  Edit Nomor Resi
                </h3>
                <p className="text-sm text-gray-600">
                  Perbarui nomor resi pengiriman
                </p>
              </div>
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
                  className="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
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
        <td className="px-4 py-3">
          {order.product?.name || order.auction?.name || '-'}
        </td>
        <td className="px-4 py-3 text-center font-semibold">{order.quantity}</td>
        <td className="px-4 py-3 font-bold text-[#53685B]">
          {formatIDR(order.price)}
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
            <option value="Waiting">⏳ Waiting</option>
            <option value="Processing">🔄 Processing</option>
            <option value="On The Way">🚚 On The Way</option>
            <option value="Delivered">✅ Delivered</option>
            <option value="Cancelled">❌ Cancelled</option>
          </select>
          {order.tracking_number && (
            <div className="mt-2 flex items-center gap-2">
              <p className="text-xs text-gray-600">
                Resi: <span className="font-semibold text-gray-800">{order.tracking_number}</span>
              </p>
              <button
                onClick={openEditTracking}
                className="text-xs text-blue-600 hover:text-blue-800"
                title="Edit nomor resi"
              >
                ✏️
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
              {
                label: 'Hapus',
                icon: '🗑️',
                variant: 'destructive',
                onClick: isUpdating ? undefined : handleDelete,
              },
            ]}
          />
        </div>
      </td>
    </tr>
    </>
  );
}

SellerDashboard.layout = (page) => (
  <MainLayout title="Seller Dashboard">{page}</MainLayout>
);
