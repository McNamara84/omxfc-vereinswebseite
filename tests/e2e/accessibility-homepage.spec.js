import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Accessibility checks', () => {
  test('Homepage meets WCAG AA guidelines', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/');

    const navigation = page.locator('nav[x-data]');
    const menuToggle = page.locator('button[aria-controls="mobile-navigation"]');

    await expect(navigation).toHaveAttribute('x-data', /open/);
    await expect(menuToggle).toHaveAccessibleName('Menü öffnen');
    await expect(menuToggle).toHaveAttribute('aria-expanded', 'false');

    await expect(menuToggle).toHaveAttribute('@click', /open\s*=\s*!open/);
    await expect(menuToggle).toContainText('Menü');

    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      // daisyUI drawer-toggle ist ein verstecktes Checkbox-Element ohne Label
      .exclude('input.drawer-toggle')
      // maryUI ThemeToggle erzeugt ein verstecktes Checkbox-Element ohne zugängliches Label
      .exclude('input.theme-controller')
      // Deaktiviere nested-interactive - bekanntes maryUI Dropdown Problem
      .disableRules(['nested-interactive'])
      .analyze();

    const formattedViolations = accessibilityScanResults.violations
      .map((violation) => {
        const targets = violation.nodes
          .flatMap((node) => node.target)
          .join(', ');
        return `${violation.id}: ${violation.help} -> ${targets}`;
      })
      .join('\n');

    expect(accessibilityScanResults.violations, formattedViolations).toEqual([]);
  });
});
