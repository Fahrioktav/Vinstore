# Hasil Blackbox Testing Marketplace Vinstore

## Ringkasan Pengujian

Pengujian blackbox dilakukan untuk memvalidasi fitur utama marketplace Vinstore dari sudut pandang pengguna akhir. Pengujian dijalankan menggunakan Playwright dengan browser Chromium terhadap aplikasi Laravel yang berjalan secara lokal.

Hasil akhir pengujian menunjukkan bahwa seluruh skenario yang diuji berhasil dijalankan.

```text
Total test case : 9
Berhasil        : 9
Gagal           : 0
Status          : Passed
Durasi          : 2.6 menit
```

## Lingkungan Pengujian

| Komponen | Keterangan |
|---|---|
| Aplikasi | Vinstore Marketplace |
| Framework Backend | Laravel |
| Framework Frontend | React + Inertia |
| Tools Testing | Playwright |
| Browser | Chromium |
| Server Lokal | `http://127.0.0.1:8001` |
| Database | MySQL lokal |
| Tanggal Pengujian | 18 Juli 2026 |

## Perintah Pengujian

Sebelum pengujian dijalankan, data khusus testing disiapkan menggunakan script seed Playwright.

```powershell
npm.cmd run test:e2e:seed
```

Setelah data testing siap, seluruh test case dijalankan menggunakan perintah berikut.

```powershell
$env:PLAYWRIGHT_BASE_URL='http://127.0.0.1:8001'
npm.cmd run test:e2e -- --reporter=line --timeout=60000
```

## Hasil Eksekusi

Output akhir dari Playwright:

```text
Running 9 tests using 1 worker

[1/9] barter.spec.js - TC-BA-01 & TC-BA-02
[2/9] jual_beli.spec.js - TC-JB-01
[3/9] jual_beli.spec.js - TC-JB-02
[4/9] jual_beli.spec.js - TC-JB-03
[5/9] lelang.spec.js - TC-LE-01
[6/9] lelang.spec.js - TC-LE-02
[7/9] lelang.spec.js - TC-LE-03
[8/9] tebak_harga.spec.js - TC-TH-01
[9/9] tebak_harga.spec.js - TC-TH-02

9 passed (2.6m)
```

## Daftar Test Case

| Kode | Fitur | Skenario Pengujian | Hasil |
|---|---|---|---|
| TC-BA-01 | Barter | Seller A mengajukan barter terhadap produk milik Seller B | Passed |
| TC-BA-02 | Barter | Seller B menyetujui pengajuan barter dari Seller A | Passed |
| TC-JB-01 | Jual Beli | User baru melakukan registrasi akun | Passed |
| TC-JB-02 | Produk Seller | Seller mengajukan produk baru untuk divalidasi | Passed |
| TC-JB-03 | Checkout | Buyer melakukan pembelian produk normal dan diarahkan ke payment gateway | Passed |
| TC-LE-01 | Lelang | Seller mengajukan barang lelang baru | Passed |
| TC-LE-02 | Lelang | Buyer mengirim bid dengan nominal valid | Passed |
| TC-LE-03 | Lelang | Sistem menolak bid yang berada di bawah minimal kenaikan | Passed |
| TC-TH-01 | Tebak Harga | Harga asli produk tebak harga disembunyikan selama periode aktif | Passed |
| TC-TH-02 | Tebak Harga | Buyer mengirim tebakan harga pada produk tebak harga | Passed |

## Detail Hasil Pengujian

### TC-BA-01 - Seller Mengajukan Barter

Pengujian dilakukan dengan login sebagai Seller A, membuka halaman barter, memilih produk milik Seller B, memilih produk milik Seller A sebagai penawaran, mengisi tambahan uang, lalu mengirim pengajuan barter.

Hasil pengujian menunjukkan bahwa sistem berhasil menyimpan pengajuan barter dan menampilkannya pada tab permintaan milik Seller A.

Status: Passed.

### TC-BA-02 - Seller Menyetujui Barter

Pengujian dilanjutkan dengan login sebagai Seller B, membuka tab permintaan masuk, lalu menyetujui pengajuan barter dari Seller A.

