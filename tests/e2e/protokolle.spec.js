import { expect, test } from '@playwright/test';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

test.describe('Protokolle page', () => {
    test('redirects unauthenticated visitors to the login page', async ({ page }) => {
        await page.goto('/protokolle');

        await expect(page).toHaveURL(/\/login/);
        await expect(page.getByRole('heading', { name: /login/i })).toBeVisible();
    });

    test('allows members to open accordion sections and see documents', async ({ page }) => {
        await login(page, 'playwright-member@example.com');

        await page.goto('/protokolle');

        await expect(page).toHaveURL(/\/protokolle$/);
        await expect(page.getByRole('heading', { level: 1, name: 'Protokolle' })).toBeVisible();
        await expect(page.getByText('3 Dokumente')).toBeVisible();

        const firstAccordion = page.locator('details[data-protokolle-accordion-item]').first();
        const accordionButton = page.getByRole('button', { name: /Protokolle 2023/i });
        await expect(accordionButton).toHaveAttribute('aria-expanded', 'false');
        await expect(firstAccordion).toHaveJSProperty('open', false);

        await accordionButton.click();

        await expect(firstAccordion).toHaveJSProperty('open', true);
        const firstPanel = page.locator('#content-2023');
        await expect(firstPanel).toBeVisible();
        await expect(firstPanel).toContainText('Gründungsversammlung');
        await accordionButton.focus();
        await page.keyboard.press('Space');

        await expect(firstAccordion).toHaveJSProperty('open', false);
        await expect(page.locator('#content-2023')).toBeHidden();
    });
});
