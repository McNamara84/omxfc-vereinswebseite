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
        await expect(page.locator('[data-testid="page-title"]')).toContainText('Protokolle');

        // Das erste Accordion (2026) ist standardmäßig geöffnet
        const firstAccordion = page.locator('details[data-protokolle-accordion-item]').first();
        const accordionButton2026 = page.getByRole('button', { name: /Protokolle 2026/i });
        await expect(accordionButton2026).toHaveAttribute('aria-expanded', 'true');
        await expect(firstAccordion).toHaveJSProperty('open', true);
        const firstPanel = page.locator('#content-2026');
        await expect(firstPanel).toBeVisible();
        await expect(firstPanel).toContainText('Außerordentliche Mitgliederversammlung');

        // Zuklappen per Klick
        await accordionButton2026.click();
        await expect(firstAccordion).toHaveJSProperty('open', false);
        await expect(page.locator('#content-2026')).toBeHidden();

        // 2023 Accordion per Tastatur öffnen und wieder schließen
        const accordion2023 = page.locator('details[data-protokolle-accordion-item]').last();
        const accordionButton2023 = page.getByRole('button', { name: /Protokolle 2023/i });
        await expect(accordionButton2023).toHaveAttribute('aria-expanded', 'false');

        await accordionButton2023.click();
        await expect(accordion2023).toHaveJSProperty('open', true);
        await expect(page.locator('#content-2023')).toContainText('Gründungsversammlung');
        await accordionButton2023.focus();
        await page.keyboard.press('Space');
        await expect(accordion2023).toHaveJSProperty('open', false);
        await expect(page.locator('#content-2023')).toBeHidden();
    });
});
