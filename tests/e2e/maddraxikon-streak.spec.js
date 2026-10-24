import AxeBuilder from '@axe-core/playwright';
import { test, expect } from './test-support.js';
import { runArtisan } from './utils/artisan.js';

test.beforeAll(async () => {
    await runArtisan(['db:seed', '--class=Database\\Seeders\\MaddraxikonStreakPlaywrightSeeder']);
});

async function login(page, state) {
    await page.goto('/login');
    await page.getByLabel('E-Mail').fill(`streak-${state}@example.com`);
    await page.locator('input[name="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
}

test('Unverknüpftes Mitglied findet den Einstieg auf allen drei Seiten', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 844 });
    await login(page, 'new');
    for (const path of ['/dashboard', '/user/profile', '/aufgaben']) {
        await page.goto(path);
        const card = page.getByTestId('maddraxikon-streak');
        await expect(card).toContainText('1, 2, 3 … zusätzlich.');
        await expect(card.getByRole('img', { name: 'Tag 1: ein Bonus-Baxx, Tag 2: zwei Bonus-Baxx, Tag 3: drei Bonus-Baxx' })).toBeVisible();
        await expect(card.getByRole('link', { name: 'Maddraxikon verbinden', exact: true })).toHaveAttribute('href', /#maddraxikon-account$/);
        expect(await card.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true);
        const scan = await new AxeBuilder({ page }).include('[data-testid="maddraxikon-streak"]').analyze();
        expect(scan.violations).toEqual([]);
    }
});

test('Bestätigte Serie ist mobil und am Desktop bedienbar und verständlich', async ({ page }, testInfo) => {
    await login(page, 'active');
    for (const width of [320, 1280]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/dashboard');
        const card = page.getByTestId('maddraxikon-streak');
        await expect(card).toContainText('+5 Bonus-Baxx');
        await expect(card.getByRole('listitem')).toHaveCount(7);
        expect(await card.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true);
        const scan = await new AxeBuilder({ page }).include('[data-testid="maddraxikon-streak"]').analyze();
        expect(scan.violations).toEqual([]);
        await card.screenshot({ path: testInfo.outputPath(`serie-${width}.png`) });
    }
    await page.getByRole('link', { name: 'Serienverlauf ansehen' }).click();
    // Wait for the profile's hash navigation to finish moving keyboard focus.
    await expect(page.locator('#maddraxikon-serie')).toBeFocused();
    const detail = page.getByTestId('maddraxikon-streak');
    await expect(detail).toContainText('Persönlicher Rekord');
    await expect(detail).toContainText('10 Baxx');
    await detail.locator('summary').press('Enter');
    await expect(detail.locator('details')).toHaveAttribute('open', '');
});

test('Erkannte Aktivität bleibt bis zur Prüfung als vorläufig markiert', async ({ page }) => {
    await login(page, 'pending');
    await expect(page.getByTestId('maddraxikon-streak')).toContainText('Aktivität erkannt – Bonus wird geprüft.');
    await expect(page.getByTestId('maddraxikon-streak')).toContainText('24 Stunden');
});

test('Korrekturen zeigen Tagesanspruch und tatsächlichen Baxx-Abzug', async ({ page }) => {
    await login(page, 'corrected');
    await page.goto('/user/profile');
    const detail = page.getByTestId('maddraxikon-streak');
    await expect(detail).toContainText('Serienkorrektur');
    await expect(detail).toContainText('-2 Baxx');
    await expect(detail).toContainText('4 Baxx');
});

test('Dark Mode und reduzierte Bewegung behalten lesbare Zustände', async ({ page }, testInfo) => {
    await page.emulateMedia({ colorScheme: 'dark', reducedMotion: 'reduce' });
    await page.addInitScript(() => {
        localStorage.setItem('mary-theme', JSON.stringify('coffee'));
        localStorage.setItem('omxfc-theme-explicit', '1');
    });
    await page.setViewportSize({ width: 375, height: 900 });
    await login(page, 'active');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'coffee');
    const card = page.getByTestId('maddraxikon-streak');
    await expect(card).toContainText('+5 Bonus-Baxx');
    const scan = await new AxeBuilder({ page }).include('[data-testid="maddraxikon-streak"]').analyze();
    expect(scan.violations).toEqual([]);
    await card.screenshot({ path: testInfo.outputPath('serie-dark.png') });
});
