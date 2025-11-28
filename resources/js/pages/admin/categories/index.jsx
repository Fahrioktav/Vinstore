import React from 'react';
import { Link, router } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';

export default function CategoriesIndex({ categories }) {
  const handleDelete = (id, name) => {
    if (confirm(`Apakah Anda yakin ingin menghapus kategori "${name}"?`)) {
      router.delete(`/admin/categories/${id}`, {
        onSuccess: () => {
          alert('Kategori berhasil dihapus!');
        },
      });
    }
  };

  return (
    <MainLayout>
      <div className="container mx-auto px-4 py-8">
        <div className="mb-6 flex items-center justify-between">
          <h1 className="text-3xl font-bold text-[#2F3E46]">
            Manage Categories
          </h1>
          <Link
            href="/admin/categories/create"
            className="rounded-lg bg-[#53685B] px-6 py-2 text-white transition hover:bg-[#2F3E46]"
          >
            + Add Category
          </Link>
        </div>

        <div className="overflow-hidden rounded-lg bg-white shadow-md">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-[#53685B]">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-white uppercase">
                  ID
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-white uppercase">
                  Image
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-white uppercase">
                  Category Name
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-white uppercase">
                  Created At
                </th>
                <th className="px-6 py-3 text-center text-xs font-medium tracking-wider text-white uppercase">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 bg-white">
              {categories.length === 0 ? (
                <tr>
                  <td
                    colSpan="5"
                    className="px-6 py-4 text-center text-gray-500"
                  >
                    Belum ada kategori
                  </td>
                </tr>
              ) : (
                categories.map((category) => (
                  <tr key={category.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 text-sm whitespace-nowrap text-gray-900">
                      {category.id}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {category.image ? (
                        <img
                          src={`/storage/${category.image}`}
                          alt={category.name}
                          className="h-16 w-16 rounded-lg object-cover"
                        />
                      ) : (
                        <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-gradient-to-br from-[#53685B] to-[#B77C4C]">
                          <span className="text-xl font-bold text-white">
                            {category.name.charAt(0).toUpperCase()}
                          </span>
                        </div>
                      )}
                    </td>
                    <td className="px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-900">
                      {category.name}
                    </td>
                    <td className="px-6 py-4 text-sm whitespace-nowrap text-gray-500">
                      {new Date(category.created_at).toLocaleDateString(
                        'id-ID'
                      )}
                    </td>
                    <td className="space-x-2 px-6 py-4 text-center text-sm font-medium whitespace-nowrap">
                      <Link
                        href={`/admin/categories/${category.id}/edit`}
                        className="font-semibold text-[#53685B] hover:text-[#2F3E46]"
                      >
                        Edit
                      </Link>
                      <button
                        onClick={() => handleDelete(category.id, category.name)}
                        className="font-semibold text-red-600 hover:text-red-900"
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </MainLayout>
  );
}
