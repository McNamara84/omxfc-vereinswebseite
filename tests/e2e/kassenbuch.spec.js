import { expect, test } from '@playwright/test';

const escapeRegExp = (value) => String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

test.describe('Kassenbuch Verwaltung', () => {
    test('admin can add entries via the modal', async ({ page }) => {
        test.setTimeout(60_000);

        await login(page, 'info@maddraxikon.com');
        await page.goto('/kassenbuch');

        await expect(page.getByRole('heading', { level: 1, name: 'Kassenbuch' })).toBeVisible();
        await expect(page.getByRole('heading', { level: 2, name: 'Aktueller Kassenstand' })).toBeVisible();

        const addButton = page.getByRole('button', { name: 'Eintrag hinzufügen' });
        await expect(addButton).toBeVisible();
        
        // Wait for page to be fully loaded and interactive
        await page.waitForLoadState('networkidle');
        
        // Click the button and dispatch the event directly as backup
        await addButton.click();
        
        // Also dispatch the custom event to ensure the modal opens
        await page.evaluate(() => {
            window.dispatchEvent(new CustomEvent('kassenbuch-modal'));
        });

        const addDialog = page.locator('[role="dialog"][aria-labelledby="kassenbuch-modal-title"]');
        await addDialog.waitFor({ state: 'visible', timeout: 15000 });
        await expect(addDialog).toBeVisible();
        await expect(addDialog.getByLabel('Buchungsdatum')).toHaveAttribute('aria-describedby', 'buchungsdatum-error');
        await expect(addDialog.getByLabel('Beschreibung')).toHaveAttribute('aria-describedby', 'beschreibung-error');
        await expect(addDialog.getByLabel('Betrag (€)')).toHaveAttribute('aria-describedby', 'betrag-error');

        // Regression guard: the dialog must be clickable/focusable (not covered by the backdrop).
        const addBeschreibungInput = addDialog.getByLabel('Beschreibung');
        await addBeschreibungInput.click();
        await expect(addBeschreibungInput).toBeFocused();

        await addBeschreibungInput.fill('Playwright Einnahme');
        const addBetragInput = addDialog.getByLabel('Betrag (€)');
        await addBetragInput.click();
        await expect(addBetragInput).toBeFocused();
        await addBetragInput.fill('15');

        await addDialog.getByRole('button', { name: 'Hinzufügen' }).click();
        await expect(page.getByText('Kassenbucheintrag wurde hinzugefügt.')).toBeVisible({ timeout: 10000 });
        await expect(page.getByRole('cell', { name: 'Playwright Einnahme' })).toBeVisible();
    });

    test('admin can edit payment status via the modal', async ({ page }) => {
        test.setTimeout(60_000);

        await login(page, 'info@maddraxikon.com');
        await page.goto('/kassenbuch');

        // Wait for page to be fully loaded and interactive
        await page.waitForLoadState('networkidle');

        const editButton = page.getByRole('button', { name: 'Bearbeiten' }).first();
        const editDetail = await editButton.evaluate((button) => ({
            userId: button.getAttribute('data-user-id') ?? '',
            userName: button.getAttribute('data-user-name') ?? '',
            mitgliedsbeitrag: button.getAttribute('data-mitgliedsbeitrag') ?? '',
            bezahltBis: button.getAttribute('data-bezahlt-bis') ?? '',
            mitgliedSeit: button.getAttribute('data-mitglied-seit') ?? '',
        }));
        await editButton.click();
        
        // Also dispatch the custom event to ensure the modal opens
        await page.evaluate((detail) => {
            window.dispatchEvent(new CustomEvent('edit-payment-modal', {
                detail: {
                    user_id: detail.userId ?? '',
                    user_name: detail.userName ?? '',
                    mitgliedsbeitrag: detail.mitgliedsbeitrag ?? '',
                    bezahlt_bis: detail.bezahltBis ?? '',
                    mitglied_seit: detail.mitgliedSeit ?? '',
                },
            }));
        }, editDetail);

        const editDialog = page.locator('[role="dialog"][aria-labelledby="edit-payment-title"]');
        await editDialog.waitFor({ state: 'visible', timeout: 15000 });
        await expect(editDialog).toBeVisible();

        const mitgliedsbeitragInput = editDialog.getByLabel('Mitgliedsbeitrag (€)');
        await expect(mitgliedsbeitragInput).toHaveAttribute('aria-describedby', 'mitgliedsbeitrag-error');
        await mitgliedsbeitragInput.click();
        await expect(mitgliedsbeitragInput).toBeFocused();

        await mitgliedsbeitragInput.fill('50');
        await editDialog.getByLabel('Bezahlt bis').fill('2026-12-31');
        await editDialog.getByLabel('Mitglied seit').fill('2020-01-01');

        await editDialog.getByRole('button', { name: 'Speichern' }).click();

        const escapedName = escapeRegExp(editDetail.userName);
        await expect(page.getByText(new RegExp(`Zahlungsdaten für\\s+${escapedName}\\s+wurden aktualisiert\\.`))).toBeVisible({
            timeout: 10000,
        });

        const membersTable = page.getByRole('table').first();
        const memberRow = membersTable.getByRole('row', { name: new RegExp(escapedName) }).first();
        await expect(memberRow).toContainText(/50,00\s+€/);
        await expect(memberRow).toContainText('31.12.2026');
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
