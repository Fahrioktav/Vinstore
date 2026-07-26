import { expect } from '@playwright/test';

export async function loginAs(page, email, expectedUrl = /\/$/) {
  await page.goto('/login');
  await page.fill('input[name="login"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.getByRole('button', { name: 'Masuk Sekarang' }).click();
  await expect(page).toHaveURL(expectedUrl, { timeout: 20000 });
}
