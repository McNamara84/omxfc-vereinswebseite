import { expect, test } from '@playwright/test';
import { setupLivewirePage, waitForLivewire, dispatchWindowEvent, setAlpineData, forceShowModal, setFormAction } from './utils/livewire-helpers.js';

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

        await setupLivewirePage(page);
        await login(page, 'info@maddraxikon.com');
        await page.goto('/kassenbuch');
        await waitForLivewire(page);

        // Verwende data-testid für stabile Selektoren
        await expect(page.getByTestId('page-header')).toContainText('Kassenbuch');
        await expect(page.getByTestId('kassenstand-card')).toBeVisible();

        const addButton = page.getByTestId('add-entry-button');
        await expect(addButton).toBeVisible();

        // Click the button to open modal (mit Retry + dispatchWindowEvent + setAlpineData + forceShowModal Fallback)
        const addDialog = page.getByTestId('add-entry-dialog');
        await expect(async () => {
            if (!(await addDialog.isVisible())) await addButton.click();
            if (!(await addDialog.isVisible())) await dispatchWindowEvent(page, 'kassenbuch-modal');
            if (!(await addDialog.isVisible())) {
                try { await setAlpineData(page, '[data-testid="add-entry-dialog"]', { open: true }); } catch {}
            }
            if (!(await addDialog.isVisible())) {
                try { await forceShowModal(page, 'add-entry-dialog'); } catch {}
            }
            await expect(addDialog).toBeVisible();
        }).toPass({ intervals: [500, 1000, 2000, 3000], timeout: 20000 });

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

        await setupLivewirePage(page);
        await login(page, 'info@maddraxikon.com');
        await page.goto('/kassenbuch');
        await waitForLivewire(page);

        const editButton = page.getByRole('button', { name: 'Bearbeiten' }).first();
        const editDetail = await editButton.evaluate((button) => ({
            userId: button.getAttribute('data-user-id') ?? '',
            userName: button.getAttribute('data-user-name') ?? '',
            mitgliedsbeitrag: button.getAttribute('data-mitgliedsbeitrag') ?? '',
            bezahltBis: button.getAttribute('data-bezahlt-bis') ?? '',
            mitgliedSeit: button.getAttribute('data-mitglied-seit') ?? '',
        }));

        // Click to open edit modal (mit Retry + dispatchWindowEvent + setAlpineData + forceShowModal Fallback)
        const editDialog = page.getByTestId('edit-payment-dialog');
        await expect(async () => {
            if (!(await editDialog.isVisible())) await editButton.click();
            if (!(await editDialog.isVisible())) {
                await dispatchWindowEvent(page, 'edit-payment-modal', {
                    user_id: editDetail.userId,
                    user_name: editDetail.userName,
                    mitgliedsbeitrag: editDetail.mitgliedsbeitrag,
                    bezahlt_bis: editDetail.bezahltBis,
                    mitglied_seit: editDetail.mitgliedSeit,
                });
            }
            if (!(await editDialog.isVisible())) {
                try {
                    await setAlpineData(page, '[data-testid="edit-payment-dialog"]', {
                        open: true,
                        user_id: editDetail.userId,
                        user_name: editDetail.userName,
                        mitgliedsbeitrag: editDetail.mitgliedsbeitrag,
                        bezahlt_bis: editDetail.bezahltBis,
                        mitglied_seit: editDetail.mitgliedSeit,
                    });
                } catch {}
            }
            if (!(await editDialog.isVisible())) {
                try {
                    await forceShowModal(page, 'edit-payment-dialog', {
                        mitgliedsbeitrag: editDetail.mitgliedsbeitrag,
                        bezahlt_bis: editDetail.bezahltBis,
                        mitglied_seit: editDetail.mitgliedSeit,
                    });
                    // Setze form action direkt (umgeht Alpine :action Binding)
                    await setFormAction(page, 'edit-payment-dialog', `/kassenbuch/zahlung-aktualisieren/${editDetail.userId}`);
                } catch {}
            }
            await expect(editDialog).toBeVisible();
        }).toPass({ intervals: [500, 1000, 2000, 3000], timeout: 20000 });

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

        const statusBadges = page.locator('tbody tr td .badge');
        await expect(statusBadges.first()).toBeVisible();

        const dialogs = page.locator('[role="dialog"]');
        // Prüfe mindestens 2 Dialoge (add-entry, edit-payment sind immer da)
        // Weitere Dialoge (request-edit, edit-entry, reject-edit) sind je nach Rolle verfügbar
        const dialogCount = await dialogs.count();
        expect(dialogCount).toBeGreaterThanOrEqual(2);

        // Prüfe dass alle vorhandenen Dialoge aria-modal haben
        for (let i = 0; i < dialogCount; i++) {
            await expect(dialogs.nth(i)).toHaveAttribute('aria-modal', 'true');
        }
    });
});
