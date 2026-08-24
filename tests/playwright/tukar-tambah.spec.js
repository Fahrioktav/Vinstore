import { test, expect } from '@playwright/test';
import { buatProduk, loginAs, namaUji, setujuiProduk } from './helpers.js';

/**
 * Pengujian black box tukar tambah antar seller.
 *
 * Kedua produk dibuat dengan harga sama supaya tidak muncul selisih uang —
 * alur pembayaran selisih lewat Midtrans di luar cakupan pengujian ini karena
 * memerlukan layanan pembayaran sungguhan.
 */
test.describe.configure({ mode: 'serial' });

test.describe('Fitur Tukar Tambah Seller-to-Seller', () => {
  let produkA; // milik sellerA, ditawarkan
  let produkB; // milik sellerB, diinginkan

  test.setTimeout(300000);

  test.beforeAll(async ({ browser }) => {
    // Produk milik Seller A
    const ctxA = await browser.newContext();
    const halA = await ctxA.newPage();

    await loginAs(halA, 'sellerA');
    produkA = await buatProduk(halA, {
      nama: namaUji('Katana Tukar'),
      harga: '5000000',
      stok: '1',
      kategori: 'Senjata',
      tukarTambah: true,
    });
    await ctxA.close();

    // Produk milik Seller B
    const ctxB = await browser.newContext();
    const halB = await ctxB.newPage();

    await loginAs(halB, 'sellerB');
    produkB = await buatProduk(halB, {
      nama: namaUji('Keris Tukar'),
      harga: '5000000',
      stok: '1',
      kategori: 'Senjata',
      tukarTambah: true,
    });
    await ctxB.close();

    await setujuiProduk(browser, produkA);
    await setujuiProduk(browser, produkB);
  });

  test('TC-TT-01: Seller hanya melihat produk tukar tambah milik seller lain', async ({
    page,
  }) => {
    await loginAs(page, 'sellerA');
    await page.goto('/seller/tukar-tambah');

    // Produk seller lain yang membuka tukar tambah muncul sebagai penawaran.
    await expect(page.locator(`h3:has-text("${produkB}")`).first()).toBeVisible(
      {
        timeout: 20000,
      }
    );

    // Produknya sendiri tidak ikut ditawarkan kepada dirinya sendiri.
    await expect(page.locator(`h3:has-text("${produkA}")`)).toHaveCount(0);
  });

  test('TC-TT-02: Seller A dapat mengajukan tukar tambah ke produk seller B', async ({
    page,
  }) => {
    await loginAs(page, 'sellerA');
    await page.goto('/seller/tukar-tambah');

    const kartuTarget = page
      .locator('div')
      .filter({ has: page.locator(`h3:has-text("${produkB}")`) })
      .last();

    await kartuTarget
      .getByRole('button', { name: 'Ajukan Tukar Tambah' })
      .first()
      .click();

    // Pilih produk sendiri yang ditawarkan. Nilai option adalah public_id,
    // jadi dibaca dulu dari teksnya.
    const opsi = page.locator('option').filter({ hasText: produkA }).first();
    const nilai = await opsi.getAttribute('value');
    await page.locator('select').last().selectOption(nilai);

    await page
      .getByPlaceholder('Sampaikan pesan untuk seller...')
      .fill('Pengajuan tukar tambah otomatis dari Playwright.');
    await page.getByRole('button', { name: 'Kirim Pengajuan' }).click();

    // Hasil yang diharapkan: pengajuan tercatat di tab "Permintaan Saya".
    await page.getByRole('button', { name: /Permintaan Saya/ }).click();
    await expect(page.locator(`text=${produkB}`).first()).toBeVisible({
      timeout: 20000,
    });
  });

  test('TC-TT-03: Pengajuan muncul di Permintaan Masuk milik seller B', async ({
    page,
  }) => {
    await loginAs(page, 'sellerB');
    await page.goto('/seller/tukar-tambah');
    await page.getByRole('button', { name: /Permintaan Masuk/ }).click();

    await expect(page.locator(`text=${produkA}`).first()).toBeVisible({
      timeout: 20000,
    });
  });

  test('TC-TT-04: Seller B menyetujui dan tukar tambah masuk tahap pengiriman', async ({
    page,
  }) => {
    await loginAs(page, 'sellerB');
    await page.goto('/seller/tukar-tambah');
    await page.getByRole('button', { name: /Permintaan Masuk/ }).click();

    await page.getByRole('button', { name: 'Setujui' }).first().click();

    // Hasil yang diharapkan: pengajuan disetujui. Karena kedua produk berharga
    // sama, tidak ada selisih yang harus dibayar dan prosesnya langsung
    // berlanjut ke tahap pengiriman.
    await expect(
      page.locator('text=Tukar tambah disetujui').first()
    ).toBeVisible({
      timeout: 20000,
    });
  });

  test('TC-TT-05: Produk yang sedang ditukar tidak dapat dibeli pembeli biasa', async ({
    browser,
  }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();

    await loginAs(page, 'pembeli1');
    await page.goto(`/products?q=${encodeURIComponent(produkB)}`);

    // Produk yang terkunci tetap tampil di katalog — yang diblokir adalah
    // pembeliannya, dan pembeli diberi tahu alasannya.
    await page.locator(`text=${produkB}`).first().click();
    await page.getByRole('button', { name: 'Masukkan Keranjang' }).click();

    // Hasil yang diharapkan: penolakan dengan alasan tukar tambah.
    await expect(
      page.locator('text=sedang dalam proses tukar tambah').first()
    ).toBeVisible({ timeout: 20000 });

    await ctx.close();
  });

  test('TC-TT-06: Produk yang terkunci tidak lagi ditawarkan di daftar tukar tambah', async ({
    page,
  }) => {
    await loginAs(page, 'sellerA');
    await page.goto('/seller/tukar-tambah');

    // scopeTradeInEnabled mengecualikan produk yang locked_for_trade_in_id-nya
    // terisi, jadi produk yang sedang ditukar tidak bisa ditawar ulang.
    await expect(page.locator(`h3:has-text("${produkB}")`)).toHaveCount(0);
  });
});
