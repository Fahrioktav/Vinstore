/**
 * Script Playwright untuk Mengambil Screenshot Semua Halaman
 * Untuk Dokumentasi Laporan Skripsi
 * 
 * Cara menjalankan:
 * 1. Pastikan server Laravel berjalan: php artisan serve
 * 2. Jalankan script ini: npx playwright test tests/playwright/screenshot-all-pages.spec.js --headed
 */

const { test, expect } = require('@playwright/test');
const path = require('path');

// Konfigurasi
const BASE_URL = 'http://localhost:8000';
const SCREENSHOT_DIR = path.join(__dirname, '../../public/screenshots/laporan-skripsi');

// Kredensial untuk testing
const USERS = {
  buyer: {
    email: 'asep@example.com',
    password: 'password123',
  },
  seller: {
    email: 'cikidaw@gmail.com',
    password: 'daw123',
  },
  validator: {
    email: 'validator@gmail.com',
    password: 'password123',
  },
  admin: {
    email: 'adminganteng@gmail.com',
    password: 'password123',
  },
};

// Helper function untuk mengambil screenshot dengan kualitas tinggi
async function takeScreenshot(page, name, fullPage = true) {
  // Tunggu sampai tidak ada loading
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1000); // Extra wait untuk animasi
  
  await page.screenshot({
    path: path.join(SCREENSHOT_DIR, `${name}.png`),
    fullPage: fullPage,
    animations: 'disabled', // Disable animations for consistent screenshots
  });
  
  console.log(`✓ Screenshot saved: ${name}.png`);
}

