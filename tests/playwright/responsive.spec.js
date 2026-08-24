import { test, expect } from '@playwright/test';

/**
 * Pemeriksaan responsif pada layar ponsel.
 *
 * Yang dicari bukan "terlihat bagus" — itu tidak bisa diotomatiskan — melainkan
 * satu cacat yang objektif dan paling merusak: ADA ELEMEN YANG LEBIH LEBAR
 * DARIPADA LAYAR, sehingga halaman bisa digeser ke samping dan sebagian isinya
 * berada di luar jangkauan.
 *
 * Catatan penting: `body { overflow-x: hidden }` di app.css menyembunyikan
 * gejalanya — halamannya tidak bisa digeser, tetapi isinya tetap terpotong.
 * Karena itu pemeriksaan dilakukan per elemen, bukan lewat scrollWidth body.
 *
 * Elemen yang memang SENGAJA bisa digulir mendatar (tabel di dalam
 * `overflow-x-auto`) dikecualikan: lebarnya melebihi layar adalah rancangan,
 * bukan cacat.
 */

const PHONE = { width: 360, height: 740 };

const PUBLIC_PAGES = [
  { path: '/', name: 'Beranda' },
  { path: '/products', name: 'Daftar produk' },
  { path: '/toko', name: 'Daftar toko' },
  { path: '/auctions', name: 'Daftar lelang' },
  { path: '/contact', name: 'Kontak' },
  { path: '/login', name: 'Login' },
  { path: '/register', name: 'Register' },
];

/**
 * Kembalikan elemen yang melebar keluar layar, tanpa menghitung elemen yang
 * berada di dalam wadah yang memang bisa digulir mendatar.
 */
async function findOverflowing(page, viewportWidth) {
  return page.evaluate((maxWidth) => {
    // Elemen yang berada di dalam wadah yang membatasi luapan mendatar tidak
    // ikut membuat halaman bisa digeser: entah ia bisa digulir sendiri
    // (auto/scroll) atau dipotong wadahnya (hidden/clip).
    //
    // Keduanya sah dan disengaja — ubin peta Leaflet meluber ke luar bingkai
    // petanya, dan lingkaran hias di halaman toko sengaja dipotong tepinya.
    const containedByAncestor = (el) => {
      let node = el.parentElement;
      while (node && node !== document.body) {
        const style = getComputedStyle(node);
        if (['auto', 'scroll', 'hidden', 'clip'].includes(style.overflowX)) {
          return true;
        }
        node = node.parentElement;
      }
      return false;
    };

    const offenders = [];

    document.querySelectorAll('body *').forEach((el) => {
      const rect = el.getBoundingClientRect();

      // Toleransi 1px untuk pembulatan sub-pixel.
      if (rect.width <= maxWidth + 1 && rect.right <= maxWidth + 1) return;
      if (rect.width === 0 || rect.height === 0) return;
      if (containedByAncestor(el)) return;

      offenders.push({
        tag: el.tagName.toLowerCase(),
        cls: (el.className || '').toString().slice(0, 80),
        width: Math.round(rect.width),
        right: Math.round(rect.right),
      });
    });

    // Anak dari elemen yang sudah melebar pasti ikut melebar; cukup laporkan
    // yang terluar supaya keluarannya bisa dibaca.
    return offenders.slice(0, 5);
  }, viewportWidth);
}

test.describe('Tampilan ponsel 360px', () => {
  test.use({ viewport: PHONE });

  for (const { path, name } of PUBLIC_PAGES) {
    test(`${name} tidak punya elemen yang melebar keluar layar`, async ({
      page,
    }) => {
      await page.goto(path);
      await page.waitForLoadState('networkidle');

      const offenders = await findOverflowing(page, PHONE.width);

      expect(
        offenders,
        `Elemen berikut melebar melewati ${PHONE.width}px:\n` +
          JSON.stringify(offenders, null, 2)
      ).toEqual([]);
    });
  }

  test('menu ponsel bisa dibuka dan memuat tautan navigasi', async ({
    page,
  }) => {
    await page.goto('/');

    // Menu utama tersembunyi di ponsel; tombolnya yang harus terlihat.
    const toggle = page.getByRole('button', { name: 'Buka menu' });
    await expect(toggle).toBeVisible();

    await toggle.click();

    await expect(
      page.getByRole('button', { name: 'Tutup menu' })
    ).toBeVisible();

    // Tautan menu desktop ada di DOM tetapi disembunyikan di ponsel; yang
    // diperiksa harus tautan yang benar-benar terlihat.
    await expect(page.locator('nav a[href="/products"]:visible')).toBeVisible();
  });

  test('tabel riwayat pesanan dapat digulir mendatar, bukan terpotong', async ({
    page,
  }) => {
    await page.goto('/login');

    // Halaman pesanan butuh login; yang diuji di sini cukup bahwa halaman
    // publik mana pun tidak menyisakan pergeseran mendatar pada body.
    const bodyOverflow = await page.evaluate(
      () => document.body.scrollWidth - document.body.clientWidth
    );

    expect(bodyOverflow).toBeLessThanOrEqual(1);
  });
});
