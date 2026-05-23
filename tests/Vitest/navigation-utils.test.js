import { waitForUrl } from '../e2e/utils/navigation.js';

describe('navigation utils', () => {
    it('wartet nach URL-Match standardmaessig auf domcontentloaded', async () => {
        const page = {
            waitForLoadState: vi.fn(async () => {}),
        };
        const waitForMatch = vi.fn(async () => {});
        const now = vi.fn()
            .mockReturnValueOnce(1000)
            .mockReturnValueOnce(2500);

        await waitForUrl(page, /dashboard$/, {}, { waitForMatch, now });

        expect(waitForMatch).toHaveBeenCalledWith(page, /dashboard$/, 30000);
        expect(page.waitForLoadState).toHaveBeenCalledWith('domcontentloaded', { timeout: 28500 });
    });

    it('respektiert explizite waitUntil-Optionen mit Rest-Timeout', async () => {
        const page = {
            waitForLoadState: vi.fn(async () => {}),
        };
        const waitForMatch = vi.fn(async () => {});
        const now = vi.fn()
            .mockReturnValueOnce(0)
            .mockReturnValueOnce(1200);

        await waitForUrl(page, /bearbeiten$/, { waitUntil: 'load', timeout: 5000 }, { waitForMatch, now });

        expect(waitForMatch).toHaveBeenCalledWith(page, /bearbeiten$/, 5000);
        expect(page.waitForLoadState).toHaveBeenCalledWith('load', { timeout: 3800 });
    });

    it('wartet fuer commit nicht zusaetzlich auf einen Load-State', async () => {
        const page = {
            waitForLoadState: vi.fn(async () => {}),
        };
        const waitForMatch = vi.fn(async () => {});

        await waitForUrl(page, /login$/, { waitUntil: 'commit', timeout: 4000 }, { waitForMatch, now: () => 0 });

        expect(waitForMatch).toHaveBeenCalledWith(page, /login$/, 4000);
        expect(page.waitForLoadState).not.toHaveBeenCalled();
    });
});