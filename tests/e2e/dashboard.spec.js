import { test, expect } from '@playwright/test';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

test.describe('Dashboard overview', () => {
    test('admin sees dashboard insights, applicants and verification card', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');

        await page.goto('/dashboard');

        await expect(page).toHaveURL(/\/dashboard$/);
        const cards = page.locator('div[aria-label="Überblick wichtiger Community-Kennzahlen"] [role="region"]');
        await expect(cards).toHaveCount(6);

        await expect(page.getByText('Mitgliedsanträge')).toBeVisible();
        await expect(page.getByRole('row', { name: /Playwright Anwärter/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /Auf Verifizierung wartende Challenges/i })).toBeVisible();

        const topUsers = page.locator('[data-dashboard-top-users]');
        await expect(topUsers).toBeVisible();
        await expect(topUsers).toHaveAttribute('aria-label', /Top 3 Baxx-Sammler/i);
        await expect(topUsers.locator('[data-dashboard-top-summary]')).toContainText(/Top 3 Baxx-Sammler/i);
    });

    test('member sees dashboard without applicant management', async ({ page }) => {
        await login(page, 'playwright-member@example.com');

        await page.goto('/dashboard');

        await expect(page.locator('.card:has-text("Mitgliedsanträge")')).toHaveCount(0);
        await expect(page.getByRole('link', { name: /Auf Verifizierung wartende Challenges/i })).toHaveCount(0);

        const cards = page.locator('div[aria-label="Überblick wichtiger Community-Kennzahlen"] [role="region"]');
        await expect(cards).toHaveCount(6);
        await expect(page.locator('[data-dashboard-top-summary]')).toContainText(/Top 3 Baxx-Sammler/i);
    });
});
