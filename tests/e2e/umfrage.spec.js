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
    // DEBUG: Listen for page errors and console errors
    page.on('pageerror', (error) => {
      console.log('[DEBUG] Public poll - Page error:', error.message);
    });
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        console.log('[DEBUG] Public poll - Console error:', msg.text());
      }
    });
    
    await runArtisan(['db:seed', '--class=Database\\Seeders\\PollPlaywrightPublicSeeder']);

    await page.goto('/');
    await expect(page.getByRole('link', { name: 'Playwright Umfrage' }).first()).toBeVisible();

    await page.goto('/umfrage');
    await expect(page.locator('[data-testid="page-title"]')).toContainText('Playwright: Öffentliche Umfrage?');

    // Warte auf Livewire-Initialisierung (nötig damit wire:submit.prevent funktioniert)
    await page.waitForFunction(() => typeof window.Livewire !== 'undefined', { timeout: 15000 });

    await page.getByRole('radio', { name: 'Option A' }).check();
    
    // DEBUG: Check Livewire/Alpine status before submit
    const statusBefore = await page.evaluate(() => {
        return {
            livewireExists: typeof window.Livewire !== 'undefined',
            alpineExists: typeof window.Alpine !== 'undefined',
            alpineVersion: window.Alpine?.version ?? 'unknown',
            formAction: document.querySelector('form')?.getAttribute('wire:submit.prevent'),
        };
    });
    console.log('[DEBUG] Livewire/Alpine status before submit:', JSON.stringify(statusBefore));
    
    // Start listening for response before clicking
    const responsePromise = page.waitForResponse(
      (response) => response.url().includes('/livewire') && response.status() === 200,
      { timeout: 15000 }
    ).catch((err) => {
      console.log('[DEBUG] No Livewire response received within timeout');
      return null;
    });
    
    await page.getByRole('button', { name: 'Stimme abgeben' }).click();

    // Wait for Livewire to complete the request
    const response = await responsePromise;
    console.log('[DEBUG] Livewire response received:', response ? 'yes' : 'no');
    
    // DEBUG: Wait and check page state
    await page.waitForTimeout(1000);
    const pageState = await page.evaluate(() => {
        const statusEl = document.querySelector('#poll-status-message, [role="status"]');
        return {
            statusText: statusEl?.textContent?.trim(),
            pageText: document.body.innerText.substring(0, 2000),
            hasSuccessMessage: document.body.innerText.includes('Danke'),
        };
    });
    console.log('[DEBUG] Page state after submit:', JSON.stringify(pageState));
    
    // Wait for the page to update - the success message should appear somewhere on the page
    await expect(page.getByText('Danke! Deine Stimme wurde gespeichert.')).toBeVisible({ timeout: 15000 }).catch(async (err) => {
        await page.screenshot({ path: 'test-results/umfrage-public-vote-debug.png', fullPage: true });
        console.log('[DEBUG] Screenshot saved to test-results/umfrage-public-vote-debug.png');
        const html = await page.content();
        console.log('[DEBUG] Page HTML (first 3000 chars):', html.substring(0, 3000));
        throw err;
    });

    await page.reload();
    await expect(page.getByText('Von dieser IP wurde bereits abgestimmt.')).toBeVisible({ timeout: 10000 });
  });

  test('guest: internal poll requires login', async ({ page }) => {
    await runArtisan(['db:seed', '--class=Database\\Seeders\\PollPlaywrightInternalSeeder']);

    await page.goto('/umfrage');

    // maryUI x-header rendert title als h1 mit data-testid
    await expect(page.locator('[data-testid="page-title"]')).toContainText('Playwright: Interne Umfrage?');
    await expect(page.getByRole('status')).toContainText('Bitte logge dich ein');
    await expect(page.getByRole('button', { name: 'Stimme abgeben' })).toBeDisabled();
  });

  test('member: internal poll can be voted once', async ({ page }) => {
    // DEBUG: Listen for page errors and console errors
    page.on('pageerror', (error) => {
      console.log('[DEBUG] Member poll - Page error:', error.message);
    });
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        console.log('[DEBUG] Member poll - Console error:', msg.text());
      }
    });
    
    await runArtisan(['db:seed', '--class=Database\\Seeders\\PollPlaywrightInternalSeeder']);

    await login(page, 'playwright-member@example.com');

    await page.goto('/umfrage');
    await expect(page.locator('[data-testid="page-title"]')).toContainText('Playwright: Interne Umfrage?');

    // Warte auf Livewire-Initialisierung (nötig damit wire:submit.prevent funktioniert)
    await page.waitForFunction(() => typeof window.Livewire !== 'undefined', { timeout: 15000 });

    await page.getByRole('radio', { name: 'Ja' }).check();
    
    // DEBUG: Check Livewire/Alpine status before submit
    const statusBefore = await page.evaluate(() => {
        return {
            livewireExists: typeof window.Livewire !== 'undefined',
            alpineExists: typeof window.Alpine !== 'undefined',
            alpineVersion: window.Alpine?.version ?? 'unknown',
            formAction: document.querySelector('form')?.getAttribute('wire:submit.prevent'),
        };
    });
    console.log('[DEBUG] Member vote - Livewire/Alpine status:', JSON.stringify(statusBefore));
    
    // Start listening for response before clicking
    const responsePromise = page.waitForResponse(
      (response) => response.url().includes('/livewire') && response.status() === 200,
      { timeout: 15000 }
    ).catch((err) => {
      console.log('[DEBUG] No Livewire response received within timeout');
      return null;
    });
    
    await page.getByRole('button', { name: 'Stimme abgeben' }).click();

    // Wait for Livewire to complete the request
    const response = await responsePromise;
    console.log('[DEBUG] Member vote - Livewire response received:', response ? 'yes' : 'no');
    
    // DEBUG: Check page state after submit
    await page.waitForTimeout(1000);
    const pageState = await page.evaluate(() => {
        return {
            statusText: document.querySelector('#poll-status-message')?.textContent?.trim(),
            hasSuccessMessage: document.body.innerText.includes('Danke'),
            bodyTextStart: document.body.innerText.substring(0, 1000),
        };
    });
    console.log('[DEBUG] Member vote - Page state:', JSON.stringify(pageState));
    
    // Wait for the success message to appear somewhere on the page
    await expect(page.getByText('Danke! Deine Stimme wurde gespeichert.')).toBeVisible({ timeout: 15000 }).catch(async (err) => {
        await page.screenshot({ path: 'test-results/umfrage-member-vote-debug.png', fullPage: true });
        console.log('[DEBUG] Screenshot saved to test-results/umfrage-member-vote-debug.png');
        throw err;
    });

    await page.reload();
    await expect(page.getByText('Du hast bereits an dieser Umfrage teilgenommen.')).toBeVisible({ timeout: 10000 });
  });

  test('guest: admin poll management redirects to login', async ({ page }) => {
    await page.goto('/admin/umfragen');
    await expect(page).toHaveURL(/\/login$/);
  });
});
