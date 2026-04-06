import { test, expect } from '@playwright/test';

test('satzung page displays correct main heading', async ({ page }) => {
  await page.goto('/satzung');
  await expect(page).toHaveURL(/\/satzung$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Satzung des Offiziellen MADDRAX Fanclub e.V.' })).toBeVisible();
});

test('satzung page displays all paragraph headings', async ({ page }) => {
  await page.goto('/satzung');

  const expectedHeadings = [
    '§1 Name, Sitz des Vereins, Rechtsform und Geschäftsjahr',
    '§2 Zweck und Ziele',
    '§3 Mitgliedschaft',
    '§4 Mitgliedsbeiträge',
    '§5 Austritt',
    '§6 Organe',
    '§7 Vorstand',
    '§8 Mittel',
    '§9 Kassenprüfung',
  ];

  for (const heading of expectedHeadings) {
    await expect(page.getByRole('heading', { level: 2, name: heading })).toBeVisible();
  }
});

test('satzung page displays version date', async ({ page }) => {
  await page.goto('/satzung');
  await expect(page.getByText('Fassung vom 14. März 2026')).toBeVisible();
});
