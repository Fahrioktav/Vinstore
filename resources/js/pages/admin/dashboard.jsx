import { Head, Link, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';

export default function AdminDashboard() {
  const {
    totalUsers,
    totalSellers,
    totalProducts,
    totalOrders,
    totalCategories,
    totalMessages,
    totalPendingProducts,
  } = usePage().props;

  const stats = [
    {
      label: 'Total User',
      value: totalUsers,
      icon: '/assets/1.png',
      href: '/admin/users',
      color: 'from-blue-500 to-blue-600',
    },
    {
      label: 'Total Seller',
      value: totalSellers,
      icon: '/assets/2.png',
      href: '/admin/sellers',
      color: 'from-green-500 to-green-600',
    },
    {
      label: 'Total Stock',
      value: totalProducts,
      icon: '/assets/4.png',
      href: '/admin/products',
      color: 'from-orange-500 to-orange-600',
    },
    {
      label: 'Total Order',
      value: totalOrders,
      icon: '/assets/5.png',
      href: '/admin/orders',
      color: 'from-red-500 to-red-600',
    },
    {
      label: 'Category',
      value: totalCategories,
      icon: '/assets/6.png',
      href: '/admin/categories',
      color: 'from-indigo-500 to-indigo-600',
    },
    {
      label: 'Messages',
      value: totalMessages,
      icon: '/assets/icons/social-whatsapp.png',
      href: '/admin/bantuan',
      color: 'from-teal-500 to-teal-600',
    },
    {
      label: 'Menunggu Persetujuan',
      value: totalPendingProducts,
      icon: '/assets/4.png',
      href: '/admin/products/pending',
      color: 'from-amber-500 to-amber-600',
    },
  ];

  return (
    <div className="">
      <Head title="Admin Dashboard" />
      <div className="mx-auto max-w-7xl px-6 py-8">
        <h2 className="mt-2 mb-6 text-3xl font-bold text-[#E9E19E]/90">
          Dashboard Admin
        </h2>

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
                  <img src={stat.icon} className="h-8 w-8" alt={stat.label} />
                </div>
                <div>
                  <p className="text-3xl font-bold text-[#53685B]">
                    {stat.value}
                  </p>
                  <p className="text-sm font-medium text-gray-600">
                    {stat.label}
                  </p>
                </div>
              </div>
              <div className="absolute top-3 right-4 text-4xl text-[#53685B] opacity-20 transition group-hover:opacity-40">
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
