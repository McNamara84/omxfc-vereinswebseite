import AxeBuilder from '@axe-core/playwright';
import { test, expect } from '@playwright/test';

// Playwright 1.65.0 + Firefox crashes when the same ARIA element references
// are read in the page (axe) and the isolated locator world, in either order.
// https://github.com/microsoft/playwright/issues/43967
for (const axeFirst of [false, true]) {
    test(`ARIA label references survive ${axeFirst ? 'axe before locators' : 'locators before axe'}`, async ({ page }) => {
        await page.setContent(`<!doctype html>
            <html lang="de"><head><title>ARIA regression</title></head><body>
                <main><h1>Formular</h1><span id="save-label">Speichern</span>
                    <button aria-labelledby="save-label"></button>
                </main>
            </body></html>`);

        const checkLabel = () => expect(page.getByRole('button', { name: 'Speichern', exact: true })).toBeVisible();
        const checkAccessibility = async () => {
            const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
            expect(result.violations).toEqual([]);
        };

        if (axeFirst) {
            await checkAccessibility();
            await checkLabel();
        } else {
            await checkLabel();
            await checkAccessibility();
        }
    });
}
