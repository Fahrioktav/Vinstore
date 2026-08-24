import { test, expect } from '@playwright/test';
import { AKUN, loginAs, logout } from './helpers.js';

/**
 * Pengujian black box autentikasi dan kontrol akses berbasis role.
 *
 * Seluruh pengujian di berkas ini hanya membaca — tidak ada data yang dibuat
 * atau diubah, kecuali TC-AU-02 yang mendaftarkan akun baru dengan email unik.
 */
test.describe('Autentikasi dan Kontrol Akses', () => {
  test('TC-AU-01: Login dengan kredensial benar mengarahkan ke beranda sesuai role', async ({
    browser,
  }) => {
    const kasus = [
      { akun: 'pembeli1', tujuan: /localhost:\d+\/$/ },
      { akun: 'seller1', tujuan: /\/seller\/dashboard/ },
      { akun: 'validator', tujuan: /\/validator\/dashboard/ },
      { akun: 'admin', tujuan: /\/admin\/dashboard/ },
    ];

    for (const { akun, tujuan } of kasus) {
      const context = await browser.newContext();
      const page = await context.newPage();

      await loginAs(page, akun);
      await expect(page, `role ${AKUN[akun].role} salah tujuan`).toHaveURL(
        tujuan
      );

      await context.close();
    }
  });

  test('TC-AU-02: Login dapat memakai username maupun email', async ({
    page,
  }) => {
    // helpers.AKUN.sellerA memakai username, seller1 memakai email.
    // Keduanya harus sampai ke dashboard seller.
    await loginAs(page, 'sellerA');
    await expect(page).toHaveURL(/\/seller\/dashboard/);
  });

  test('TC-AU-03: Password salah ditolak dan menampilkan pesan kesalahan', async ({
    page,
  }) => {
    await page.goto('/login');
    await page.fill('input[name="login"]', AKUN.pembeli1.login);
    await page.fill('input[name="password"]', 'passwordSalah999');
    await page.getByRole('button', { name: 'Masuk Sekarang' }).click();

    // Hasil yang diharapkan: tetap di halaman login dengan pesan generik yang
    // tidak membocorkan apakah akunnya ada atau tidak.
    await expect(page).toHaveURL(/\/login/);
    await expect(
      page.locator('text=Username/Email atau password salah.')
    ).toBeVisible({ timeout: 15000 });
  });

  test('TC-AU-04: Akun tidak terdaftar ditolak dengan pesan yang sama', async ({
    page,
  }) => {
    await page.goto('/login');
    await page.fill('input[name="login"]', `tidakada${Date.now()}@example.com`);
    await page.fill('input[name="password"]', 'password123');
    await page.getByRole('button', { name: 'Masuk Sekarang' }).click();

    await expect(page).toHaveURL(/\/login/);
    await expect(
      page.locator('text=Username/Email atau password salah.')
    ).toBeVisible({ timeout: 15000 });
  });

  test('TC-AU-05: Registrasi menolak konfirmasi password yang tidak cocok', async ({
    page,
  }) => {
    const unik = Date.now();

    await page.goto('/register');
    await page.fill('input[name="username"]', `uji${unik}`);
    await page.fill('input[name="first_name"]', 'Uji');
    await page.fill('input[name="last_name"]', 'Validasi');
    await page.fill('input[name="email"]', `uji.${unik}@example.com`);
    await page.fill('input[name="phone"]', `08${String(unik).slice(-10)}`);
    await page.fill('textarea[name="address"]', 'Alamat uji Playwright');
    await page.fill('input[name="password"]', 'password123');
    await page.fill('input[name="password_confirmation"]', 'passwordBeda123');
    await page.click('button[type="submit"]');

    // Hasil yang diharapkan: tidak jadi terdaftar, tetap di halaman registrasi.
    await expect(page).toHaveURL(/\/register/, { timeout: 15000 });
  });

  test('TC-AU-06: Registrasi menolak email yang sudah terpakai', async ({
    page,
  }) => {
    const unik = Date.now();

    await page.goto('/register');
    await page.fill('input[name="username"]', `duplikat${unik}`);
    await page.fill('input[name="first_name"]', 'Email');
    await page.fill('input[name="last_name"]', 'Duplikat');
    await page.fill('input[name="email"]', AKUN.pembeli1.login); // sudah ada
    await page.fill('input[name="phone"]', `08${String(unik).slice(-10)}`);
    await page.fill('textarea[name="address"]', 'Alamat uji Playwright');
    await page.fill('input[name="password"]', 'password123');
    await page.fill('input[name="password_confirmation"]', 'password123');
    await page.click('button[type="submit"]');

    await expect(page).toHaveURL(/\/register/, { timeout: 15000 });
  });

  test('TC-AU-07: Pembeli tidak dapat membuka dashboard seller', async ({
    page,
  }) => {
    await loginAs(page, 'pembeli1');
    await page.goto('/seller/dashboard');

    // Middleware CheckRole mengalihkan diam-diam, bukan menampilkan 403.
    await expect(page).not.toHaveURL(/\/seller\/dashboard/, { timeout: 15000 });
  });

  test('TC-AU-08: Pembeli tidak dapat membuka dashboard admin maupun validator', async ({
    page,
  }) => {
    await loginAs(page, 'pembeli1');

    await page.goto('/admin/dashboard');
    await expect(page).not.toHaveURL(/\/admin\/dashboard/, { timeout: 15000 });

    await page.goto('/validator/dashboard');
    await expect(page).not.toHaveURL(/\/validator\/dashboard/, {
      timeout: 15000,
    });
  });

  test('TC-AU-09: Seller tidak dapat membuka dashboard admin', async ({
    page,
  }) => {
    await loginAs(page, 'seller1');
    await page.goto('/admin/dashboard');

    await expect(page).not.toHaveURL(/\/admin\/dashboard/, { timeout: 15000 });
  });

  test('TC-AU-10: Halaman yang butuh login menolak pengunjung anonim', async ({
    page,
  }) => {
    for (const url of ['/cart', '/order', '/profile']) {
      await page.goto(url);
      await expect(
        page,
        `${url} seharusnya tidak bisa dibuka tanpa login`
      ).toHaveURL(/\/login/, { timeout: 15000 });
    }
  });

  test('TC-AU-11: Logout mengakhiri sesi', async ({ page }) => {
    await loginAs(page, 'pembeli1');
    await logout(page, 'pembeli1');

    // Setelah logout, halaman yang butuh login harus menolak lagi.
    await page.goto('/cart');
    await expect(page).toHaveURL(/\/login/, { timeout: 15000 });
  });
});
