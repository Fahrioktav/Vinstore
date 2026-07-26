import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.describe('Fitur Tebak Harga Vinstore', () => {

  test('TC-TH-01: Harga asli tersembunyi selama masa tebak harga aktif', async ({ page }) => {
    await loginAs(page, 'pembeli@example.com');
    await page.goto('/products');
    
    // Menampilkan daftar produk
    await expect(page.locator('text=Koin Kuno Yasin')).toBeVisible();
    
    // Memastikan teks harga tidak menampilkan nominal melainkan placeholder tebak harga
    await expect(page.locator('text=???').first()).toBeVisible();
    
    // Buka detail produk tebak harga
    await page.click('text=Koin Kuno Yasin');
    await expect(page.locator('main')).toContainText('??? (Tebak Harga)');
  });

  test('TC-TH-02: Buyer mengirim tebakan harga', async ({ page }) => {
    // Login Buyer
    await loginAs(page, 'pembeli@example.com');

    // Buka halaman produk tebak harga
    await page.goto('/products');
    await page.click('text=Koin Kuno Yasin');

    // Masukkan Tebakan Harga
    await page.fill('input[name="amount"]', '750000');
    await page.click('button:has-text("Kirim Tebakan")');

    // Hasil yang diharapkan: Muncul konfirmasi bahwa tebakan berhasil disimpan
    await expect(page.locator('text=Tebakan harga Anda berhasil dikirim').first()).toBeVisible();
    
    // Input form digantikan oleh status tebakan yang terkunci
    await expect(page.locator('text=Tebakan Anda sudah tersimpan')).toBeVisible();
    await expect(page.locator('input[name="amount"]')).not.toBeVisible();
  });
});
