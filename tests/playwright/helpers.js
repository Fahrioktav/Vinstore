import { expect } from '@playwright/test';

/**
 * Kredensial akun uji.
 *
 * Akun-akun ini sudah ada di database pengembangan dan TIDAK dibuat ulang oleh
 * pengujian. Bila password di database diganti, cukup ubah di sini — tidak ada
 * seeder yang menimpanya.
 *
 * Dapat ditimpa lewat environment variable bila suatu saat dijalankan terhadap
 * database lain, mis. PW_ADMIN_PASSWORD=xxx npm run test:e2e
 */
export const AKUN = {
  seller1: {
    login: 'seller@example.com',
    password: env('PW_SELLER_PASSWORD', 'password123'),
    role: 'seller',
  },
  sellerA: {
    login: 'sellera',
    password: env('PW_SELLER_PASSWORD', 'password123'),
    role: 'seller',
  },
  sellerB: {
    login: 'sellerb',
    password: env('PW_SELLER_PASSWORD', 'password123'),
    role: 'seller',
  },
  pembeli1: {
    login: 'pembeli@example.com',
    password: env('PW_BUYER_PASSWORD', 'password123'),
    role: 'user',
  },
  pembeli2: {
    login: 'pembeli2@example.com',
    password: env('PW_BUYER_PASSWORD', 'password123'),
    role: 'user',
  },
  asep: {
    login: 'asep',
    password: env('PW_ASEP_PASSWORD', 'asep123'),
    role: 'user',
  },
  validator: {
    login: 'validator',
    password: env('PW_VALIDATOR_PASSWORD', 'password123'),
    role: 'validator',
  },
  admin: {
    login: 'admin',
    password: env('PW_ADMIN_PASSWORD', 'admin123'),
    role: 'admin',
  },
};

/** Halaman tujuan setelah login, ditentukan oleh role (lihat LoginController). */
const BERANDA_ROLE = {
  user: /localhost:\d+\/$/,
  seller: /\/seller\/dashboard/,
  validator: /\/validator\/dashboard/,
  admin: /\/admin\/dashboard/,
};

function env(name, fallback) {
  return process.env[name] || fallback;
}

/**
 * Login lewat form, lalu pastikan benar-benar sampai di beranda role-nya.
 *
 * Menerima kunci dari AKUN ('seller1') maupun objek kredensial mentah. Bila
 * masih tertahan di /login, pesan errornya ikut dilaporkan supaya kegagalan
 * kredensial tidak menyamar sebagai timeout.
 */
export async function loginAs(page, akun) {
  const kredensial = typeof akun === 'string' ? AKUN[akun] : akun;

  if (!kredensial) {
    throw new Error(
      `Akun uji "${akun}" tidak dikenal. Lihat AKUN di helpers.js`
    );
  }

  await page.goto('/login');
  await page.fill('input[name="login"]', kredensial.login);
  await page.fill('input[name="password"]', kredensial.password);
  await page.getByRole('button', { name: 'Masuk Sekarang' }).click();

  const tujuan = BERANDA_ROLE[kredensial.role];

  try {
    await expect(page).toHaveURL(tujuan, { timeout: 20000 });
  } catch (e) {
    const pesan = await page
      .locator('p.text-red-500')
      .first()
      .textContent()
      .catch(() => null);

    throw new Error(
      `Login "${kredensial.login}" gagal. URL sekarang: ${page.url()}` +
        (pesan ? `\nPesan dari aplikasi: ${pesan.trim()}` : '')
    );
  }
}

/**
 * Logout lewat dropdown user di navbar. `username` dipakai untuk menemukan
 * tombol dropdown-nya, karena tombol itu menampilkan username pengguna.
 */
export async function logout(page, username) {
  await page.goto('/');
  await page
    .locator('nav button')
    .filter({ hasText: username })
    .first()
    .click();
  await page.getByRole('menuitem', { name: 'Logout' }).click();
  await expect(page.locator('nav')).toContainText(/Login|Masuk/i, {
    timeout: 15000,
  });
}

