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
        await expect(page.getByRole('button', { name: 'Rolle' })).toHaveCount(0);
    });
});
