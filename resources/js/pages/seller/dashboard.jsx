import { Link, useForm, usePage, router } from '@inertiajs/react';
import React, { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR, getProductCertificate, getProductImage } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { BadgeIcon } from '@/components/icons';

export default function SellerDashboard() {
  const { products, orders, productCount, orderCount } = usePage().props;
  const [selectedProducts, setSelectedProducts] = React.useState([]);

  const handleSelectProduct = (productId) => {
    setSelectedProducts((prev) =>
      prev.includes(productId)
        ? prev.filter((id) => id !== productId)
        : [...prev, productId]
    );
  };

  const handleSelectAll = (e) => {
    if (e.target.checked) {
      setSelectedProducts(products.map((p) => p.id));
    } else {
      setSelectedProducts([]);
    }
  };

  return (
    <>
      <div className="mx-auto max-w-7xl px-6 py-8">
        {/* Header Stats */}
        <div className="mb-8 grid gap-6 md:grid-cols-2">
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
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-left">Date</th>
                  <th className="px-4 py-3 text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                {orders.length > 0 ? (
                  orders.map((order) => (
                    <OrderRow key={order.id} order={order} />
                  ))
                ) : (
                  <tr>
                    <td colSpan="7" className="py-8 text-center text-gray-500">
                      📭 Belum ada pesanan
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Products Section */}
        <div className="rounded-2xl bg-white p-6 shadow-md">
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
                      key={product.id}
                      product={product}
                      isSelected={selectedProducts.includes(product.id)}
                      onSelect={handleSelectProduct}
                    />
                  ))
                ) : (
                  <tr>
                    <td colSpan="8" className="py-8 text-center text-gray-500">
                      📦 Belum ada produk
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
    patch(`/seller/products/${product.id}`, {
      preserveScroll: true,
    });
  };

  const handleDelete = () => {
    if (confirm('Yakin ingin menghapus produk ini?')) {
      destroy(`/seller/products/${product.id}`, {
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
          onChange={() => onSelect(product.id)}
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
        <form
          onSubmit={handleStockUpdate}
          className="flex items-center justify-center gap-2"
        >
          <input
            type="number"
            value={data.stock}
            onChange={(e) => setData('stock', e.target.value)}
            min="0"
            className="w-16 rounded border border-gray-300 px-2 py-1 text-center text-sm"
            disabled={processing}
          />
          <Button
            variant="ghost"
            size="icon-sm"
            type="submit"
            disabled={processing}
            className="text-green-600 hover:text-green-800"
          >
            ✓
          </Button>
        </form>
      </td>
      <td className="px-4 py-3 font-semibold text-[#53685B]">
        {formatIDR(product.price)}
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
        <div className="flex items-center justify-center gap-2">
          <Button variant="ghost" size="icon-sm" asChild>
            <Link
              href={`/seller/products/${product.id}/edit`}
              className="text-blue-600 transition hover:text-blue-800"
            >
              ✏️
            </Link>
          </Button>
          <Button
            variant="ghost"
            size="icon-sm"
            onClick={handleDelete}
            className="text-red-600 transition hover:text-red-800"
            disabled={processing}
          >
            🗑️
          </Button>
        </div>
      </td>
    </tr>
  );
}

function OrderRow({ order }) {
  const [currentStatus, setCurrentStatus] = useState(order.status);
  const [isUpdating, setIsUpdating] = useState(false);

  const handleStatusChange = (e) => {
    const newStatus = e.target.value;
    setCurrentStatus(newStatus); // Update UI immediately
    setIsUpdating(true);

    // Post dengan router.post
    router.post(
      `/seller/orders/${order.id}/status`,
      { status: newStatus },
      {
        preserveScroll: true,
        onSuccess: () => {
          setIsUpdating(false);
          console.log('Status updated successfully');
        },
        onError: (errors) => {
          // Revert jika error
          setCurrentStatus(order.status);
          setIsUpdating(false);
          console.error('Failed to update status:', errors);
        },
      }
    );
  };

  const handleDelete = () => {
    if (confirm('Yakin ingin menghapus order ini?')) {
      router.delete(`/seller/orders/${order.id}`, {
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

  return (
    <tr className="border-t hover:bg-gray-50">
      <td className="px-4 py-3">
        <p className="font-semibold">
          {order.user.first_name} {order.user.last_name}
        </p>
        <p className="text-xs text-gray-500">{order.user.email}</p>
      </td>
      <td className="px-4 py-3">{order.product.name}</td>
      <td className="px-4 py-3 text-center font-semibold">{order.quantity}</td>
      <td className="px-4 py-3 font-bold text-[#53685B]">
        {formatIDR(order.price)}
      </td>
      <td className="px-4 py-3">
        <select
          value={currentStatus}
          onChange={handleStatusChange}
          disabled={isUpdating}
          className={`rounded-full px-3 py-1 text-xs font-semibold ${statusColors[currentStatus] || 'bg-gray-100 text-gray-700'}`}
        >
          <option value="Waiting">⏳ Waiting</option>
          <option value="Processing">🔄 Processing</option>
          <option value="On The Way">🚚 On The Way</option>
          <option value="Delivered">✅ Delivered</option>
          <option value="Cancelled">❌ Cancelled</option>
        </select>
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
        <Button
          variant="ghost"
          size="icon-sm"
          onClick={handleDelete}
          className="text-red-600 transition hover:text-red-800"
          disabled={isUpdating}
        >
          🗑️
        </Button>
      </td>
    </tr>
  );
}

SellerDashboard.layout = (page) => (
  <MainLayout title="Seller Dashboard">{page}</MainLayout>
);
