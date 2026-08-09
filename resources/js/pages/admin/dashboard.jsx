import { Head, Link, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR } from '@/lib/utils';
import {
  AuctionIcon,
  CategoryIcon,
  ChatIcon,
  MoneyIcon,
  OrderIcon,
  PendingIcon,
  StockIcon,
  StoreIcon,
  UsersIcon,
} from '@/components/icons';

export default function AdminDashboard() {
  const {
    totalUsers,
    totalSellers,
    totalProducts,
    totalOrders,
    totalCategories,
    totalMessages,
    totalPendingProducts,
    totalPendingAuctions,
    platformBalance,
  } = usePage().props;

  // Ikon berupa komponen, bukan berkas PNG: warnanya ikut kelas Tailwind,
  // tajam di layar kepadatan tinggi, dan tidak menambah permintaan jaringan.
  const stats = [
    {
      label: 'Total User',
      value: totalUsers,
      icon: UsersIcon,
      href: '/admin/users',
    },
    {
      label: 'Total Seller',
      value: totalSellers,
      icon: StoreIcon,
      href: '/admin/sellers',
    },
    {
      label: 'Total Stock',
      value: totalProducts,
      icon: StockIcon,
      href: '/admin/products',
    },
    {
      label: 'Total Order',
      value: totalOrders,
      icon: OrderIcon,
      href: '/admin/orders',
    },
    {
      label: 'Category',
      value: totalCategories,
      icon: CategoryIcon,
      href: '/admin/categories',
    },
    {
      label: 'Messages',
      value: totalMessages,
      icon: ChatIcon,
      href: '/admin/bantuan',
    },
    {
      label: 'Produk Menunggu',
      value: totalPendingProducts,
      icon: PendingIcon,
      href: '/admin/products/pending',
    },
    {
      label: 'Lelang Menunggu',
      value: totalPendingAuctions,
      icon: AuctionIcon,
      href: '/admin/auctions',
    },
  ];

  return (
    <div className="">
      <Head title="Admin Dashboard" />
      <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        <h2 className="mt-2 mb-6 text-xl font-bold text-[#E9E19E]/90 sm:text-3xl">
          Dashboard Admin
        </h2>

        {/* Dompet admin: pemasukan marketplace dari biaya layanan */}
        <Link
          href="/admin/pendapatan"
          className="mb-8 block rounded-2xl bg-gradient-to-br from-[#53685B] to-[#3c4a3e] p-6 shadow-lg transition hover:brightness-110"
        >
          <p className="text-sm font-medium text-[#E9E19E]/80">
            <MoneyIcon className="mr-2 inline h-6 w-6 align-text-bottom" />
            Dompet Admin (biaya layanan)
          </p>
          <p className="mt-1 text-2xl font-bold text-white sm:text-4xl">
            {formatIDR(platformBalance ?? 0)}
          </p>
          <p className="mt-2 text-xs text-white/70">
            Akumulasi biaya layanan dari pesanan lunas, dikurangi pembalikan
            atas refund. Klik untuk melihat rinciannya.
          </p>
        </Link>

        {/* Stats Grid */}
        <div className="mb-12 grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
          {stats.map((stat, index) => (
            <Link
              key={index}
              href={stat.href}
              className="group relative block rounded-2xl border border-gray-100 bg-gradient-to-br from-white to-gray-50 p-6 shadow-lg shadow-[#53685B]/10 transition-all duration-300 hover:scale-105 hover:shadow-xl"
            >
              <div className="flex items-center gap-4">
                <div className="rounded-xl bg-[#53685B]/10 p-3 transition group-hover:bg-[#53685B]/20">
                  <stat.icon className="h-8 w-8 text-[#53685B]" />
                </div>
                <div>
                  <p className="text-xl font-bold text-[#53685B] sm:text-3xl">
                    {stat.value}
                  </p>
                  <p className="text-sm font-medium text-gray-600">
                    {stat.label}
                  </p>
                </div>
              </div>
              <div className="absolute top-3 right-4 text-2xl text-[#53685B] opacity-20 transition group-hover:opacity-40 sm:text-4xl">
                →
              </div>
            </Link>
          ))}
        </div>
      </div>
    </div>
  );
}

AdminDashboard.layout = (page) => (
  <MainLayout title="Admin Dashboard">{page}</MainLayout>
);
