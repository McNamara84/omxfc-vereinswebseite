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
        
        // DEBUG: Listen for page errors and console errors
        page.on('pageerror', (error) => {
            console.log('[DEBUG] Page error:', error.message);
        });
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                console.log('[DEBUG] Console error:', msg.text());
            }
        });

        await login(page, 'info@maddraxikon.com');
        await page.goto('/kassenbuch');

        await expect(page.locator('header').getByText('Kassenbuch')).toBeVisible();
        await expect(page.getByText('Aktueller Kassenstand')).toBeVisible();

        const addButton = page.getByRole('button', { name: 'Eintrag hinzufügen' });
        await expect(addButton).toBeVisible();
        
        // Wait for page to be fully loaded
        await page.waitForLoadState('networkidle');
        
        // DEBUG: Check if Alpine.js is loaded
        const alpineStatus = await page.evaluate(() => {
            return {
                alpineExists: typeof window.Alpine !== 'undefined',
                alpineStarted: window.Alpine?._x_dataStack !== undefined,
                alpineVersion: window.Alpine?.version ?? 'unknown',
                livewireExists: typeof window.Livewire !== 'undefined',
            };
        });
        console.log('[DEBUG] Alpine/Livewire status before click:', JSON.stringify(alpineStatus));
        
        // DEBUG: Check button attributes
        const buttonInfo = await addButton.evaluate((btn) => ({
            hasXData: btn.hasAttribute('x-data'),
            hasAlpineClick: btn.getAttribute('@click') ?? btn.getAttribute('x-on:click'),
            outerHTML: btn.outerHTML.substring(0, 500),
        }));
        console.log('[DEBUG] Add button info:', JSON.stringify(buttonInfo));
        
        // Click the button to open modal
        await addButton.click();
        
        // DEBUG: Wait a moment and check DOM state
        await page.waitForTimeout(500);
        const modalState = await page.evaluate(() => {
            const dialogs = document.querySelectorAll('[role="dialog"]');
            const xShowElements = document.querySelectorAll('[x-show]');
            return {
                dialogCount: dialogs.length,
                dialogsVisible: Array.from(dialogs).map(d => ({
                    ariaLabel: d.getAttribute('aria-labelledby'),
                    display: window.getComputedStyle(d).display,
                    visibility: window.getComputedStyle(d).visibility,
                    title: d.querySelector('h3')?.textContent?.trim(),
                })),
                xShowCount: xShowElements.length,
            };
        });
        console.log('[DEBUG] Modal state after click:', JSON.stringify(modalState));

        const addDialog = page.getByRole('dialog', { name: 'Kassenbucheintrag hinzufügen' });
        await addDialog.waitFor({ state: 'visible', timeout: 10000 }).catch(async (err) => {
            // DEBUG: Take screenshot on failure
            await page.screenshot({ path: 'test-results/kassenbuch-add-modal-debug.png', fullPage: true });
            console.log('[DEBUG] Screenshot saved to test-results/kassenbuch-add-modal-debug.png');
            
            // DEBUG: Dump page HTML
            const html = await page.content();
            console.log('[DEBUG] Page HTML (first 5000 chars):', html.substring(0, 5000));
            
            throw err;
        });
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
        
        // DEBUG: Listen for page errors and console errors
        page.on('pageerror', (error) => {
            console.log('[DEBUG] Edit test - Page error:', error.message);
        });
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                console.log('[DEBUG] Edit test - Console error:', msg.text());
            }
        });

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
        // Wait for page to be fully loaded
        await page.waitForLoadState('networkidle');
        
        // DEBUG: Check if Alpine.js is loaded
        const alpineStatus = await page.evaluate(() => {
            return {
                alpineExists: typeof window.Alpine !== 'undefined',
                alpineStarted: window.Alpine?._x_dataStack !== undefined,
                alpineVersion: window.Alpine?.version ?? 'unknown',
            };
        });
        console.log('[DEBUG] Alpine status before edit click:', JSON.stringify(alpineStatus));
        
        await editButton.click();
        
        // DEBUG: Wait and check modal state
        await page.waitForTimeout(500);
        const modalState = await page.evaluate(() => {
            const dialogs = document.querySelectorAll('[role="dialog"]');
            return {
                dialogCount: dialogs.length,
                dialogsInfo: Array.from(dialogs).map(d => ({
                    display: window.getComputedStyle(d).display,
                    visibility: window.getComputedStyle(d).visibility,
                    title: d.querySelector('h3')?.textContent?.trim(),
                })),
            };
        });
        console.log('[DEBUG] Modal state after edit click:', JSON.stringify(modalState));

        const editDialog = page.getByRole('dialog', { name: 'Zahlungsdaten bearbeiten' });
        await editDialog.waitFor({ state: 'visible', timeout: 10000 }).catch(async (err) => {
            await page.screenshot({ path: 'test-results/kassenbuch-edit-modal-debug.png', fullPage: true });
            console.log('[DEBUG] Screenshot saved to test-results/kassenbuch-edit-modal-debug.png');
            throw err;
        });
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
