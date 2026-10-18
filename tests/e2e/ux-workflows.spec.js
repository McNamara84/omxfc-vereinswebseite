import AxeBuilder from '@axe-core/playwright';
import { test, expect } from './test-support.js';

async function login(page, role) {
    await page.goto('/login');
    await page.locator('[name=email]').fill(`ux-${role}@example.com`);
    await page.locator('[name=password]').fill('password');
    await page.locator('[name=password]').press('Enter');
    await expect(page).toHaveURL(/\/dashboard$/);
}

test('membership validation preserves values and focuses linked server errors', async ({ page }) => {
    await page.goto('/mitglied-werden');
    const form = page.getByTestId('mitglied-werden-form');
    await form.locator('[name=vorname]').fill('Mara');
    await form.getByRole('checkbox', { name: /Satzung/ }).check();
    await expect(page.getByTestId('mitglied-submit')).toBeEnabled();
    // Exercise server-side validation even when the client's required checks are bypassed.
    await form.evaluate(element => { element.noValidate = true; element.requestSubmit(); });
    const summary = page.locator('[data-ux-error-summary]');
    await expect(summary).toBeVisible();
    await expect(summary).toBeFocused();
    await expect(form.locator('[name=vorname]')).toHaveValue('Mara');
    await summary.getByRole('link', { name: 'Nachname ist erforderlich.' }).click();
    await expect(form.locator('[name=nachname]')).toBeFocused();
    const result = await new AxeBuilder({ page }).include('[data-testid="mitglied-werden-form"]').withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
    expect(result.violations).toEqual([]);
});

test('review editing protects unsaved text and saves without an extra leave prompt', async ({ page }) => {
    await login(page, 'mitglied');
    await page.goto('/rezensionen/1/erstellen');
    const form = page.locator('form[data-guard-dirty]');
    await expect(form).toBeVisible();
    await form.getByRole('textbox', { name: 'Rezensionstitel', exact: true }).fill('Eine Reise durch die Welt von Maddrax');
    const text = 'Dieser Roman bietet eine spannende Geschichte und interessante Figuren. Besonders überzeugend sind die Atmosphäre und die überraschenden Wendungen, die zum Weiterlesen einladen.';
    await page.getByTestId('review-markdown-input').fill(text);
    page.once('dialog', dialog => dialog.dismiss());
    await form.getByRole('link', { name: 'Abbrechen', exact: true }).click();
    await expect(page).toHaveURL(/\/erstellen$/);
    await expect(page.getByTestId('review-markdown-input')).toHaveValue(text);
    const dialogs = [];
    page.on('dialog', async dialog => { dialogs.push(dialog.message()); await dialog.accept(); });
    await form.getByRole('button', { name: 'Rezension absenden', exact: true }).click();
    await expect(page).toHaveURL(/\/rezensionen\/1$/);
    await expect(page.getByText('Eine Reise durch die Welt von Maddrax', { exact: true })).toBeVisible();
    expect(dialogs).toEqual([]);
});

test('governance can reach pending challenges and confirm a completion', async ({ page }) => {
    await login(page, 'vorstand');
    await page.getByRole('navigation', { name: 'Schnellstart' }).getByRole('link', { name: 'Challenges prüfen' }).click();
    await expect(page).toHaveURL(/filter=pending/);
    const button = page.getByRole('button', { name: 'Abschluss bestätigen und Baxx vergeben', exact: true }).first();
    await expect(button).toBeVisible();
    const row = button.locator('xpath=ancestor::tr');
    const title = await row.locator('td').first().innerText();
    await button.click();
    await expect(page.getByRole('row').filter({ hasText: title })).toHaveCount(0);
    await expect(page).toHaveURL(/filter=pending/);
});

test('mobile dashboard quick actions precede statistics and anchor opens governance details', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, 'admin');
    await expect(page.locator('#dashboard-applicants')).not.toHaveAttribute('open');
    await page.getByRole('navigation', { name: 'Schnellstart' }).getByRole('link', { name: 'Mitgliedsanträge' }).click();
    await expect(page.locator('#dashboard-applicants')).toHaveAttribute('open');
    await expect(page.locator('#dashboard-applicants')).toBeFocused();
    await expect(page.getByTestId('dashboard-applicants-panel')).toBeVisible();
});
