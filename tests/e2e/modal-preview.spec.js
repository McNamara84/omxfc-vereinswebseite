import { test, expect } from '@playwright/test';

async function expectedModalCount(page) {
    return page.evaluate(() => window.__omxfcPreviewExpectedModalCount());
}

async function backdropAlpha(page, modalId) {
    return page.evaluate((id) => window.__omxfcPreviewBackdropAlpha(id), modalId);
}

async function closePreviewModals(page) {
    await page.evaluate(() => {
        window.__omxfcClosePreviewModals();
    });
}

test.describe('Modal-Vorschau', () => {
    test('alle Vorschau-Modals nutzen einen deckenden Hintergrund', async ({ page }) => {
        test.slow();

        await page.goto('/_testing/modal-vorschau');
        await expect(page.getByRole('heading', { level: 1, name: 'Modal-Vorschau' })).toBeVisible();

        const modalCount = await expectedModalCount(page);
        expect(modalCount).toBeGreaterThan(0);

        const triggers = page.locator('[data-modal-trigger]');
        await expect(triggers).toHaveCount(modalCount);

        for (let index = 0; index < modalCount; index += 1) {
            const trigger = triggers.nth(index);
            const modalId = await trigger.getAttribute('data-modal-target');

            expect(modalId).toBeTruthy();

            await trigger.scrollIntoViewIfNeeded();
            await trigger.click();

            const modal = page.locator(`#${modalId}`);
            await expect(modal).toBeVisible();
            await expect
                .poll(() => backdropAlpha(page, modalId), {
                    message: `Backdrop von ${modalId} bleibt transparent.`,
                })
                .toBeGreaterThan(0.2);

            await closePreviewModals(page);
        }
    });

    test('schreibt in Chromium Screenshots fuer alle Vorschau-Modals', async ({ page, browserName }, testInfo) => {
        test.skip(browserName !== 'chromium', 'Screenshots werden nur einmal benötigt.');
        test.slow();

        await page.goto('/_testing/modal-vorschau');

        const modalCount = await expectedModalCount(page);
        expect(modalCount).toBeGreaterThan(0);

        const triggers = page.locator('[data-modal-trigger]');
        await expect(triggers).toHaveCount(modalCount);

        for (let index = 0; index < modalCount; index += 1) {
            const trigger = triggers.nth(index);
            const modalId = await trigger.getAttribute('data-modal-target');

            expect(modalId).toBeTruthy();

            await trigger.scrollIntoViewIfNeeded();
            await trigger.click();

            const modal = page.locator(`#${modalId}`);
            await expect(modal).toBeVisible();
            await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'auto' }));
            await page.screenshot({
                path: testInfo.outputPath(`modal-preview/${String(index + 1).padStart(2, '0')}-${modalId}.png`),
            });

            await closePreviewModals(page);
        }
    });
});