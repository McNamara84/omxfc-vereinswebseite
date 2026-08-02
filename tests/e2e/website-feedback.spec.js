import AxeBuilder from '@axe-core/playwright';
import { expect, test, clickAndWaitForLivewireUpdate } from './test-support.js';

const login = async (page) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'playwright-member@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { waitUntil: 'domcontentloaded' });
};

test.describe('Website feedback', () => {
    test('member can submit anonymous feedback and the affordance then disappears', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page);

        const trigger = page.getByTestId('website-feedback-trigger');
        await expect(trigger).toBeVisible();
        await expect(trigger).toHaveAccessibleName('Feedback zur Vereinswebsite geben');

        const triggerBox = await trigger.boundingBox();
        expect(triggerBox?.height).toBeGreaterThanOrEqual(44);
        expect(triggerBox?.width).toBeGreaterThanOrEqual(44);

        await clickAndWaitForLivewireUpdate(page, trigger);

        const modal = page.getByTestId('website-feedback-modal');
        await expect(modal).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Website-Feedback' })).toBeVisible();

        const accessibility = await new AxeBuilder({ page })
            .include('[data-testid="website-feedback-modal"]')
            .analyze();
        expect(accessibility.violations).toEqual([]);

        await page.getByRole('radio', { name: 'Idee' }).check();
        await page.getByLabel('Dein Feedback').fill('Auf Mobilgeräten wäre eine kompaktere Terminübersicht hilfreich.');
        const anonymousCheckbox = page.getByLabel('Anonym an Admins und Vorstand senden');
        await clickAndWaitForLivewireUpdate(page, anonymousCheckbox);
        await expect(anonymousCheckbox).toBeChecked();
        await expect(modal).toBeVisible();
        await expect(page.getByText(
            'Dein Name und deine E-Mail-Adresse werden nicht in die Nachricht oder deren Antwortadresse aufgenommen.',
        )).toBeVisible();

        await clickAndWaitForLivewireUpdate(page, page.getByRole('button', { name: 'Feedback senden' }));

        await expect(page.getByTestId('website-feedback-success')).toBeVisible();
        await expect(trigger).toHaveCount(0);
    });

    test('closing the dialog keeps the button and returns keyboard focus', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await login(page);

        const trigger = page.getByTestId('website-feedback-trigger');
        await trigger.focus();
        await page.keyboard.press('Enter');
        await expect(page.getByTestId('website-feedback-modal')).toBeVisible();

        await page.keyboard.press('Escape');

        await expect(page.getByTestId('website-feedback-modal')).toBeHidden();
        await expect(trigger).toBeVisible();
        await expect(trigger).toBeFocused();
    });
});