/**
 * Nama unik untuk data yang dibuat pengujian.
 *
 * Semua entitas yang dibuat E2E memakai penanda ini supaya (a) tidak bentrok
 * dengan data yang sudah ada di database pengembangan, dan (b) mudah dikenali
 * bila perlu dibersihkan manual.
 */
export function namaUji(prefix) {
  return `${prefix} E2E-${Date.now()}-${Math.floor(Math.random() * 1000)}`;
}

/**
 * Format datetime-local (YYYY-MM-DDTHH:mm) untuk input tanggal.
 *
 * Dibulatkan ke ATAS ke menit penuh. Input datetime-local tidak menyimpan
 * detik, jadi pembulatan ke bawah bisa menghasilkan waktu yang sudah lewat
 * saat form akhirnya disubmit — dan validasi `after_or_equal:now` di
 * AuctionController akan menolaknya.
 */
export function waktuLokal(offsetMenit = 0) {
  const t = new Date(
    Math.ceil((Date.now() + offsetMenit * 60000) / 60000) * 60000
  );
  const pad = (n) => String(n).padStart(2, '0');

  return (
    `${t.getFullYear()}-${pad(t.getMonth() + 1)}-${pad(t.getDate())}` +
    `T${pad(t.getHours())}:${pad(t.getMinutes())}`
  );
}

/* ===================== Aksi yang dipakai banyak spec ===================== */

/**
 * Buat produk baru sebagai seller yang sedang login.
 * Mengembalikan nama produk supaya pemanggil bisa mencarinya kembali.
 */
export async function buatProduk(page, opsi = {}) {
  const {
    nama = namaUji('Produk'),
    harga = '1500000',
    stok = '3',
    kategori = 'Koin',
    deskripsi = 'Produk uji otomatis Playwright.',
    tukarTambah = false,
    tipe = 'normal',
    hargaDiskon = null,
    tebakMulai = null,
    tebakSelesai = null,
  } = opsi;

  await page.goto('/seller/products/create');
  await page.selectOption('select[name="sale_type"]', tipe);
  await page.fill('input[name="name"]', nama);
  await page.fill('input[name="stock"]', String(stok));
  await page.fill('input[name="price"]', String(harga));
  await page.selectOption('select[name="category"]', kategori);
  await page.fill('textarea[name="description"]', deskripsi);
  await page.setInputFiles('input[name="image"]', 'tests/fixtures/koin.jpg');

  if (tipe === 'tebak_harga') {
    await page.fill(
      'input[name="guess_discount_price"]',
      String(hargaDiskon ?? Math.floor(Number(harga) * 0.8))
    );
    await page.fill(
      'input[name="guess_starts_at"]',
      tebakMulai ?? waktuLokal(2)
    );
    await page.fill(
      'input[name="guess_ends_at"]',
      tebakSelesai ?? waktuLokal(60 * 24)
    );
  }

  if (tukarTambah) {
    await page.check('input[name="is_trade_in_enabled"]');
  }

  await page.getByRole('button', { name: 'Simpan Produk' }).click();
  await expect(page).toHaveURL(/\/seller\/dashboard/, { timeout: 30000 });

  return nama;
}

/**
 * Buat lelang baru sebagai seller yang sedang login.
 *
 * Lelang diajukan lewat form Tambah Produk dengan memilih jenis penjualan
 * "Lelang" — tidak ada lagi form pengajuan lelang tersendiri.
 *
 * `starts_at` divalidasi terhadap AWAL MENIT BERJALAN, jadi menit yang sedang
 * berjalan pun diterima. Nilai default di sini tetap menit berikutnya supaya
 * lelangnya sempat melewati status `scheduled` — itulah yang ditunggu
 * `tungguLelangAktif()`.
 */
