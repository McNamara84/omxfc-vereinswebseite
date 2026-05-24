import { test, expect } from './test-support.js';

test('arbeitsgruppen page shows heading for public teams', async ({ page }) => {
  await page.goto('/arbeitsgruppen');
  await expect(page).toHaveURL(/\/arbeitsgruppen$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Arbeitsgruppen des OMXFC e.V.' })).toBeVisible();
});

test('arbeitsgruppen page shows obfuscated contact details and a stacked contact card', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1200 });
  await page.goto('/arbeitsgruppen');

  const article = page.locator('article', {
    has: page.getByRole('heading', { level: 2, name: 'AG Fanhoerbuecher' }),
  });

  await expect(article).toContainText('Martin');
  await expect(article).not.toContainText('Martin Gobrecht');
  await expect(article).not.toContainText('ag-hoerbuecher@maddrax-fanclub.de');
  await expect(article.getByText('Kontakt aufnehmen', { exact: true })).toBeVisible();

  const contactLink = article.getByRole('link', { name: 'Kontakt per E-Mail aufnehmen' });
  await expect(contactLink).toBeVisible();
  await expect(contactLink).toHaveAttribute('href', /^mailto:ag-hoerbuecher@maddrax-fanclub\.de$/);

  const leadershipCard = article.locator('dl > div').nth(0);
  const contactCard = article.locator('dl > div').nth(2);

  await expect(leadershipCard.getByText('AG-Leitung', { exact: true })).toBeVisible();
  await expect(contactCard.getByText('Kontakt', { exact: true })).toBeVisible();

  const leadershipBox = await leadershipCard.boundingBox();
  const contactBox = await contactCard.boundingBox();

  expect(leadershipBox).not.toBeNull();
  expect(contactBox).not.toBeNull();
  expect(contactBox.y).toBeGreaterThan(leadershipBox.y + 20);
});
