import { test, expect } from '@playwright/test';

test('admin can filter and accept challenges', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'info@maddraxikon.com');
    await page.fill('input[name="password"]', 'password');

    await page.click('button[type="submit"]');
    await page.waitForURL(url => !url.pathname.endsWith('/login'));

    await page.goto('/aufgaben');

    await expect(page.getByRole('heading', { name: 'Deine Challenges' })).toBeVisible();
    await expect(page.locator('[data-todo-filter-status]')).toHaveText(/alle verfügbaren Challenges/i);

    const filterToggle = page.getByRole('button', { name: /Filter (anzeigen|verbergen)/i });
    const filterPanel = page.locator('#todo-filter-panel');

    if ((await filterToggle.getAttribute('aria-expanded')) !== 'true') {
        await filterToggle.click();
    }

    await expect(filterToggle).toHaveAttribute('aria-expanded', 'true');
    await expect(filterPanel).toBeVisible();

    const verifyButton = page.getByRole('button', { name: 'Zu verifizieren', exact: true });
    await expect(verifyButton).toBeVisible();

    await verifyButton.click();
    await expect(page).toHaveURL(/filter=pending/);
    await expect(page.locator('[data-todo-filter-status]')).toHaveText(/Verifizierung warten/i);
    await expect(page.getByRole('heading', { name: 'Zu verifizierende Challenges' })).toBeVisible();

    const allButton = page.getByRole('button', { name: 'Alle', exact: true });
    await allButton.click();
    await expect(page).not.toHaveURL(/filter=pending/);

    const assignButton = page.getByRole('button', { name: 'Übernehmen', exact: true }).first();
    await expect(assignButton).toBeVisible();

    await assignButton.click();
    await expect(page.getByRole('status')).toContainText('erfolgreich übernommen');
});

test('member can focus on own challenges and release one', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'playwright-member@example.com');
    await page.fill('input[name="password"]', 'password');

    await page.click('button[type="submit"]');
    await page.waitForURL(url => !url.pathname.endsWith('/login'));

    await page.goto('/aufgaben');

    const filterToggle = page.getByRole('button', { name: /Filter (anzeigen|verbergen)/i });
    const filterPanel = page.locator('#todo-filter-panel');

    if ((await filterToggle.getAttribute('aria-expanded')) !== 'true') {
        await filterToggle.click();
    }

    await expect(filterToggle).toHaveAttribute('aria-expanded', 'true');
    await expect(filterPanel).toBeVisible();

    const ownButton = page.getByRole('button', { name: 'Eigene Challenges', exact: true });
    await ownButton.click();

    const releaseButton = page.getByRole('button', { name: 'Freigeben', exact: true }).first();

    await releaseButton.click();
    await expect(page.locator('div[role="status"]').first()).toContainText('erfolgreich freigegeben');
    await expect(page.locator('[data-todo-section="assigned"]')).not.toContainText('Übernommene Playwright Challenge');
    await expect(page.locator('[data-todo-section="open"]')).toContainText('Übernommene Playwright Challenge');
});
