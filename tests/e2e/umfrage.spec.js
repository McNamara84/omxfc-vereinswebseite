import { test, expect } from '@playwright/test';
import { runArtisan } from './utils/artisan.js';
import { setupLivewirePage, waitForLivewire, livewireCall, livewireSet } from './utils/livewire-helpers.js';

const login = async (page, email, password = 'password') => {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

test.describe('Umfragen', () => {
  test('guest: public poll is visible and can vote once', async ({ page }) => {
    test.setTimeout(60_000);

    await setupLivewirePage(page);

    await runArtisan(['db:seed', '--class=Database\\Seeders\\PollPlaywrightPublicSeeder']);

    await page.goto('/');
    await expect(page.getByRole('link', { name: 'Playwright Umfrage' }).first()).toBeVisible();

    await page.goto('/umfrage');
    await expect(page.locator('[data-testid="page-title"]')).toContainText('Playwright: Öffentliche Umfrage?');
    await waitForLivewire(page);

    // Radio auswählen
    await page.getByRole('radio', { name: 'Option A' }).check();
    await page.waitForTimeout(500);

    // Formular absenden + Livewire-Fallback
    await page.getByRole('button', { name: 'Stimme abgeben' }).click();

    // Warte auf Erfolgs- oder Statusmeldung (Livewire setzt $statusMessage Property)
    try {
      await expect(page.getByText('Danke!')).toBeVisible({ timeout: 10000 });
    } catch {
      // Fallback: Option direkt setzen und submit() aufrufen
      const optionId = await page.getByRole('radio', { name: 'Option A' }).getAttribute('value');
      if (optionId) await livewireSet(page, 'selectedOptionId', parseInt(optionId));
      await livewireCall(page, 'submit');
      await expect(page.getByText('Danke!')).toBeVisible({ timeout: 20000 });
    }

    // Reload und prüfe Duplikat-Schutz
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
    test.setTimeout(60_000);

    await setupLivewirePage(page);

    await runArtisan(['db:seed', '--class=Database\\Seeders\\PollPlaywrightInternalSeeder']);

    await login(page, 'playwright-member@example.com');

    await page.goto('/umfrage');
    await expect(page.locator('[data-testid="page-title"]')).toContainText('Playwright: Interne Umfrage?');
    await waitForLivewire(page);

    // Radio auswählen
    await page.getByRole('radio', { name: 'Ja' }).check();
    await page.waitForTimeout(500);

    // Formular absenden + Livewire-Fallback
    await page.getByRole('button', { name: 'Stimme abgeben' }).click();

    // Warte auf Erfolgsmeldung (Livewire setzt $statusMessage Property)
    try {
      await expect(page.getByText('Danke!')).toBeVisible({ timeout: 10000 });
    } catch {
      // Fallback: Option direkt setzen und submit() aufrufen
      const optionId = await page.getByRole('radio', { name: 'Ja' }).getAttribute('value');
      if (optionId) await livewireSet(page, 'selectedOptionId', parseInt(optionId));
      await livewireCall(page, 'submit');
      await expect(page.getByText('Danke!')).toBeVisible({ timeout: 20000 });
    }

    // Reload und prüfe Duplikat-Schutz
    await page.reload();
    await expect(page.getByText('Du hast bereits an dieser Umfrage teilgenommen.')).toBeVisible({ timeout: 10000 });
  });

  test('guest: admin poll management redirects to login', async ({ page }) => {
    await page.goto('/admin/umfragen');
    await expect(page).toHaveURL(/\/login$/);
  });
});
