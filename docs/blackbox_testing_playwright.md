# PENGUJIAN BLACK-BOX (E2E TESTING) MENGGUNAKAN PLAYWRIGHT FRAMEWORK PADA APLIKASI VINSTORE

Dokumen ini disusun sebagai panduan dan materi lampiran untuk **Bab 3 (Metodologi Penelitian / Perancangan Sistem)** atau **Bab 4 (Implementasi dan Pengujian)** pada Skripsi. Dokumen ini menyajikan rencana pengujian, rancangan kasus uji (*test case*), serta implementasi *automated script* menggunakan **Playwright** untuk empat fitur utama: **Jual Beli**, **Lelang**, **Barter (Seller-to-Seller)**, dan **Tebak Harga**.

---

## 1. PENDAHULUAN

### 1.1 Definisi Pengujian Black-Box
Pengujian *Black-Box* (Kotak Hitam) adalah metode pengujian perangkat lunak yang berfokus pada fungsionalitas aplikasi tanpa harus mengetahui struktur internal kode program. Pengujian dilakukan berdasarkan antarmuka pengguna (UI) dan respons sistem terhadap aksi yang diberikan.

### 1.2 Mengapa Playwright Framework?
Playwright dipilih sebagai alat pengujian otomatis (*automated E2E testing*) karena beberapa alasan akademis dan teknis:
1. **Dukungan Single Page Application (SPA):** Aplikasi Vinstore dibangun dengan arsitektur Laravel Inertia.js React yang bertindak sebagai SPA. Playwright memiliki mekanisme *auto-waiting* bawaan yang handal untuk menangani rendering asinkronus dan transisi halaman tanpa *hard reload*.
2. **Multi-Browser & Multi-Platform:** Mendukung pengujian lintas browser (*Chromium*, *Firefox*, *WebKit*) dalam satu konfigurasi terpadu.
3. **Simulasi Skenario Kompleks:** Memudahkan simulasi interaksi pengguna secara dinamis, seperti upload sertifikat produk, interaksi peta interaktif (Leaflet), penawaran lelang (*bidding*), pengajuan barter, dan transaksi pembayaran (Midtrans Payment Gateway).
4. **Isolasi State & Multi-Aktor:** Dapat membuka beberapa konteks browser secara paralel (*browser contexts*) untuk menguji skenario multi-aktor (misalnya, interaksi antara Seller A dan Seller B dalam fitur barter secara *realtime*).

---

## 2. LINGKUNGAN PENGUJIAN (TESTING ENVIRONMENT)

Pengujian dilakukan pada lingkungan lokal (*local development*) dengan konfigurasi berikut:
*   **Base URL:** `http://localhost:8000` atau `http://127.0.0.1:8000`
*   **Aktor Pengujian:**
    1.  `Buyer/User`: Pembeli barang antik, peserta lelang, dan peserta tebak harga.
    2.  `Seller`: Pemilik toko yang mengupload produk, mengajukan lelang, menyetujui barter, dan memproses order.
    3.  `Validator`: Petugas penilai keaslian barang antik sebelum diajukan ke admin.
    4.  `Admin`: Pengelola utama yang menyetujui lelang, refund, dan transaksi global.

---

## 3. RENCANA SKENARIO DAN KASUS UJI (TEST CASE DESIGN)

Berikut adalah rancangan kasus uji dalam bentuk tabel terstruktur yang siap diintegrasikan ke dalam naskah Skripsi.

### A. Fitur Jual Beli (E-Commerce Utama)

