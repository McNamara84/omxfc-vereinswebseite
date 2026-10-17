import AxeBuilder from '@axe-core/playwright';
import { expect, test } from './test-support.js';

const login = async (page) => {
    await page.goto('/login');
    await page.getByRole('textbox', { name: /E-Mail/i }).fill('playwright-member@example.com');
    await page.locator('input[name="password"]').fill('password');
    await page.locator('input[name="password"]').press('Enter');
    await page.waitForURL(/\/dashboard$/);
};
const open = async (page, query) => {
    await page.getByTestId('navigation-search-trigger').click();
    const dialog = page.getByRole('dialog', { name: 'Seite oder Funktion suchen' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('searchbox').fill(query);
    return dialog;
};
const expectInputFocus = async (dialog) => {
    try {
        await expect(dialog.getByRole('searchbox')).toBeFocused();
    } catch (error) {
        const state = await dialog.evaluate(element => ({
            open: element.open, activeId: document.activeElement?.id,
            activeTag: document.activeElement?.tagName, documentFocused: document.hasFocus(),
            inputIsActive: element.querySelector('input') === document.activeElement,
        }));
        throw new Error(`${error.message}\nDialog focus state: ${JSON.stringify(state)}`, { cause: error });
    }
};

test('guest search, encoded input, no results, keyboard selection and focus return', async ({ page }) => {
    test.setTimeout(60_000);
    await page.goto('/');
    const trigger = page.getByTestId('navigation-search-trigger');
    const dialog = await open(page, 'Chronik');
    await expect(dialog.getByRole('link', { name: /Chronik/ })).toBeVisible();
    await page.keyboard.press('ArrowDown');
    await expect(dialog.getByRole('link', { name: /Chronik/ })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
    await expect(trigger).toBeFocused();
    await page.keyboard.press('Control+k');
    await expectInputFocus(dialog);
    await dialog.getByRole('searchbox').fill('A & B?');
    await expect(dialog.getByRole('status')).toContainText('Keine passenden Seiten');
    await dialog.getByRole('searchbox').fill('Datenbank');
    await expect(dialog.getByRole('status')).toContainText('Keine passenden Seiten');
    await dialog.getByRole('searchbox').fill('Chronik');
    await expect(dialog.getByRole('link', { name: /Chronik/ })).toBeVisible();
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/chronik$/);
    await expect(page.getByRole('dialog')).not.toBeVisible();
    await page.getByTestId('navigation-search-trigger').click();
    await expect(page.getByRole('dialog').getByRole('searchbox')).toHaveValue('');
    await expect(page.locator('#navigation-search-dialog')).toHaveCount(1);
});

test('search reports network failures separately and can retry', async ({ page }) => {
    await page.goto('/');
    await page.route('**/mary/spotlight?**', route => route.fulfill({ status: 503, body: '{}' }));
    const dialog = await open(page, 'Chronik');
    await expect(dialog.getByRole('alert')).toContainText('nicht erreichbar');
    await expect(dialog.getByRole('searchbox')).toHaveAttribute('aria-busy', 'false');
    await page.unroute('**/mary/spotlight?**');
    await dialog.getByRole('searchbox').fill('Satzung');
    await expect(dialog.getByRole('link', { name: /Satzung/ })).toBeVisible();
});

test('member can search from public pages and switch between both site areas', async ({ page }) => {
    test.setTimeout(60_000);
    await login(page);
    await page.getByTestId('public-site-switch').click();
    await expect(page).toHaveURL(/\/\?ansicht=oeffentlich$/);
    await expect(page.getByTestId('nav-sections')).toContainText('Verein');
    await expect(page.getByTestId('member-sidebar-navigation')).toHaveCount(0);
    const dialog = await open(page, 'Baxx verdienen');
    const result = dialog.getByRole('link', { name: /Challenges & Baxx/ });
    await expect(result).toBeVisible();
    await result.click();
    await expect(page).toHaveURL(/\/aufgaben$/);
    await expect(page.getByTestId('member-sidebar-navigation').locator('[aria-current="page"]')).toContainText('Challenges & Baxx');
    await page.getByTestId('public-site-switch').click();
    await page.getByTestId('member-site-switch').click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goBack();
    await expect(page).toHaveURL(/ansicht=oeffentlich/);
});

test('public mobile menu closes with Escape and focus returns to its toggle', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');
    const toggle = page.locator('button[aria-controls="mobile-navigation"]');
    await expect(toggle).toHaveAccessibleName('Menü öffnen');
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.press('Escape');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(toggle).toBeFocused();
});

for (const shortcut of ['Control+k', 'Meta+k']) {
    test(`search opened with ${shortcut} from a mobile drawer link returns focus to the search button`, async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page);
        await page.getByTestId('member-drawer-toggle').click();
        const drawer = page.locator('#member-drawer');
        const opener = page.getByTestId('member-sidebar-navigation').getByRole('link', { name: 'Dashboard', exact: true, includeHidden: true });
        await expect(drawer).toBeChecked();
        await opener.focus();
        await expect(opener).toBeFocused();

        await page.keyboard.press(shortcut);

        const dialog = page.getByRole('dialog', { name: 'Seite oder Funktion suchen' });
        await expect(dialog).toBeVisible();
        await expect(drawer).not.toBeChecked();
        await expect(opener).toBeHidden();
        expect(await opener.evaluate(element => element.getClientRects().length)).toBeGreaterThan(0);
        await expectInputFocus(dialog);
        await page.keyboard.press('Escape');

        await expect(dialog).not.toBeVisible();
        await expect(page.getByTestId('navigation-search-trigger')).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(dialog).toBeVisible();
    });
}

