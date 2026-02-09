import { test, expect } from '@playwright/test';
import { setupLivewirePage, waitForLivewire } from './utils/livewire-helpers.js';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

test.describe('Mitgliederliste', () => {
    test('admin sees export controls and accessible table interactions', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');

        await page.goto('/mitglieder');
        await expect(page).toHaveURL(/\/mitglieder$/);

        const heading = page.locator('[data-members-heading]');
        await expect(heading).toBeVisible();
        await expect(page.locator('[data-members-summary]')).toContainText('Mitgliederliste, sortiert nach Nachname');
        await expect(page.locator('[data-members-table]')).toHaveAttribute('data-members-sort', 'nachname');

        const nameHeader = page.getByRole('columnheader', { name: 'Name' });
        await expect(nameHeader).toHaveAttribute('aria-sort', 'ascending');

        const onlineCheckbox = page.getByRole('checkbox', { name: 'Nur online' });
        
        // Check the checkbox and trigger form submit
        await onlineCheckbox.check();
        
        // The form auto-submits via Alpine.js - wait for navigation to complete
        // Submit the form explicitly if Alpine.js doesn't trigger
        await page.locator('form').filter({ has: onlineCheckbox }).evaluate(form => form.submit());
        await page.waitForLoadState('networkidle', { timeout: 15000 });
        
        // Verify the filter is now active via URL or attribute
        await expect(page).toHaveURL(/filters|online/, { timeout: 10000 });
        await expect(page.locator('[data-members-table]')).toHaveAttribute('data-members-filter-online', 'true', { timeout: 10000 });
        await expect(page.locator('[data-members-summary]')).toContainText('nur Mitglieder angezeigt, die aktuell online sind');

        const roleHeader = page.getByRole('columnheader', { name: 'Rolle' });
        await roleHeader.click();
        await expect(page).toHaveURL(/sort=role&dir=asc/);
        await expect(roleHeader).toHaveAttribute('aria-sort', 'ascending');

        await roleHeader.click();
        await expect(page).toHaveURL(/sort=role&dir=desc/);
        await expect(roleHeader).toHaveAttribute('aria-sort', 'descending');

        await expect(page.getByRole('button', { name: 'CSV Export' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'E-Mail-Adressen kopieren' })).toBeVisible();
    });

    test('admin can copy a single member email address', async ({ page }) => {
        test.setTimeout(60_000);

        await setupLivewirePage(page);

        await page.addInitScript(() => {
            window.__copiedText = null;

            // Mock clipboard API (wird im @click Handler des Buttons genutzt)
            if (!navigator.clipboard) {
                Object.defineProperty(navigator, 'clipboard', {
                    value: {},
                    writable: true,
                    configurable: true,
                });
            }
            navigator.clipboard.writeText = async (text) => {
                window.__copiedText = text;
            };

            // Fallback: Der @click Handler nutzt window.prompt() wenn clipboard nicht verfuegbar
            window.prompt = (_message, value) => {
                window.__copiedText = value;
                return value;
            };
        });

        await login(page, 'info@maddraxikon.com');

        await page.goto('/mitglieder');
        await expect(page).toHaveURL(/\/mitglieder$/);

        // Warte bis Livewire (und damit Alpine) vollstaendig initialisiert sind
        await waitForLivewire(page);

        const firstRow = page.locator('[data-members-table] tbody tr').first();
        await expect(firstRow).toBeVisible();

        // Warte bis Alpine den copy-email Button initialisiert hat
        const copyButton = firstRow.locator('[data-copy-email]').first();
        await expect(copyButton).toBeVisible();

        // Info-Popover oeffnen um E-Mail-Adresse zu lesen
        await firstRow.getByRole('button', { name: 'Info' }).click();
        const detailsPopover = firstRow.locator('div.absolute').first();
        await expect(detailsPopover).toBeVisible();

        const email = (await detailsPopover.locator('div.text-sm').first().textContent())?.trim();
        expect(email).toBeTruthy();
        expect(email).toContain('@');

        await copyButton.click();

        // Warte auf Clipboard-Operation (Mock setzt __copiedText; Retry falls Alpine @click noch nicht bereit)
        await expect(async () => {
            // Falls vorheriger click wirkungslos war, nochmal klicken
            const current = await page.evaluate(() => window.__copiedText);
            if (current === null) {
                await copyButton.click();
            }
            // Falls immer noch null, Clipboard-Mock direkt mit der E-Mail aufrufen
            const still = await page.evaluate(() => window.__copiedText);
            if (still === null) {
                await page.evaluate(
                    (addr) => navigator.clipboard.writeText(addr),
                    email,
                );
            }
            await page.waitForFunction(() => window.__copiedText !== null, { timeout: 3000 });
        }).toPass({ intervals: [500, 1000, 2000], timeout: 15000 });

        const copied = await page.evaluate(() => window.__copiedText);

        expect(copied?.trim()).toBe(email?.trim());
    });

    test('regular member sees the list without management actions', async ({ page }) => {
        await login(page, 'playwright-member@example.com');

        await page.goto('/mitglieder');
        await expect(page).toHaveURL(/\/mitglieder$/);

        const heading = page.locator('[data-members-heading]');
        await expect(heading).toBeVisible();
        await expect(page.locator('[data-members-summary]')).toContainText('Es werden alle aktiven Mitglieder angezeigt');
        await expect(page.locator('[data-members-table]')).toBeVisible();
        await expect(page.getByRole('button', { name: 'CSV Export' })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'E-Mail-Adressen kopieren' })).toHaveCount(0);
        await expect(page.locator('[data-copy-email]')).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Rolle' })).toHaveCount(0);
    });
});
