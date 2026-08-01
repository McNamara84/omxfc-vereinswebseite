import { expect, test } from './test-support.js';

const login = async (page, email, password = 'password') => {
    await Promise.all([
        page.route('https://fonts.bunny.net/**', route => route.abort()),
        page.route('https://cdnjs.cloudflare.com/**', route => route.abort()),
    ]);

    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await Promise.all([
        page.waitForURL(url => !url.pathname.endsWith('/login')),
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
        test.setTimeout(60_000);

        await login(page, 'playwright-map-locked@example.com');

        await page.goto('/mitglieder/karte');

        await expect(page.getByRole('heading', { level: 1, name: 'Mitgliederkarte' })).toBeVisible();
        await expect(page.getByText('Mitgliederkarte freischalten')).toBeVisible();
        await expect(page.locator('[data-member-map]')).toBeVisible();
        await expect(page.getByText(/Dir fehlen aktuell \d+ Baxx/)).toBeVisible();

        const earnBaxxCta = page.getByTestId('member-map-earn-baxx-cta');
        await expect(earnBaxxCta).toBeVisible();
        await expect(earnBaxxCta).toHaveRole('link');
        await expect(earnBaxxCta).toHaveAccessibleName('Zu Baxx verdienen');
        await expect(earnBaxxCta).toHaveAttribute('href', /\/aufgaben$/);
    });
});
