import { expect, test } from './test-support.js';

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
        const authorsList = page.getByTestId('vip-authors-list');

        // ÃƒÅ“berschrift sichtbar (maryUI <x-header> rendert als <div>, nicht als heading)
        await expect(page.getByText('VIP-Autoren verwalten').first()).toBeVisible();

        // Bestehende Autoren aus dem Seeder sichtbar
        await expect(authorsList.getByText('Oliver FrÃƒÂ¶hlich', { exact: true })).toBeVisible();
        await expect(authorsList.getByText('Jo Zybell', { exact: true })).toBeVisible();
    });

    test('Neuer VIP-Autor kann angelegt werden', async ({ page }) => {
        await page.goto('/admin/fantreffen-2026/vip-autoren');
        await page.waitForLoadState('networkidle');
        const authorsList = page.getByTestId('vip-authors-list');

        // "Neuen Autor hinzufÃƒÂ¼gen"-Button klicken
        await page.getByRole('button', { name: /Neuen Autor hinzufÃƒÂ¼gen/i }).click();

        // Formular muss sichtbar sein (maryUI <x-card title> rendert als <div>; Button ist jetzt weg)
        await expect(page.getByText('Neuen Autor hinzufÃƒÂ¼gen').first()).toBeVisible();

        // Name eingeben
        const nameInput = page.locator('input[wire\\:model="name"]');
        await nameInput.fill('Testautor Playwright');

        // Pseudonym eingeben
        const pseudonymInput = page.locator('input[wire\\:model="pseudonym"]');
        await pseudonymInput.fill('Pseudo Test');

        // Submit-Button klicken (muss innerhalb des Formulars liegen)
        const submitButton = page.getByRole('button', { name: /HinzufÃƒÂ¼gen/i });
        await expect(submitButton).toBeVisible();
        await submitButton.click();

        // Warten auf Livewire-Aktualisierung
        await page.waitForLoadState('networkidle');

        // Erfolg prÃƒÂ¼fen: neuer Autor in der Liste sichtbar
        await expect(authorsList.getByText('Testautor Playwright', { exact: true })).toBeVisible();
        await expect(authorsList.getByText('Pseudo Test')).toBeVisible();

        // Formular sollte geschlossen sein
        await expect(page.locator('input[wire\\:model="name"]')).not.toBeVisible();
    });

    test('Validierung verhindert leeren Namen', async ({ page }) => {
        await page.goto('/admin/fantreffen-2026/vip-autoren');
        await page.waitForLoadState('networkidle');

        // Formular ÃƒÂ¶ffnen
        await page.getByRole('button', { name: /Neuen Autor hinzufÃƒÂ¼gen/i }).click();

        // Submit ohne Name
        await page.getByRole('button', { name: /HinzufÃƒÂ¼gen/i }).click();
        await page.waitForLoadState('networkidle');

        // Fehlermeldung sichtbar (maryUI rendert Fehler als <div class="text-error"> in <fieldset>)
        await expect(page.locator('.text-error').first()).toBeVisible();
    });

    test('Abbrechen-Button schlieÃƒÅ¸t das Formular', async ({ page }) => {
        await page.goto('/admin/fantreffen-2026/vip-autoren');
        await page.waitForLoadState('networkidle');

        // Formular ÃƒÂ¶ffnen
        await page.getByRole('button', { name: /Neuen Autor hinzufÃƒÂ¼gen/i }).click();
        await expect(page.locator('input[wire\\:model="name"]')).toBeVisible();

        // Abbrechen klicken
        await page.getByRole('button', { name: /Abbrechen/i }).click();
        await page.waitForLoadState('networkidle');

        // Formular geschlossen
        await expect(page.locator('input[wire\\:model="name"]')).not.toBeVisible();

        // "Neuen Autor hinzufÃƒÂ¼gen"-Button wieder sichtbar
        await expect(page.getByRole('button', { name: /Neuen Autor hinzufÃƒÂ¼gen/i })).toBeVisible();
    });
});
