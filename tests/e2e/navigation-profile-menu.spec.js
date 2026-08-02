import AxeBuilder from '@axe-core/playwright';
import { expect, test } from './test-support.js';

const login = async (page) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'playwright-member@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.locator('input[name="password"]').press('Enter');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

test('profile dropdown is keyboard accessible and has no nested interactive control', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page);

    const trigger = page.getByTestId('profile-menu-trigger');
    const profileLink = page.getByRole('link', { name: 'Profil', exact: true });

    await expect(trigger).toBeVisible();
    await expect(trigger).toHaveAccessibleName(/Profilmenü von .+ öffnen/);
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');

    await trigger.focus();
    await page.keyboard.press('Enter');

    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    await expect(profileLink).toBeVisible();

    const accessibilityScanResults = await new AxeBuilder({ page })
        .include('nav[aria-label="Hauptnavigation"]')
        .withRules(['nested-interactive'])
        .analyze();

    expect(accessibilityScanResults.violations).toEqual([]);

    await page.keyboard.press('Escape');

    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    await expect(trigger).toBeFocused();
    await expect(profileLink).toBeHidden();
});