| ID Kasus Uji | Skenario Pengujian | Langkah Pengujian | Data Masukan | Hasil yang Diharapkan |
| :--- | :--- | :--- | :--- | :--- |
| **TC-JB-01** | Registrasi Akun Pembeli Baru | 1. Buka halaman `/register`<br>2. Isi form registrasi<br>3. Klik tombol "Register" | Nama: `Budi Buyer`<br>Email: `budi@example.com`<br>Password: `password123` | Pengguna dialihkan ke halaman utama dan berstatus sebagai akun terdaftar. |
| **TC-JB-02** | Pengajuan Produk oleh Seller & Validasi | 1. Login sebagai Seller<br>2. Masuk ke Dashboard Seller -> Tambah Produk<br>3. Isi detail produk & unggah sertifikat keaslian<br>4. Submit produk<br>5. Login sebagai Validator<br>6. Masuk ke Dashboard Validator dan klik "Approve" | Nama Produk: `Koin Kuno Yasin`<br>Harga: `1.500.000`<br>Stok: `5`<br>Kategori: `Koin`<br>File Sertifikat: `sertifikat.pdf` | Produk berstatus `pending_admin` setelah disetujui validator, lalu disetujui admin menjadi `approved`. |
| **TC-JB-03** | Transaksi Pembelian Langsung (Checkout) | 1. Login sebagai Buyer<br>2. Cari produk yang telah `approved`<br>3. Klik tombol "Beli Sekarang"<br>4. Di halaman checkout klik "Proses Pembayaran" | Produk ID: `PRD883291`<br>Qty: `1` | Pengguna dialihkan ke halaman pembayaran Midtrans Snap. Status order baru adalah `Waiting` (Pending). |
| **TC-JB-04** | Pembaruan Status Pesanan (Seller) | 1. Login sebagai Seller<br>2. Masuk ke Dashboard -> Orders<br>3. Ubah status pesanan menjadi `Processing` lalu `On The Way` | Order ID: `ORD-9921`<br>Status: `On The Way` | Status pesanan di sisi Buyer berubah menjadi sedang dikirim. |

### B. Fitur Lelang (Auction)

| ID Kasus Uji | Skenario Pengujian | Langkah Pengujian | Data Masukan | Hasil yang Diharapkan |
| :--- | :--- | :--- | :--- | :--- |
| **TC-LE-01** | Pembuatan Lelang oleh Seller | 1. Login sebagai Seller<br>2. Masuk ke form lelang `/seller/auctions/create`<br>3. Isi detail barang lelang, harga awal, dan increment minimal<br>4. Klik "Submit" | Nama: `Guci Dinasti Ming`<br>Harga Awal: `10.000.000`<br>Increment: `500.000`<br>Starts: `Besok`<br>Ends: `Lusa` | Barang lelang berhasil diajukan dan menunggu persetujuan dari Admin. |
| **TC-LE-02** | Proses Penawaran Harga (Bid) oleh Buyer | 1. Login sebagai Buyer<br>2. Buka halaman detail lelang `/auctions/{id}`<br>3. Masukkan nominal bid di atas harga tertinggi + increment minimum<br>4. Klik "Kirim Bid" | Nominal Bid: `10.600.000` | Penawaran berhasil dikirim. Harga tertinggi produk lelang diperbarui menjadi `10.600.000`. |
| **TC-LE-03** | Validasi Bid Tidak Sah | 1. Login sebagai Buyer<br>2. Buka halaman lelang yang sama<br>3. Masukkan nominal bid di bawah (harga tertinggi + increment)<br>4. Klik "Kirim Bid" | Nominal Bid: `10.200.000` (Kurang dari minimal `11.100.000`) | Sistem menampilkan pesan error "Nominal bid minimal Rp X.XXX.XXX." dan bid ditolak. |

### C. Fitur Barter (Seller-to-Seller Barter)

| ID Kasus Uji | Skenario Pengujian | Langkah Pengujian | Data Masukan | Hasil yang Diharapkan |
| :--- | :--- | :--- | :--- | :--- |
| **TC-BA-01** | Pengajuan Barter oleh Seller A | 1. Login sebagai Seller A<br>2. Buka dashboard barter `/seller/barter`<br>3. Pilih produk milik Seller B yang ingin ditukar<br>4. Pilih produk milik sendiri untuk ditawarkan<br>5. Masukkan penawaran uang tambahan dan catatan<br>6. Klik "Kirim Pengajuan" | Produk yang diminta: `Keris Pusaka`<br>Produk ditawarkan: `Pedang Katana`<br>Uang Tambahan: `200.000` | Pengajuan barter tercatat di tabel keluar Seller A dengan status `Pending`. |
| **TC-BA-02** | Seller B Menerima Pengajuan Barter (Accept) | 1. Login sebagai Seller B<br>2. Masuk ke halaman barter -> Pengajuan Masuk<br>3. Klik tombol "Accept" pada tawaran Seller A | ID Pengajuan Barter | Pengajuan berstatus `Accepted`. Kepemilikan barang bertukar: Keris Pusaka menjadi milik Seller A, Pedang Katana milik Seller B. |
| **TC-BA-03** | Seller B Menolak Pengajuan Barter (Reject) | 1. Login sebagai Seller B<br>2. Masuk ke halaman barter -> Pengajuan Masuk<br>3. Klik tombol "Reject" | ID Pengajuan Barter | Pengajuan berstatus `Rejected`. Kepemilikan barang tidak berubah. |

