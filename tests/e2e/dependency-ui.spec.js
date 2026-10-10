import AxeBuilder from '@axe-core/playwright';
import { test, expect, clickAndWaitForLivewireUpdate } from './test-support.js';

const login = async (page, { waitForFeed = true } = {}) => {
    await page.goto('/login');
    await page.getByRole('textbox', { name: 'E-Mail', exact: true }).fill('playwright-member@example.com');
    await page.locator('input[name="password"]').fill('password');
    await page.locator('input[name="password"]').press('Enter');
    await page.waitForURL(/\/dashboard$/);
    if (waitForFeed) {
        await expect(page.locator('[data-dashboard-activity-feed]:not([aria-busy="true"])')).toBeAttached();
    }
};

test('profile hints work with keyboard and click, have unique IDs and survive navigation', async ({ page }) => {
    await login(page);

    for (let visit = 0; visit < 2; visit++) {
        await page.goto('/user/profile');
        const street = page.getByLabel('Straße', { exact: true });
        const streetField = page.locator('fieldset').filter({ has: street });
        const trigger = page.getByRole('button', { name: 'Hinweis anzeigen', exact: true }).within(streetField);

        await expect(street).toHaveAttribute('aria-describedby', 'strasse-hilfe');
        await expect(street).toHaveAccessibleDescription(/interaktive Mitgliederkarte und den Postversand/);
        await trigger.focus();
        await expect(trigger).toHaveAttribute('aria-expanded', 'true');
        const tooltipId = await trigger.getAttribute('aria-controls');
        const tooltip = page.locator(`[id="${tooltipId}"]`);
        await expect(tooltip).toBeVisible();
        await expect(tooltip).toContainText('Postversand');
        await trigger.press('Escape');
        await expect(tooltip).toBeHidden();
        await expect(trigger).toBeFocused();
        // A click without mouse hover also exercises the touch activation path.
        await trigger.dispatchEvent('click');
        await expect(tooltip).toBeVisible();
        await trigger.press('Escape');

        const ids = await page.getByRole('tooltip', { includeHidden: true }).evaluateAll(elements => elements.map(el => el.id));
        expect(ids.every(Boolean)).toBe(true);
        expect(new Set(ids).size).toBe(ids.length);
        const accessibility = await new AxeBuilder({ page }).include('form[wire\\:submit="updateProfileInformation"]')
            .withRules(['aria-valid-attr-value', 'aria-allowed-attr', 'nested-interactive', 'label'])
            .analyze();
        expect(accessibility.violations).toEqual([]);
        await page.goto('/dashboard');
        await expect(page.locator('[data-dashboard-activity-feed]:not([aria-busy="true"])')).toBeAttached();
    }
});

test('dashboard renders its accessible placeholder before the deferred feed request completes', async ({ page }) => {
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    let deferredRequestSeen = false;
    await page.route(/\/livewire(?:-[^/]+)?\/update\/?$/, async route => {
        if (route.request().postData()?.includes('__lazyLoad')) {
            deferredRequestSeen = true;
            await gate;
        }
        await route.continue();
    });

    try {
        await login(page, { waitForFeed: false });
        const feed = page.locator('[data-dashboard-activity-feed]');
        await expect(feed).toHaveAttribute('aria-busy', 'true');
        await expect(feed.getByRole('status')).toContainText('Aktivitäten werden geladen');
        await expect.poll(() => deferredRequestSeen).toBe(true);
        release();
        const all = page.getByRole('button', { name: 'Alle', exact: true }).within(feed);
        await expect(all).toBeVisible();
        await expect(all).toHaveAttribute('aria-pressed', 'true');
        const club = page.getByRole('button', { name: 'Verein & Veranstaltungen', exact: true }).within(feed);
        await clickAndWaitForLivewireUpdate(page, club);
        await expect(club).toHaveAttribute('aria-pressed', 'true');
        await expect(all).toHaveAttribute('aria-pressed', 'false');
    } finally {
        release();
    }
});
