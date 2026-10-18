import { test, expect } from './test-support.js';

test('navigation and quick-action labels remain readable across responsive boundaries', async ({ page }) => {
    test.setTimeout(90_000);
    const widths = [320, 390, 480, 640, 768, 1023, 1024, 1279, 1280, 1536];
    await page.goto('/login');
    await page.locator('[name=email]').fill('ux-admin@example.com');
    await page.locator('[name=password]').fill('password');
    await page.locator('[name=password]').press('Enter');
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.locator('[data-dashboard-details]').evaluateAll(sections => sections.forEach(section => { section.open = true; }));
    await expect(page.locator('#dashboard-details-community')).toHaveAttribute('open');
    for (const width of widths) {
        await page.setViewportSize({ width, height: 900 });
        const quick = page.getByRole('navigation', { name: 'Schnellstart' });
        await expect(quick.getByRole('link')).toHaveCount(4);
        const labelsFit = await quick.locator('a > span').evaluateAll(labels => labels.every(label => label.scrollWidth <= label.clientWidth + 1));
        expect(labelsFit, `member labels at ${width}px`).toBe(true);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `member at ${width}px`).toBe(true);
    }
    await page.goto('/?ansicht=oeffentlich');
    for (const width of widths) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `public at ${width}px`).toBe(true);
    }
});

test('public pages retain a usable layout at narrow widths', async ({ page }) => {
    test.setTimeout(120_000);
    await page.setViewportSize({ width: 320, height: 812 });
    for (const url of ['/', '/mitglied-werden', '/chronik', '/ehrenmitglieder', '/arbeitsgruppen', '/satzung', '/termine', '/spenden', '/changelog', '/impressum', '/datenschutz']) {
        const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
        expect(response.status(), url).toBeLessThan(400);
        await expect(page.locator('#main-content')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), url).toBe(true);
    }
});

for (const role of ['admin', 'vorstand', 'kassenwart', 'mitglied']) {
    test(`all menu destinations render for ${role}`, async ({ page }) => {
        test.setTimeout(180_000);
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.goto('/login');
        await page.locator('[name=email]').fill(`ux-${role}@example.com`);
        await page.locator('[name=password]').fill('password');
        await page.locator('[name=password]').press('Enter');
        await expect(page).toHaveURL(/\/dashboard$/);
        const links = await page.getByTestId('member-sidebar-navigation').locator('a[href]').evaluateAll(elements => [...new Set(elements.map(element => element.href))]);
        expect(links.length).toBeGreaterThan(10);
        for (const url of links) {
            const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
            expect(response.status(), url).toBe(200);
            await expect(page.getByRole('heading', { name: '500', exact: true })).toHaveCount(0);
            await expect(page.locator('#main-content')).toBeVisible();
        }
    });
}