### D. Fitur Tebak Harga (Price Guessing)

| ID Kasus Uji | Skenario Pengujian | Langkah Pengujian | Data Masukan | Hasil yang Diharapkan |
| :--- | :--- | :--- | :--- | :--- |
| **TC-TH-01** | Penyembunyian Harga Asli Produk Tebak Harga | 1. Buka halaman utama atau detail produk Tebak Harga yang berstatus `active` | Halaman Produk Tebak Harga | Harga asli tidak ditampilkan (ditampilkan tanda tanya `???` atau label "Tebak Harga"). |
| **TC-TH-02** | Pengiriman Tebakan oleh Buyer | 1. Login sebagai Buyer<br>2. Buka detail produk Tebak Harga<br>3. Masukkan nominal tebakan di input form<br>4. Klik "Kirim Tebakan" | Nominal Tebakan: `850.000` | Tebakan terkirim dan disimpan. Tombol input digantikan informasi bahwa tebakan telah tersimpan (tidak bisa menebak 2 kali). |
| **TC-TH-03** | Pembelian Produk oleh Pemenang (Winner Priority) | 1. Login sebagai Buyer Pemenang (setelah periode `ended`)<br>2. Masuk ke halaman detail produk / checkout<br>3. Lakukan checkout barang | Qty: `1` | Pemenang diperbolehkan membeli barang antik tersebut selama masa prioritas 24 jam masih aktif. Pengguna non-pemenang diblokir untuk membeli. |

---

## 4. IMPLEMENTASI AUTOMATED SCRIPT PLAYWRIGHT

Berikut adalah struktur kode *automated test* menggunakan Playwright Javascript (`.spec.js`) yang menguji fungsionalitas di atas.

### 4.1 File Konfigurasi: `playwright.config.js`
```javascript
// playwright.config.js
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/playwright',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 1,
  workers: 1, // Dijalankan secara sekuensial agar database tetap konsisten
  reporter: 'html',
  use: {
    baseURL: 'http://localhost:8000',
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
```

### 4.2 Script Uji Jual Beli: `tests/playwright/jual_beli.spec.js`
```javascript
const { test, expect } = require('@playwright/test');

test.describe('Fitur Jual Beli Vinstore', () => {
  
  test('TC-JB-01: Pembeli dapat melakukan registrasi akun baru', async ({ page }) => {
    await page.goto('/register');
    
    // Mengisi form registrasi
    await page.fill('input[name="name"]', 'Budi Buyer');
    await page.fill('input[name="email"]', `budi.${Date.now()}@example.com`); // Email unik tiap pengujian
    await page.fill('input[name="password"]', 'password123');
    await page.fill('input[name="password_confirmation"]', 'password123');
    
    // Submit form
    await page.click('button[type="submit"]');
    
    // Hasil yang Diharapkan: Dialihkan ke halaman utama dan ada session user terdaftar
    await expect(page).toHaveURL('/');
    await expect(page.locator('text=Budi Buyer')).toBeVisible();
  });

  test('TC-JB-02: Seller dapat mengajukan produk baru untuk divalidasi', async ({ page }) => {
    // Langkah 1: Login sebagai Seller
    await page.goto('/login');
    await page.fill('input[name="email"]', 'seller@example.com');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL('/seller/dashboard');

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
    await page.click('button[type="submit"]');
    
    // Hasil yang diharapkan: Dialihkan kembali ke dashboard dengan pesan sukses
    await expect(page).toHaveURL('/seller/dashboard');
    await expect(page.locator('text=menunggu persetujuan')).toBeVisible();
  });

  test('TC-JB-03: Buyer dapat melakukan checkout produk antik normal', async ({ page }) => {
    // Login sebagai Buyer
    await page.goto('/login');
    await page.fill('input[name="email"]', 'pembeli@example.com');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');

    // Cari Produk di Halaman Marketplace
    await page.goto('/products');
    await page.click('text=Koin Emas Kerajaan Majapahit'); // Klik produk hasil approve
    
    // Checkout produk langsung
    await expect(page.locator('text=Beli Sekarang')).toBeVisible();
    await page.click('text=Beli Sekarang');
    
    // Masuk halaman konfirmasi order
    await expect(page).toHaveURL(/\/checkout\/product\/.+/);
    
    // Jalankan transaksi pembayaran
    await page.click('button:has-text("Proses Pembayaran")');
    
    // Hasil yang diharapkan: Dialihkan ke halaman redirect URL Midtrans Snap
    await page.waitForTimeout(3000);
    const currentUrl = page.url();
    expect(currentUrl).toContain('midtrans.com'); // Memastikan terintegrasi ke payment gateway
  });
});
```

