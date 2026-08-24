import { test, expect } from '@playwright/test';
import {
  barisLelangSeller,
  buatLelang,
  loginAs,
  namaUji,
  setujuiLelang,
  tungguLelangAktif,
  waktuLokal,
} from './helpers.js';

/**
 * Pengujian black box mode lelang.
 *
 * Skenario penawaran memakai satu lelang bersama yang dibuat sekali di
 * beforeAll: menyiapkan lelang berarti menunggu jadwal mulainya, jadi tidak
 * praktis diulang di setiap test. Karena itu describe-nya serial — TC-LE-04
 * bergantung pada harga tertinggi yang ditinggalkan TC-LE-03.
 */
test.describe.configure({ mode: 'serial' });

test.describe('Fitur Lelang', () => {
  let namaLelangAktif;

  // Menyiapkan lelang bersama berarti: buat, validasi, setujui admin, lalu
  // menunggu jadwal mulainya. Jauh lebih lama dari satu test biasa.
  test.setTimeout(300000);

  test.beforeAll(async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();

    await loginAs(page, 'seller1');
    // Harga awalnya sengaja DI BAWAH ambang deposit (Rp 1.000.000) supaya
    // skenario penawaran tetap menguji aturan penawaran saja. Gerbang
    // depositnya diuji terpisah di TC-LE-06, yang memang tidak bisa menawar
    // karena pembayaran Midtrans tidak dapat diselesaikan dari Playwright.
    namaLelangAktif = await buatLelang(page, {
      nama: namaUji('Guci Lelang'),
      hargaAwal: '900000',
      kelipatan: '50000',
    });
    await ctx.close();

    await setujuiLelang(browser, namaLelangAktif);
  });

  test('TC-LE-01: Lelang baru dari seller berstatus menunggu validator', async ({
    page,
  }) => {
    await loginAs(page, 'seller1');

    const nama = await buatLelang(page, { nama: namaUji('Lelang Baru') });

    await expect(barisLelangSeller(page, nama).first()).toContainText(
      'Menunggu Validator'
    );
  });

  test('TC-LE-02: Lelang menolak tanggal selesai lebih awal dari tanggal mulai', async ({
    page,
  }) => {
    await loginAs(page, 'seller1');
    await page.goto('/seller/products/create');
    await page.selectOption('select[name="sale_type"]', 'lelang');

    const nama = namaUji('Lelang Tanggal Salah');

    await page.fill('input[name="name"]', nama);
    await page.fill('textarea[name="description"]', 'Uji validasi tanggal.');
    await page.fill('input[name="weight"]', '2000');
    await page.fill('input#starting_price', '5000000');
    await page.fill('input#min_increment', '100000');
    await page.fill('input[name="starts_at"]', waktuLokal(60 * 24));
    await page.fill('input[name="ends_at"]', waktuLokal(60)); // lebih awal dari mulai
    await page.setInputFiles('input[name="image"]', 'tests/fixtures/guci.jpg');
    await page.getByRole('button', { name: 'Ajukan Lelang' }).click();

    // Hasil yang diharapkan: tidak tersimpan, tetap di form pengajuan.
    await expect(page).toHaveURL(/\/seller\/products\/create/, {
      timeout: 20000,
    });
  });

  test('TC-LE-03: Penawaran yang memenuhi kelipatan minimum diterima', async ({
    page,
  }) => {
    await loginAs(page, 'pembeli1');
    await tungguLelangAktif(page, namaLelangAktif);

    // Harga awal 900.000 + kelipatan 50.000 -> minimum 950.000
    await page.fill('input#amount', '960000');
    await page.getByRole('button', { name: 'Tombol ajukan penawaran' }).click();

    // Pesan sukses muncul dua kali (toast dan banner di dalam halaman),
    // karena itu diambil yang pertama.
    await expect(
      page.locator('text=Penawaran berhasil diajukan').first()
    ).toBeVisible({ timeout: 20000 });

    // Harga di layar diperbarui lewat siaran WebSocket (Reverb). Pengujian ini
    // sengaja memuat ulang halaman supaya yang diverifikasi adalah harga yang
    // benar-benar tersimpan, bukan pembaruan realtime — dengan begitu spec
    // tetap sahih walau server Reverb tidak dijalankan.
    await page.reload();

    // Hasil yang diharapkan: nominal itu menjadi harga tertinggi.
    await expect(page.locator('text=Rp 960.000').first()).toBeVisible({
      timeout: 20000,
    });
  });

  test('TC-LE-04: Penawaran di bawah kelipatan minimum ditolak', async ({
    page,
  }) => {
    await loginAs(page, 'pembeli2');
    await tungguLelangAktif(page, namaLelangAktif);

    // Harga tertinggi sekarang 960.000, minimum berikutnya 1.010.000.
    await page.fill('input#amount', '980000');
    await page.getByRole('button', { name: 'Tombol ajukan penawaran' }).click();

    await page.reload();

    // Hasil yang diharapkan: harga tertinggi tidak berubah dan penawar ini
    // tidak masuk riwayat bid.
    await expect(page.locator('text=Rp 960.000').first()).toBeVisible({
      timeout: 20000,
    });
    await expect(page.locator('tbody')).not.toContainText('pembeli2');
  });

  test('TC-LE-05: Lelang yang belum disetujui tidak tampil di daftar publik', async ({
    browser,
  }) => {
    const ctxSeller = await browser.newContext();
    const halSeller = await ctxSeller.newPage();

    await loginAs(halSeller, 'seller1');
    const nama = await buatLelang(halSeller, {
      nama: namaUji('Lelang Rahasia'),
    });
    await ctxSeller.close();

    const ctxPembeli = await browser.newContext();
    const halPembeli = await ctxPembeli.newPage();

    await loginAs(halPembeli, 'pembeli1');
    await halPembeli.goto('/auctions');

    await expect(halPembeli.locator(`text=${nama}`)).toHaveCount(0);

    await ctxPembeli.close();
  });

  /**
   * Gerbang deposit pada lelang bernilai tinggi.
   *
   * Yang diverifikasi hanya sampai gerbangnya: pembayaran depositnya sendiri
   * melewati popup Midtrans yang tidak dapat diselesaikan dari Playwright.
   * Perpindahan status setelah dibayar diuji di AuctionDepositTest (PHPUnit).
   */
  test('TC-LE-06: Lelang di atas Rp 1 juta menutup penawaran sebelum deposit dibayar', async ({
    browser,
  }) => {
    const ctxSeller = await browser.newContext();
    const halSeller = await ctxSeller.newPage();

    await loginAs(halSeller, 'seller1');
    const nama = await buatLelang(halSeller, {
      nama: namaUji('Lelang Deposit'),
      hargaAwal: '5000000',
      kelipatan: '250000',
    });
    await ctxSeller.close();

    await setujuiLelang(browser, nama);

    const ctxPembeli = await browser.newContext();
    const halPembeli = await ctxPembeli.newPage();

    await loginAs(halPembeli, 'pembeli1');
    await tungguLelangAktif(halPembeli, nama);

    // 10% dari Rp 5.000.000.
    await expect(halPembeli.locator('text=Rp 500.000').first()).toBeVisible({
      timeout: 20000,
    });
    await expect(
      halPembeli.getByRole('button', { name: 'Bayar Deposit' })
    ).toBeVisible();

    // Form penawaran belum tersedia sebelum jaminannya dibayar.
    await expect(halPembeli.locator('input#amount')).toHaveCount(0);

    await ctxPembeli.close();
  });
});
