import { expect, test } from '@playwright/test';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button[type="submit"]'),
    ]);
};

test.describe('Mitgliederkarte', () => {
    test('admin sees accessible map view with legend and popup', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');

        await page.goto('/mitglieder/karte');

        await expect(page.locator('[data-testid="page-title"]')).toContainText('Mitgliederkarte');
        const mapRegion = page.locator('[data-member-map]');
        await expect(mapRegion).toHaveAttribute('role', 'region');
        await expect(mapRegion).toHaveAttribute('aria-label', 'Mitgliederkarte');
        await expect(page.locator('#member-map-note')).toBeVisible();

    });

    test('member without points sees locked message', async ({ page }) => {
        await login(page, 'playwright-member@example.com');

        await page.goto('/mitglieder/karte');

        await expect(page.locator('[data-testid="page-title"]')).toContainText('Mitgliederkarte');
        await expect(page.getByText('Karte noch nicht verfügbar')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Zu den verfügbaren Challenges' })).toBeVisible();
    });
});
