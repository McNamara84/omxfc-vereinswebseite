import { test, expect } from './test-support.js';

test('chronik page displays timeline images with alt text', async ({ page }) => {
  await page.goto('/chronik');
  await expect(page).toHaveURL(/\/chronik$/);
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Chronik');
  await expect(page.getByAltText('Gründungsversammlung in Berlin 2023')).toBeVisible();
});

test.describe('Chronik Lightbox', () => {
  const firstTriggerName = 'Bild Gründungsversammlung in Berlin 2023 öffnen';
  const secondTriggerName = 'Bild Jahreshauptversammlung in Köln 2024 öffnen';

  async function openLightbox(trigger) {
    // This suite validates Alpine lightbox behavior, not browser pointer hit-testing.
    // dispatchEvent avoids a Firefox-only flake where real clicks occasionally
    // do not reach the trigger before the visibility assertion runs.
    await trigger.dispatchEvent('click');
  }

  test.beforeEach(async ({ page }) => {
    await page.goto('/chronik');
    await page.waitForLoadState('networkidle');
    await page.waitForFunction(() => typeof window.Alpine !== 'undefined' && typeof window.Livewire !== 'undefined');
    await expect(page.getByRole('button', { name: firstTriggerName })).toBeVisible();

    // Wait for Alpine.js to fully initialize ALL x-data components on the page
    await page.waitForFunction(() => {
      const components = document.querySelectorAll('[x-data]');
      return components.length > 0 && [...components].every(el => el._x_dataStack && el._x_dataStack.length > 0);
    });
  });

  test('opens lightbox on image click and shows correct alt text', async ({ page }) => {
    await openLightbox(page.getByRole('button', { name: firstTriggerName }));

    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible();
    await expect(dialog).toHaveAttribute('aria-modal', 'true');
    await expect(dialog).toHaveAttribute('aria-labelledby', 'chronik-lightbox-title');

    const title = page.locator('#chronik-lightbox-title');
    await expect(title).toHaveText('Gründungsversammlung in Berlin 2023');
  });

  test('closes lightbox via close button', async ({ page }) => {
    await openLightbox(page.getByRole('button', { name: firstTriggerName }));

    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible();

    await page.getByLabel('Bild schließen').click();
    await expect(dialog).not.toBeVisible();
  });

  test('closes lightbox via Escape key', async ({ page }) => {
    await openLightbox(page.getByRole('button', { name: firstTriggerName }));

    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
  });

  test('closes lightbox via backdrop click', async ({ page }) => {
    const trigger = page.getByRole('button', { name: firstTriggerName });

    await openLightbox(trigger);

    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible();

    // dispatchEvent sets event.target to the dialog element itself,
    // which reliably triggers Alpine's @click.self handler
    await dialog.dispatchEvent('click');
    await expect(dialog).not.toBeVisible();
  });

  test('switches image when opening different lightbox triggers', async ({ page }) => {
    await openLightbox(page.getByRole('button', { name: firstTriggerName }));

    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible();

    const title = page.locator('#chronik-lightbox-title');
    await expect(title).toHaveText('Gründungsversammlung in Berlin 2023');

    // Close and open a different image
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();

    const secondEntry = page.locator('article').filter({ hasText: '11. Mai 2024' });
    const secondTrigger = secondEntry.getByRole('button', { name: secondTriggerName });

    await secondEntry.scrollIntoViewIfNeeded();
    await expect(secondTrigger).toBeVisible();
    await openLightbox(secondTrigger);
    await expect(dialog).toBeVisible();
    await expect(title).toHaveText('Jahreshauptversammlung in Köln 2024');
  });
});
