import { test, expect } from './test-support.js';

test('mitglied werden bestaetigt page thanks user', async ({ page }) => {
  await page.goto('/mitglied-werden/bestaetigt');
  await expect(page.getByRole('heading', { level: 1, name: 'Vielen Dank fÃƒÂ¼r deine BestÃƒÂ¤tigung!' })).toBeVisible();
  await expect(page).toHaveURL(/\/mitglied-werden\/bestaetigt$/);
});