export async function buatLelang(page, opsi = {}) {
  const {
    nama = namaUji('Lelang'),
    hargaAwal = '10000000',
    kelipatan = '500000',
    berat = '3000',
    deskripsi = 'Lelang uji otomatis Playwright.',
    mulai = waktuLokal(2),
    selesai = waktuLokal(60 * 24),
  } = opsi;

  await page.goto('/seller/products/create');
  await page.selectOption('select[name="sale_type"]', 'lelang');
  await page.fill('input[name="name"]', nama);
  await page.fill('textarea[name="description"]', deskripsi);
  // Berat wajib sejak pesanan lelang ikut menagih ongkir.
  await page.fill('input[name="weight"]', String(berat));
  await page.fill('input[name="starting_price"]', String(hargaAwal));
  await page.fill('input[name="min_increment"]', String(kelipatan));
  await page.fill('input[name="starts_at"]', mulai);
  await page.fill('input[name="ends_at"]', selesai);
  await page.setInputFiles('input[name="image"]', 'tests/fixtures/guci.jpg');
  await page.getByRole('button', { name: 'Ajukan Lelang' }).click();

  await expect(barisLelangSeller(page, nama).first()).toBeVisible({
    timeout: 30000,
  });

  return nama;
}

/** Loloskan lelang melalui validator lalu admin. */
export async function setujuiLelang(browser, nama) {
  const ctxValidator = await browser.newContext();
  const halValidator = await ctxValidator.newPage();

  await loginAs(halValidator, 'validator');
  await halValidator.goto('/validator/dashboard');

  const barisValidator = halValidator
    .locator('tr')
    .filter({ hasText: nama })
    .first();
  await expect(barisValidator).toBeVisible({ timeout: 15000 });
  await aksiBaris(halValidator, barisValidator, 'Validasi', 'Ya, validasi');
  await expect(
    halValidator.locator('tr').filter({ hasText: nama }).first()
  ).toContainText(/Menunggu Admin|Disetujui/, { timeout: 15000 });

  await ctxValidator.close();

  const ctxAdmin = await browser.newContext();
  const halAdmin = await ctxAdmin.newPage();

  await loginAs(halAdmin, 'admin');
  await halAdmin.goto('/admin/auctions');

  const barisAdmin = halAdmin.locator('tr').filter({ hasText: nama }).first();
  await expect(barisAdmin).toBeVisible({ timeout: 15000 });
  // Persetujuan lelang oleh admin tidak memakai dialog konfirmasi.
  await aksiBaris(halAdmin, barisAdmin, 'Setujui');
  await expect(
    halAdmin.locator('tr').filter({ hasText: nama }).first()
  ).toContainText('Disetujui', { timeout: 20000 });

  await ctxAdmin.close();
}

/**
 * Tunggu sampai lelang berpindah ke status aktif.
 *
 * Aktivasi terjadi saat halaman lelang dibuka (activateApprovedAuctions),
 * bukan lewat penjadwal, jadi cukup memuat ulang halaman daftar lelang.
 */
export async function tungguLelangAktif(page, nama, batasMs = 180000) {
  const batas = Date.now() + batasMs;

  while (Date.now() < batas) {
    await page.goto('/auctions');

    const tautan = page.locator(`text=${nama}`).first();

    if (await tautan.count()) {
      await tautan.click();
      // Menunggu form bid muncul sekaligus menunggu navigasinya selesai;
      // mengecek visibilitas tanpa menunggu akan selalu false tepat setelah
      // klik dan membuat perulangan ini tidak pernah berhasil.
      const adaFormBid = await page
        .locator('input[name="amount"]')
        .waitFor({ state: 'visible', timeout: 8000 })
        .then(() => true)
        .catch(() => false);

      if (adaFormBid) {
        return;
      }
    }

    await page.waitForTimeout(5000);
  }

  throw new Error(`Lelang "${nama}" tidak kunjung aktif dalam ${batasMs} ms.`);
}

/**
 * Buka halaman produk tebak harga dan tunggu sampai sesi tebaknya aktif.
 *
 * Perpindahan status dilakukan PriceGuessService::sync() yang dipanggil saat
 * halaman katalog dibuka, bukan oleh penjadwal — jadi memuat ulang katalog
 * sudah cukup untuk memicunya.
 */
