import { test, expect } from './test-support.js';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { waitUntil: 'domcontentloaded' });
};

test.describe('Dashboard overview', () => {
    const expectDashboardMetrics = async (page) => {
        const metricGroups = page.locator('[data-testid^="dashboard-metric-group-"]');
        const metrics = page.locator(
            '[data-testid^="dashboard-metric-"]:not([data-testid^="dashboard-metric-group-"])',
        );

        await expect(metricGroups).toHaveCount(3);
        await expect(metrics).toHaveCount(12);
    };

    test('admin sees dashboard insights, applicants and verification task', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');

        await expect(page).toHaveURL(/\/dashboard$/);
        await expectDashboardMetrics(page);

        await expect(page.getByTestId('dashboard-applicants-panel')).toBeVisible();
        await expect(page.getByTestId('dashboard-applicant-row').first()).toBeVisible();
        await expect(page.getByTestId('dashboard-task-verification')).toBeVisible();
        await expect(page.getByTestId('dashboard-quick-actions')).toContainText(/Schnellstart/i);

        const topUsers = page.locator('[data-dashboard-top-users]');
        await expect(topUsers).toBeVisible();
        await expect(topUsers).toHaveAttribute('aria-label', /Top 3 Baxx-Sammler/i);
        await expect(topUsers.locator('[data-dashboard-top-summary]')).toContainText(/Top 3 Baxx-Sammler/i);
    });

    test('member sees dashboard without applicant management', async ({ page }) => {
        await login(page, 'playwright-member@example.com');
        await expect(page).toHaveURL(/\/dashboard$/);

        await expect(page.getByTestId('dashboard-applicants-panel')).toHaveCount(0);
        await expect(page.getByTestId('dashboard-task-verification')).toHaveCount(0);
        await expect(page.getByTestId('dashboard-quick-actions')).not.toContainText(/Fantreffen verwalten/i);

        await expectDashboardMetrics(page);
        await expect(page.locator('[data-dashboard-top-summary]')).toContainText(/Top 3 Baxx-Sammler/i);
    });
});
