import { expect, test } from '@playwright/test';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

test.describe('Fantreffen VIP-Autoren Verwaltung', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, 'info@maddraxikon.com');
    });

    test('Seite ist erreichbar und zeigt bestehende VIP-Autoren', async ({ page }) => {
        await page.goto('/admin/fantreffen-2026/vip-autoren');
        await page.waitForLoadState('networkidle');

        // Überschrift sichtbar (maryUI <x-header> rendert als <div>, nicht als heading)
        await expect(page.getByText('VIP-Autoren verwalten').first()).toBeVisible();

        // Bestehende Autoren aus dem Seeder sichtbar
        await expect(page.getByText('Oliver Fröhlich')).toBeVisible();
        await expect(page.getByText('Jo Zybell')).toBeVisible();
    });

    test('Neuer VIP-Autor kann angelegt werden', async ({ page }) => {
        await page.goto('/admin/fantreffen-2026/vip-autoren');
        await page.waitForLoadState('networkidle');

        // "Neuen Autor hinzufügen"-Button klicken
        await page.getByRole('button', { name: /Neuen Autor hinzufügen/i }).click();

        // Formular muss sichtbar sein (maryUI <x-card title> rendert als <div>)
        await expect(page.getByText('Neuen Autor hinzufügen').nth(1)).toBeVisible();

        // Name eingeben
        const nameInput = page.locator('input[wire\\:model="name"]');
        await nameInput.fill('Testautor Playwright');

        // Pseudonym eingeben
        const pseudonymInput = page.locator('input[wire\\:model="pseudonym"]');
        await pseudonymInput.fill('Pseudo Test');

        // Submit-Button klicken (muss innerhalb des Formulars liegen)
        const submitButton = page.getByRole('button', { name: /Hinzufügen/i });
        await expect(submitButton).toBeVisible();
        await submitButton.click();

        // Warten auf Livewire-Aktualisierung
        await page.waitForLoadState('networkidle');

        // Erfolg prüfen: neuer Autor in der Liste sichtbar
        await expect(page.getByText('Testautor Playwright')).toBeVisible();
        await expect(page.getByText('Pseudo Test')).toBeVisible();

        // Formular sollte geschlossen sein
        await expect(page.locator('input[wire\\:model="name"]')).not.toBeVisible();
    });

    test('Validierung verhindert leeren Namen', async ({ page }) => {
        await page.goto('/admin/fantreffen-2026/vip-autoren');
        await page.waitForLoadState('networkidle');

        // Formular öffnen
        await page.getByRole('button', { name: /Neuen Autor hinzufügen/i }).click();

        // Submit ohne Name
        await page.getByRole('button', { name: /Hinzufügen/i }).click();
        await page.waitForLoadState('networkidle');

        // Fehlermeldung sichtbar (maryUI rendert Fehler als <div class="text-error"> in <fieldset>)
        await expect(page.locator('.text-error').first()).toBeVisible();
    });

    test('Abbrechen-Button schließt das Formular', async ({ page }) => {
        await page.goto('/admin/fantreffen-2026/vip-autoren');
        await page.waitForLoadState('networkidle');

        // Formular öffnen
        await page.getByRole('button', { name: /Neuen Autor hinzufügen/i }).click();
        await expect(page.locator('input[wire\\:model="name"]')).toBeVisible();

        // Abbrechen klicken
        await page.getByRole('button', { name: /Abbrechen/i }).click();
        await page.waitForLoadState('networkidle');

        // Formular geschlossen
        await expect(page.locator('input[wire\\:model="name"]')).not.toBeVisible();

        // "Neuen Autor hinzufügen"-Button wieder sichtbar
        await expect(page.getByRole('button', { name: /Neuen Autor hinzufügen/i })).toBeVisible();
    });
});
