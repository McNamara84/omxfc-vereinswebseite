import { test, expect } from '@playwright/test';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button[type="submit"]'),
    ]);
};

test.describe('Mitgliederliste', () => {
    test('admin sees export controls and accessible table interactions', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');

        await page.goto('/mitglieder');
        await expect(page).toHaveURL(/\/mitglieder$/);

        const heading = page.locator('[data-members-heading]');
        await expect(heading).toBeVisible();
        await expect(page.locator('[data-members-summary]')).toContainText('Mitgliederliste, sortiert nach Nachname');
        await expect(page.locator('[data-members-table]')).toHaveAttribute('data-members-sort', 'nachname');

        const nameHeader = page.getByRole('columnheader', { name: 'Name' });
        await expect(nameHeader).toHaveAttribute('aria-sort', 'ascending');

        const onlineCheckbox = page.getByRole('checkbox', { name: 'Nur online' });
        
        // Check the checkbox and trigger form submit
        await onlineCheckbox.check();
        
        // The form auto-submits via Alpine.js - wait for navigation to complete
        // Submit the form explicitly if Alpine.js doesn't trigger
        await page.locator('form').filter({ has: onlineCheckbox }).evaluate(form => form.submit());
        await page.waitForLoadState('networkidle', { timeout: 15000 });
        
        // Verify the filter is now active via URL or attribute
        await expect(page).toHaveURL(/filters|online/, { timeout: 10000 });
        await expect(page.locator('[data-members-table]')).toHaveAttribute('data-members-filter-online', 'true', { timeout: 10000 });
        await expect(page.locator('[data-members-summary]')).toContainText('nur Mitglieder angezeigt, die aktuell online sind');

        const roleHeader = page.getByRole('columnheader', { name: 'Rolle' });
        await roleHeader.click();
        await expect(page).toHaveURL(/sort=role&dir=asc/);
        await expect(roleHeader).toHaveAttribute('aria-sort', 'ascending');

        await roleHeader.click();
        await expect(page).toHaveURL(/sort=role&dir=desc/);
        await expect(roleHeader).toHaveAttribute('aria-sort', 'descending');

        await expect(page.getByRole('button', { name: 'CSV Export' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'E-Mail-Adressen kopieren' })).toBeVisible();
    });

    test('admin can copy a single member email address', async ({ page }) => {
        await page.addInitScript(() => {
            window.__copiedText = null;

            // Mock the clipboard API
            if (!navigator.clipboard) {
                Object.defineProperty(navigator, 'clipboard', {
                    value: {},
                    writable: true,
                    configurable: true,
                });
            }
            navigator.clipboard.writeText = async (text) => {
                window.__copiedText = text;
            };

            // Fallback path uses prompt
            window.prompt = (_message, value) => {
                window.__copiedText = value;
                return value;
            };
            
            // DEBUG: Capture console errors
            window.__consoleLogs = [];
            const originalConsoleError = console.error;
            console.error = (...args) => {
                window.__consoleLogs.push(['error', ...args].join(' '));
                originalConsoleError.apply(console, args);
            };
        });
        
        // DEBUG: Listen for page errors
        page.on('pageerror', (error) => {
            console.log('[DEBUG] Page error:', error.message);
        });
        
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                console.log('[DEBUG] Console error:', msg.text());
            }
        });

        await login(page, 'info@maddraxikon.com');

        await page.goto('/mitglieder');
        await expect(page).toHaveURL(/\/mitglieder$/);

        const firstRow = page.locator('[data-members-table] tbody tr').first();
        await expect(firstRow).toBeVisible();

        await firstRow.getByRole('button', { name: 'Info' }).click();
        const detailsPopover = firstRow.locator('div.absolute').first();
        await expect(detailsPopover).toBeVisible();

        const email = (await detailsPopover.locator('div.text-sm').first().textContent())?.trim();
        expect(email).toBeTruthy();
        expect(email).toContain('@');

        // DEBUG: Check Alpine status before copy
        const alpineStatus = await page.evaluate(() => {
            return {
                alpineExists: typeof window.Alpine !== 'undefined',
                alpineStarted: window.Alpine?._x_dataStack !== undefined,
                alpineVersion: window.Alpine?.version ?? 'unknown',
            };
        });
        console.log('[DEBUG] Alpine status before copy:', JSON.stringify(alpineStatus));
        
        const copyButton = firstRow.locator('[data-copy-email]').first();
        
        // DEBUG: Check button attributes
        const buttonInfo = await copyButton.evaluate((btn) => ({
            hasAlpineClick: btn.getAttribute('@click') ?? btn.getAttribute('x-on:click'),
            onclick: btn.getAttribute('onclick'),
            outerHTML: btn.outerHTML,
        }));
        console.log('[DEBUG] Copy button info:', JSON.stringify(buttonInfo));
        
        await copyButton.click();
        
        // DEBUG: Check __copiedText immediately after click
        await page.waitForTimeout(500);
        const copiedImmediate = await page.evaluate(() => window.__copiedText);
        console.log('[DEBUG] __copiedText after click:', copiedImmediate);

        // Wait for clipboard operation with extended timeout
        await page.waitForFunction(() => window.__copiedText !== null, { timeout: 15000 }).catch(async (err) => {
            await page.screenshot({ path: 'test-results/mitglieder-copy-email-debug.png', fullPage: true });
            console.log('[DEBUG] Screenshot saved to test-results/mitglieder-copy-email-debug.png');
            
            // DEBUG: Check console errors
            const consoleLogs = await page.evaluate(() => {
                return window.__consoleLogs ?? 'No logs captured';
            });
            console.log('[DEBUG] Console logs:', consoleLogs);
            throw err;
        });
        const copied = await page.evaluate(() => window.__copiedText);

        // Email should match (trim whitespace for safety)
        expect(copied?.trim()).toBe(email?.trim());
    });

    test('regular member sees the list without management actions', async ({ page }) => {
        await login(page, 'playwright-member@example.com');

        await page.goto('/mitglieder');
        await expect(page).toHaveURL(/\/mitglieder$/);

        const heading = page.locator('[data-members-heading]');
        await expect(heading).toBeVisible();
        await expect(page.locator('[data-members-summary]')).toContainText('Es werden alle aktiven Mitglieder angezeigt');
        await expect(page.locator('[data-members-table]')).toBeVisible();
        await expect(page.getByRole('button', { name: 'CSV Export' })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'E-Mail-Adressen kopieren' })).toHaveCount(0);
        await expect(page.locator('[data-copy-email]')).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Rolle' })).toHaveCount(0);
    });
});
