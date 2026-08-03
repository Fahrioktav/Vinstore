import { useEffect, useRef, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import axios from 'axios';
import MainLayout from '@/layouts/main-layout';
import echo from '@/echo';
import { cn } from '@/lib/utils';

export default function AdminSupport() {
  const {
    threads,
    selectedOwner,
    messages: initialMessages,
    user,
  } = usePage().props;

  const [messages, setMessages] = useState(initialMessages ?? []);
  const [body, setBody] = useState('');
  const [sending, setSending] = useState(false);
  const [error, setError] = useState(null);
  const endRef = useRef(null);

  const ownerPublicId = selectedOwner?.public_id;

  useEffect(() => {
    setMessages(initialMessages ?? []);
  }, [ownerPublicId]);

  // Terima pesan baru dari user/seller pada thread yang sedang dibuka.
  useEffect(() => {
    if (!ownerPublicId) return undefined;

    const channelName = `support.${ownerPublicId}`;
    const channel = echo.private(channelName);

    channel.listen('.support.message.sent', (event) => {
      const incoming = event.message;
      setMessages((prev) => {
        const exists = prev.some(
          (m) =>
            m.created_at === incoming.created_at &&
            m.body === incoming.body &&
            m.sender?.public_id === incoming.sender?.public_id
        );
        return exists ? prev : [...prev, incoming];
      });
    });

    return () => {
      echo.leave(channelName);
    };
  }, [ownerPublicId]);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    const trimmed = body.trim();
    if (!trimmed || sending || !ownerPublicId) return;

    setSending(true);
    setError(null);

    try {
      const { data } = await axios.post(
        `/admin/bantuan/${ownerPublicId}/messages`,
        { body: trimmed },
        {
          headers: {
            Accept: 'application/json',
            'X-Socket-ID': echo.socketId(),
          },
        }
      );
      setMessages((prev) => [...prev, data.message]);
      setBody('');
    } catch (err) {
      setError(
        err?.response?.data?.message ||
          err?.response?.data?.errors?.body?.[0] ||
          'Gagal mengirim pesan. Coba lagi.'
      );
    } finally {
      setSending(false);
    }
  };

  return (
    <section className="px-6 py-8 md:px-16">
      <h1 className="mb-4 text-3xl font-bold text-white">Bantuan</h1>
      <div className="grid overflow-hidden rounded-2xl bg-white shadow-xl md:grid-cols-[320px_1fr]">
        <aside className="border-r border-gray-200">
          <div className="border-b border-gray-200 p-5">
            <h2 className="text-lg font-bold text-[#53685B]">Percakapan</h2>
          </div>
          <div className="max-h-[640px] overflow-y-auto">
            {threads.length > 0 ? (
              threads.map((thread) => {
                const isActive = ownerPublicId === thread.public_id;
                return (
                  <Link
                    href={`/admin/bantuan/${thread.public_id}`}
                    key={thread.public_id}
                    preserveScroll
                    className={cn(
                      'block border-b border-gray-100 p-4 transition hover:bg-gray-50',
                      isActive && 'bg-[#53685B]/10'
                    )}
                  >
                    <div className="flex items-center justify-between">
                      <p className="font-semibold text-[#2F3E46]">
                        {thread.username}
                      </p>
                      {thread.unread > 0 && (
                        <span className="rounded-full bg-red-500 px-2 py-0.5 text-xs font-semibold text-white">
                          {thread.unread}
                        </span>
                      )}
                    </div>
                    <p className="text-xs text-gray-400 uppercase">
                      {thread.role}
                    </p>
                    <p className="mt-1 line-clamp-1 text-sm text-gray-500">
                      {thread.last_message || 'Belum ada pesan'}
                    </p>
                  </Link>
                );
              })
            ) : (
              <p className="p-5 text-sm text-gray-500">
                Belum ada percakapan bantuan.
              </p>
            )}
          </div>
        </aside>

        <main className="flex min-h-[640px] flex-col">
          {selectedOwner ? (
            <>
              <div className="border-b border-gray-200 p-5">
                <h2 className="text-xl font-bold text-[#53685B]">
                  {selectedOwner.username}
                </h2>
                <p className="text-sm text-gray-500">
                  {selectedOwner.email} · {selectedOwner.role}
                </p>
              </div>

              <div className="flex-1 space-y-4 overflow-y-auto bg-gray-50 p-5">
                {messages.length > 0 ? (
                  messages.map((message, index) => {
                    const mine = message.sender?.public_id === user.public_id;
                    return (
                      <div
                        key={`${message.sender?.public_id}-${message.created_at}-${index}`}
                        className={cn(
                          'flex',
                          mine ? 'justify-end' : 'justify-start'
                        )}
                      >
                        <div
                          className={cn(
                            'max-w-[75%] rounded-2xl px-4 py-3 text-sm shadow-sm',
                            mine
                              ? 'bg-[#53685B] text-white'
                              : 'bg-white text-gray-700'
                          )}
                        >
                          <p className="whitespace-pre-line">{message.body}</p>
                          <p
                            className={cn(
                              'mt-2 text-xs',
                              mine ? 'text-white/70' : 'text-gray-400'
                            )}
                          >
                            {formatTime(message.created_at)}
                          </p>
                        </div>
                      </div>
                    );
                  })
                ) : (
                  <p className="text-center text-sm text-gray-500">
                    Belum ada pesan pada percakapan ini.
                  </p>
                )}
                <div ref={endRef} />
              </div>

              <form
                onSubmit={handleSubmit}
                className="border-t border-gray-200 p-4"
              >
                <div className="flex gap-3">
                  <textarea
                    rows="2"
                    value={body}
                    onChange={(e) => setBody(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        handleSubmit(e);
                      }
                    }}
                    className="min-h-12 flex-1 rounded-lg border border-gray-300 px-4 py-3 focus:border-[#53685B] focus:ring-2 focus:ring-[#53685B]"
                    placeholder="Tulis balasan..."
                    required
                  />
                  <button
                    type="submit"
                    disabled={sending}
                    className="rounded-lg bg-[#53685B] px-6 font-semibold text-white hover:bg-[#3c4a3e] disabled:opacity-50"
                  >
                    Kirim
                  </button>
                </div>
                {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
              </form>
            </>
          ) : (
            <div className="flex flex-1 items-center justify-center p-8 text-center text-gray-500">
              Pilih percakapan di samping untuk mulai membalas.
            </div>
          )}
        </main>
      </div>
    </section>
  );
}

AdminSupport.layout = (page) => <MainLayout title="Bantuan">{page}</MainLayout>;

function formatTime(value) {
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}
