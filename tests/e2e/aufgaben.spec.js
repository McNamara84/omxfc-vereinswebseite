import { test, expect } from '@playwright/test';

async function expectHeadingsInDomOrder(page, headings) {
    const locators = headings.map((name) => page.getByRole('heading', { level: 2, name, exact: true }));

    for (const locator of locators) {
        await expect(locator).toBeVisible();
    }

    const handles = [];

    try {
        for (const locator of locators) {
            // eslint-disable-next-line no-await-in-loop
            handles.push(await locator.elementHandle());
        }

        for (let i = 0; i < handles.length - 1; i += 1) {
            const first = handles[i];
            const second = handles[i + 1];

            expect(first).not.toBeNull();
            expect(second).not.toBeNull();

            // eslint-disable-next-line no-await-in-loop
            const isBefore = await page.evaluate(([a, b]) => {
                if (!a || !b) return false;
                return Boolean(a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING);
            }, [first, second]);

            expect(isBefore).toBe(true);
        }
    } finally {
        await Promise.all(
            handles
                .filter(Boolean)
                .map((handle) => handle.dispose()),
        );
    }
}

test.describe('Aufgaben (Mobile)', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('loads with collapsed filter and shows sections in requested order', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[name="email"]', 'info@maddraxikon.com');
        await page.fill('input[name="password"]', 'password');

        await page.click('button[type="submit"]');
        await page.waitForURL(url => !url.pathname.endsWith('/login'));

        await page.goto('/aufgaben');

        const filterDetails = page.locator('[data-todo-filter-details]');
        const filterSummary = page.locator('[data-todo-filter-summary]');

        await expect(filterDetails).toBeVisible();
        await expect(filterDetails).not.toHaveAttribute('open');
        await expect(filterSummary).toBeVisible();

        const orderedHeadings = [
            'Zu verifizierende Challenges',
            'In Bearbeitung befindliche Challenges',
            'Deine Challenges',
            'Offene Challenges',
            'Vereins-Dashboard',
        ];

        // Nicht alle Abschnitte sind garantiert gerendert (abhängig von Seed-Daten).
        // Diese drei sollten aber immer da sein.
        await expect(page.getByRole('heading', { level: 2, name: 'Deine Challenges', exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { level: 2, name: 'Offene Challenges', exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { level: 2, name: 'Vereins-Dashboard', exact: true })).toBeVisible();

        const existingHeadings = [];
        for (const heading of orderedHeadings) {
            // eslint-disable-next-line no-await-in-loop
            const count = await page.getByRole('heading', { level: 2, name: heading, exact: true }).count();
            if (count > 0) {
                existingHeadings.push(heading);
            }
        }

        await expectHeadingsInDomOrder(page, existingHeadings);

        await filterSummary.click();
        await expect(filterDetails).toHaveAttribute('open');
        await expect(page.getByRole('button', { name: 'Zu verifizieren', exact: true })).toBeVisible();
    });
});

test('admin can filter and accept challenges', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/login');
    await page.fill('input[name="email"]', 'info@maddraxikon.com');
    await page.fill('input[name="password"]', 'password');

    await page.click('button[type="submit"]');
    await page.waitForURL(url => !url.pathname.endsWith('/login'));

    await page.goto('/aufgaben');

    const filterSummary = page.locator('[data-todo-filter-summary]');
    await expect(filterSummary).toBeVisible();
    await filterSummary.click();

    await expect(page.getByRole('heading', { name: 'Deine Challenges' })).toBeVisible();
    await expect(page.locator('[data-todo-filter-status]')).toHaveText(/alle verfügbaren Challenges/i);

    const verifyButton = page.getByRole('button', { name: 'Zu verifizieren', exact: true });
    await expect(verifyButton).toBeVisible();

    await verifyButton.click();
    await expect(page).toHaveURL(/filter=pending/);
    await expect(page.locator('[data-todo-filter-status]')).toHaveText(/Verifizierung warten/i);
    await expect(page.getByRole('heading', { name: 'Zu verifizierende Challenges' })).toBeVisible();

    // Nach dem GET-Filter ist die Seite neu geladen; der Filter ist wieder zu.
    const filterDetails = page.locator('[data-todo-filter-details]');
    await expect(filterDetails).toBeVisible();
    await expect(filterDetails).not.toHaveAttribute('open');
    await filterSummary.click();
    await expect(filterDetails).toHaveAttribute('open');

    const allButton = page.getByRole('button', { name: 'Alle', exact: true });
    await allButton.click();
    await expect(page).not.toHaveURL(/filter=pending/);

    const assignButton = page.getByRole('button', { name: 'Übernehmen', exact: true }).first();
    await expect(assignButton).toBeVisible();

    await Promise.all([
        page.waitForURL(/\/aufgaben\/\d+/),
        assignButton.click(),
    ]);
    await expect(page.locator('div[role="status"]', { hasText: /erfolgreich übernommen/i })).toBeVisible();
});

test('member can focus on own challenges and release one', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'playwright-member@example.com');
    await page.fill('input[name="password"]', 'password');

    await page.click('button[type="submit"]');
    await page.waitForURL(url => !url.pathname.endsWith('/login'));

    await page.goto('/aufgaben');

    const filterSummary = page.locator('[data-todo-filter-summary]');
    await expect(filterSummary).toBeVisible();
    await filterSummary.click();

    const ownButton = page.getByRole('button', { name: 'Eigene Challenges', exact: true });
    await ownButton.click();

    const releaseButton = page.getByRole('button', { name: 'Freigeben', exact: true }).first();

    await releaseButton.click();
    await expect(page.locator('div[role="status"]').first()).toContainText('erfolgreich freigegeben');
    await expect(page.locator('[data-todo-section="assigned"]')).not.toContainText('Übernommene Playwright Challenge');
    await expect(page.locator('[data-todo-section="open"]')).toContainText('Übernommene Playwright Challenge');
});
