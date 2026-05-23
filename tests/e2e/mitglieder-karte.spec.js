import { expect, test } from './test-support.js';

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

        await expect(page.getByRole('heading', { level: 1, name: 'Mitgliederkarte' })).toBeVisible();
        const mapRegion = page.locator('[data-member-map]');
        await expect(mapRegion).toHaveAttribute('role', 'region');
        await expect(mapRegion).toHaveAttribute('aria-label', 'Mitgliederkarte');
        await expect(page.locator('#member-map-note')).toBeVisible();

    });

    test('member without enough baxx sees preview overlay and earn-cta', async ({ page }) => {
        await login(page, 'playwright-member@example.com');

        await page.goto('/mitglieder/karte');

        await expect(page.getByRole('heading', { level: 1, name: 'Mitgliederkarte' })).toBeVisible();
        await expect(page.getByText('Mitgliederkarte freischalten')).toBeVisible();
        await expect(page.locator('[data-member-map]')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Zu Baxx verdienen' })).toBeVisible();
    });
});
