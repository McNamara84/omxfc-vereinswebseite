import AxeBuilder from '@axe-core/playwright';
import { clickAndWaitForLivewireUpdate, expect, test } from './test-support.js';

const coverMemberEmail = (flow, browserName, retry) => (
  `playwright-cover-${flow}-${browserName}-retry-${retry}@example.com`
);

const loginAsMember = async (page, email) => {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
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

const startRatingSession = async (page) => {
  await page.getByTestId('start-cover-rating').click();
  await expect(page.getByTestId('cover-rating-session')).toBeVisible();
  await expect(page.getByTestId('current-cover-image')).toBeVisible();
  await expect(page.getByTestId('brina-rating-group')).toBeVisible();
  await expect(page.getByTestId('end-cover-rating')).toBeVisible();
  await expect(page.getByTestId('cover-source-link')).toBeVisible();
  await expect(page.getByTestId('cover-source-link')).toHaveAttribute('target', '_blank');
  await expect(page.getByTestId('website-feedback-trigger')).not.toBeVisible();
};

const endRatingSession = async (page, { completed = false } = {}) => {
  await page.getByTestId('end-cover-rating').click();
  await expect(page.getByTestId('cover-rating-session')).not.toBeVisible();
  if (completed) {
    await expect(page.getByTestId('start-cover-rating')).toBeDisabled();
    await expect(page.getByTestId('cover-rating-overview-empty-state')).toBeFocused();
  } else {
    await expect(page.getByTestId('start-cover-rating')).toBeFocused();
  }
  await expect(page.locator('body')).not.toHaveClass(/cover-rating-session-open/);
};

const assertRatingSessionFitsViewport = async (page, viewport) => {
  await page.setViewportSize(viewport);
  await page.getByTestId('cover-rating-session').evaluate((session) => {
    Object.defineProperty(session, 'requestFullscreen', {
      configurable: true,
      value: undefined,
    });
  });
  await startRatingSession(page);

  const metrics = await page.evaluate(() => {
    const box = (selector) => {
      const rect = document.querySelector(selector).getBoundingClientRect();

      return {
        top: rect.top,
        right: rect.right,
        bottom: rect.bottom,
        left: rect.left,
        width: rect.width,
        height: rect.height,
      };
    };
    const image = document.querySelector('[data-testid="current-cover-image"]');
    const stage = document.querySelector('[data-testid="cover-rating-image-stage"]');
    const stageBox = stage.getBoundingClientRect();
    const scale = Math.min(
      stageBox.width / image.naturalWidth,
      stageBox.height / image.naturalHeight,
    );

    return {
      viewport: { width: window.innerWidth, height: window.innerHeight },
      session: box('[data-testid="cover-rating-session"]'),
      stage: box('[data-testid="cover-rating-image-stage"]'),
      controls: box('.cover-rating-session__controls'),
      image: {
        ...box('[data-testid="current-cover-image"]'),
        naturalWidth: image.naturalWidth,
        naturalHeight: image.naturalHeight,
        objectFit: getComputedStyle(image).objectFit,
        paintedWidth: image.naturalWidth * scale,
        paintedHeight: image.naturalHeight * scale,
      },
      horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth,
      bodyLocked: document.body.classList.contains('cover-rating-session-open'),
    };
  });

  expect(metrics.viewport).toEqual(viewport);
  expect(Math.abs(metrics.session.top)).toBeLessThanOrEqual(1);
  expect(Math.abs(metrics.session.left)).toBeLessThanOrEqual(1);
  expect(Math.abs(metrics.session.width - viewport.width)).toBeLessThanOrEqual(1);
  expect(Math.abs(metrics.session.height - viewport.height)).toBeLessThanOrEqual(1);
  expect(metrics.stage.height).toBeGreaterThan(100);
  expect(metrics.stage.top).toBeGreaterThanOrEqual(0);
  expect(metrics.stage.bottom).toBeLessThanOrEqual(metrics.controls.top + 1);
  expect(metrics.controls.bottom).toBeLessThanOrEqual(viewport.height + 1);
  expect(metrics.image.objectFit).toBe('contain');
  expect(metrics.image.width).toBeGreaterThan(metrics.image.naturalWidth);
  expect(metrics.image.height).toBeGreaterThan(metrics.image.naturalHeight);
  expect(metrics.image.paintedWidth).toBeGreaterThan(metrics.image.naturalWidth);
  expect(metrics.image.paintedHeight).toBeGreaterThan(metrics.image.naturalHeight);
  expect(metrics.horizontalOverflow).toBe(false);
  expect(metrics.bodyLocked).toBe(true);

  await endRatingSession(page);
  await page.getByTestId('cover-rating-session').evaluate((session) => {
    delete session.requestFullscreen;
  });
};

test('member rates, skips and reviews private cover ratings accessibly', async ({ page, browserName }, testInfo) => {
  await loginAsMember(page, coverMemberEmail('mobile', browserName, testInfo.retry));

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/cover-bewertungen');

  await expect(page.getByRole('heading', { level: 1, name: 'Cover-Bewertungen' })).toBeVisible();
  await expect(page.getByTestId('cover-rating-overview')).toBeVisible();
  await expect(page.getByTestId('start-cover-rating')).toBeVisible();
  await expect(page.getByTestId('cover-rating-session')).not.toBeVisible();
  const progressBefore = Number(
    (await page.getByTestId('global-progress').textContent()).trim().split('/')[0],
  );
  const firstImageUrl = await page.getByTestId('current-cover-image').getAttribute('src');
  const imageResponse = await page.request.get(firstImageUrl);
  expect(imageResponse.ok()).toBe(true);
  expect(imageResponse.headers()['content-type']).toContain('image/webp');

  await startRatingSession(page);
  await assertAccessible(page);

  await clickAndWaitForLivewireUpdate(
    page,
    page.locator('label[for$="-rating-5"]'),
  );
  await expect(page.getByTestId('rating-status')).toContainText('5 Brinas bewertet');
  await expect(page.getByTestId('global-progress')).toContainText(`${progressBefore + 1} / 6`);
  await expect(page.getByTestId('current-cover-image')).not.toHaveAttribute('src', firstImageUrl);
  await expect(page.getByTestId('cover-rating-session')).toBeVisible();
  await expect(page.getByTestId('brina-rating-group').locator('.brina-rating-icon--filled')).toHaveCount(0);

  await clickAndWaitForLivewireUpdate(page, page.getByTestId('skip-cover'));
  await expect(page.getByTestId('rating-status')).toContainText('zurückgestellt');

  await assertAccessible(page);
  await endRatingSession(page);

  await startRatingSession(page);
  for (let index = 0; index < 4; index += 1) {
    await clickAndWaitForLivewireUpdate(
      page,
      page.locator('label[for$="-rating-3"]'),
    );
  }
  await expect(page.getByTestId('cover-rating-empty-state')).toBeVisible();
  await endRatingSession(page, { completed: true });

  await page.goto('/cover-bewertungen/meine');
  await expect(page.getByRole('heading', { level: 1, name: 'Meine Bewertungen' })).toBeVisible();
  await expect(page.locator('button[aria-label="Auf 5 Brinas ändern"][aria-pressed="true"]'))
    .toHaveCount(1);

  await page.goto('/cover-bewertungen/ergebnisse');
  await expect(page.getByRole('heading', { level: 1, name: 'Ergebnisse' })).toBeVisible();
  await expect(page.getByText('Noch nicht genügend Bewertungen', { exact: false }).first()).toBeVisible();
});

test('desktop keyboard flow, upscaling and all target viewports pass', async ({ page, browserName }, testInfo) => {
  test.setTimeout(60_000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.emulateMedia({ colorScheme: 'dark', reducedMotion: 'reduce' });
  await loginAsMember(page, coverMemberEmail('desktop', browserName, testInfo.retry));

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

  for (const viewport of [
    { width: 1920, height: 1080 },
    { width: 1366, height: 768 },
    { width: 390, height: 844 },
    { width: 360, height: 800 },
  ]) {
    await assertRatingSessionFitsViewport(page, viewport);
  }

  await page.setViewportSize({ width: 1440, height: 1000 });
  await startRatingSession(page);

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
  await expect(page.getByTestId('brina-rating-group').locator('.brina-rating-icon--filled')).toHaveCount(0);
  await assertAccessible(page);

  const nativeFullscreen = await page.evaluate(() => document.fullscreenElement !== null);
  if (nativeFullscreen) {
    await page.evaluate(() => document.exitFullscreen());
  } else {
    await page.keyboard.press('Escape');
  }
  await expect(page.getByTestId('cover-rating-session')).not.toBeVisible();
  await expect(page.getByTestId('start-cover-rating')).toBeFocused();

  await page.goto('/cover-bewertungen/meine');
  await expect(page.getByRole('heading', { level: 1, name: 'Meine Bewertungen' })).toBeVisible();
  await assertAccessible(page);

  await page.goto('/cover-bewertungen/ergebnisse');
  await expect(page.getByRole('heading', { level: 1, name: 'Ergebnisse' })).toBeVisible();
  await assertAccessible(page);
});
