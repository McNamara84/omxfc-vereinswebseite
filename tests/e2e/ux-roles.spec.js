import fs from 'node:fs';
import path from 'node:path';
import AxeBuilder from '@axe-core/playwright';
import { test, expect } from './test-support.js';

const capture = process.env.UX_CAPTURE_SCREENSHOTS === '1';
const directory = path.resolve('docs/UX-Screenshots');
test.beforeEach(async ({ page }) => {
    // Keep preview avatars reproducible without a third-party network dependency.
    await page.route('https://ui-avatars.com/**', route => {
        const name = new URL(route.request().url()).searchParams.get('name') ?? 'OM';
        const initials = name.split(/\s+/).slice(0, 2).map(part => part[0] ?? '').join('').replace(/[^\p{L}\p{N}]/gu, '').toUpperCase();
        return route.fulfill({ contentType: 'image/svg+xml', body: `<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96"><rect width="96" height="96" rx="48" fill="#ead7c4"/><text x="48" y="60" text-anchor="middle" font-family="sans-serif" font-size="32" fill="#7d3823">${initials}</text></svg>` });
    });
});
const login = async (page, role) => {
    await page.goto('/login');
    await page.locator('input[name=email]').fill(`ux-${role}@example.com`);
    await page.locator('input[name=password]').fill('password');
    await page.locator('input[name=password]').press('Enter');
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByTestId('dashboard-quick-actions')).toBeVisible();
};
const screenshot = async (page, name, fullPage = false) => {
    if (!capture) return;
    fs.mkdirSync(directory, { recursive: true });
    await page.mouse.move(0, 0);
    await page.screenshot({ path: path.join(directory, `${name}.png`), fullPage, animations: 'disabled' });
};
const axe = async page => {
    const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
        .exclude('#nprogress [role="bar"]').analyze();
    expect(result.violations, result.violations.map(item => `${item.id}: ${item.nodes.map(node => node.target).join(', ')}`).join('\n')).toEqual([]);
};

for (const role of ['admin', 'vorstand', 'kassenwart', 'mitglied']) {
    test(`UX role ${role}: desktop, mobile, permissions and public return`, async ({ page }) => {
        test.setTimeout(90_000);
        await page.setViewportSize({ width: 1440, height: 1000 });
        await login(page, role);
        const quick = page.getByRole('navigation', { name: 'Schnellstart', exact: true });
        await expect(quick.getByRole('link')).toHaveCount(4);
        if (role === 'mitglied') {
            await expect(quick.getByRole('link', { name: /Termine/ })).toBeVisible();
            await expect(quick.getByRole('link', { name: 'Challenges prüfen' })).toHaveCount(0);
            await expect(page.getByTestId('member-sidebar-navigation').getByText('Vorstand', { exact: true })).toHaveCount(0);
        } else {
            await expect(quick.getByRole('link', { name: 'Challenges prüfen' })).toBeVisible();
            await expect(quick.getByRole('link', { name: 'Mitgliedsanträge' })).toBeVisible();
        }
        await expect(page.locator('[aria-current="page"]')).toHaveCount(1);
        await axe(page);
        await screenshot(page, `${role}-desktop`);

        await page.setViewportSize({ width: 390, height: 844 });
        await page.reload();
        await expect(page.locator('#dashboard-details-progress')).not.toHaveAttribute('open');
        await expect(page.getByTestId('dashboard-quick-actions')).toBeVisible();
        if (role === 'mitglied') await expect(quick.getByRole('link').first()).toBeInViewport();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await screenshot(page, `${role}-mobil`);
        await page.getByTestId('member-drawer-toggle').click();
        await expect(page.getByTestId('member-sidebar-navigation')).toBeVisible();
        await screenshot(page, `${role}-menue`);
        await page.keyboard.press('Escape');
        await expect(page.getByTestId('member-drawer-toggle')).toBeFocused();

        await page.goto('/satzung');
        await expect(page.getByTestId('public-context')).toBeVisible();
        await page.getByRole('link', { name: /Zurück zum Mitgliederbereich/ }).click();
        await expect(page).toHaveURL(/\/dashboard$/);
    });
}

test('UX guests: direct destinations, manual gallery, membership and search', async ({ page }) => {
    test.setTimeout(60_000);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto('/');
    await expect(page.getByTestId('public-context')).toHaveCount(0);
    await expect(page.getByTestId('nav-sections').getByRole('link', { name: 'Termine', exact: true })).toBeVisible();
    await expect(page.getByTestId('nav-sections').getByRole('link', { name: 'Spenden', exact: true })).toBeVisible();
    await axe(page);
    await screenshot(page, 'gast-desktop');
    const gallery = page.getByTestId('home-gallery');
    const current = gallery.locator('[aria-live=polite]');
    const initial = await current.innerText();
    await gallery.getByRole('button', { name: 'Nächstes Bild' }).click();
    await expect(current).not.toHaveText(initial);
    await gallery.getByRole('button', { name: 'Vorheriges Bild' }).click();
    await expect(current).toHaveText(initial);

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/mitglied-werden');
    await expect(page.getByTestId('mitglied-submit')).toBeDisabled();
    await expect(page.getByText('Bitte bestätige die Satzung, um deinen Antrag absenden zu können.')).toBeVisible();
    await axe(page);
    await screenshot(page, 'gast-mitgliedsantrag', true);
    await page.goto('/');
    await screenshot(page, 'gast-mobil');
    await page.getByTestId('navigation-search-trigger').click();
    await page.locator('#navigation-search-input').fill('Spenden');
    await expect(page.getByTestId('navigation-search-results').getByRole('link', { name: /Spenden/ })).toBeVisible();
});

test('UX member: breadcrumbs, dark mode and keyboard search', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await login(page, 'mitglied');
    await page.goto('/rezensionen');
    await expect(page.getByRole('navigation', { name: 'Brotkrumen' })).toContainText('Rezensionen');
    await page.getByRole('button', { name: 'Dunkles Design aktivieren' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'coffee');
    await page.getByRole('heading', { level: 1 }).click();
    await axe(page);
    await screenshot(page, 'mitglied-dunkel');
    await page.keyboard.press('Control+k');
    await expect(page.getByRole('dialog', { name: 'Seite oder Funktion suchen' })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog', { name: 'Seite oder Funktion suchen' })).not.toBeVisible();
});
