import { test, expect } from './test-support.js';

test('profile tour leaves centered controls clickable and keeps invitations centered', async ({ page }) => {
    // Exercise the built stylesheet without initializing a tour or loading the profile catalog.
    await page.goto('/login');
    await page.evaluate(() => {
        const button = document.createElement('button');
        button.id = 'profile-tour-save-test';
        button.textContent = 'Speichern';
        button.style.cssText = 'position: fixed; top: 50%; left: 70%; transform: translate(-50%, -50%); width: 100px; height: 40px;';
        document.body.append(button);
        const panel = document.createElement('section');
        panel.id = 'profile-tour-layout-test';
        panel.className = 'tour-runner-panel';
        panel.dataset.profileTour = 'true';
        panel.dataset.invitation = 'false';
        panel.style.width = 'calc(100vw - 2rem)';
        panel.style.height = '400px';
        panel.textContent = 'Profilpflege';
        document.body.append(panel);
    });
    const panel = page.locator('#profile-tour-layout-test');
    const button = page.locator('#profile-tour-save-test');
    for (const height of [720, 900]) {
        for (const width of [390, 767, 768, 1280]) {
            await page.setViewportSize({ width, height });
            await expect(panel).toBeVisible();
            await expect.poll(async () => {
                const box = await panel.boundingBox();
                return Math.round(width < 768 ? box.y : height - box.y - box.height);
            }).toBe(width < 768 ? 8 : 16);
            await expect.poll(() => button.evaluate(element => {
                const box = element.getBoundingClientRect();
                const target = document.elementFromPoint(box.x + box.width / 2, box.y + box.height / 2);
                return target === element || element.contains(target);
            }), { message: 'The tour panel must leave the centered profile control clickable.' }).toBe(true);
            await expect(panel).toHaveCSS('max-height', `${Math.round(height * 0.35)}px`);
            if (width >= 768) {
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
    }
});
