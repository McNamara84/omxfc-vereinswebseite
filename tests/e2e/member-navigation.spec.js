import AxeBuilder from '@axe-core/playwright';
import { expect, test } from './test-support.js';

const login = async (page, email = 'playwright-member@example.com') => {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password');
  await page.locator('input[name="password"]').press('Enter');
  await page.waitForURL(/\/dashboard$/);
};

for (const role of ['mitglied', 'admin']) {
  test(`unconfirmed ${role} retains desktop navigation after reload and Livewire navigation`, async ({ page }) => {
    test.setTimeout(60_000);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await login(page, `ux-${role}-unconfirmed@example.com`);
    const sidebar = page.getByTestId('member-sidebar-navigation');
    const assertMenu = async (active) => {
      await expect(sidebar.getByRole('link', { name: 'Dashboard', exact: true })).toBeVisible();
      await expect(sidebar.getByText('Community', { exact: true })).toBeVisible();
      await expect(sidebar.getByText('Lesen & Entdecken', { exact: true })).toBeVisible();
      await expect(sidebar.locator('[aria-current="page"]')).toHaveCount(1);
      await expect(sidebar.locator('[aria-current="page"]')).toHaveText(active);
      await expect(sidebar.locator('[data-tour-key="section-board"]')).toHaveCount(role === 'admin' ? 1 : 0);
      await expect(sidebar.locator('[data-tour-key="section-admin"]')).toHaveCount(role === 'admin' ? 1 : 0);
      await expect(sidebar.getByRole('link', { name: 'Kurznachrichten verwalten', exact: true, includeHidden: true })).toHaveCount(role === 'admin' ? 1 : 0);
    };

    await assertMenu('Dashboard');
    await page.reload();
    await assertMenu('Dashboard');
    await sidebar.getByText('Lesen & Entdecken', { exact: true }).click();
    await sidebar.getByRole('link', { name: 'Kompendium', exact: true }).click();
    await expect(page).toHaveURL(/\/kompendium$/);
    await assertMenu('Kompendium');
    await page.reload();
    await assertMenu('Kompendium');
    await sidebar.getByRole('link', { name: 'Dashboard', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await assertMenu('Dashboard');
    const collapseToggle = page.getByText('Navigation einklappen', { exact: true }).locator('..');
    await collapseToggle.click();
    await expect(sidebar.getByText('Community', { exact: true })).toBeHidden();
    await collapseToggle.click();
    await assertMenu('Dashboard');
  });
}

test('unconfirmed member can use and reopen the mobile drawer after navigation', async ({ page }) => {
  test.setTimeout(45_000);
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page, 'ux-mitglied-unconfirmed@example.com');

  const toggle = page.getByTestId('member-drawer-toggle');
  const sidebar = page.getByTestId('member-sidebar-navigation');
  await toggle.click();
  await expect(sidebar.getByRole('link', { name: 'Dashboard', exact: true })).toBeVisible();
  await sidebar.getByText('Community', { exact: true }).click();
  await sidebar.getByRole('link', { name: 'Mitgliederliste', exact: true }).click();
  await expect(page).toHaveURL(/\/mitglieder$/);
  await expect(page.locator('#member-drawer')).not.toBeChecked();
  await toggle.click();
  await expect(sidebar.getByRole('link', { name: 'Mitgliederliste', exact: true })).toBeVisible();
  await expect(sidebar.locator('[aria-current="page"]')).toHaveCount(1);
  await expect(sidebar.locator('[aria-current="page"]')).toHaveText('Mitgliederliste');
  await page.keyboard.press('Escape');
  await expect(page.locator('#member-drawer')).not.toBeChecked();
  await expect(toggle).toBeFocused();
});

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

  const expandedNames = await new AxeBuilder({ page })
    .include('[data-testid="member-sidebar-navigation"]')
    .withRules(['label-content-name-mismatch'])
    .analyze();
  expect(expandedNames.violations).toEqual([]);

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
  const overlay = page.locator('label.drawer-overlay[for="member-drawer"]');
  await expect(overlay).toBeVisible();
  const overlayBox = await overlay.boundingBox();
  expect(overlayBox).not.toBeNull();
  await page.mouse.click(overlayBox.x + overlayBox.width - 8, overlayBox.y + 100);
  await expect(drawer).not.toBeChecked();
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');

  await toggle.click();
  await expect(drawer).toBeChecked();
  await expect(sidebar).toBeVisible();
  await sidebar.getByText('Community', { exact: true }).click();
  await sidebar.getByRole('link', { name: 'Mitgliederliste', exact: true }).click();
  await expect(page).toHaveURL(/\/mitglieder$/, { timeout: 15_000 });
  await expect(page.locator('#member-drawer')).not.toBeChecked();
});

test('member navigation transitions only the content region and respects reduced motion', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.addInitScript(() => {
    window.__omxfcViewTransitionCalls = 0;

    if (typeof document.startViewTransition === 'function') {
      const startViewTransition = document.startViewTransition.bind(document);
      document.startViewTransition = (callback) => {
        window.__omxfcViewTransitionCalls += 1;
        return startViewTransition(callback);
      };
    }
  });
  await login(page);

  const content = page.getByTestId('member-content');
  await expect(content).toHaveCount(1);
  await expect(content).toHaveAttribute('wire:transition.navigate', 'member-content');

  await page.evaluate(() => window.Livewire.navigate('/mitglieder'));
  await expect(page).toHaveURL(/\/mitglieder$/);
  await expect(page.getByTestId('member-content')).toHaveCount(1);
  expect(await page.evaluate(() => window.__omxfcViewTransitionCalls)).toBe(0);

  await page.goBack();
  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByTestId('member-content')).toHaveCount(1);
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