export async function bukaTebakHargaAktif(page, nama, batasMs = 180000) {
  const batas = Date.now() + batasMs;

  while (Date.now() < batas) {
    await page.goto(`/products?q=${encodeURIComponent(nama)}`);

    const tautan = page.locator(`text=${nama}`).first();

    if (await tautan.count()) {
      await tautan.click();

      const adaFormTebakan = await page
        .locator('input[name="amount"]')
        .waitFor({ state: 'visible', timeout: 8000 })
        .then(() => true)
        .catch(() => false);

      if (adaFormTebakan) {
        return;
      }
    }

    await page.waitForTimeout(5000);
  }

  throw new Error(
    `Sesi tebak harga "${nama}" tidak kunjung aktif dalam ${batasMs} ms.`
  );
}

/** Baris tabel produk di dashboard seller (bukan tabel pesanan atau lelang). */
export function barisProdukSeller(page, nama) {
  return page
    .locator('div.rounded-2xl')
    .filter({
      has: page.getByRole('heading', { name: 'Daftar Barang', exact: true }),
    })
    .locator('tr')
    .filter({ hasText: nama });
}

/** Baris tabel lelang di dashboard seller. */
export function barisLelangSeller(page, nama) {
  return page
    .locator('div.rounded-2xl')
    .filter({
      has: page.getByRole('heading', {
        name: 'Daftar Barang Lelang',
        exact: true,
      }),
    })
    .locator('tr')
    .filter({ hasText: nama });
}

/**
 * Jalankan satu aksi dari menu titik-tiga pada sebuah baris tabel, lalu
 * konfirmasi dialognya bila ada.
 */
export async function aksiBaris(
  page,
  baris,
  labelAksi,
  labelKonfirmasi = null
) {
  await baris.getByRole('button', { name: 'Aksi' }).click();
  await page.getByRole('menuitem', { name: labelAksi }).click();

  if (labelKonfirmasi) {
    await page.getByRole('button', { name: labelKonfirmasi }).click();
  }
}

/**
 * Loloskan produk melalui dua tahap persetujuan: validator lalu admin.
 *
 * Memakai browser context terpisah agar sesi seller pemanggil tetap utuh.
 */
export async function setujuiProduk(browser, nama) {
  const ctxValidator = await browser.newContext();
  const halValidator = await ctxValidator.newPage();

  await loginAs(halValidator, 'validator');
  await halValidator.goto('/validator/dashboard');

  const barisValidator = halValidator
    .locator('tr')
    .filter({ hasText: nama })
    .first();
  await expect(barisValidator).toBeVisible({ timeout: 15000 });
  await aksiBaris(halValidator, barisValidator, 'Validasi', 'Ya, validasi');
  await expect(
    halValidator.locator('tr').filter({ hasText: nama }).first()
  ).toContainText(/Menunggu Admin|Disetujui/, { timeout: 15000 });

  await ctxValidator.close();

  const ctxAdmin = await browser.newContext();
  const halAdmin = await ctxAdmin.newPage();

  await loginAs(halAdmin, 'admin');

  // Halaman "pending" memuat seluruh produk tahap dua tanpa paginasi, jadi
  // produk yang baru divalidasi pasti ada di situ.
  await halAdmin.goto('/admin/products/pending');

  const kartu = halAdmin
    .locator('div.rounded-2xl')
    .filter({ hasText: nama })
    .first();
  await expect(kartu).toBeVisible({ timeout: 15000 });

  await kartu.getByRole('button', { name: 'Setujui Produk' }).click();
  await halAdmin.getByRole('button', { name: 'Ya, setujui' }).click();

  // Setelah disetujui, produk keluar dari antrean tahap dua.
  await expect(
    halAdmin.locator('div.rounded-2xl').filter({ hasText: nama })
  ).toHaveCount(0, { timeout: 20000 });

  await ctxAdmin.close();
}
