import { test, expect } from './test-support.js';
import { runArtisan } from './utils/artisan.js';

test('team switch preserves the distinction between active-team and club permissions', async ({ page }) => {
    test.setTimeout(60_000);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await runArtisan(['db:seed', '--class=Database\\Seeders\\MembershipContextPlaywrightSeeder', '--force']);
    await page.goto('/login');
    await page.locator('input[name=email]').fill('membership-context@example.com');
    await page.locator('input[name=password]').fill('password');
    await page.locator('input[name=password]').press('Enter');
    await expect(page).toHaveURL(/\/dashboard$/);
    const sidebar = page.getByTestId('member-sidebar-navigation');
    await sidebar.getByText('Vorstand', { exact: true }).click();
    await expect(sidebar.getByRole('link', { name: 'Verwaltungsstatistik', exact: true })).toBeVisible();
    const token = await page.locator('meta[name=csrf-token]').getAttribute('content');
    const switched = await page.request.put('/current-team', {
        data: { team_id: 901901 },
        headers: { 'X-CSRF-TOKEN': token },
    });
    expect(switched.ok()).toBe(true);
    await page.reload();
    await expect(page).toHaveURL(/\/dashboard$/);
    await sidebar.getByText('Vorstand', { exact: true }).click();
    await expect(sidebar.getByRole('link', { name: 'Verwaltungsstatistik', exact: true })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: 'Veranstaltungen verwalten', exact: true })).toBeVisible();
    expect((await page.request.get('/admin/statistiken')).status()).toBe(403);
    expect((await page.request.get('/admin/veranstaltungen')).status()).toBe(200);
});