### 4.3 Script Uji Lelang: `tests/playwright/lelang.spec.js`
```javascript
const { test, expect } = require('@playwright/test');

test.describe('Fitur Lelang Vinstore', () => {

  test('TC-LE-01: Seller mengajukan barang lelang baru', async ({ page }) => {
    // Login Seller
    await page.goto('/login');
    await page.fill('input[name="email"]', 'seller@example.com');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');

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
    await page.goto('/login');
    await page.fill('input[name="email"]', 'pembeli@example.com');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');

    // Masuk ke halaman lelang aktif
    await page.goto('/auctions');
    await page.click('text=Guci Kuno Dinasti Ming');

    // Input Bid baru (Harga Awal: 10.000.000 + Increment: 500.000 = Min Bid 10.500.000)
    await page.fill('input[name="amount"]', '10600000');
    await page.click('button:has-text("Kirim Bid")');

    // Hasil yang diharapkan: Ada alert sukses dan bid baru tampil sebagai penawaran tertinggi
    await expect(page.locator('text=Penawaran berhasil diajukan')).toBeVisible();
    await expect(page.locator('text=Rp 10.600.000')).toBeVisible();
  });

  test('TC-LE-03: Sistem menolak nominal bid di bawah batas minimum', async ({ page }) => {
    // Login Buyer
    await page.goto('/login');
    await page.fill('input[name="email"]', 'pembeli2@example.com');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');

    // Buka produk lelang
    await page.goto('/auctions');
    await page.click('text=Guci Kuno Dinasti Ming');

    // Input Bid tidak valid (di bawah min bid 11.100.000 karena current price sudah 10.600.000)
    await page.fill('input[name="amount"]', '10800000');
    await page.click('button:has-text("Kirim Bid")');

    // Hasil yang diharapkan: Tampil pesan error/alert validasi dari server
    await expect(page.locator('text=Nominal bid minimal')).toBeVisible();
  });
});
```