for (const theme of ['caramellatte', 'coffee']) {
    test(`search dialog is accessible in ${theme} and keeps one focus surface on mobile`, async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page);
        const logo = await page.getByTestId('member-navigation-brand-link').locator('img').boundingBox();
        expect(logo.width).toBeGreaterThanOrEqual(32);
        await page.evaluate(theme => {
            document.documentElement.dataset.theme = theme;
            document.documentElement.classList.toggle('dark', theme === 'coffee');
        }, theme);
        await page.getByTestId('member-drawer-toggle').click();
        await expect(page.locator('#member-drawer')).toBeChecked();
        await page.keyboard.press('Control+k');
        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible();
        await expect(page.locator('#member-drawer')).not.toBeChecked();
        await expectInputFocus(dialog);
        await dialog.getByRole('searchbox').fill('Baxx');
        await expect(dialog.getByRole('link', { name: /Challenges/ })).toBeVisible();
        const results = await new AxeBuilder({ page }).include('#navigation-search-dialog').analyze();
        expect(results.violations).toEqual([]);
        if (process.env.NAVIGATION_CAPTURE_PREVIEWS === '1') {
            await page.screenshot({ path: `test-results/navigation-search-${theme}.png` });
        }
        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)).toBe(false);
    });
}

test('skip link reaches actual content and fanfiction preview has the public shell', async ({ page }) => {
    await page.goto('/fanfiction-teaser');
    await expect(page.getByRole('heading', { name: 'Fanfiction', exact: true })).toBeVisible();
    await expect(page.locator('nav[aria-label="Hauptnavigation"]')).toBeVisible();
    await page.getByRole('link', { name: 'Zum Hauptinhalt' }).focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main-content')).toBeFocused();
    await expect(page.locator('#navigation-search-dialog')).toHaveCount(1);
});

test('public navigation stays within its viewport for guests and members', async ({ page }) => {
    test.setTimeout(90_000);
    const widths = [375, 390, 768, 1023, 1024, 1279, 1280, 1440, 1920];
    const expectControlsInViewport = async (width, member = false) => {
        const controls = ['navigation-search-trigger', 'theme-toggle'];
        if (member) controls.push('member-site-switch', 'profile-menu-trigger');
        if (width < 1280) {
            const toggle = page.locator('button[aria-controls="mobile-navigation"]');
            await expect(toggle).toBeVisible();
            const bounds = await toggle.boundingBox();
            expect(bounds.x + bounds.width).toBeLessThanOrEqual(width);
        }
        for (const id of controls) {
            const control = page.getByTestId(id);
            await expect(control).toBeVisible();
            const bounds = await control.boundingBox();
            expect(bounds.x, `${id} at ${width}px`).toBeGreaterThanOrEqual(0);
            expect(bounds.x + bounds.width, `${id} at ${width}px`).toBeLessThanOrEqual(width);
        }
    };
    for (const width of widths) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/satzung');
        await expectControlsInViewport(width);
        expect(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth), `guest at ${width}px`).toBe(false);
    }
    await login(page);
    for (const width of widths) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/satzung');
        await expectControlsInViewport(width, true);
        expect(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth), `member at ${width}px`).toBe(false);
    }
    if (process.env.NAVIGATION_CAPTURE_PREVIEWS === '1') {
        await page.screenshot({ path: 'test-results/navigation-public-desktop.png' });
    }
});