// Helper function untuk login
async function login(page, userType) {
  const user = USERS[userType];
  await page.goto(`${BASE_URL}/login`);
  await page.fill('input[name="email"]', user.email);
  await page.fill('input[name="password"]', user.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/*'); // Tunggu redirect
  await page.waitForTimeout(1000);
}

test.describe('Screenshot Semua Halaman Vinstore', () => {
  test.use({
    viewport: { width: 1920, height: 1080 }, // Full HD untuk screenshot berkualitas
  });

  test.beforeAll(async () => {
    // Create screenshot directory if not exists
    const fs = require('fs');
    if (!fs.existsSync(SCREENSHOT_DIR)) {
      fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
    }
    console.log(`\n📸 Screenshot akan disimpan di: ${SCREENSHOT_DIR}\n`);
  });

  test('01 - Halaman Utama (Home/Landing Page)', async ({ page }) => {
    await page.goto(`${BASE_URL}/`);
    await takeScreenshot(page, '01-home-page');
  });

  test('02 - Halaman Daftar Produk', async ({ page }) => {
    await page.goto(`${BASE_URL}/products`);
    await takeScreenshot(page, '02-products-list');
  });

  test('03 - Halaman Detail Produk', async ({ page }) => {
    await page.goto(`${BASE_URL}/products`);
    // Klik produk pertama jika ada
    const firstProduct = page.locator('a[href*="/toko/"]').first();
    if (await firstProduct.count() > 0) {
      await firstProduct.click();
      await takeScreenshot(page, '03-product-detail');
    }
  });

  test('04 - Halaman Daftar Toko', async ({ page }) => {
    await page.goto(`${BASE_URL}/toko`);
    await takeScreenshot(page, '04-stores-list');
  });

  test('05 - Halaman Detail Toko', async ({ page }) => {
    await page.goto(`${BASE_URL}/toko`);
    // Klik toko pertama jika ada
    const firstStore = page.locator('a[href*="/toko/"]').first();
    if (await firstStore.count() > 0) {
      await firstStore.click();
      await takeScreenshot(page, '05-store-detail');
    }
  });

  test('06 - Halaman Daftar Lelang', async ({ page }) => {
    await page.goto(`${BASE_URL}/auctions`);
    await takeScreenshot(page, '06-auctions-list');
  });

  test('07 - Halaman Detail Lelang', async ({ page }) => {
    await page.goto(`${BASE_URL}/auctions`);
    // Klik lelang pertama jika ada
    const firstAuction = page.locator('a[href*="/auctions/"]').first();
    if (await firstAuction.count() > 0) {
      await firstAuction.click();
      await takeScreenshot(page, '07-auction-detail');
    }
  });

  test('08 - Halaman Contact', async ({ page }) => {
    await page.goto(`${BASE_URL}/contact`);
    await takeScreenshot(page, '08-contact-page');
  });

  test('09 - Halaman Login', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`);
    await takeScreenshot(page, '09-login-page');
  });

  test('10 - Halaman Register', async ({ page }) => {
    await page.goto(`${BASE_URL}/register`);
    await takeScreenshot(page, '10-register-page');
  });

  test('11 - Halaman Forgot Password', async ({ page }) => {
    await page.goto(`${BASE_URL}/forgot-password`);
    await takeScreenshot(page, '11-forgot-password');
  });

  test('12 - [User] Halaman Profil', async ({ page }) => {
    await login(page, 'buyer');
    await page.goto(`${BASE_URL}/profile`);
    await takeScreenshot(page, '12-user-profile');
  });

  test('13 - [User] Halaman Keranjang', async ({ page }) => {
    await login(page, 'buyer');
    await page.goto(`${BASE_URL}/cart`);
    await takeScreenshot(page, '13-user-cart');
  });

  test('14 - [User] Halaman Checkout', async ({ page }) => {
    await login(page, 'buyer');
    await page.goto(`${BASE_URL}/products`);
    
    // Coba klik produk untuk checkout
    const firstProduct = page.locator('a[href*="/toko/"]').first();
    if (await firstProduct.count() > 0) {
      await firstProduct.click();
      await page.waitForTimeout(1000);
      
      // Cari tombol "Beli Sekarang" atau "Checkout"
      const buyButton = page.locator('button:has-text("Beli")').first();
      if (await buyButton.count() > 0) {
        await buyButton.click();
        await takeScreenshot(page, '14-user-checkout');
      }
    }
  });

  test('15 - [User] Halaman Riwayat Order', async ({ page }) => {
    await login(page, 'buyer');
    await page.goto(`${BASE_URL}/order`);
    await takeScreenshot(page, '15-user-orders');
  });

  test('16 - [User] Halaman Bantuan/Support', async ({ page }) => {
    await login(page, 'buyer');
    await page.goto(`${BASE_URL}/bantuan`);
    await takeScreenshot(page, '16-user-support');
  });

  test('17 - [User] Halaman Register Toko', async ({ page }) => {
    await login(page, 'buyer');
    await page.goto(`${BASE_URL}/store/register`);
    await takeScreenshot(page, '17-user-store-register');
  });

  test('18 - [Seller] Dashboard Seller', async ({ page }) => {
    await login(page, 'seller');
    await page.goto(`${BASE_URL}/seller/dashboard`);
    await takeScreenshot(page, '18-seller-dashboard');
  });

  test('19 - [Seller] Halaman Edit Toko', async ({ page }) => {
    await login(page, 'seller');
    await page.goto(`${BASE_URL}/seller/store/edit`);
    await takeScreenshot(page, '19-seller-store-edit');
  });

  test('20 - [Seller] Halaman Tambah Produk', async ({ page }) => {
    await login(page, 'seller');
    await page.goto(`${BASE_URL}/seller/products/create`);
    await takeScreenshot(page, '20-seller-product-create');
  });

  test('21 - [Seller] Halaman Barter', async ({ page }) => {
    await login(page, 'seller');
    await page.goto(`${BASE_URL}/seller/barter`);
    await takeScreenshot(page, '21-seller-barter');
  });

  test('22 - [Seller] Halaman Buat Lelang', async ({ page }) => {
    await login(page, 'seller');
    await page.goto(`${BASE_URL}/seller/auctions/create`);
    await takeScreenshot(page, '22-seller-auction-create');
  });

  test('23 - [Validator] Dashboard Validator', async ({ page }) => {
    await login(page, 'validator');
    await page.goto(`${BASE_URL}/validator/dashboard`);
    await takeScreenshot(page, '23-validator-dashboard');
  });

  test('24 - [Validator] Halaman Detail Validasi Produk', async ({ page }) => {
    await login(page, 'validator');
    await page.goto(`${BASE_URL}/validator/dashboard`);
    
    // Klik produk pertama untuk validasi
    const firstProduct = page.locator('a[href*="/validator/products/"]').first();
    if (await firstProduct.count() > 0) {
      await firstProduct.click();
      await takeScreenshot(page, '24-validator-product-detail');
    }
  });

  test('25 - [Admin] Dashboard Admin', async ({ page }) => {
    await login(page, 'admin');
    await page.goto(`${BASE_URL}/admin/dashboard`);
    await takeScreenshot(page, '25-admin-dashboard');
  });

  test('26 - [Admin] Kelola Users', async ({ page }) => {
    await login(page, 'admin');
    await page.goto(`${BASE_URL}/admin/users`);
    await takeScreenshot(page, '26-admin-users');
  });

  test('27 - [Admin] Kelola Sellers', async ({ page }) => {
    await login(page, 'admin');
    await page.goto(`${BASE_URL}/admin/sellers`);
    await takeScreenshot(page, '27-admin-sellers');
  });

  test('28 - [Admin] Kelola Toko', async ({ page }) => {
    await login(page, 'admin');
    await page.goto(`${BASE_URL}/admin/stores`);
    await takeScreenshot(page, '28-admin-stores');
  });

  test('29 - [Admin] Kelola Produk', async ({ page }) => {
    await login(page, 'admin');
    await page.goto(`${BASE_URL}/admin/products`);
    await takeScreenshot(page, '29-admin-products');
  });

  test('30 - [Admin] Kelola Orders', async ({ page }) => {
    await login(page, 'admin');
    await page.goto(`${BASE_URL}/admin/orders`);
    await takeScreenshot(page, '30-admin-orders');
  });

  test('31 - [Admin] Kelola Lelang', async ({ page }) => {
    await login(page, 'admin');
    await page.goto(`${BASE_URL}/admin/auctions`);
    await takeScreenshot(page, '31-admin-auctions');
  });

  test('32 - [Admin] Kelola Refund', async ({ page }) => {
    await login(page, 'admin');
    await page.goto(`${BASE_URL}/admin/refunds`);
    await takeScreenshot(page, '32-admin-refunds');
  });

  test('33 - [Admin] Kelola Pencairan', async ({ page }) => {
    await login(page, 'admin');
    await page.goto(`${BASE_URL}/admin/withdrawals`);
    await takeScreenshot(page, '33-admin-withdrawals');
  });

  test('34 - [Admin] Kelola Kategori', async ({ page }) => {
    await login(page, 'admin');
    await page.goto(`${BASE_URL}/admin/categories`);
    await takeScreenshot(page, '34-admin-categories');
  });

  test('35 - [Admin] Kelola Pesan Contact', async ({ page }) => {
    await login(page, 'admin');
    await page.goto(`${BASE_URL}/admin/contacts`);
    await takeScreenshot(page, '35-admin-contacts');
  });

  test('36 - [Admin] Halaman Bantuan Admin', async ({ page }) => {
    await login(page, 'admin');
    await page.goto(`${BASE_URL}/admin/bantuan`);
    await takeScreenshot(page, '36-admin-support');
  });

  test.afterAll(async () => {
    console.log(`\n✅ Screenshot selesai! Cek folder: ${SCREENSHOT_DIR}\n`);
  });
});
