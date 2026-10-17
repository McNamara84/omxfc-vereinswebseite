import AxeBuilder from '@axe-core/playwright';
import { test, expect, clickAndWaitForLivewireUpdate } from './test-support.js';

const login = async (page, { waitForFeed = true, email = 'playwright-member@example.com' } = {}) => {
    await page.goto('/login');
    await page.getByRole('textbox', { name: 'E-Mail', exact: true }).fill(email);
    await page.locator('input[name="password"]').fill('password');
    await page.locator('input[name="password"]').press('Enter');
    await page.waitForURL(/\/dashboard$/);
    if (waitForFeed) {
        await expect(page.locator('[data-dashboard-activity-feed]:not([aria-busy="true"])')).toBeAttached();
    }
};

for (const path of ['/login', '/register', '/forgot-password']) {
    test(`public form ${path} keeps visible labels in accessible names`, async ({ page }) => {
        await page.goto(path);
        await expect(page.locator('form').first()).toBeVisible();
        const result = await new AxeBuilder({ page }).include('form')
            .withRules(['label', 'label-content-name-mismatch', 'aria-valid-attr-value'])
            .analyze();
        expect(result.violations).toEqual([]);
    });
}

test('meeting rhythm help is keyboard accessible and preserves the selected week', async ({ page }) => {
    await login(page, { email: 'info@maddraxikon.com' });
    await page.goto('/admin/treffen');
    await page.getByRole('button', { name: 'Neues Treffen', exact: true }).click();

    const week = page.getByLabel('Woche im Monat *', { exact: true });
    await expect(week).toHaveAccessibleDescription(/zweiten Dienstag im Monat/);
    await week.selectOption('2');
    const field = page.locator('fieldset').filter({ has: week });
    const help = page.getByRole('button', { name: 'Hinweis anzeigen', exact: true }).within(field);
    await help.focus();
    const tooltipId = await help.getAttribute('aria-controls');
    await expect(page.locator(`[id="${tooltipId}"]`)).toContainText('nicht die Kalenderwoche');
    await help.press('Escape');
    await expect(help).toBeFocused();

    await page.getByTestId('meeting-rhythm-type').selectOption('every_n_weeks');
    await expect(page.getByLabel('Startdatum *', { exact: true })).toBeVisible();
    await page.getByTestId('meeting-rhythm-type').selectOption('monthly_nth_weekday');
    await expect(week).toHaveValue('2');
});

test('search loading indicator remains visible in forced colors', async ({ page }) => {
    await page.emulateMedia({ forcedColors: 'active' });
    await login(page, { email: 'playwright-kompendium@example.com' });
    await page.goto('/kompendium');
    await page.getByTestId('kompendium-search').fill('Maddrax');

    let release;
    const gate = new Promise(resolve => { release = resolve; });
    await page.route(/\/livewire(?:-[^/]+)?\/update\/?$/, async route => {
        await gate;
        await route.continue();
    });

    try {
        await page.getByRole('button', { name: 'Suchen', exact: true }).click();
        const spinner = page.locator('[wire\\:target="performSearch"] .loading');
        await expect(spinner).toBeVisible();
        const style = await spinner.evaluate(element => {
            const css = getComputedStyle(element);
            return { background: css.backgroundColor, mask: css.maskImage };
        });
        expect(style.background).not.toMatch(/^(transparent|rgba\(0, 0, 0, 0\))$/);
        expect(style.mask).not.toBe('none');
    } finally {
        release();
    }
});

test('mary tabs keep badges and independent table sorting through updates', async ({ page }) => {
    test.setTimeout(45_000);
    await login(page, { email: 'info@maddraxikon.com' });
    await page.goto('/belohnungen/admin');
    const tabs = page.locator('.tabs').first();
    const purchases = tabs.locator('label').filter({ hasText: 'Freischaltungen' });
    const downloads = tabs.locator('label').filter({ hasText: 'Downloads' });
    const state = () => tabs.evaluate(element => {
        const root = element.closest('[wire\\:id]');
        const component = window.Livewire.find(root.getAttribute('wire:id'));
        return { purchases: component.get('purchaseSortBy'), downloads: component.get('downloadSortBy') };
    });

    // Seeded downloads and the separate search fixture make both counters real.
    await expect(purchases.locator('.badge')).toHaveText('1');
    await expect(downloads.locator('.badge')).toHaveText('2');
    const initial = await state();
    await purchases.click();
    await expect(purchases).toHaveClass(/tab-active/);
    await clickAndWaitForLivewireUpdate(page, page.getByRole('columnheader', { name: 'Kosten (Baxx)', exact: true }));
    const purchaseSorted = await state();
    expect(purchaseSorted.purchases).toEqual({ column: 'cost_baxx', direction: 'asc' });
    expect(purchaseSorted.downloads).toEqual(initial.downloads);

    await page.getByPlaceholder('Mitglied suchen...').fill('missing-dependency-test-user');
    await expect(purchases.locator('.badge')).toHaveCount(0);
    await page.getByPlaceholder('Mitglied suchen...').fill('');
    await expect(purchases.locator('.badge')).toHaveText('1');

    await downloads.click();
    await expect(downloads).toHaveClass(/tab-active/);
    await clickAndWaitForLivewireUpdate(page, page.getByRole('columnheader', { name: 'Titel', exact: true }));
    const downloadSorted = await state();
    expect(downloadSorted.downloads).toEqual({ column: 'title', direction: 'asc' });
    expect(downloadSorted.purchases).toEqual(purchaseSorted.purchases);
    await purchases.click();
    await expect(purchases).toHaveClass(/tab-active/);
    await expect(purchases.locator('.badge')).toHaveText('1');
    expect((await state()).purchases).toEqual(purchaseSorted.purchases);
});

test('profile hints work with keyboard and click, have unique IDs and survive navigation', async ({ page }) => {
    // Four SPA navigations and two complete accessibility scans share this
    // budget; each navigation keeps the member suite's 15-second limit.
    test.setTimeout(45_000);
    await login(page);

    for (let visit = 0; visit < 2; visit++) {
        await page.evaluate(() => window.Livewire.navigate('/user/profile'));
        await page.waitForURL(/\/user\/profile$/, { timeout: 15_000 });
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
            .withRules(['aria-valid-attr-value', 'aria-allowed-attr', 'nested-interactive', 'label', 'label-content-name-mismatch'])
            .analyze();
        expect(accessibility.violations).toEqual([]);
        await page.evaluate(() => window.Livewire.navigate('/dashboard'));
        await page.waitForURL(/\/dashboard$/, { timeout: 15_000 });
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
        const club = page.getByRole('button', { name: 'Verein & Veranstaltungen', exact: true }).within(feed);
        await expect(all).toBeVisible();
        await expect(all).toHaveAttribute('aria-pressed', 'true');
        const primaryColor = await all.evaluate(button => getComputedStyle(button).getPropertyValue('--color-primary').trim());
        const buttonColor = button => getComputedStyle(button).getPropertyValue('--btn-color').trim();
        await expect.poll(() => all.evaluate(buttonColor)).toBe(primaryColor);
        await expect.poll(() => club.evaluate(buttonColor)).toBe('');
        await clickAndWaitForLivewireUpdate(page, club);
        await expect(club).toHaveAttribute('aria-pressed', 'true');
        await expect(all).toHaveAttribute('aria-pressed', 'false');
        await expect.poll(() => club.evaluate(buttonColor)).toBe(primaryColor);
        await expect.poll(() => all.evaluate(buttonColor)).toBe('');
    } finally {
        release();
    }
});
