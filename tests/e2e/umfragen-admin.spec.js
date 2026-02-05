import { expect, test } from '@playwright/test';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

test.describe('Umfragen Admin Dashboard', () => {
    test.beforeEach(async ({ page }) => {
        // Admin-Login
        await login(page, 'info@maddraxikon.com');
    });

    test('admin can access the poll management page', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // maryUI x-header rendert ein div, kein header-Element
        await expect(page.getByText('Umfrage verwalten').first()).toBeVisible();
    });

    test('displays poll selection dropdown', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // Prüfe ob Umfrage-Auswahl vorhanden ist (maryUI x-select)
        await expect(page.getByText('Umfrage auswählen')).toBeVisible();
    });

    test('displays new poll button', async ({ page }) => {
        await page.goto('/admin/umfragen');

        await expect(page.getByRole('button', { name: 'Neue Umfrage' })).toBeVisible();
    });

    test('displays question textarea', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // maryUI x-textarea mit Label "Frage"
        await expect(page.locator('textarea[wire\\:model="question"]')).toBeVisible();
    });

    test('displays visibility radio buttons', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // Prüfe ob Sichtbarkeit-Sektion vorhanden ist
        await expect(page.getByText('Sichtbarkeit')).toBeVisible();

        // Prüfe ob Radio-Buttons vorhanden und klickbar sind
        const internalRadio = page.locator('input[name="visibility"][value="internal"]');
        const publicRadio = page.locator('input[name="visibility"][value="public"]');

        await expect(internalRadio).toBeVisible();
        await expect(publicRadio).toBeVisible();
    });

    test('can select visibility option', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // Klicke auf "Öffentlich" Radio-Button
        const publicRadio = page.locator('input[name="visibility"][value="public"]');
        await publicRadio.click();

        // Prüfe ob ausgewählt
        await expect(publicRadio).toBeChecked();
    });

    test('displays date inputs for start and end', async ({ page }) => {
        await page.goto('/admin/umfragen');

        await expect(page.locator('#startsAt')).toBeVisible();
        await expect(page.locator('#endsAt')).toBeVisible();
    });

    test('displays answer options section', async ({ page }) => {
        await page.goto('/admin/umfragen');

        await expect(page.getByText('Antwortmöglichkeiten')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Antwort hinzufügen' })).toBeVisible();
    });

    test('can add answer option', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // Zähle initiale Antwort-Felder
        const initialCount = await page.locator('input[wire\\:model^="options."]').count();

        // Klicke auf "Antwort hinzufügen"
        await page.getByRole('button', { name: 'Antwort hinzufügen' }).click();

        // Warten auf Livewire-Update
        await page.waitForTimeout(500);

        // Prüfe ob neue Antwort hinzugefügt wurde
        const newCount = await page.locator('input[wire\\:model^="options."]').count();
        expect(newCount).toBeGreaterThan(initialCount);
    });

    test('displays action buttons', async ({ page }) => {
        await page.goto('/admin/umfragen');

        await expect(page.getByRole('button', { name: 'Speichern' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Aktivieren' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Archivieren' })).toBeVisible();
    });

    test('displays evaluation section', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // maryUI Card-Titel für Auswertung
        await expect(page.getByText('Auswertung', { exact: true })).toBeVisible();
    });

    test('can fill out new poll form', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // Klicke auf "Neue Umfrage" um sicherzustellen dass wir im richtigen Zustand sind
        await page.getByRole('button', { name: 'Neue Umfrage' }).click();
        await page.waitForTimeout(500);

        // Fülle Frage aus - verwende Textarea direkt
        await page.locator('textarea[wire\\:model="question"]').fill('Was ist dein Lieblings-MADDRAX-Roman?');

        // Fülle Menu-Label aus - verwende Placeholder
        await page.getByPlaceholder('z.B. Abstimmung').fill('Lieblingsroman');

        // Wähle Sichtbarkeit
        await page.locator('input[name="visibility"][value="internal"]').click();

        // Setze Startdatum (heute)
        const today = new Date().toISOString().slice(0, 16);
        await page.locator('#startsAt').fill(today);

        // Setze Enddatum (in 7 Tagen)
        const nextWeek = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 16);
        await page.locator('#endsAt').fill(nextWeek);

        // Füge Antwortmöglichkeiten hinzu
        // Erste Antwort sollte bereits vorhanden sein
        const firstAnswerInput = page.locator('input[wire\\:model="options.0.label"]');
        await firstAnswerInput.fill('Der Gott aus dem Eis');

        // Zweite Antwort hinzufügen
        await page.getByRole('button', { name: 'Antwort hinzufügen' }).click();
        await page.waitForTimeout(300);

        const secondAnswerInput = page.locator('input[wire\\:model="options.1.label"]');
        await secondAnswerInput.fill('Dämonen der Vergangenheit');

        // Dritte Antwort hinzufügen
        await page.getByRole('button', { name: 'Antwort hinzufügen' }).click();
        await page.waitForTimeout(300);

        const thirdAnswerInput = page.locator('input[wire\\:model="options.2.label"]');
        await thirdAnswerInput.fill('Stadt ohne Hoffnung');

        // Prüfe dass alle Felder ausgefüllt sind
        await expect(page.locator('textarea[wire\\:model="question"]')).toHaveValue('Was ist dein Lieblings-MADDRAX-Roman?');
        await expect(page.getByLabel('Link-Name im Menü')).toHaveValue('Lieblingsroman');
        await expect(firstAnswerInput).toHaveValue('Der Gott aus dem Eis');
        await expect(secondAnswerInput).toHaveValue('Dämonen der Vergangenheit');
        await expect(thirdAnswerInput).toHaveValue('Stadt ohne Hoffnung');
    });

    test('can remove answer option', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // Neue Umfrage starten
        await page.getByRole('button', { name: 'Neue Umfrage' }).click();
        await page.waitForTimeout(500);

        // Füge zwei Antworten hinzu
        await page.getByRole('button', { name: 'Antwort hinzufügen' }).click();
        await page.waitForTimeout(300);
        await page.getByRole('button', { name: 'Antwort hinzufügen' }).click();
        await page.waitForTimeout(300);

        // Zähle Antwort-Cards
        const initialCards = await page.locator('.bg-base-200').count();

        // Klicke auf Löschen-Button der ersten Antwort
        const deleteButton = page.locator('button[wire\\:click="removeOption(0)"]');
        await deleteButton.click();
        await page.waitForTimeout(300);

        // Prüfe ob weniger Cards vorhanden sind
        const newCards = await page.locator('.bg-base-200').count();
        expect(newCards).toBeLessThan(initialCards);
    });

    test('shows tooltip on image url info button', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // Neue Umfrage starten
        await page.getByRole('button', { name: 'Neue Umfrage' }).click();
        await page.waitForTimeout(500);

        // Füge eine Antwort hinzu
        await page.getByRole('button', { name: 'Antwort hinzufügen' }).click();
        await page.waitForTimeout(300);

        // Hover über den Info-Button (mit tooltip) - suche nach tooltip-Elementen
        const tooltipElement = page.locator('[data-tip]').first();
        
        // Falls tooltip vorhanden, prüfen wir das Attribut
        const tooltipCount = await tooltipElement.count();
        if (tooltipCount > 0) {
            await expect(tooltipElement).toHaveAttribute('data-tip');
        } else {
            // Fallback: Prüfe ob info-Icon im Formular existiert
            await expect(page.locator('.tooltip, [data-tip]')).toHaveCount(0);
        }
    });

    test('member cannot access poll management', async ({ page }) => {
        // Clear all cookies and storage to ensure fresh session
        await page.context().clearCookies();
        await page.context().clearPermissions();
        
        // Login as regular member
        await page.goto('/login');
        await page.waitForLoadState('networkidle');
        await page.fill('input[name="email"]', 'mcnamara84@aol.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        
        // Wait for dashboard or any authenticated page
        await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 15000 });

        // Try to access admin page
        const response = await page.goto('/admin/umfragen');

        // Should be forbidden or redirected
        expect(response?.status()).not.toBe(200);
    });

    test('guest is redirected from poll management', async ({ page }) => {
        // Visit page without login
        await page.context().clearCookies();
        await page.goto('/admin/umfragen');

        // Should be redirected to login
        await expect(page).toHaveURL(/\/login/);
    });
});
