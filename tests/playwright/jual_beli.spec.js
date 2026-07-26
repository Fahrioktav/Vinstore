import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.describe('Fitur Jual Beli Vinstore', () => {
  
  test('TC-JB-01: Pembeli dapat melakukan registrasi akun baru', async ({ page }) => {
    await page.goto('/register');
    
    // Mengisi form registrasi
    const unique = Date.now();
    await page.fill('input[name="username"]', `budi${unique}`);
    await page.fill('input[name="first_name"]', 'Budi');
    await page.fill('input[name="last_name"]', 'Buyer');
    await page.fill('input[name="email"]', `budi.${unique}@example.com`); // Email unik tiap pengujian
    await page.fill('input[name="phone"]', `08${String(unique).slice(-10)}`);
    await page.fill('textarea[name="address"]', 'Alamat test Playwright');
    await page.fill('input[name="password"]', 'password123');
    await page.fill('input[name="password_confirmation"]', 'password123');
    
    // Submit form
    await page.click('button[type="submit"]');
    
    // Hasil yang Diharapkan: Pendaftaran berhasil dan user diarahkan ke login
    await expect(page).toHaveURL('/login', { timeout: 20000 });
    await expect(page.locator('text=Pendaftaran berhasil')).toBeVisible();
  });

  test('TC-JB-02: Seller dapat mengajukan produk baru untuk divalidasi', async ({ page }) => {
    // Langkah 1: Login sebagai Seller
    await loginAs(page, 'seller@example.com', '/seller/dashboard');

    // Langkah 2: Masuk ke Form Tambah Produk
    await page.goto('/seller/products/create');
    await page.fill('input[name="name"]', 'Koin Emas Kerajaan Majapahit');
    await page.fill('input[name="price"]', '2500000');
    await page.fill('input[name="stock"]', '1');
    await page.selectOption('select[name="category"]', 'Koin');
    await page.fill('textarea[name="description"]', 'Koin emas kuno era Majapahit asli bersertifikat.');
    
    // Upload File Sertifikat
    await page.setInputFiles('input[name="certificate"]', 'tests/fixtures/sertifikat.pdf');
    await page.setInputFiles('input[name="image"]', 'tests/fixtures/koin.jpg');
    
    // Submit produk
    await page.getByRole('button', { name: 'Simpan Produk' }).click();
    
    // Hasil yang diharapkan: Dialihkan kembali ke dashboard dengan pesan sukses
    await expect(page).toHaveURL('/seller/dashboard', { timeout: 20000 });
    await expect(page.locator('tr', { hasText: 'Koin Emas Kerajaan Majapahit' }).first()).toContainText('Menunggu Validator');
  });

  test('TC-JB-03: Buyer dapat melakukan checkout produk antik normal', async ({ page }) => {
    // Login sebagai Buyer
    await loginAs(page, 'pembeli@example.com');

    // Cari Produk di Halaman Marketplace
    await page.goto('/products');
    await page.click('text=Koin Emas Kerajaan Majapahit'); // Klik produk hasil approve
    
    // Checkout produk langsung
    await expect(page.locator('text=Beli Sekarang')).toBeVisible();
    await page.getByRole('button', { name: 'Beli Sekarang' }).click();
    
    // Hasil yang diharapkan: aplikasi mencoba membuka redirect URL Midtrans Snap.
    // Di environment tanpa akses eksternal, Chrome dapat menampilkan chrome-error.
    await expect(page).toHaveURL(/midtrans|snap|chrome-error/, { timeout: 15000 });
  });
});