### 4.4 Script Uji Barter: `tests/playwright/barter.spec.js`
```javascript
const { test, expect } = require('@playwright/test');

test.describe('Fitur Barter Seller-to-Seller', () => {

  test('TC-BA-01 & TC-BA-02: Alur Lengkap Pengajuan dan Penerimaan Barter', async ({ browser }) => {
    // Skenario Multi-Aktor menggunakan 2 Browser Context Berbeda secara Paralel
    
    // ----------------------------------------------------
    // AKTORKU 1: SELLER A (Pengaju Barter)
    // ----------------------------------------------------
    const contextA = await browser.newContext();
    const pageA = await contextA.newPage();
    
    await pageA.goto('/login');
    await pageA.fill('input[name="email"]', 'sellera@example.com');
    await pageA.fill('input[name="password"]', 'password123');
    await pageA.click('button[type="submit"]');
    
    // Masuk Dashboard Barter
    await pageA.goto('/seller/barter');
    
    // Ajukan Barter untuk produk milik Seller B
    await pageA.click('text=Keris Pusaka Omyang Jimbe'); // Pilih barang milik Seller B
    await pageA.selectOption('select[name="offered_product_id"]', { label: 'Pedang Katana Kuno' }); // Barang milik Seller A
    await pageA.fill('input[name="additional_cash"]', '500000'); // Tawarkan uang tambahan
    await pageA.fill('textarea[name="note"]', 'Tukar dengan katana milik saya ditambah uang tunai.');
    await pageA.click('button:has-text("Kirim Pengajuan")');
    
    // Validasi status pengajuan barter keluar
    await expect(pageA.locator('text=Pengajuan barter berhasil dikirim')).toBeVisible();
    await expect(pageA.locator('td:has-text("Keris Pusaka Omyang Jimbe")')).toBeVisible();
    
    // ----------------------------------------------------
    // AKTORKU 2: SELLER B (Penerima Barter)
    // ----------------------------------------------------
    const contextB = await browser.newContext();
    const pageB = await contextB.newPage();
    
    await pageB.goto('/login');
    await pageB.fill('input[name="email"]', 'sellerb@example.com');
    await pageB.fill('input[name="password"]', 'password123');
    await pageB.click('button[type="submit"]');
    
    // Masuk Halaman Barter untuk memproses pengajuan masuk
    await pageB.goto('/seller/barter');
    await expect(pageB.locator('text=Pedang Katana Kuno')).toBeVisible(); // Pengajuan dari Seller A
    
    // Terima Barter (Accept)
    await pageB.click('button:has-text("Accept")');
    
    // Hasil yang diharapkan: Pengajuan selesai dan status berubah menjadi disetujui (kepemilikan bertukar)
    await expect(pageB.locator('text=Barter disetujui, produk berhasil ditukarkan')).toBeVisible();
    
    await contextA.close();
    await contextB.close();
  });
});
```

### 4.5 Script Uji Tebak Harga: `tests/playwright/tebak_harga.spec.js`
```javascript
const { test, expect } = require('@playwright/test');

test.describe('Fitur Tebak Harga Vinstore', () => {

  test('TC-TH-01: Harga asli tersembunyi selama masa tebak harga aktif', async ({ page }) => {
    await page.goto('/');
    
    // Menampilkan daftar produk
    await expect(page.locator('text=Koin Kuno Yasin')).toBeVisible();
    
    // Memastikan teks harga tidak menampilkan nominal melainkan placeholder tebak harga
    await expect(page.locator('text=??? (Tebak Harga)')).toBeVisible();
    
    // Buka detail produk tebak harga
    await page.click('text=Koin Kuno Yasin');
    await expect(page.locator('text=Harga: ??? (Tebak Harga)')).toBeVisible();
  });

  test('TC-TH-02: Buyer mengirim tebakan harga', async ({ page }) => {
    // Login Buyer
    await page.goto('/login');
    await page.fill('input[name="email"]', 'pembeli@example.com');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');

    // Buka halaman produk tebak harga
    await page.goto('/products');
    await page.click('text=Koin Kuno Yasin');

    // Masukkan Tebakan Harga
    await page.fill('input[name="amount"]', '750000');
    await page.click('button:has-text("Kirim Tebakan")');

    // Hasil yang diharapkan: Muncul konfirmasi bahwa tebakan berhasil disimpan
    await expect(page.locator('text=Tebakan harga Anda berhasil dikirim')).toBeVisible();
    
    // Input form digantikan oleh status tebakan yang terkunci
    await expect(page.locator('text=Tebakan Anda sudah tersimpan')).toBeVisible();
    await expect(page.locator('input[name="amount"]')).not.toBeVisible();
  });
});
```

---

## 5. PANDUAN EKSEKUSI PENGUJIAN

Untuk menguji aplikasi menggunakan script di atas di lingkungan pengembangan local, ikuti langkah-langkah berikut:

1.  **Instalasi Playwright:**
    Jalankan perintah berikut di direktori root project:
    ```bash
    npm install -D @playwright/test
    npx playwright install
    ```

2.  **Menjalankan Seluruh Pengujian:**
    ```bash
    npx playwright test
    ```

3.  **Menjalankan Pengujian dengan Mode UI (Interactive Mode):**
    Sangat direkomendasikan untuk melihat interaksi UI berjalan secara visual:
    ```bash
    npx playwright test --ui
    ```

4.  **Membuka Laporan Hasil Pengujian (HTML Report):**
    Setelah tes selesai, Playwright akan membuat laporan komprehensif yang bisa dianalisis untuk Bab 4 Skripsi Anda.
    ```bash
    npx playwright show-report
    ```