Hasil pengujian menunjukkan bahwa sistem berhasil memproses persetujuan barter.

Status: Passed.

### TC-JB-01 - Registrasi User Baru

Pengujian dilakukan dengan membuka halaman registrasi, mengisi data user baru, lalu mengirim form registrasi.

Hasil pengujian menunjukkan bahwa sistem berhasil membuat akun baru dan mengarahkan user ke halaman login dengan pesan pendaftaran berhasil.

Status: Passed.

### TC-JB-02 - Seller Mengajukan Produk Baru

Pengujian dilakukan dengan login sebagai seller, membuka halaman tambah produk, mengisi data produk, memilih kategori, mengunggah gambar dan sertifikat, lalu menyimpan produk.

Hasil pengujian menunjukkan bahwa produk berhasil dibuat dan tampil pada dashboard seller dengan status menunggu validasi.

Status: Passed.

### TC-JB-03 - Checkout Produk Normal

Pengujian dilakukan dengan login sebagai buyer, membuka halaman produk, memilih produk normal, lalu menekan tombol beli.

Hasil pengujian menunjukkan bahwa aplikasi berhasil menjalankan proses checkout dan mencoba mengarahkan user ke halaman payment gateway Midtrans.

Status: Passed.

Catatan: Pada environment lokal atau environment tanpa akses penuh ke URL eksternal, browser Playwright dapat menampilkan `chrome-error://chromewebdata/` ketika membuka redirect eksternal. Dalam pengujian ini, kondisi tersebut tetap dianggap valid karena aplikasi sudah menjalankan proses redirect keluar dari halaman lokal menuju payment gateway.

### TC-LE-01 - Seller Mengajukan Barang Lelang

Pengujian dilakukan dengan login sebagai seller, membuka form tambah barang lelang, mengisi nama barang, deskripsi, foto, harga awal, minimal kenaikan bid, tanggal mulai, dan tanggal selesai.

Hasil pengujian menunjukkan bahwa barang lelang berhasil diajukan dan masuk ke alur persetujuan admin.

Status: Passed.

### TC-LE-02 - Buyer Mengirim Bid Valid

Pengujian dilakukan dengan login sebagai buyer, membuka halaman lelang aktif, lalu mengirim nominal bid yang lebih tinggi dari harga tertinggi saat ini ditambah minimal kenaikan.

Hasil pengujian menunjukkan bahwa bid berhasil disimpan dan harga tertinggi lelang berubah sesuai nominal bid.

Status: Passed.

### TC-LE-03 - Sistem Menolak Bid Tidak Valid

Pengujian dilakukan dengan login sebagai buyer lain, membuka halaman lelang yang sama, lalu mengirim nominal bid di bawah batas minimal kenaikan.

Hasil pengujian menunjukkan bahwa bid tidak diterima. Harga tertinggi tetap tidak berubah dan buyer tersebut tidak masuk ke riwayat bid.

Status: Passed.

### TC-TH-01 - Harga Tebak Harga Disembunyikan

Pengujian dilakukan dengan membuka produk yang menggunakan mekanisme tebak harga pada periode aktif.

Hasil pengujian menunjukkan bahwa harga asli produk tidak ditampilkan kepada user. Sistem hanya menampilkan placeholder harga berupa tanda tanya.

Status: Passed.

### TC-TH-02 - Buyer Mengirim Tebakan Harga

Pengujian dilakukan dengan login sebagai buyer, membuka produk tebak harga, mengisi nominal tebakan, lalu mengirim tebakan harga.

Hasil pengujian menunjukkan bahwa tebakan berhasil disimpan dan form tebakan berubah menjadi status bahwa tebakan user sudah tersimpan.

Status: Passed.

## Kesimpulan

Berdasarkan hasil pengujian blackbox menggunakan Playwright, seluruh fitur utama yang diuji pada marketplace Vinstore berjalan sesuai skenario. Fitur registrasi, pengajuan produk, checkout, barter, lelang, dan tebak harga berhasil dijalankan dari sisi pengguna akhir.

Dengan hasil 9 test case berhasil dari total 9 test case, maka fitur yang diuji dapat dinyatakan valid berdasarkan skenario blackbox testing yang telah dibuat.

