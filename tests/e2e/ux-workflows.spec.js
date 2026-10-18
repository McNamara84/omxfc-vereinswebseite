import AxeBuilder from '@axe-core/playwright';
import { spawnSync } from 'node:child_process';
import { test, expect } from './test-support.js';
import { createPhpProcess } from './utils/php.js';

function createFixture(scenario) {
    const command = createPhpProcess(['tests/e2e/create-ux-workflow-fixture.php', scenario], { env: process.env });
    const result = spawnSync(command.command, command.args, {
        env: process.env, shell: command.shell, encoding: 'utf8', windowsHide: true,
    });
    if (result.error || result.status !== 0) throw new Error(result.stderr || result.error?.message || result.stdout);
    return JSON.parse(result.stdout.trim());
}

async function login(page, email) {
    await page.goto('/login');
    await page.locator('[name=email]').fill(email);
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
    const fixture = createFixture('review');
    await login(page, fixture.email);
    await page.goto(`/rezensionen/${fixture.bookId}/erstellen`);
    const form = page.locator('form[data-guard-dirty]');
    await expect(form).toBeVisible();
    await form.getByRole('textbox', { name: 'Rezensionstitel', exact: true }).fill('Eine Reise durch die Welt von Maddrax');
    const text = 'Dieser Roman bietet eine spannende Geschichte und interessante Figuren. Besonders überzeugend sind die Atmosphäre und die überraschenden Wendungen, die zum Weiterlesen einladen.';
    await page.getByTestId('review-markdown-input').fill(text);
    const cancelDialog = page.waitForEvent('dialog');
    const cancelClick = form.getByRole('link', { name: 'Abbrechen', exact: true }).click();
    const dialog = await cancelDialog;
    expect(dialog.type()).toBe('confirm');
    await dialog.dismiss();
    await cancelClick;
    await expect(page).toHaveURL(/\/erstellen$/);
    await expect(page.getByTestId('review-markdown-input')).toHaveValue(text);
    const dialogs = [];
    page.on('dialog', async dialog => { dialogs.push(dialog.message()); await dialog.accept(); });
    await form.getByRole('button', { name: 'Rezension absenden', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/rezensionen/${fixture.bookId}$`));
    await expect(page.getByText('Eine Reise durch die Welt von Maddrax', { exact: true })).toBeVisible();
    expect(dialogs).toEqual([]);
});

test('governance can reach pending challenges and confirm a completion', async ({ page }) => {
    const fixture = createFixture('challenge');
    await login(page, 'ux-vorstand@example.com');
    await page.getByRole('navigation', { name: 'Schnellstart' }).getByRole('link', { name: 'Challenges prüfen' }).click();
    await expect(page).toHaveURL(/filter=pending/);
    const row = page.getByRole('row').filter({ hasText: fixture.todoTitle });
    await expect(row).toHaveCount(1);
    const button = row.getByRole('button', { name: 'Abschluss bestätigen und Baxx vergeben', exact: true });
    await expect(button).toBeVisible();
    await button.click();
    await expect(row).toHaveCount(0);
    await expect(page).toHaveURL(/filter=pending/);
});

test('mobile dashboard quick actions precede statistics and anchor opens governance details', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, 'ux-admin@example.com');
    await expect(page.locator('#dashboard-applicants')).not.toHaveAttribute('open');
    await page.getByRole('navigation', { name: 'Schnellstart' }).getByRole('link', { name: 'Mitgliedsanträge' }).click();
    await expect(page.locator('#dashboard-applicants')).toHaveAttribute('open');
    await expect(page.locator('#dashboard-applicants')).toBeFocused();
    await expect(page.getByTestId('dashboard-applicants-panel')).toBeVisible();
});
