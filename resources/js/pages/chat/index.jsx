import { useEffect, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import MainLayout from '@/layouts/main-layout';
import echo from '@/echo';
import { cn, getProductImage, getStoreImage, getUserImage } from '@/lib/utils';

/**
 * Halaman chat pembeli-penjual, dipakai oleh kedua sisi.
 *
 * `mode` menentukan siapa lawan bicaranya: 'buyer' berarti pengguna sedang
 * berbicara dengan toko, 'seller' berarti ia pemilik toko yang membalas
 * pembeli. Selebihnya identik.
 */
export default function ChatPage() {
  const {
    mode,
    threads,
    selected,
    messages: initialMessages,
    pendingContext,
    user,
  } = usePage().props;

  const [messages, setMessages] = useState(initialMessages ?? []);
  const [body, setBody] = useState('');
  const [context, setContext] = useState(pendingContext ?? null);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState(null);
  const endRef = useRef(null);

  // Berpindah utas mengganti seluruh isi percakapan. Tanpa penyetelan ulang
  // ini, pesan utas sebelumnya ikut terbawa karena state-nya tidak pernah
  // dibuang oleh Inertia — komponennya sama, hanya propsnya yang berganti.
  useEffect(() => {
    setMessages(initialMessages ?? []);
    setContext(pendingContext ?? null);
    setBody('');
    setError(null);
  }, [selected?.public_id, initialMessages, pendingContext]);

  useEffect(() => {
    if (!selected?.public_id) return undefined;

    const channelName = `conversation.${selected.public_id}`;
    const channel = echo.private(channelName);

    channel.listen('.chat.message.sent', (event) => {
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
  }, [selected?.public_id]);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    const trimmed = body.trim();
    if (!trimmed || sending || !selected?.public_id) return;

    setSending(true);
    setError(null);

    try {
      const { data } = await axios.post(
        `/chat/${selected.public_id}/messages`,
        { body: trimmed, ...(context?.param ?? {}) },
        {
          headers: {
            Accept: 'application/json',
            'X-Socket-ID': echo.socketId(),
          },
        }
      );
      setMessages((prev) => [...prev, data.message]);
      setBody('');
      // Konteks hanya menempel pada pesan PERTAMA. Mengulanginya di tiap pesan
      // susulan hanya membuat kartu yang sama tercetak berkali-kali.
      setContext(null);
      // Utas yang baru menerima pesan pertamanya belum ada di daftar kiri.
      if (!threads.some((t) => t.public_id === selected.public_id)) {
        router.reload({ only: ['threads'] });
      }
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

  const judulKotak = mode === 'seller' ? 'Chat Pembeli' : 'Chat Penjual';
  const kosongPesan =
    mode === 'seller'
      ? 'Belum ada pembeli yang menghubungi toko Anda.'
      : 'Belum ada percakapan. Mulai dari halaman produk atau pesanan Anda.';

  return (
    <section className="px-4 py-8 sm:px-6 sm:py-10 md:px-16">
      <div className="mx-auto grid min-h-[640px] max-w-6xl grid-cols-1 gap-4 md:grid-cols-[300px_1fr]">
        {/* Daftar percakapan */}
        <aside className="flex max-h-[640px] flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
          <div className="border-b border-gray-200 p-5">
            <h1 className="text-xl font-bold text-[#53685B]">{judulKotak}</h1>
          </div>
          <div className="flex-1 overflow-y-auto">
            {threads.length > 0 ? (
              threads.map((thread) => (
                <Link
                  key={thread.public_id}
                  href={`/chat/${thread.public_id}`}
                  preserveScroll
                  className={cn(
                    'flex items-center gap-3 border-b border-gray-100 p-4 transition',
                    thread.public_id === selected?.public_id
                      ? 'bg-[#E9E19E]/40'
                      : 'hover:bg-[#E9E19E]/20'
                  )}
                >
                  <img
                    src={
                      mode === 'seller'
                        ? getUserImage({
                            photo: thread.photo,
                            // Dipakai membangun avatar berinisial saat pembeli
                            // belum mengunggah foto; tanpa ini namanya
                            // terbaca "undefined".
                            username: thread.title,
                          })
                        : getStoreImage({ photo: thread.photo })
                    }
                    alt={thread.title}
                    className="h-10 w-10 shrink-0 rounded-full object-cover"
                  />
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center justify-between gap-2">
                      <p className="truncate text-sm font-semibold text-[#2F3E46]">
                        {thread.title}
                      </p>
                      {thread.unread > 0 && (
                        <span className="shrink-0 rounded-full bg-[#B77C4C] px-2 py-0.5 text-xs font-semibold text-white">
                          {thread.unread}
                        </span>
                      )}
                    </div>
                    <p className="truncate text-xs text-gray-500">
                      {thread.last_message || 'Belum ada pesan'}
                    </p>
                  </div>
                </Link>
              ))
            ) : (
              <p className="p-5 text-center text-sm text-gray-500">
                {kosongPesan}
              </p>
            )}
          </div>
        </aside>

        {/* Isi percakapan */}
        <div className="flex max-h-[640px] flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
          {selected ? (
            <>
              <div className="flex items-center justify-between gap-3 border-b border-gray-200 p-5">
                <div className="min-w-0">
                  <h2 className="truncate text-lg font-bold text-[#2F3E46]">
                    {selected.title}
                  </h2>
                  {selected.subtitle && (
                    <p className="truncate text-sm text-gray-500">
                      {selected.subtitle}
                    </p>
                  )}
                </div>
                {selected.store_url && (
                  <Link
                    href={selected.store_url}
                    className="shrink-0 text-sm font-semibold text-[#B77C4C] hover:underline"
                  >
                    Lihat Toko
                  </Link>
                )}
              </div>

              <div className="flex-1 space-y-4 overflow-y-auto bg-gray-50 p-5">
                {messages.length > 0 ? (
                  messages.map((message, index) => {
                    const mine = message.sender?.public_id === user?.public_id;
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
                          {message.context && (
                            <ContextCard
                              context={message.context}
                              mine={mine}
                            />
                          )}
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
                    Belum ada pesan di percakapan ini.
                  </p>
                )}
                <div ref={endRef} />
              </div>

              <form
                onSubmit={handleSubmit}
                className="border-t border-gray-200 p-4"
              >
                {context && (
                  <div className="mb-3 flex items-center gap-3 rounded-lg border border-[#E9E19E] bg-[#E9E19E]/20 p-3">
                    {context.image && (
                      <img
                        src={getProductImage({ image: context.image })}
                        alt={context.name}
                        className="h-10 w-10 shrink-0 rounded object-cover"
                      />
                    )}
                    <div className="min-w-0 flex-1">
                      <p className="text-xs font-semibold text-[#B77C4C]">
                        {context.label}
                      </p>
                      <p className="truncate text-sm text-[#2F3E46]">
                        {context.name}
                        {context.reference ? ` · ${context.reference}` : ''}
                      </p>
                    </div>
                    <button
                      type="button"
                      onClick={() => setContext(null)}
                      className="shrink-0 text-xs font-semibold text-gray-500 hover:text-gray-700"
                    >
                      Hapus
                    </button>
                  </div>
                )}
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
                    maxLength={2000}
                    className="min-h-12 flex-1 rounded-lg border border-gray-300 px-4 py-3 focus:border-[#53685B] focus:ring-2 focus:ring-[#53685B]"
                    placeholder={
                      mode === 'seller'
                        ? 'Balas pembeli...'
                        : 'Tulis pesan untuk penjual...'
                    }
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
            <div className="flex flex-1 items-center justify-center p-8 text-center text-sm text-gray-500">
              {kosongPesan}
            </div>
          )}
        </div>
      </div>
    </section>
  );
}

/** Kartu barang/pesanan yang menempel di atas sebuah pesan. */
function ContextCard({ context, mine }) {
  const isi = (
    <div
      className={cn(
        'mb-2 flex items-center gap-2 rounded-lg p-2',
        mine ? 'bg-white/15' : 'bg-gray-100'
      )}
    >
      {context.image && (
        <img
          src={getProductImage({ image: context.image })}
          alt={context.name}
          className="h-9 w-9 shrink-0 rounded object-cover"
        />
      )}
      <div className="min-w-0">
        <p
          className={cn(
            'text-xs font-semibold',
            mine ? 'text-white/80' : 'text-[#B77C4C]'
          )}
        >
          {context.label}
        </p>
        <p className="truncate text-xs">
          {context.name}
          {context.reference ? ` · ${context.reference}` : ''}
        </p>
      </div>
    </div>
  );

  return context.url ? (
    <Link href={context.url} className="block">
      {isi}
    </Link>
  ) : (
    isi
  );
}

ChatPage.layout = (page) => <MainLayout title="Chat">{page}</MainLayout>;

function formatTime(value) {
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}
