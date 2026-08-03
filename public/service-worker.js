const CACHE_NAME = 'vinstore-v2';

// HANYA aset statis yang boleh di-cache.
//
// Sebelumnya service worker menyimpan SEMUA respons 200, termasuk halaman
// Inertia yang memuat data pribadi (riwayat pesanan, invoice, profil,
// dashboard admin) di dalam atribut data-page. Cache itu tidak per-user dan
// tidak dibersihkan saat logout, sehingga di perangkat bersama data pengguna
// sebelumnya masih bisa tersaji dalam kondisi offline.
const urlsToCache = [
  '/manifest.json',
  '/favicon.ico',
];

// Awalan path yang aman untuk di-cache: aset build Vite, ikon, dan gambar.
const CACHEABLE_PATH_PREFIXES = [
  '/build/',
  '/icons/',
  '/assets/',
];

const CACHEABLE_FILE_PATTERN = /\.(css|js|mjs|woff2?|ttf|eot|png|jpe?g|gif|svg|webp|ico)$/i;

/**
 * Boleh di-cache hanya jika: satu origin dengan aplikasi, bukan navigasi
 * dokumen (halaman HTML selalu bisa berisi data pribadi), dan merupakan aset
 * statis berdasarkan path atau ekstensinya.
 */
function isCacheableRequest(request) {
  if (request.method !== 'GET') return false;
  if (request.mode === 'navigate') return false;

  let url;
  try {
    url = new URL(request.url);
  } catch (e) {
    return false;
  }

  if (url.origin !== self.location.origin) return false;

  // Permintaan data Inertia/XHR tidak pernah boleh masuk cache.
  if (request.headers.get('X-Inertia')) return false;
  if (request.destination === 'document') return false;

  if (urlsToCache.includes(url.pathname)) return true;
  if (CACHEABLE_PATH_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) return true;

  return CACHEABLE_FILE_PATTERN.test(url.pathname);
}

// Install Service Worker
self.addEventListener('install', (event) => {
  console.log('[Service Worker] Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[Service Worker] Caching app shell');
        return cache.addAll(urlsToCache);
      })
      .then(() => self.skipWaiting())
  );
});

// Activate Service Worker
self.addEventListener('activate', (event) => {
  console.log('[Service Worker] Activating...');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('[Service Worker] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Strategy: Network First, fallback to Cache — khusus aset statis.
self.addEventListener('fetch', (event) => {
  // Skip non-GET requests
  if (event.request.method !== 'GET') return;

  // Skip chrome extensions and other schemas
  if (!event.request.url.startsWith('http')) return;

  // Navigasi halaman dan permintaan data dibiarkan lewat apa adanya, tanpa
  // pernah disimpan maupun disajikan dari cache. Halaman Vinstore memuat data
  // pribadi, jadi menyajikannya dari cache bisa membocorkan data pengguna
  // sebelumnya di perangkat yang sama.
  if (!isCacheableRequest(event.request)) return;

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        // Hanya cache respons yang benar-benar milik origin ini dan sukses.
        if (response.status === 200 && response.type === 'basic') {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }

        return response;
      })
      .catch(() => {
        // Network gagal: sajikan aset dari cache bila ada.
        return caches.match(event.request).then((response) => {
          if (response) {
            return response;
          }

          return new Response('Offline - Content not available', {
            status: 503,
            statusText: 'Service Unavailable',
            headers: new Headers({
              'Content-Type': 'text/plain'
            })
          });
        });
      })
  );
});

// Bersihkan cache saat user logout, dipicu dari aplikasi lewat postMessage.
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'CLEAR_CACHE') {
    event.waitUntil(
      caches.keys().then((names) => Promise.all(names.map((name) => caches.delete(name))))
    );
  }
});

// Background Sync (untuk order yang pending)
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-orders') {
    event.waitUntil(syncOrders());
  }
});

async function syncOrders() {
  // Implementasi sync orders jika diperlukan
  console.log('[Service Worker] Syncing orders...');
}

// Push Notifications
self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};
  const title = data.title || 'Vinstore Notification';
  const options = {
    body: data.body || 'Anda memiliki notifikasi baru',
    icon: '/icons/icon-192x192.png',
    badge: '/icons/icon-72x72.png',
    vibrate: [200, 100, 200],
    data: {
      url: data.url || '/'
    }
  };

  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

// Notification Click
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  event.waitUntil(
    clients.openWindow(event.notification.data.url || '/')
  );
});
