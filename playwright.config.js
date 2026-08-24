// playwright.config.js
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/playwright',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 1,
  workers: 1, // Dijalankan secara sekuensial agar database tetap konsisten
  // Alur di aplikasi ini panjang: satu skenario bisa berisi beberapa kali login
  // (seller -> validator -> admin -> pembeli) dengan navigasi penuh di antaranya.
  // 30 detik bawaan Playwright terlalu pendek untuk itu.
  timeout: 120000,
  expect: { timeout: 15000 },
  reporter: 'html',
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
