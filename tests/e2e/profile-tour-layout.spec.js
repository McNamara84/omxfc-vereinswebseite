import { test, expect } from './test-support.js';

test('profile tour panel respects the mobile breakpoint and keeps invitations centered', async ({ page }) => {
    // Exercise the built stylesheet without initializing a tour or loading the profile catalog.
    await page.goto('/login');
    await page.evaluate(() => {
        const panel = document.createElement('section');
        panel.id = 'profile-tour-layout-test';
        panel.className = 'tour-runner-panel';
        panel.dataset.profileTour = 'true';
        panel.dataset.invitation = 'false';
        panel.style.height = '400px';
        panel.textContent = 'Profilpflege';
        document.body.append(panel);
    });
    const panel = page.locator('#profile-tour-layout-test');
    const height = 900;
    for (const width of [390, 767, 768, 1280]) {
        await page.setViewportSize({ width, height });
        await expect(panel).toBeVisible();
        await expect.poll(async () => {
            const box = await panel.boundingBox();
            return Math.round(width < 768 ? box.y : height - box.y - box.height);
        }).toBe(width < 768 ? 8 : 16);
        if (width < 768) {
            await expect(panel).toHaveCSS('max-height', '315px');
        } else {
            await expect(panel).toHaveCSS('max-height', 'none');
            await expect.poll(async () => {
                const box = await panel.boundingBox();
                return Math.round(width - box.x - box.width);
            }).toBe(24);
        }

        await panel.evaluate(element => { element.dataset.invitation = 'true'; });
        await expect.poll(async () => {
            const box = await panel.boundingBox();
            return Math.abs(box.y + box.height / 2 - height / 2);
        }).toBeLessThan(1);
        await panel.evaluate(element => { element.dataset.invitation = 'false'; });
    }
});
