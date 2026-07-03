import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import MainLayout from '@/layouts/main-layout';
import echo from '@/echo';
import { cn } from '@/lib/utils';

export default function SupportChat() {
  const { messages: initialMessages, user } = usePage().props;

  const [messages, setMessages] = useState(initialMessages ?? []);
  const [body, setBody] = useState('');
  const [sending, setSending] = useState(false);
  const [error, setError] = useState(null);
  const endRef = useRef(null);

  // Terima balasan admin secara real-time.
  useEffect(() => {
    if (!user?.public_id) return undefined;

    const channelName = `support.${user.public_id}`;
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
  }, [user?.public_id]);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    const trimmed = body.trim();
    if (!trimmed || sending) return;

    setSending(true);
    setError(null);

    try {
      const { data } = await axios.post(
        '/bantuan/messages',
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
    <section className="px-6 py-10 md:px-16">
      <div className="mx-auto flex min-h-[640px] max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
        <div className="border-b border-gray-200 p-5">
          <h1 className="text-2xl font-bold text-[#53685B]">Bantuan</h1>
          <p className="text-sm text-gray-500">
            Kirim pertanyaan atau kendala Anda. Admin akan membalas di sini.
          </p>
        </div>

        <div className="flex-1 space-y-4 overflow-y-auto bg-gray-50 p-5">
          {messages.length > 0 ? (
            messages.map((message, index) => {
              const mine = message.sender?.public_id === user.public_id;
              return (
                <div
                  key={`${message.sender?.public_id}-${message.created_at}-${index}`}
                  className={cn('flex', mine ? 'justify-end' : 'justify-start')}
                >
                  <div
                    className={cn(
                      'max-w-[75%] rounded-2xl px-4 py-3 text-sm shadow-sm',
                      mine ? 'bg-[#53685B] text-white' : 'bg-white text-gray-700'
                    )}
                  >
                    {!mine && (
                      <p className="mb-1 text-xs font-semibold text-[#B77C4C]">
                        Admin
                      </p>
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
              Belum ada pesan. Mulai percakapan dengan admin sekarang.
            </p>
          )}
          <div ref={endRef} />
        </div>

        <form onSubmit={handleSubmit} className="border-t border-gray-200 p-4">
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
              placeholder="Tulis pesan untuk admin..."
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
      </div>
    </section>
  );
}

SupportChat.layout = (page) => <MainLayout title="Bantuan">{page}</MainLayout>;

function formatTime(value) {
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}
