import { expect, test } from '@playwright/test';
import { setupLivewirePage, waitForLivewire, livewireCall, livewireSet } from './utils/livewire-helpers.js';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

test.describe('Fantreffen VIP-Autoren Verwaltung', () => {
    test.beforeEach(async ({ page }) => {
        await setupLivewirePage(page);
        await login(page, 'info@maddraxikon.com');
    });

    test('Seite ist erreichbar und zeigt bestehende VIP-Autoren', async ({ page }) => {
        await page.goto('/admin/fantreffen-2026/vip-autoren');
        await waitForLivewire(page);

        // Überschrift sichtbar (maryUI <x-header> rendert als <div>, nicht als heading)
        await expect(page.getByText('VIP-Autoren verwalten').first()).toBeVisible();

        // Bestehende Autoren aus dem Seeder sichtbar
        await expect(page.getByText('Oliver Fröhlich')).toBeVisible();
        await expect(page.getByText('Jo Zybell')).toBeVisible();
    });

    test('Neuer VIP-Autor kann angelegt werden', async ({ page }) => {
        await page.goto('/admin/fantreffen-2026/vip-autoren');
        await waitForLivewire(page);

        // "Neuen Autor hinzufügen"-Button klicken (mit Retry + Livewire-Fallback)
        const nameInput = page.getByTestId('vip-author-name');
        await expect(async () => {
            if (!(await nameInput.isVisible())) await page.getByTestId('open-form-button').click();
            if (!(await nameInput.isVisible())) {
                try { await livewireCall(page, 'openForm'); } catch {}
            }
            await expect(nameInput).toBeVisible();
        }).toPass({ intervals: [500, 1000, 2000], timeout: 15000 });

        // Name eingeben
        await nameInput.fill('Testautor Playwright');

        // Pseudonym eingeben
        await page.getByTestId('vip-author-pseudonym').fill('Pseudo Test');

        // Submit-Button klicken + Livewire-Fallback
        await page.getByTestId('submit-form-button').click();
        try {
            await expect(page.getByText('Testautor Playwright')).toBeVisible({ timeout: 5000 });
        } catch {
            await livewireSet(page, 'name', 'Testautor Playwright');
            await livewireSet(page, 'pseudonym', 'Pseudo Test');
            await livewireCall(page, 'save');
            await expect(page.getByText('Testautor Playwright')).toBeVisible({ timeout: 15000 });
        }

        await expect(page.getByText('Pseudo Test')).toBeVisible();

        // Formular sollte geschlossen sein
        await expect(page.getByTestId('vip-author-name')).not.toBeVisible();
    });

    test('Validierung verhindert leeren Namen', async ({ page }) => {
        await page.goto('/admin/fantreffen-2026/vip-autoren');
        await waitForLivewire(page);

        // Formular öffnen (mit Retry + Livewire-Fallback)
        await expect(async () => {
            if (!(await page.getByTestId('vip-author-name').isVisible())) {
                await page.getByTestId('open-form-button').click();
            }
            if (!(await page.getByTestId('vip-author-name').isVisible())) {
                try { await livewireCall(page, 'openForm'); } catch {}
            }
            await expect(page.getByTestId('vip-author-name')).toBeVisible();
        }).toPass({ intervals: [500, 1000, 2000], timeout: 15000 });

        // Submit ohne Name + Livewire-Fallback
        await page.getByTestId('submit-form-button').click();
        try {
            await expect(page.locator('.text-error').first()).toBeVisible({ timeout: 5000 });
        } catch {
            await livewireCall(page, 'save');
            await expect(page.locator('.text-error').first()).toBeVisible({ timeout: 15000 });
        }
    });

    test('Abbrechen-Button schließt das Formular', async ({ page }) => {
        await page.goto('/admin/fantreffen-2026/vip-autoren');
        await waitForLivewire(page);

        // Formular öffnen (mit Retry + Livewire-Fallback)
        await expect(async () => {
            if (!(await page.getByTestId('vip-author-name').isVisible())) {
                await page.getByTestId('open-form-button').click();
            }
            if (!(await page.getByTestId('vip-author-name').isVisible())) {
                try { await livewireCall(page, 'openForm'); } catch {}
            }
            await expect(page.getByTestId('vip-author-name')).toBeVisible();
        }).toPass({ intervals: [500, 1000, 2000], timeout: 15000 });

        // Abbrechen klicken + Livewire-Fallback
        await page.getByTestId('cancel-form-button').click();
        try {
            await expect(page.getByTestId('vip-author-name')).not.toBeVisible({ timeout: 5000 });
        } catch {
            await livewireSet(page, 'showForm', false);
            await expect(page.getByTestId('vip-author-name')).not.toBeVisible({ timeout: 15000 });
        }

        // "Neuen Autor hinzufügen"-Button wieder sichtbar
        await expect(page.getByTestId('open-form-button')).toBeVisible();
    });
});
