import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';

export default function CategoriesCreate() {
  const [imagePreview, setImagePreview] = React.useState(null);
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    image: null,
  });

  const handleImageChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      setData('image', file);
      const reader = new FileReader();
      reader.onloadend = () => {
        setImagePreview(reader.result);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    post('/admin/categories', {
      forceFormData: true,
      onSuccess: () => {
        alert('Kategori berhasil ditambahkan!');
      },
    });
  };

  return (
    <MainLayout>
      <div className="container mx-auto px-4 py-8">
        <div className="mx-auto max-w-2xl">
          <div className="mb-6 flex items-center">
            <Link
              href="/admin/categories"
              className="mr-4 text-[#53685B] hover:text-[#2F3E46]"
            >
              ← Back
            </Link>
            <h1 className="text-3xl font-bold text-[#2F3E46]">
              Add New Category
            </h1>
          </div>

          <div className="rounded-lg bg-white p-6 shadow-md">
            <form onSubmit={handleSubmit}>
              <div className="mb-6">
                <label className="mb-2 block font-semibold text-gray-700">
                  Category Name *
                </label>
                <input
                  type="text"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:ring-2 focus:ring-[#53685B]"
                  placeholder="Enter category name"
                  required
                />
                {errors.name && (
                  <p className="mt-1 text-sm text-red-500">{errors.name}</p>
                )}
              </div>

              <div className="mb-6">
                <label className="mb-2 block font-semibold text-gray-700">
                  Category Image
                </label>
                <input
                  type="file"
                  accept="image/*"
                  onChange={handleImageChange}
                  className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:ring-2 focus:ring-[#53685B]"
                />
                {errors.image && (
                  <p className="mt-1 text-sm text-red-500">{errors.image}</p>
                )}
                {imagePreview && (
                  <div className="mt-4">
                    <img
                      src={imagePreview}
                      alt="Preview"
                      className="h-32 w-32 rounded-lg border-2 border-gray-300 object-cover"
                    />
                  </div>
                )}
              </div>

              <div className="flex justify-end space-x-4">
                <Link
                  href="/admin/categories"
                  className="rounded-lg border border-gray-300 px-6 py-2 text-gray-700 hover:bg-gray-50"
                >
                  Cancel
                </Link>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-lg bg-[#53685B] px-6 py-2 text-white transition hover:bg-[#2F3E46] disabled:opacity-50"
                >
                  {processing ? 'Saving...' : 'Save Category'}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}
