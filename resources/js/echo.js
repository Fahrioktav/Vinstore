import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

window.Pusher = Pusher;

const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';

const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
  wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
  forceTLS: scheme === 'https',
  enabledTransports: ['ws', 'wss'],
  // Gunakan axios agar cookie sesi + CSRF (X-XSRF-TOKEN) terkirim
  // saat melakukan otorisasi private channel ke /broadcasting/auth.
  authorizer: (channel) => ({
    authorize: (socketId, callback) => {
      axios
        .post('/broadcasting/auth', {
          socket_id: socketId,
          channel_name: channel.name,
        })
        .then((response) => callback(null, response.data))
        .catch((error) => callback(error, null));
    },
  }),
});

window.Echo = echo;

export default echo;
