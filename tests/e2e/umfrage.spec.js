import { test, expect } from '@playwright/test';
import { runArtisan } from './utils/artisan.js';

const login = async (page, email, password = 'password') => {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

test.describe('Umfragen', () => {
  test('guest: public poll is visible and can vote once', async ({ page }) => {
    await runArtisan(['db:seed', '--class=Database\\Seeders\\PollPlaywrightPublicSeeder']);

    await page.goto('/');
    await expect(page.getByRole('link', { name: 'Playwright Umfrage' }).first()).toBeVisible();

    await page.goto('/umfrage');
    await expect(page.getByRole('heading', { name: 'Playwright: Öffentliche Umfrage?' })).toBeVisible();

    await page.getByRole('radio', { name: 'Option A' }).check();
    
    // Start listening for response before clicking
    const responsePromise = page.waitForResponse(
      (response) => response.url().includes('/livewire') && response.status() === 200,
      { timeout: 15000 }
    );
    await page.getByRole('button', { name: 'Stimme abgeben' }).click();

    // Wait for Livewire to complete the request and update the DOM
    await responsePromise;
    await expect(page.getByRole('status')).toContainText('Danke! Deine Stimme wurde gespeichert.', { timeout: 10000 });

    await page.reload();
    await expect(page.getByRole('status')).toContainText('Von dieser IP wurde bereits abgestimmt.');
  });

  test('guest: internal poll requires login', async ({ page }) => {
    await runArtisan(['db:seed', '--class=Database\\Seeders\\PollPlaywrightInternalSeeder']);

    await page.goto('/umfrage');

    await expect(page.getByRole('heading', { name: 'Playwright: Interne Umfrage?' })).toBeVisible();
    await expect(page.getByRole('status')).toContainText('Bitte logge dich ein');
    await expect(page.getByRole('button', { name: 'Stimme abgeben' })).toBeDisabled();
  });

  test('member: internal poll can be voted once', async ({ page }) => {
    await runArtisan(['db:seed', '--class=Database\\Seeders\\PollPlaywrightInternalSeeder']);

    await login(page, 'playwright-member@example.com');

    await page.goto('/umfrage');
    await expect(page.getByRole('heading', { name: 'Playwright: Interne Umfrage?' })).toBeVisible();

    await page.getByRole('radio', { name: 'Ja' }).check();
    
    // Start listening for response before clicking
    const responsePromise = page.waitForResponse(
      (response) => response.url().includes('/livewire') && response.status() === 200,
      { timeout: 15000 }
    );
    await page.getByRole('button', { name: 'Stimme abgeben' }).click();

    // Wait for Livewire to complete the request and update the DOM
    await responsePromise;
    await expect(page.getByRole('status')).toContainText('Danke! Deine Stimme wurde gespeichert.', { timeout: 10000 });

    await page.reload();
    await expect(page.getByRole('status')).toContainText('Du hast bereits an dieser Umfrage teilgenommen.');
  });

  test('guest: admin poll management redirects to login', async ({ page }) => {
    await page.goto('/admin/umfragen');
    await expect(page).toHaveURL(/\/login$/);
  });
});
