import { Link, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';

export default function UserContacts({ contacts }) {
  const getStatusBadge = (status) => {
    const badges = {
      pending: 'bg-yellow-100 text-yellow-800',
      replied: 'bg-green-100 text-green-800',
      closed: 'bg-gray-100 text-gray-800',
    };
    const labels = {
      pending: 'Menunggu',
      replied: 'Dibalas',
      closed: 'Ditutup',
    };
    return (
      <span className={`rounded-full px-3 py-1 text-xs font-semibold ${badges[status]}`}>
        {labels[status]}
      </span>
    );
  };

  return (
    <div className="mx-auto max-w-6xl px-6 py-8">
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-3xl font-bold text-white">Riwayat Pesan</h1>
        <Link
          href="/contact"
          className="rounded-lg bg-[#5A6E5A] px-5 py-2 text-white transition hover:bg-[#6d7f6d]"
        >
          ✉️ Kirim Pesan Baru
        </Link>
      </div>

      {contacts.length === 0 ? (
        <div className="rounded-lg bg-white p-8 text-center shadow">
          <p className="text-gray-500">Belum ada pesan yang dikirim</p>
          <Link
            href="/contact"
            className="mt-4 inline-block text-[#5A6E5A] hover:underline"
          >
            Kirim pesan pertama Anda
          </Link>
        </div>
      ) : (
        <div className="space-y-4">
          {contacts.map((contact) => (
            <div
              key={contact.id}
              className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md"
            >
              <div className="mb-3 flex items-start justify-between">
                <div>
                  <h3 className="text-lg font-semibold text-[#2F3E46]">
                    {contact.subject}
                  </h3>
                  <p className="text-sm text-gray-500">
                    {new Date(contact.created_at).toLocaleDateString('id-ID', {
                      day: 'numeric',
                      month: 'long',
                      year: 'numeric',
                      hour: '2-digit',
                      minute: '2-digit',
                    })}
                  </p>
                </div>
                {getStatusBadge(contact.status)}
              </div>

              <div className="mb-4 rounded-lg bg-gray-50 p-4">
                <p className="text-sm font-semibold text-gray-700">Pesan Anda:</p>
                <p className="mt-2 text-gray-600">{contact.message}</p>
              </div>

              {contact.admin_reply && (
                <div className="rounded-lg border-l-4 border-green-500 bg-green-50 p-4">
                  <p className="text-sm font-semibold text-green-800">
                    Balasan Admin:
                  </p>
                  <p className="mt-2 text-gray-700">{contact.admin_reply}</p>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

UserContacts.layout = (page) => (
  <MainLayout title="Riwayat Pesan">{page}</MainLayout>
);
