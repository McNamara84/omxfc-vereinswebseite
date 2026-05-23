import { test, expect } from './test-support.js';

test('satzung page displays correct main heading', async ({ page }) => {
  await page.goto('/satzung');
  await expect(page).toHaveURL(/\/satzung$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Satzung des Offiziellen MADDRAX Fanclub e.V.' })).toBeVisible();
});

test('satzung page displays all paragraph headings', async ({ page }) => {
  await page.goto('/satzung');

  const expectedHeadings = [
    'Ã‚Â§1 Name, Sitz des Vereins, Rechtsform und GeschÃƒÂ¤ftsjahr',
    'Ã‚Â§2 Zweck und Ziele',
    'Ã‚Â§3 Mitgliedschaft',
    'Ã‚Â§4 MitgliedsbeitrÃƒÂ¤ge',
    'Ã‚Â§5 Austritt',
    'Ã‚Â§6 Organe',
    'Ã‚Â§7 Vorstand',
    'Ã‚Â§8 Mittel',
    'Ã‚Â§9 KassenprÃƒÂ¼fung',
  ];

  for (const heading of expectedHeadings) {
    await expect(page.getByRole('heading', { level: 2, name: heading })).toBeVisible();
  }
});

test('satzung page displays version date', async ({ page }) => {
  await page.goto('/satzung');
  await expect(page.getByText('Fassung vom 14. MÃƒÂ¤rz 2026')).toBeVisible();
});
