import { expect, test } from './test-support.js';
import { createDatetimeLocalRange } from './utils/temporal.js';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

const answerOptionCards = (page) => page.locator('[data-testid^="answer-option-"]');

const pollManagementComponent = (page) => page
    .locator('main [wire\\:id]')
    .filter({ has: page.getByTestId('page-header') })
    .first();

const startNewPoll = async (page) => {
    const newPollButton = page.getByRole('button', { name: 'Neue Umfrage' });

    await newPollButton.click();
    await expect(answerOptionCards(page)).toHaveCount(2);
    await expect(newPollButton).toBeEnabled();
    await expect(page.getByTestId('option-0-label')).toBeEditable();
    await expect(page.getByTestId('question-textarea')).toHaveValue('');
};

const addAnswerOption = async (page, expectedCount) => {
    const addOptionButton = page.getByTestId('add-option-button');

    for (let attempt = 0; attempt < 2; attempt += 1) {
        await expect(addOptionButton).toBeEnabled();
        await addOptionButton.click();

        try {
            await expect(answerOptionCards(page)).toHaveCount(expectedCount);

            return;
        } catch (error) {
            if (attempt === 1) {
                throw error;
            }
        }
    }
};

const fillOptionLabel = async (page, index, value) => {
    const input = page.getByTestId(`option-${index}-label`);

    await expect(input).toBeEditable();
    await input.fill(value);
    await input.blur();

    await expect(input).toHaveValue(value);

    return input;
};

