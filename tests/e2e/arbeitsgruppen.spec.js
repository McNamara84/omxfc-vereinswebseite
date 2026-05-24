import { test, expect } from './test-support.js';

test('arbeitsgruppen page shows heading for public teams', async ({ page }) => {
  await page.goto('/arbeitsgruppen');
  await expect(page).toHaveURL(/\/arbeitsgruppen$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Arbeitsgruppen des OMXFC e.V.' })).toBeVisible();
});

test('arbeitsgruppen page links to the protected contact flow and keeps the contact card wide', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1200 });
  await page.goto('/arbeitsgruppen');

  const article = page.locator('article', {
    has: page.getByRole('heading', { level: 2, name: 'AG Fanhoerbuecher' }),
  });

  await expect(article).toContainText('Martin');
  await expect(article).not.toContainText('Martin Gobrecht');
  await expect(article).not.toContainText('ag-hoerbuecher@maddrax-fanclub.de');
  await expect(article.getByText('Kontakt aufnehmen', { exact: true })).toBeVisible();

  const logoStage = article.getByTestId('ag-logo-stage');
  const logoImage = article.getByTestId('ag-logo-image');

  await expect(logoStage).toBeVisible();
  await expect(logoImage).toHaveAttribute('alt', 'Logo der AG Fanhoerbuecher');
  await expect(logoImage).toHaveAttribute('src', /\/storage\/ag-logos\/arbeitsgruppen-playwright-logo\.svg$/);
  await expect(logoImage).toHaveClass(/object-contain/);
  await expect(logoImage).not.toHaveClass(/object-cover/);

  const logoSource = await logoImage.getAttribute('src');
  expect(logoSource).toBeTruthy();

  const logoResponse = await page.request.get(logoSource);
  expect(logoResponse.ok()).toBeTruthy();

  const contactLink = article.getByRole('link', { name: 'Kontakt zur Arbeitsgruppe AG Fanhoerbuecher aufnehmen' });
  await expect(contactLink).toBeVisible();
  await expect(contactLink).toHaveAttribute('href', /\/arbeitsgruppen\/\d+\/kontakt$/);
  await expect(contactLink).not.toHaveAttribute('href', /ag-hoerbuecher@maddrax-fanclub\.de/);

  await expect(article.getByTestId('ag-detail-grid')).toHaveClass(/sm:grid-cols-2/);
  await expect(article.getByTestId('ag-contact-card')).toHaveClass(/sm:col-span-2/);
  await expect(article.getByTestId('ag-contact-card').getByText('Kontakt', { exact: true })).toBeVisible();
});

test('arbeitsgruppen page keeps the logo stage stable on narrow viewports', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/arbeitsgruppen');

  const article = page.locator('article', {
    has: page.getByRole('heading', { level: 2, name: 'AG Fanhoerbuecher' }),
  });

  await expect(article.getByTestId('ag-logo-stage')).toBeVisible();
  await expect(article.getByTestId('ag-logo-image')).toHaveClass(/object-contain/);
  await expect(article.getByRole('link', { name: 'Kontakt zur Arbeitsgruppe AG Fanhoerbuecher aufnehmen' })).toBeVisible();
});
