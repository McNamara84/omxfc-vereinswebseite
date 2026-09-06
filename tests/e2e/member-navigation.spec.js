import AxeBuilder from '@axe-core/playwright';
import { expect, test } from './test-support.js';

const login = async (page) => {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'playwright-member@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.locator('input[name="password"]').press('Enter');
  await page.waitForURL(/\/dashboard$/);
};

test('member shell exposes a collapsible desktop sidebar with one active link', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 900 });
  await login(page);

  const sidebar = page.getByTestId('member-sidebar-navigation');
  const drawerToggle = page.getByTestId('member-drawer-toggle');

  await expect(sidebar).toBeVisible();
  await expect(drawerToggle).toBeHidden();
  await expect(sidebar.getByRole('link', { name: /Profil von .+ öffnen/ })).toBeVisible();
  await expect(sidebar.locator('[aria-current="page"]')).toHaveCount(1);
  await expect(sidebar.locator('[aria-current="page"]')).toContainText('Dashboard');

  await page.getByText('Navigation einklappen', { exact: true }).click();
  await expect(sidebar.getByText('Community', { exact: true })).toBeHidden();

  const results = await new AxeBuilder({ page })
    .include('nav[aria-label="Kopfleiste des Mitgliederbereichs"]')
    .include('[data-testid="member-sidebar-navigation"]')
    .analyze();

  expect(results.violations).toEqual([]);
});

test('mobile member drawer opens, closes with escape and closes after navigation', async ({ page }) => {
  test.setTimeout(45_000);

  await page.setViewportSize({ width: 390, height: 844 });
  await login(page);

  const toggle = page.getByTestId('member-drawer-toggle');
  const drawer = page.locator('#member-drawer');
  const sidebar = page.getByTestId('member-sidebar-navigation');

  await expect(toggle).toBeVisible();
  await expect(toggle).toHaveAccessibleName('Hauptmenü öffnen');
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await expect(drawer).not.toBeChecked();

  await toggle.click();
  await expect(drawer).toBeChecked();
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(sidebar).toBeVisible();

  await page.keyboard.press('Escape');
  await expect(drawer).not.toBeChecked();
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await expect(toggle).toBeFocused();

  await toggle.click();
  const overlayBox = await page.locator('label.drawer-overlay[for="member-drawer"]').boundingBox();
  expect(overlayBox).not.toBeNull();
  await page.mouse.click(overlayBox.x + overlayBox.width - 8, overlayBox.y + 100);
  await expect(drawer).not.toBeChecked();
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');

  await toggle.click();
  await sidebar.getByText('Community', { exact: true }).click();
  await sidebar.getByRole('link', { name: 'Mitgliederliste', exact: true }).click();
  await expect(page).toHaveURL(/\/mitglieder$/, { timeout: 15_000 });
  await expect(page.locator('#member-drawer')).not.toBeChecked();
});

[
  { width: 768, mobile: true },
  { width: 1023, mobile: true },
  { width: 1024, mobile: false },
  { width: 1440, mobile: false },
].forEach(({ width, mobile }) => {
  test(`member shell switches navigation correctly at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    await login(page);

    const toggle = page.getByTestId('member-drawer-toggle');
    const sidebar = page.getByTestId('member-sidebar-navigation');

    if (mobile) {
      await expect(toggle).toBeVisible();
      await expect(sidebar).toBeHidden();
    } else {
      await expect(toggle).toBeHidden();
      await expect(sidebar).toBeVisible();
    }

    const hasHorizontalOverflow = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
    );
    expect(hasHorizontalOverflow).toBe(false);
  });
});

test('authenticated users retain the public navbar on public routes', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 900 });
  await login(page);
  await page.goto('/satzung');

  await expect(page.locator('nav[aria-label="Hauptnavigation"]')).toBeVisible();
  await expect(page.locator('#member-drawer')).toHaveCount(0);
  await expect(page.getByTestId('member-sidebar-navigation')).toHaveCount(0);
});
