/**
 * Helper pemanggilan Midtrans Snap.
 *
 * Snap.js dimuat lewat tag <script> di app.blade.php. Skrip itu bisa saja
 * belum selesai diunduh saat komponen React pertama kali dirender — terutama
 * pada koneksi lambat, atau saat skripnya diblokir extension browser.
 *
 * Kode sebelumnya memeriksa `window.snap` satu kali lalu diam saja bila belum
 * tersedia, sehingga popup pembayaran kadang muncul kadang tidak tanpa pesan
 * error apa pun. Helper ini menunggu sampai Snap benar-benar siap, dan
 * melaporkan kegagalan bila memang tidak pernah termuat.
 */

const READY_TIMEOUT_MS = 15000;
const POLL_INTERVAL_MS = 100;

/**
 * Menunggu sampai window.snap tersedia.
 *
 * @returns {Promise<object>} instance Snap
 */
export function waitForSnap(timeout = READY_TIMEOUT_MS) {
  if (window.snap) {
    return Promise.resolve(window.snap);
  }

  return new Promise((resolve, reject) => {
    const startedAt = Date.now();

    const timer = setInterval(() => {
      if (window.snap) {
        clearInterval(timer);
        resolve(window.snap);
        return;
      }

      if (Date.now() - startedAt >= timeout) {
        clearInterval(timer);
        reject(
          new Error(
            'Layanan pembayaran Midtrans gagal dimuat. Periksa koneksi Anda lalu muat ulang halaman.'
          )
        );
      }
    }, POLL_INTERVAL_MS);
  });
}

/**
 * Buka popup pembayaran Snap setelah memastikan librarynya siap.
 *
 * @param {string} token snap_token dari backend
 * @param {object} callbacks onSuccess / onPending / onError / onClose
 * @returns {Promise<void>}
 */
export async function openSnapPayment(token, callbacks = {}) {
  const snap = await waitForSnap();

  snap.pay(token, callbacks);
}
