import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Accessibility checks', () => {
  test('Homepage meets WCAG AA guidelines', async ({ page }) => {
    await page.goto('/');

    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
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
