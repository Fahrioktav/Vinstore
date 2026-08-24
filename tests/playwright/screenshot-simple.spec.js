/**
 * Script Playwright untuk Screenshot Halaman Spesifik
 * Versi Simple - Hanya Halaman yang Dibutuhkan
 */

const { test } = require('@playwright/test');
const path = require('path');

const BASE_URL = 'http://localhost:8000';
const SCREENSHOT_DIR = path.join(
  __dirname,
  '../../public/screenshots/laporan-skripsi'
);

async function takeScreenshot(page, name, fullPage = true) {
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);

  await page.screenshot({
    path: path.join(SCREENSHOT_DIR, `${name}.png`),
    fullPage: fullPage,
  });

  console.log(`✓ ${name}.png`);
}

async function login(page, email, password) {
  await page.goto(`${BASE_URL}/login`);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForTimeout(2000);
}

test.use({ viewport: { width: 1920, height: 1080 } });

// ========== HALAMAN PUBLIC ==========

test('Screenshot: Home Page', async ({ page }) => {
  await page.goto(`${BASE_URL}/`);
  await takeScreenshot(page, 'home-page');
});

test('Screenshot: Products List', async ({ page }) => {
  await page.goto(`${BASE_URL}/products`);
  await takeScreenshot(page, 'products-list');
});

test('Screenshot: Stores List', async ({ page }) => {
  await page.goto(`${BASE_URL}/toko`);
  await takeScreenshot(page, 'stores-list');
});

test('Screenshot: Auctions List', async ({ page }) => {
  await page.goto(`${BASE_URL}/auctions`);
  await takeScreenshot(page, 'auctions-list');
});

test('Screenshot: Contact Page', async ({ page }) => {
  await page.goto(`${BASE_URL}/contact`);
  await takeScreenshot(page, 'contact-page');
});

test('Screenshot: Login Page', async ({ page }) => {
  await page.goto(`${BASE_URL}/login`);
  await takeScreenshot(page, 'login-page');
});

test('Screenshot: Register Page', async ({ page }) => {
  await page.goto(`${BASE_URL}/register`);
  await takeScreenshot(page, 'register-page');
});

// ========== HALAMAN USER ==========

test('Screenshot: User Profile', async ({ page }) => {
  await login(page, 'asep@example.com', 'password123');
  await page.goto(`${BASE_URL}/profile`);
  await takeScreenshot(page, 'user-profile');
});

test('Screenshot: User Cart', async ({ page }) => {
  await login(page, 'asep@example.com', 'password123');
  await page.goto(`${BASE_URL}/cart`);
  await takeScreenshot(page, 'user-cart');
});

test('Screenshot: User Orders', async ({ page }) => {
  await login(page, 'asep@example.com', 'password123');
  await page.goto(`${BASE_URL}/order`);
  await takeScreenshot(page, 'user-orders');
});

// ========== HALAMAN SELLER ==========

test('Screenshot: Seller Dashboard', async ({ page }) => {
  await login(page, 'cikidaw@gmail.com', 'daw123');
  await page.goto(`${BASE_URL}/seller/dashboard`);
  await takeScreenshot(page, 'seller-dashboard');
});

test('Screenshot: Seller Add Product', async ({ page }) => {
  await login(page, 'cikidaw@gmail.com', 'daw123');
  await page.goto(`${BASE_URL}/seller/products/create`);
  await takeScreenshot(page, 'seller-add-product');
});

test('Screenshot: Seller Tukar Tambah', async ({ page }) => {
  await login(page, 'cikidaw@gmail.com', 'daw123');
  await page.goto(`${BASE_URL}/seller/tukar-tambah`);
  await takeScreenshot(page, 'seller-tukar tambah');
});

// ========== HALAMAN VALIDATOR ==========

test('Screenshot: Validator Dashboard', async ({ page }) => {
  await login(page, 'validator@vinstore.com', 'password123');
  await page.goto(`${BASE_URL}/validator/dashboard`);
  await takeScreenshot(page, 'validator-dashboard');
});

// ========== HALAMAN ADMIN ==========

test('Screenshot: Admin Dashboard', async ({ page }) => {
  await login(page, 'adminganteng@gmail.com', 'password123');
  await page.goto(`${BASE_URL}/admin/dashboard`);
  await takeScreenshot(page, 'admin-dashboard');
});

test('Screenshot: Admin Users', async ({ page }) => {
  await login(page, 'adminganteng@gmail.com', 'password123');
  await page.goto(`${BASE_URL}/admin/users`);
  await takeScreenshot(page, 'admin-users');
});

test('Screenshot: Admin Products', async ({ page }) => {
  await login(page, 'adminganteng@gmail.com', 'password123');
  await page.goto(`${BASE_URL}/admin/products`);
  await takeScreenshot(page, 'admin-products');
});

test('Screenshot: Admin Orders', async ({ page }) => {
  await login(page, 'adminganteng@gmail.com', 'password123');
  await page.goto(`${BASE_URL}/admin/orders`);
  await takeScreenshot(page, 'admin-orders');
});

test('Screenshot: Admin Categories', async ({ page }) => {
  await login(page, 'adminganteng@gmail.com', 'password123');
  await page.goto(`${BASE_URL}/admin/categories`);
  await takeScreenshot(page, 'admin-categories');
});
