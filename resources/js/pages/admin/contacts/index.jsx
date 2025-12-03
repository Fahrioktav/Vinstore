import { Link, router, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { useState } from 'react';

export default function AdminContacts({ contacts }) {
  const { flash } = usePage().props;
  const [selectedContact, setSelectedContact] = useState(null);
  const [replyText, setReplyText] = useState('');
  const [isReplying, setIsReplying] = useState(false);

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

  const handleReply = (contactId) => {
    if (!replyText.trim()) {
      alert('Balasan tidak boleh kosong!');
      return;
    }

    setIsReplying(true);
    router.post(
      `/admin/contacts/${contactId}/reply`,
      { admin_reply: replyText },
      {
        preserveScroll: true,
        onSuccess: () => {
          setReplyText('');
          setSelectedContact(null);
        },
        onFinish: () => setIsReplying(false),
      }
    );
  };

  const handleStatusChange = (contactId, newStatus) => {
    router.post(
      `/admin/contacts/${contactId}/status`,
      { status: newStatus },
      { preserveScroll: true }
    );
  };

  const handleDelete = (contactId) => {
    if (confirm('Yakin ingin menghapus pesan ini?')) {
      router.delete(`/admin/contacts/${contactId}`);
    }
  };

  return (
    <div className="mx-auto max-w-7xl px-6 py-8">
      <div className="mb-6">
        <h1 className="text-3xl font-bold text-white">Kelola Pesan Kontak</h1>
        <p className="mt-2 text-white">
          Kelola semua pesan dari user dan seller
        </p>
      </div>

      {flash?.success && (
        <div className="mb-4 rounded-lg bg-green-100 border border-green-400 px-4 py-3 text-green-700">
          {flash.success}
        </div>
      )}

      {contacts.length === 0 ? (
        <div className="rounded-lg bg-white p-8 text-center shadow">
          <p className="text-gray-500">Belum ada pesan masuk</p>
        </div>
      ) : (
        <div className="overflow-hidden rounded-lg bg-white shadow">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-[#2F3E46]">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-white">
                  Pengirim
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-white">
                  Subjek
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-white">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-white">
                  Tanggal
                </th>
                <th className="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-white">
                  Aksi
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 bg-white">
              {contacts.map((contact) => (
                <>
                  <tr key={contact.id} className="hover:bg-gray-50">
                    <td className="whitespace-nowrap px-6 py-4">
                      <div>
                        <p className="font-medium text-gray-900">{contact.name}</p>
                        <p className="text-sm text-gray-500">{contact.email}</p>
                        {contact.user && (
                          <span className="text-xs text-gray-400">
                            Role: {contact.user.role}
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <p className="font-medium text-gray-900">{contact.subject}</p>
                      <p className="mt-1 text-sm text-gray-500 line-clamp-2">
                        {contact.message}
                      </p>
                    </td>
                    <td className="whitespace-nowrap px-6 py-4">
                      {getStatusBadge(contact.status)}
                    </td>
                    <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                      {new Date(contact.created_at).toLocaleDateString('id-ID', {
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric',
                      })}
                    </td>
                    <td className="whitespace-nowrap px-6 py-4 text-center">
                      <button
                        onClick={() =>
                          setSelectedContact(
                            selectedContact === contact.id ? null : contact.id
                          )
                        }
                        className="mr-2 rounded bg-blue-500 px-3 py-1 text-xs text-white hover:bg-blue-600"
                      >
                        {selectedContact === contact.id ? 'Tutup' : 'Detail'}
                      </button>
                      <select
                        value={contact.status}
                        onChange={(e) =>
                          handleStatusChange(contact.id, e.target.value)
                        }
                        className="mr-2 rounded border px-2 py-1 text-xs"
                      >
                        <option value="pending">Menunggu</option>
                        <option value="replied">Dibalas</option>
                        <option value="closed">Tutup</option>
                      </select>
                      <button
                        onClick={() => handleDelete(contact.id)}
                        className="rounded bg-red-500 px-3 py-1 text-xs text-white hover:bg-red-600"
                      >
                        Hapus
                      </button>
                    </td>
                  </tr>
                  {selectedContact === contact.id && (
                    <tr>
                      <td colSpan="5" className="bg-gray-50 px-6 py-4">
                        <div className="space-y-4">
                          <div className="rounded-lg bg-white p-4">
                            <p className="font-semibold text-gray-700">Pesan Lengkap:</p>
                            <p className="mt-2 text-gray-600">{contact.message}</p>
                          </div>

                          {contact.admin_reply ? (
                            <div className="rounded-lg border-l-4 border-green-500 bg-green-50 p-4">
                              <p className="font-semibold text-green-800">
                                Balasan Anda:
                              </p>
                              <p className="mt-2 text-gray-700">{contact.admin_reply}</p>
                            </div>
                          ) : (
                            <div className="rounded-lg bg-white p-4">
                              <p className="mb-2 font-semibold text-gray-700">
                                Balas Pesan:
                              </p>
                              <textarea
                                value={replyText}
                                onChange={(e) => setReplyText(e.target.value)}
                                rows="4"
                                className="w-full rounded-lg border px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#5A6E5A]"
                                placeholder="Tulis balasan untuk user..."
                              />
                              <button
                                onClick={() => handleReply(contact.id)}
                                disabled={isReplying}
                                className="mt-2 rounded-lg bg-[#5A6E5A] px-4 py-2 text-white hover:bg-[#6d7f6d] disabled:opacity-50"
                              >
                                {isReplying ? 'Mengirim...' : 'Kirim Balasan'}
                              </button>
                            </div>
                          )}
                        </div>
                      </td>
                    </tr>
                  )}
                </>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

AdminContacts.layout = (page) => (
  <MainLayout title="Kelola Pesan Kontak">{page}</MainLayout>
);
