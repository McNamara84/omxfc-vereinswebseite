import { test, expect } from '@playwright/test';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button[type="submit"]'),
    ]);
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
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            onlineCheckbox.check(),
        ]);
        await expect(page).toHaveURL(/filters%5B%5D=online/);
        await expect(page.locator('[data-members-table]')).toHaveAttribute('data-members-filter-online', 'true');
        await expect(page.locator('[data-members-summary]')).toContainText('nur Mitglieder angezeigt, die aktuell online sind');

        const roleHeader = page.getByRole('columnheader', { name: 'Rolle' });
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            roleHeader.click(),
        ]);
        await expect(page).toHaveURL(/sort=role&dir=asc/);
        await expect(roleHeader).toHaveAttribute('aria-sort', 'ascending');

        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            roleHeader.click(),
        ]);
        await expect(page).toHaveURL(/sort=role&dir=desc/);
        await expect(roleHeader).toHaveAttribute('aria-sort', 'descending');

        await expect(page.getByRole('button', { name: 'CSV Export' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'E-Mail-Adressen kopieren' })).toBeVisible();
    });

    test('admin can copy a single member email address', async ({ page }) => {
        await page.addInitScript(() => {
            window.__copiedText = null;

            // Fallback path in our implementation uses prompt.
            window.prompt = (_message, value) => {
                window.__copiedText = value;
                return value;
            };

            // Primary path uses Clipboard API.
            try {
                Object.defineProperty(navigator, 'clipboard', {
                    configurable: true,
                    value: {
                        writeText: async (text) => {
                            window.__copiedText = text;
                        },
                    },
                });
            } catch {
                // If clipboard is not configurable in a given browser, prompt stub still covers the fallback.
            }
        });

        await login(page, 'info@maddraxikon.com');

        await page.goto('/mitglieder');
        await expect(page).toHaveURL(/\/mitglieder$/);

        const firstRow = page.locator('[data-members-table] tbody tr').first();
        await expect(firstRow).toBeVisible();

        await firstRow.getByRole('button', { name: 'Info' }).click();
        const detailsPopover = firstRow.locator('div.absolute').first();
        await expect(detailsPopover).toBeVisible();

        const email = (await detailsPopover.locator('div.text-sm').first().textContent())?.trim();
        expect(email).toBeTruthy();
        expect(email).toContain('@');

        await firstRow.locator('[data-copy-email]').first().click();

        await page.waitForFunction(() => window.__copiedText !== null);
        const copied = await page.evaluate(() => window.__copiedText);

        expect(copied).toBe(email);
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