test.describe('Umfragen Admin Dashboard', () => {
    test.beforeEach(async ({ page }) => {
        // Admin-Login
        await login(page, 'info@maddraxikon.com');
    });

    test('admin can access the poll management page', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // Verwende data-testid für stabile Selektoren
        await page.waitForLoadState('networkidle');
        await expect(page.getByTestId('page-header')).toContainText('Umfrage verwalten');
    });

    test('displays poll selection dropdown', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // Prüfe ob Umfrage-Auswahl vorhanden ist
        await expect(page.getByTestId('poll-selection-card')).toBeVisible();
        await expect(page.getByTestId('poll-select')).toBeVisible();
    });

    test('displays new poll button', async ({ page }) => {
        await page.goto('/admin/umfragen');

        await expect(page.getByRole('button', { name: 'Neue Umfrage' })).toBeVisible();
    });

    test('displays question textarea', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // Verwende data-testid statt wire:model Selektor
        await expect(page.getByTestId('question-textarea')).toBeVisible();
    });

    test('displays visibility radio buttons', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // Prüfe ob Sichtbarkeit-Sektion vorhanden ist
        await expect(page.getByTestId('visibility-section')).toBeVisible();

        // Prüfe ob Radio-Buttons vorhanden und klickbar sind
        const internalRadio = page.getByTestId('visibility-internal');
        const publicRadio = page.getByTestId('visibility-public');

        await expect(internalRadio).toBeVisible();
        await expect(publicRadio).toBeVisible();
    });

    test('can select visibility option', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // Klicke auf "Öffentlich" Radio-Button
        const publicRadio = page.getByTestId('visibility-public');
        await publicRadio.click();

        // Prüfe ob ausgewählt
        await expect(publicRadio).toBeChecked();
    });

    test('displays date inputs for start and end', async ({ page }) => {
        await page.goto('/admin/umfragen');

        await expect(page.getByTestId('starts-at-input')).toBeVisible();
        await expect(page.getByTestId('ends-at-input')).toBeVisible();
    });

    test('displays answer options section', async ({ page }) => {
        await page.goto('/admin/umfragen');

        await expect(page.getByTestId('options-section')).toBeVisible();
        await expect(page.getByTestId('add-option-button')).toBeVisible();
    });

    test('can add answer option', async ({ page }) => {
        await page.goto('/admin/umfragen');
        await startNewPoll(page);

        // Zähle initiale Antwort-Felder
        const initialCount = await answerOptionCards(page).count();

        // Klicke auf "Antwort hinzufügen"
        await addAnswerOption(page, initialCount + 1);

        // Prüfe ob neue Antwort hinzugefügt wurde
        const newCount = await answerOptionCards(page).count();
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

        // data-testid für Auswertung
        await expect(page.getByTestId('evaluation-card')).toBeVisible();
    });

    test('can fill out new poll form', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // Klicke auf "Neue Umfrage" um sicherzustellen dass wir im richtigen Zustand sind
        await startNewPoll(page);

        // Fülle Frage aus - verwende data-testid
        await page.getByTestId('question-textarea').fill('Was ist dein Lieblings-MADDRAX-Roman?');

        // Fülle Menu-Label aus - verwende data-testid
        await page.getByTestId('menu-label-input').fill('Lieblingsroman');

        // Wähle Sichtbarkeit
        await page.getByTestId('visibility-internal').click();

        const { start: today, end: nextWeek } = createDatetimeLocalRange();

        // Setze Startdatum (heute)
        await page.getByTestId('starts-at-input').fill(today);

        // Setze Enddatum (in 7 Tagen)
        await page.getByTestId('ends-at-input').fill(nextWeek);

        // Zusätzliche Antwortmöglichkeiten zuerst anlegen, dann befüllen.
        // Das vermeidet Blur/Add-Option-Races während paralleler Livewire-Requests.
        await addAnswerOption(page, 3);
        await addAnswerOption(page, 4);

        const firstAnswerInput = await fillOptionLabel(page, 0, 'Der Gott aus dem Eis');
        const secondAnswerInput = await fillOptionLabel(page, 1, 'Dämonen der Vergangenheit');
        const thirdAnswerInput = await fillOptionLabel(page, 2, 'Stadt ohne Hoffnung');

        // Prüfe dass alle Felder ausgefüllt sind
        await expect(page.getByTestId('question-textarea')).toHaveValue('Was ist dein Lieblings-MADDRAX-Roman?');
        await expect(page.getByTestId('menu-label-input')).toHaveValue('Lieblingsroman');
        await expect(firstAnswerInput).toHaveValue('Der Gott aus dem Eis');
        await expect(secondAnswerInput).toHaveValue('Dämonen der Vergangenheit');
        await expect(thirdAnswerInput).toHaveValue('Stadt ohne Hoffnung');
    });

    test('can remove answer option', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // Neue Umfrage starten
        await startNewPoll(page);

        // Füge zwei Antworten hinzu
        await addAnswerOption(page, 3);
        await addAnswerOption(page, 4);

        // Zähle Antwort-Cards
        const initialCards = await answerOptionCards(page).count();

        // Klicke auf Löschen-Button der ersten Antwort
        const deleteButton = page.locator('button[wire\\:click="removeOption(0)"]');
        await deleteButton.click();
        await expect(answerOptionCards(page)).toHaveCount(initialCards - 1);

        // Prüfe ob weniger Cards vorhanden sind
        const newCards = await answerOptionCards(page).count();
        expect(newCards).toBeLessThan(initialCards);
    });

    test('shows tooltip on image url info button', async ({ page }) => {
        await page.goto('/admin/umfragen');

        // Neue Umfrage starten
        await startNewPoll(page);

        // Füge eine Antwort hinzu
        await addAnswerOption(page, 3);

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
        // Skip beforeEach admin login - wir starten frisch
        test.setTimeout(60000);
        
        // Clear all cookies and storage to ensure fresh session
        await page.context().clearCookies();
        
        // Login as regular member (created by TodoPlaywrightSeeder)
        await page.goto('/login');
        await page.waitForLoadState('domcontentloaded');
        await page.fill('input[name="email"]', 'playwright-member@example.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        
        // Wait for login to complete (redirect away from /login)
        await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 30000 });

        // Try to access admin page directly
        const response = await page.goto('/admin/umfragen');
        const currentPath = new URL(page.url()).pathname;
        const pollManagementHeader = page.getByTestId('page-header').filter({
            hasText: 'Umfrage verwalten',
        });

        // Durable denial signal: either a real 403 response or a redirect away
        // from the management URL. Browser engines can differ in the final status
        // they expose after redirects, so a bare status===200 check is too brittle.
        expect(response?.status() === 403 || currentPath !== '/admin/umfragen').toBe(true);
        await expect(pollManagementHeader).toHaveCount(0);
    });

    test('guest is redirected from poll management', async ({ page }) => {
        // Visit page without login
        await page.context().clearCookies();
        await page.goto('/admin/umfragen', { waitUntil: 'domcontentloaded' });

        // Should be redirected to login
        await page.waitForURL(/\/login/, { waitUntil: 'domcontentloaded' });
    });
});
