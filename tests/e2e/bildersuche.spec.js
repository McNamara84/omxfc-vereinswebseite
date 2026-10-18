import AxeBuilder from '@axe-core/playwright';
import { clickAndWaitForLivewireUpdate, expect, test } from './test-support.js';

async function login(page, flow, browserName, retry) {
    await page.goto('/login');
    await page.getByLabel('E-Mail').fill(`bildersuche-${flow}-${browserName}-${retry}@example.com`);
    await page.locator('input[name="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
    await page.goto('/bildersuche');
}

async function search(page, query) {
    await page.getByTestId('bildersuche-query').fill(query);
    await clickAndWaitForLivewireUpdate(page, page.getByRole('button', { name: 'Suchen', exact: true }));
}

async function accessible(page) {
    const report = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa'])
        .exclude('#nprogress [role="bar"]').analyze();
    expect(report.violations).toEqual([]);
}

test('AG members search combinations, filter series and preview covers accessibly', async ({ page, browserName }, testInfo) => {
    await login(page, 'ag', browserName, testInfo.retry);
    await expect(page.getByRole('link', { name: 'Bildersuche', exact: true }).first()).toBeVisible();
    await search(page, 'Drax');
    await expect(page.getByTestId('bildersuche-cover')).toHaveCount(2);
    await page.getByLabel('Serie', { exact: true }).selectOption('missionmars');
    await expect(page.getByTestId('bildersuche-cover')).toHaveCount(1);
    await page.getByLabel('Serie', { exact: true }).selectOption('all');
    await search(page, 'Aruula Schwert');
    await expect(page.getByTestId('bildersuche-cover')).toHaveCount(1);
    await accessible(page);
    const trigger = page.getByTestId('bildersuche-cover').first();
    await trigger.click();
    const modal = page.getByTestId('bildersuche-preview');
    await expect(modal).toBeVisible();
    await expect(modal).toContainText('F-17 Alpha 2');
    await expect(page.getByTestId('bildersuche-source')).toHaveAttribute('href', /^https:\/\/de\.maddraxikon\.com\/index\.php\?title=Datei/);
    await expect(modal.locator('img')).toBeVisible();
    await expect.poll(() => modal.locator('img').evaluate((img) => img.complete && img.naturalWidth > 0)).toBe(true);
    await accessible(page);
    await page.keyboard.press('Escape');
    await expect(modal).toHaveCount(0);
    await expect(trigger).toBeFocused();
    await page.setViewportSize({ width: 390, height: 844 });
    await trigger.click();
    await expect(modal).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await modal.getByRole('button', { name: 'Schließen' }).click();
    await search(page, 'Aruula Wulfane');
    await expect(page.getByTestId('bildersuche-status')).toContainText('Keine Treffer');
});

test('members purchase once and keep access after a reload', async ({ page, browserName }, testInfo) => {
    await login(page, 'purchase', browserName, testInfo.retry);
    await expect(page.getByTestId('bildersuche-query')).toHaveCount(0);
    page.once('dialog', (dialog) => dialog.accept());
    await clickAndWaitForLivewireUpdate(page, page.getByTestId('bildersuche-purchase'));
    await expect(page.getByTestId('bildersuche-query')).toBeVisible();
    await page.reload();
    await search(page, 'Drax');
    await expect(page.getByTestId('bildersuche-cover')).toHaveCount(2);
    await expect(page.getByTestId('bildersuche-purchase')).toHaveCount(0);
    await page.goto('/belohnungen');
    await expect(page.getByRole('link', { name: 'Zur Bildersuche' })).toBeVisible();
});

test('locked members can find the menu entry but cannot retrieve cover images', async ({ page, browserName }, testInfo) => {
    await login(page, 'locked', browserName, testInfo.retry);
    await expect(page.getByTestId('bildersuche-purchase')).toBeDisabled();
    await expect(page.getByTestId('bildersuche-form')).toHaveCount(0);
    await accessible(page);
    const response = await page.request.get('/bildersuche/cover/1/small');
    expect([403, 404]).toContain(response.status());
});
