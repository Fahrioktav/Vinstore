import { test, expect } from '@playwright/test';
import {
  barisProdukSeller,
  buatProduk,
  loginAs,
  namaUji,
  setujuiProduk,
} from './helpers.js';

/**
 * Pengujian black box mode transaksi jual beli biasa.
 *
 * Setiap skenario menyiapkan produknya sendiri dengan nama unik, jadi tidak
 * bergantung pada data seeder maupun isi database pengembangan.
 */
test.describe('Fitur Jual Beli', () => {
  test('TC-JB-01: Pembeli dapat melakukan registrasi akun baru', async ({
    page,
  }) => {
    const unik = Date.now();

    await page.goto('/register');
    await page.fill('input[name="username"]', `budi${unik}`);
    await page.fill('input[name="first_name"]', 'Budi');
    await page.fill('input[name="last_name"]', 'Buyer');
    await page.fill('input[name="email"]', `budi.${unik}@example.com`);
    await page.fill('input[name="phone"]', `08${String(unik).slice(-10)}`);
    await page.fill('textarea[name="address"]', 'Alamat test Playwright');
    await page.fill('input[name="password"]', 'password123');
    await page.fill('input[name="password_confirmation"]', 'password123');
    await page.click('button[type="submit"]');

    await expect(page).toHaveURL('/login', { timeout: 20000 });
    await expect(page.locator('text=Pendaftaran berhasil')).toBeVisible();
  });

  test('TC-JB-02: Produk baru dari seller berstatus menunggu validator', async ({
    page,
  }) => {
    await loginAs(page, 'seller1');

    const nama = await buatProduk(page, { nama: namaUji('Koin Uji') });

    // Hasil yang diharapkan: produk muncul di tabel "Daftar Barang" dengan
    // status tahap pertama, bukan langsung terbit.
    await expect(barisProdukSeller(page, nama).first()).toContainText(
      'Menunggu Validator'
    );
  });

  test('TC-JB-03: Produk yang belum disetujui tidak tampil ke pembeli', async ({
    browser,
  }) => {
    const ctxSeller = await browser.newContext();
    const halSeller = await ctxSeller.newPage();

    await loginAs(halSeller, 'seller1');
    const nama = await buatProduk(halSeller, { nama: namaUji('Belum Terbit') });
    await ctxSeller.close();

    const ctxPembeli = await browser.newContext();
    const halPembeli = await ctxPembeli.newPage();

    await loginAs(halPembeli, 'pembeli1');
    await halPembeli.goto(`/products?q=${encodeURIComponent(nama)}`);

    // Hasil yang diharapkan: katalog publik tidak memuat produk itu.
    await expect(halPembeli.locator(`text=${nama}`)).toHaveCount(0);

    await ctxPembeli.close();
  });

  test('TC-JB-04: Produk terbit setelah lolos validator dan admin', async ({
    browser,
  }) => {
    const ctxSeller = await browser.newContext();
    const halSeller = await ctxSeller.newPage();

    await loginAs(halSeller, 'seller1');
    const nama = await buatProduk(halSeller, {
      nama: namaUji('Produk Terbit'),
    });
    await ctxSeller.close();

    // Tahap 1 validator, tahap 2 admin.
    await setujuiProduk(browser, nama);

    const ctxPembeli = await browser.newContext();
    const halPembeli = await ctxPembeli.newPage();

    await loginAs(halPembeli, 'pembeli1');
    await halPembeli.goto(`/products?q=${encodeURIComponent(nama)}`);

    // Hasil yang diharapkan: produk sudah dapat ditemukan pembeli.
    await expect(halPembeli.locator(`text=${nama}`).first()).toBeVisible({
      timeout: 20000,
    });

    await ctxPembeli.close();
  });

  test('TC-JB-05: Pembeli dapat memasukkan produk ke keranjang', async ({
    browser,
  }) => {
    const ctxSeller = await browser.newContext();
    const halSeller = await ctxSeller.newPage();

    await loginAs(halSeller, 'seller1');
    const nama = await buatProduk(halSeller, {
      nama: namaUji('Produk Keranjang'),
      harga: '750000',
    });
    await ctxSeller.close();

    await setujuiProduk(browser, nama);

    const ctxPembeli = await browser.newContext();
    const halPembeli = await ctxPembeli.newPage();

    await loginAs(halPembeli, 'pembeli1');
    await halPembeli.goto(`/products?q=${encodeURIComponent(nama)}`);
    await halPembeli.locator(`text=${nama}`).first().click();

    await halPembeli
      .getByRole('button', { name: 'Masukkan Keranjang' })
      .click();

    // Hasil yang diharapkan: produk tercatat di halaman keranjang.
    await halPembeli.goto('/cart');
    await expect(halPembeli.locator(`text=${nama}`).first()).toBeVisible({
      timeout: 20000,
    });

    await ctxPembeli.close();
  });

  test('TC-JB-06: Tombol Beli Sekarang membuka halaman checkout produk', async ({
    browser,
  }) => {
    const ctxSeller = await browser.newContext();
    const halSeller = await ctxSeller.newPage();

    await loginAs(halSeller, 'seller1');
    const nama = await buatProduk(halSeller, {
      nama: namaUji('Produk Checkout'),
      harga: '1250000',
    });
    await ctxSeller.close();

    await setujuiProduk(browser, nama);

    const ctxPembeli = await browser.newContext();
    const halPembeli = await ctxPembeli.newPage();

    await loginAs(halPembeli, 'pembeli1');
    await halPembeli.goto(`/products?q=${encodeURIComponent(nama)}`);
    await halPembeli.locator(`text=${nama}`).first().click();

    // "Beli Sekarang" adalah tautan Inertia ke halaman checkout, bukan tombol
    // yang langsung memanggil Midtrans.
    await halPembeli.getByRole('link', { name: 'Beli Sekarang' }).click();

    // Hasil yang diharapkan: masuk halaman checkout produk tersebut.
    await expect(halPembeli).toHaveURL(/\/checkout\/product\//, {
      timeout: 20000,
    });
    await expect(halPembeli.locator(`text=${nama}`).first()).toBeVisible();

    await ctxPembeli.close();
  });

  test('TC-JB-07: Checkout ditolak bila titik antar belum dipilih', async ({
    browser,
  }) => {
    const ctxSeller = await browser.newContext();
    const halSeller = await ctxSeller.newPage();

    await loginAs(halSeller, 'seller1');
    const nama = await buatProduk(halSeller, {
      nama: namaUji('Produk Alamat'),
      harga: '900000',
    });
    await ctxSeller.close();

    await setujuiProduk(browser, nama);

    const ctxPembeli = await browser.newContext();
    const halPembeli = await ctxPembeli.newPage();

    await loginAs(halPembeli, 'pembeli1');
    await halPembeli.goto(`/products?q=${encodeURIComponent(nama)}`);
    await halPembeli.locator(`text=${nama}`).first().click();
    await halPembeli.getByRole('link', { name: 'Beli Sekarang' }).click();
    await expect(halPembeli).toHaveURL(/\/checkout\/product\//, {
      timeout: 20000,
    });

    // Submit tanpa memilih titik antar di peta.
    await halPembeli
      .getByRole('button', { name: /Bayar|Lanjutkan|Checkout/i })
      .first()
      .click();

    // Hasil yang diharapkan: tetap di halaman checkout, tidak diteruskan ke
    // Midtrans, karena alamat pengiriman wajib.
    await expect(halPembeli).toHaveURL(/\/checkout\/product\//, {
      timeout: 20000,
    });

    await ctxPembeli.close();
  });
});
