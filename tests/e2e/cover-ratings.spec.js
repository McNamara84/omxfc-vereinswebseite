import AxeBuilder from '@axe-core/playwright';
import { clickAndWaitForLivewireUpdate, expect, test } from './test-support.js';

const loginAsMember = async (page) => {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'playwright-member@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

const assertAccessible = async (page) => {
  const accessibility = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa'])
    .exclude('input.drawer-toggle')
    .exclude('input.theme-controller')
    .exclude('#nprogress [role="bar"]')
    .disableRules(['nested-interactive'])
    .analyze();

  expect(accessibility.violations).toEqual([]);
};

test('member rates, skips and reviews private cover ratings accessibly', async ({ page }) => {
  await loginAsMember(page);

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/cover-bewertungen');

  await expect(page.getByRole('heading', { level: 1, name: 'Cover-Bewertungen' })).toBeVisible();
  await expect(page.getByTestId('cover-rating-card')).toBeVisible();
  await expect(page.getByTestId('brina-rating-group')).toBeVisible();
  const progressBefore = Number(
    (await page.getByTestId('global-progress').textContent()).trim().split('/')[0],
  );
  const firstImageUrl = await page.getByTestId('current-cover-image').getAttribute('src');
  const imageResponse = await page.request.get(firstImageUrl);
  expect(imageResponse.ok()).toBe(true);
  expect(imageResponse.headers()['content-type']).toContain('image/webp');

  await clickAndWaitForLivewireUpdate(
    page,
    page.locator('label[for$="-rating-5"]'),
  );
  await expect(page.getByTestId('rating-status')).toContainText('5 Brinas bewertet');
  await expect(page.getByTestId('global-progress')).toContainText(`${progressBefore + 1} / 6`);
  await expect(page.getByTestId('current-cover-image')).not.toHaveAttribute('src', firstImageUrl);

  await clickAndWaitForLivewireUpdate(page, page.getByTestId('skip-cover'));
  await expect(page.getByTestId('rating-status')).toContainText('zurückgestellt');

  await assertAccessible(page);

  await page.goto('/cover-bewertungen/meine');
  await expect(page.getByRole('heading', { level: 1, name: 'Meine Bewertungen' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Auf 5 Brinas ändern' }).first())
    .toHaveAttribute('aria-pressed', 'true');

  await page.goto('/cover-bewertungen/ergebnisse');
  await expect(page.getByRole('heading', { level: 1, name: 'Ergebnisse' })).toBeVisible();
  await expect(page.getByText('Noch nicht genügend Bewertungen', { exact: false })).toBeVisible();
});

test('desktop keyboard flow loads only the large current cover and all views pass axe', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.emulateMedia({ colorScheme: 'dark', reducedMotion: 'reduce' });
  await loginAsMember(page);

  const coverRequests = [];
  page.on('request', (request) => {
    const pathname = new URL(request.url()).pathname;

    if (pathname.includes('/cover-bewertungen/cover/')) {
      coverRequests.push(pathname);
    }
  });

  await page.goto('/cover-bewertungen');
  await expect(page.getByTestId('current-cover-image')).toHaveJSProperty('complete', true);
  expect(coverRequests).toHaveLength(1);
  expect(coverRequests[0]).toMatch(/\/large$/);

  const firstRadio = page.getByRole('radio', { name: '1 von 5 Brina' });
  await firstRadio.focus();
  await expect(firstRadio).toBeFocused();
  const update = page.waitForResponse((response) => (
    response.request().method() === 'POST'
      && /\/livewire(?:-[^/]+)?\/update\/?$/.test(new URL(response.url()).pathname)
  ));
  await page.keyboard.press('Space');
  await update;
  await expect(page.getByTestId('rating-status')).toContainText('1 Brina bewertet');
  await expect(page.locator('[data-cover-focus]')).toBeFocused();
  await assertAccessible(page);

  await page.goto('/cover-bewertungen/meine');
  await expect(page.getByRole('heading', { level: 1, name: 'Meine Bewertungen' })).toBeVisible();
  await assertAccessible(page);

  await page.goto('/cover-bewertungen/ergebnisse');
  await expect(page.getByRole('heading', { level: 1, name: 'Ergebnisse' })).toBeVisible();
  await assertAccessible(page);
});
