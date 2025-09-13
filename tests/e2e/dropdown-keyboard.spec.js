import { test, expect } from '@playwright/test';

async function login(page) {
  await page.goto('/login');
  await page.getByLabel('E-Mail').fill('test@example.com');
  await page.getByLabel('Passwort').fill('password');
  await page.getByRole('button', { name: 'Login' }).click();
  await expect(page).toHaveURL(/dashboard/);
}

test('dropdown keyboard navigation', async ({ page }) => {
  await login(page);

  const trigger = page.locator('#verein-button');
  await trigger.focus();
  await page.keyboard.press('Enter');
  await expect(trigger).toHaveAttribute('aria-expanded', 'true');

  const firstItem = page.getByRole('menuitem', { name: 'Mitgliederliste' });
  await expect(firstItem).toBeFocused();

  await page.keyboard.press('Tab');
  const secondItem = page.getByRole('menuitem', { name: 'Mitgliederkarte' });
  await expect(secondItem).toBeFocused();

  await page.keyboard.press('Escape');
  await expect(trigger).toHaveAttribute('aria-expanded', 'false');
  await expect(trigger).toBeFocused();
});
