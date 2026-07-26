import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.describe('Fitur Lelang Vinstore', () => {

  test('TC-LE-01: Seller mengajukan barang lelang baru', async ({ page }) => {
    // Login Seller
    await loginAs(page, 'seller@example.com', '/seller/dashboard');

    // Halaman buat lelang
    await page.goto('/seller/auctions/create');
    await page.fill('input[name="name"]', 'Guci Kuno Dinasti Ming');
    await page.fill('textarea[name="description"]', 'Guci antik dinasti ming utuh tanpa retak.');
    await page.fill('input[name="starting_price"]', '10000000');
    await page.fill('input[name="min_increment"]', '500000');
    
    // Set Waktu Mulai & Waktu Selesai (Format YYYY-MM-DD THH:mm)
    await page.fill('input[name="starts_at"]', '2026-08-01T12:00');
    await page.fill('input[name="ends_at"]', '2026-08-05T12:00');
    await page.setInputFiles('input[name="image"]', 'tests/fixtures/guci.jpg');

    await page.click('button[type="submit"]');

    // Menunggu persetujuan admin
    await expect(page.locator('text=menunggu persetujuan admin')).toBeVisible();
  });

  test('TC-LE-02: Buyer mengirim bid lelang yang valid', async ({ page }) => {
    // Login Buyer
    await loginAs(page, 'pembeli@example.com');

    // Masuk ke halaman lelang aktif
    await page.goto('/auctions');
    await page.click('text=Guci Kuno Dinasti Ming');

    // Input Bid baru (Harga Awal: 10.000.000 + Increment: 500.000 = Min Bid 10.500.000)
    await page.fill('input[name="amount"]', '10600000');
    await page.click('button:has-text("Tombol ajukan penawaran")');

    // Hasil yang diharapkan: bid baru tampil sebagai harga tertinggi.
    await expect(page.locator('text=Rp 10.600.000').first()).toBeVisible({ timeout: 20000 });
  });

  test('TC-LE-03: Sistem menolak nominal bid di bawah batas minimum', async ({ page }) => {
    // Login Buyer
    await loginAs(page, 'pembeli2@example.com');

    // Buka produk lelang
    await page.goto('/auctions');
    await page.click('text=Guci Kuno Dinasti Ming');

    // Input Bid tidak valid (di bawah min bid 11.100.000 karena current price sudah 10.600.000)
    await page.fill('input[name="amount"]', '10800000');
    await page.click('button:has-text("Tombol ajukan penawaran")');

    // Hasil yang diharapkan: bid ditolak, harga tertinggi tidak berubah,
    // dan pembeli2 tidak masuk ke riwayat bid.
    await expect(page.locator('text=Rp 10.600.000').first()).toBeVisible();
    await expect(page.locator('tbody')).not.toContainText('pembeli2');
  });
});
