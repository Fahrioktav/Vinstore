import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.describe('Fitur Barter Seller-to-Seller', () => {

  test('TC-BA-01 & TC-BA-02: Alur Lengkap Pengajuan dan Penerimaan Barter', async ({ browser }) => {
    // Skenario Multi-Aktor menggunakan 2 Browser Context Berbeda secara Paralel
    
    // ----------------------------------------------------
    // AKTORKU 1: SELLER A (Pengaju Barter)
    // ----------------------------------------------------
    const contextA = await browser.newContext();
    const pageA = await contextA.newPage();
    
    await loginAs(pageA, 'sellera@example.com', '/seller/dashboard');
    
    // Masuk Dashboard Barter
    await pageA.goto('/seller/barter');
    
    // Ajukan Barter untuk produk milik Seller B
    const kerisCard = pageA.locator('div:has(h3:has-text("Keris Pusaka Omyang Jimbe"))').first();
    await kerisCard.getByRole('button', { name: 'Ajukan Barter' }).first().click();
    await pageA.getByRole('combobox').selectOption({ index: 0 }); // Barang milik Seller A
    await pageA.getByRole('spinbutton').fill('500000'); // Tawarkan uang tambahan
    await pageA.getByPlaceholder('Sampaikan pesan untuk seller...').fill('Tukar dengan katana milik saya ditambah uang tunai.');
    await pageA.getByRole('button', { name: 'Kirim Pengajuan' }).click();
    
    // Validasi status pengajuan barter keluar
    await pageA.getByRole('button', { name: /Permintaan Saya/ }).click();
    await expect(pageA.locator('text=Keris Pusaka Omyang Jimbe')).toBeVisible();
    
    // ----------------------------------------------------
    // AKTORKU 2: SELLER B (Penerima Barter)
    // ----------------------------------------------------
    const contextB = await browser.newContext();
    const pageB = await contextB.newPage();
    
    await loginAs(pageB, 'sellerb@example.com', '/seller/dashboard');
    
    // Masuk Halaman Barter untuk memproses pengajuan masuk
    await pageB.goto('/seller/barter');
    await pageB.getByRole('button', { name: /Permintaan Masuk/ }).click();
    await expect(pageB.locator('text=Pedang Katana Kuno')).toBeVisible(); // Pengajuan dari Seller A
    
    // Terima Barter
    await pageB.getByRole('button', { name: 'Setujui' }).click();
    
    // Hasil yang diharapkan: Pengajuan selesai dan status berubah menjadi disetujui (kepemilikan bertukar)
    await expect(pageB.locator('text=Barter disetujui').first()).toBeVisible({ timeout: 20000 });
    
    await contextA.close();
    await contextB.close();
  });
});
