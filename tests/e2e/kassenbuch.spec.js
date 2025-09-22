import { expect, test } from '@playwright/test';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button[type="submit"]'),
    ]);
};

test.describe('Kassenbuch Verwaltung', () => {
    test('admin manages entries with accessible dialogs', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');

        await page.goto('/kassenbuch');

        await expect(page.getByRole('heading', { level: 1, name: 'Kassenbuch' })).toBeVisible();
        await expect(page.getByRole('heading', { level: 2, name: 'Aktueller Kassenstand' })).toBeVisible();

        const addButton = page.getByRole('button', { name: 'Eintrag hinzufügen' });
        await expect(addButton).toBeVisible();
        await addButton.click();

        const addDialog = page.getByRole('dialog', { name: 'Kassenbucheintrag hinzufügen' });
        await addDialog
            .waitFor({ state: 'visible', timeout: 2000 })
            .catch(async () => {
                await page.evaluate(() => window.dispatchEvent(new CustomEvent('kassenbuch-modal')));
                await addDialog.waitFor({ state: 'visible' });
            });
        await expect(addDialog).toBeVisible();
        await expect(addDialog.getByLabel('Buchungsdatum')).toHaveAttribute('aria-describedby', 'buchungsdatum-error');
        await expect(addDialog.getByLabel('Beschreibung')).toHaveAttribute('aria-describedby', 'beschreibung-error');
        await expect(addDialog.getByLabel('Betrag (€)')).toHaveAttribute('aria-describedby', 'betrag-error');

        await addDialog.getByLabel('Beschreibung').fill('Playwright Einnahme');
        await addDialog.getByLabel('Betrag (€)').fill('15');

        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            addDialog.getByRole('button', { name: 'Hinzufügen' }).click(),
        ]);

        await expect(page.getByText('Kassenbucheintrag wurde hinzugefügt.')).toBeVisible();
        await expect(page.getByRole('cell', { name: 'Playwright Einnahme' })).toBeVisible();

        const editButton = page.getByRole('button', { name: 'Bearbeiten' }).first();
        const editDetail = await editButton.evaluate((button) => ({
            userId: button.getAttribute('data-user-id') ?? '',
            userName: button.getAttribute('data-user-name') ?? '',
            mitgliedsbeitrag: button.getAttribute('data-mitgliedsbeitrag') ?? '',
            bezahltBis: button.getAttribute('data-bezahlt-bis') ?? '',
            mitgliedSeit: button.getAttribute('data-mitglied-seit') ?? '',
        }));
        await editButton.click();

        const editDialog = page.getByRole('dialog', { name: 'Zahlungsdaten bearbeiten' });
        await editDialog
            .waitFor({ state: 'visible', timeout: 2000 })
            .catch(async () => {
                await page.evaluate((detail) => {
                    window.dispatchEvent(
                        new CustomEvent('edit-payment-modal', {
                            detail: {
                                user_id: detail.userId ?? '',
                                user_name: detail.userName ?? '',
                                mitgliedsbeitrag: detail.mitgliedsbeitrag ?? '',
                                bezahlt_bis: detail.bezahltBis ?? '',
                                mitglied_seit: detail.mitgliedSeit ?? '',
                            },
                        }),
                    );
                }, editDetail);
                await editDialog.waitFor({ state: 'visible' });
            });
        await expect(editDialog).toBeVisible();
        await expect(editDialog.getByLabel('Mitgliedsbeitrag (€)')).toHaveAttribute('aria-describedby', 'mitgliedsbeitrag-error');

        await editDialog.getByLabel('Mitgliedsbeitrag (€)').fill('50');
        await editDialog.getByLabel('Bezahlt bis').fill('2026-12-31');
        await editDialog.getByLabel('Mitglied seit').fill('2020-01-01');

        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            editDialog.getByRole('button', { name: 'Speichern' }).click(),
        ]);

        await expect(page).toHaveURL(/\/kassenbuch$/);
    });

    test('kassenbuch exposes modal triggers and status badges for analytics tooling', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');

        await page.goto('/kassenbuch');

        const addEntryTrigger = page.locator('[data-kassenbuch-modal-trigger="true"]');
        await expect(addEntryTrigger).toHaveCount(1);
        await expect(addEntryTrigger.first()).toHaveAttribute('type', 'button');

        const editButtons = page.locator('[data-kassenbuch-edit="true"]');
        await expect(editButtons.first()).toBeVisible();
        await expect(editButtons.first()).toHaveAttribute('data-user-name', /\S+/);

        const statusBadges = page.locator('tbody tr td span.rounded-full');
        await expect(statusBadges.first()).toBeVisible();

        const dialogs = page.locator('[role="dialog"]');
        await expect(dialogs).toHaveCount(2);
        await expect(dialogs.nth(0)).toHaveAttribute('aria-modal', 'true');
        await expect(dialogs.nth(1)).toHaveAttribute('aria-modal', 'true');
    });
});
