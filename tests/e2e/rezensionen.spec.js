import { test, expect } from '@playwright/test';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

test.describe('Rezensionen overview', () => {
    test('member can toggle filters and open accordion sections', async ({ page }) => {
        await login(page, 'playwright-member@example.com');

        await page.goto('/rezensionen');

        await expect(page).toHaveURL(/\/rezensionen$/);
        await expect(page.getByRole('heading', { level: 1, name: 'Rezensionen' })).toBeVisible();

        const filterToggle = page.getByRole('button', { name: /Filter (anzeigen|ausblenden)/i });
        await expect(filterToggle).toHaveAttribute('aria-expanded', 'false');

        await filterToggle.click();

        await expect(filterToggle).toHaveAttribute('aria-expanded', 'true');
        await expect(page.locator('#reviews-filter-panel')).toBeVisible();

        const accordionButtons = page.locator('[data-reviews-accordion-button]');
        await expect(accordionButtons.first()).toHaveAttribute('aria-expanded', 'true');
        await expect(accordionButtons.nth(1)).toHaveAttribute('aria-expanded', 'false');

        const hardcoverButton = page.locator(
            '[data-reviews-accordion-button][aria-controls="content-maddrax-hardcover"]'
        );
        await hardcoverButton.click();

        await expect(hardcoverButton).toHaveAttribute('aria-expanded', 'true');
        await expect(page.locator('#content-maddrax-hardcover')).toBeVisible();
        await expect(page.locator('#content-maddrax-hardcover')).toContainText('Maddrax Hardcover – Sammlerausgabe');
    });
});
