import { test, expect } from '@playwright/test';
import {
  bukaTebakHargaAktif,
  buatProduk,
  loginAs,
  namaUji,
  setujuiProduk,
} from './helpers.js';

/**
 * Pengujian black box mode tebak harga.
 *
 * Satu produk tebak harga disiapkan di beforeAll lalu dipakai bersama: sesi
 * tebak baru aktif setelah waktu mulainya lewat, jadi tidak praktis menyiapkan
 * produk baru di tiap skenario. Serial, karena TC-TH-03 bergantung pada
 * tebakan yang dikirim TC-TH-02.
 */
test.describe.configure({ mode: 'serial' });

test.describe('Fitur Tebak Harga', () => {
  let namaProduk;

  test.setTimeout(300000);

  test.beforeAll(async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();

    await loginAs(page, 'seller1');
    namaProduk = await buatProduk(page, {
      nama: namaUji('Koin Tebak'),
      tipe: 'tebak_harga',
      harga: '800000',
      hargaDiskon: '600000',
      stok: '1',
    });
    await ctx.close();

    await setujuiProduk(browser, namaProduk);
  });

  test('TC-TH-01: Harga asli disembunyikan selama sesi tebak berjalan', async ({
    page,
  }) => {
    await loginAs(page, 'pembeli1');
    await bukaTebakHargaAktif(page, namaProduk);

    // Yang ditebak adalah harga DISKON, jadi itulah yang disembunyikan.
    // Harga normal memang tetap ditampilkan sebagai pembanding.
    await expect(page.locator('main')).toContainText('Harga Diskon');
    await expect(page.locator('main')).toContainText('??? (Tebak Harga)');
    await expect(page.locator('main')).toContainText('Rp 800.000'); // harga normal
    await expect(page.locator('main')).not.toContainText('Rp 600.000'); // harga diskon
  });

  test('TC-TH-02: Pembeli dapat mengirim satu tebakan harga', async ({
    page,
  }) => {
    await loginAs(page, 'pembeli1');
    await bukaTebakHargaAktif(page, namaProduk);

    await page.fill('input#amount', '750000');
    await page.getByRole('button', { name: 'Kirim Tebakan' }).click();

    // Hasil yang diharapkan: tebakan tersimpan dan formnya digantikan status.
    await expect(
      page.locator('text=Tebakan harga Anda berhasil dikirim').first()
    ).toBeVisible({ timeout: 20000 });
    await expect(
      page.locator('text=Tebakan Anda sudah tersimpan')
    ).toBeVisible();
    await expect(page.locator('input#amount')).not.toBeVisible();
  });

  test('TC-TH-03: Tebakan bersifat final — pembeli tidak dapat menebak dua kali', async ({
    page,
  }) => {
    await loginAs(page, 'pembeli1');
    await page.goto(`/products?q=${encodeURIComponent(namaProduk)}`);
    await page.locator(`text=${namaProduk}`).first().click();

    // Hasil yang diharapkan: form tebakan tidak tersedia lagi bagi pembeli
    // yang sudah menebak, sekalipun sesinya masih berjalan.
    await expect(page.locator('text=Tebakan Anda sudah tersimpan')).toBeVisible(
      {
        timeout: 20000,
      }
    );
    await expect(page.locator('input#amount')).toHaveCount(0);
  });

  test('TC-TH-04: Produk tebak harga belum bisa dibeli selama sesi berjalan', async ({
    page,
  }) => {
    await loginAs(page, 'pembeli2');
    await page.goto(`/products?q=${encodeURIComponent(namaProduk)}`);
    await page.locator(`text=${namaProduk}`).first().click();

    // Hasil yang diharapkan: jalur pembelian normal belum terbuka.
    await expect(page.getByRole('link', { name: 'Beli Sekarang' })).toHaveCount(
      0
    );
    await expect(
      page.getByRole('button', { name: 'Masukkan Keranjang' })
    ).toHaveCount(0);
  });

  test('TC-TH-05: Pembeli lain masih dapat mengirim tebakannya sendiri', async ({
    page,
  }) => {
    await loginAs(page, 'pembeli2');
    await bukaTebakHargaAktif(page, namaProduk);

    await page.fill('input#amount', '640000');
    await page.getByRole('button', { name: 'Kirim Tebakan' }).click();

    await expect(
      page.locator('text=Tebakan harga Anda berhasil dikirim').first()
    ).toBeVisible({ timeout: 20000 });
  });

  /**
   * Papan tebakan boleh menunjukkan SIAPA yang sudah ikut, tetapi tidak
   * angkanya. Dua tebakan yang mengapit sudah cukup untuk menyimpulkan letak
   * harga diskonnya, dan itu menguntungkan yang menebak paling akhir.
   */
  test('TC-TH-06: Nominal tebakan peserta lain tidak terlihat selama sesi berjalan', async ({
    page,
  }) => {
    await loginAs(page, 'pembeli2');
    await page.goto(`/products?q=${encodeURIComponent(namaProduk)}`);
    await page.locator(`text=${namaProduk}`).first().click();

    const papan = page.locator('text=Tebakan yang Sudah Masuk').locator('..');
    await expect(papan).toBeVisible({ timeout: 20000 });

    // Tebakan pembeli1 dari TC-TH-02 tidak boleh terbaca di mana pun.
    await expect(page.locator('main')).not.toContainText('Rp 750.000');
    await expect(page.locator('main')).toContainText('???');

    // Tebakannya sendiri tetap terbaca.
    await expect(page.locator('main')).toContainText('Rp 640.000');
  });
});
