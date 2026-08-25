import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import ActionMenu from '@/components/ui/action-menu';
import {
  BlockedIcon,
  EditIcon,
  EmptyStateIcon,
  LockIcon,
  SuccessIcon,
} from '@/components/icons';
import { confirmDialog, promptDialog } from '@/lib/dialog';

export default function AdminUsers() {
  const { users, success, flash } = usePage().props;

  // Akun tidak pernah dihapus, hanya dinonaktifkan — menghapusnya ikut
  // memusnahkan seluruh riwayat pesanannya (temuan V4-12).
  const toggleActive = async (user) => {
    if (user.deactivated_at) {
      const ok = await confirmDialog({
        title: `Aktifkan kembali ${user.username}?`,
        description: 'Akun ini bisa masuk dan berbelanja lagi seperti biasa.',
        confirmLabel: 'Ya, aktifkan',
      });

      if (ok) {
        router.delete(`/admin/${users}/${user.public_id}`, {
          preserveScroll: true,
        });
      }

      return;
    }

    const reason = await promptDialog({
      title: `Nonaktifkan ${user.username}?`,
      description:
        'Akun tidak bisa masuk lagi dan sesinya langsung diputus. Seluruh riwayat pesanannya tetap tersimpan dan dapat diaktifkan kembali kapan saja.',
      label: 'Alasan penonaktifan',
      placeholder: 'Contoh: melanggar ketentuan, permintaan pengguna...',
      confirmLabel: 'Nonaktifkan akun',
      variant: 'destructive',
    });

    if (reason === null) return;

    router.delete(`/admin/${users}/${user.public_id}`, {
      data: { reason },
      preserveScroll: true,
    });
  };

  // Pemulihan password lewat surel belum hidup (MAIL_MAILER masih 'log').
  // Pengguna yang terkunci di luar melapor lewat halaman Kontak, lalu admin
  // membuatkan tautan sekali pakai ini dan menyampaikannya kembali — lewat
  // balasan kontak, telepon, atau apa pun yang identitasnya sudah dipastikan
  // (temuan V11-03).
  const buatTautanReset = async (user) => {
    const ok = await confirmDialog({
      title: `Buat tautan reset password untuk ${user.username}?`,
      description:
        'Pastikan lebih dulu bahwa yang meminta memang pemilik akun ini. Tautannya sekali pakai dan hanya ditampilkan satu kali di halaman ini.',
      confirmLabel: 'Buat tautan',
    });

    if (!ok) return;

    router.post(
      `/admin/users/${user.public_id}/reset-link`,
      {},
      { preserveScroll: true }
    );
  };

  return (
    <>
      <Head title="Kelola User" />
      <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        {success && (
          <div className="mb-6 rounded-lg border-l-4 border-green-500 bg-green-50 px-4 py-3 text-green-700 shadow-sm">
            <p className="flex items-center gap-2 font-semibold">
              <SuccessIcon className="h-5 w-5 shrink-0" />
              {success}
            </p>
          </div>
        )}

        {flash?.passwordResetLink && (
          <div className="mb-6 rounded-lg border-l-4 border-amber-500 bg-amber-50 px-4 py-3 shadow-sm">
            <p className="font-semibold text-amber-900">
              Tautan reset password untuk {flash.passwordResetFor}
            </p>
            <p className="mt-1 text-xs text-amber-800">
              Salin dan sampaikan kepada pemilik akun. Tautan ini sekali pakai,
              punya masa berlaku, dan tidak akan ditampilkan lagi setelah
              halaman ini dimuat ulang.
            </p>
            <code className="mt-2 block overflow-x-auto rounded bg-white px-3 py-2 text-xs break-all text-gray-800">
              {flash.passwordResetLink}
            </code>
          </div>
        )}

        <div className="rounded-2xl bg-white p-6 shadow-md shadow-[#53685B]/20">
          <h2 className="mb-6 text-2xl font-bold text-[#53685B]">
            Kelola Data User
          </h2>
          <div className="overflow-x-auto">
            <table className="w-full border border-gray-200 text-sm">
              <thead className="bg-[#53685B] text-white">
                <tr>
                  <th className="px-4 py-3 text-left">ID</th>
                  <th className="px-4 py-3 text-left">Username</th>
                  <th className="px-4 py-3 text-left">Email</th>
                  <th className="px-4 py-3 text-left">Role</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {users.length > 0 ? (
                  users.map((user) => (
                    <tr
                      key={user.public_id}
                      className="border-t hover:bg-gray-50"
                    >
                      <td className="px-4 py-3 font-semibold">
                        {user.public_id}
                      </td>
                      <td className="px-4 py-3">{user.username}</td>
                      <td className="px-4 py-3 text-sm text-gray-600">
                        {user.email}
                      </td>
                      <td className="px-4 py-3">
                        <span className="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                          {user.role}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        {user.deactivated_at ? (
                          <span
                            className="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700"
                            title={user.deactivation_reason || undefined}
                          >
                            Nonaktif
                          </span>
                        ) : (
                          <span className="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">
                            Aktif
                          </span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex justify-center">
                          <ActionMenu
                            items={[
                              {
                                label: 'Edit',
                                icon: <EditIcon />,
                                href: `/admin/users/${user.public_id}/edit`,
                              },
                              {
                                label: 'Tautan Reset Password',
                                icon: <LockIcon />,
                                onClick: () => buatTautanReset(user),
                              },
                              user.deactivated_at
                                ? {
                                    label: 'Aktifkan',
                                    icon: <SuccessIcon />,
                                    onClick: () => toggleActive(user),
                                  }
                                : {
                                    label: 'Nonaktifkan',
                                    icon: <BlockedIcon />,
                                    variant: 'destructive',
                                    onClick: () => toggleActive(user),
                                  },
                            ]}
                          />
                        </div>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td
                      colSpan="6"
                      className="px-4 py-8 text-center text-gray-500"
                    >
                      <div className="flex flex-col items-center gap-2">
                        <EmptyStateIcon className="h-10 w-10 text-gray-300" />
                        <p className="text-lg">Belum ada user</p>
                      </div>
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

AdminUsers.layout = (page) => (
  <MainLayout title="Kelola User">{page}</MainLayout>
);
