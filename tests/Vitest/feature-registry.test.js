import { afterEach, expect, it, vi } from 'vitest';
const mocks = vi.hoisted(() => Object.fromEntries(['member', 'map', 'poll', 'admin', 'stats', 'viewer', 'combat', 'editor', 'checks', 'progression', 'alpine'].map(name => [name, vi.fn(() => vi.fn())])));
vi.mock('../../resources/js/mitglieder/karte', () => ({ mountMemberMap: mocks.member }));
vi.mock('../../resources/js/maddraxiversum', () => ({ mountMaddraxiversum: mocks.map }));
vi.mock('../../resources/js/polls/charts', () => ({ mountPollCharts: mocks.poll }));
vi.mock('../../resources/js/admin/charts', () => ({ mountAdminCharts: mocks.admin }));
vi.mock('../../resources/js/statistik', () => ({ mountStatistikChart: mocks.stats }));
vi.mock('../../resources/js/three-d-viewer', () => ({ initThreeDViewer: mocks.viewer }));
vi.mock('../../resources/js/rpg-combats', () => ({ mountCombat: mocks.combat }));
vi.mock('../../resources/js/alpine/char-editor', () => ({ registerCharEditor: mocks.editor }));
vi.mock('../../resources/js/alpine/rpg-checks', () => ({ registerRpgChecks: mocks.checks }));
vi.mock('../../resources/js/alpine/rpg-progression', () => ({ registerRpgProgression: mocks.progression }));
vi.mock('../../resources/js/features/alpine', () => ({ mountAlpineFeature: (register, root) => { register(); return mocks.alpine(root); } }));
let lifecycle;
afterEach(() => { lifecycle?.destroy(); vi.clearAllMocks(); document.body.innerHTML = ''; });
it.each([
    ['member', '<div id="map" data-member-map-enabled></div>'], ['map', '<div data-maddraxiversum-map></div>'],
    ['poll', '<section data-poll-charts><canvas></canvas></section>'], ['admin', '<div id="admin-charts-config"></div>'],
    ['stats', '<canvas data-statistik-chart></canvas>'], ['viewer', '<div data-three-d-viewer data-file-url="/model" data-format="stl"></div>'],
    ['combat', '<div data-rpg-combat></div>'], ['editor', '<div data-feature="char-editor"></div>'],
    ['checks', '<div data-feature="rpg-checks"></div>'], ['progression', '<div data-feature="rpg-progression"></div>'],
])('discovers and mounts only the %s feature, then cleans up on navigation', async (name, html) => {
    vi.resetModules();
    document.body.innerHTML = html;
    lifecycle = (await import('../../resources/js/features/index')).featureLifecycle;
    lifecycle.scan();
    await vi.waitFor(() => expect(document.body.firstElementChild.dataset.featureState).toBe('ready'));
    expect(mocks[name]).toHaveBeenCalledTimes(1);
    for (const [key, mock] of Object.entries(mocks)) if (key !== name && key !== 'alpine') expect(mock).not.toHaveBeenCalled();
    document.dispatchEvent(new Event('livewire:navigating'));
    const mounted = ['editor', 'checks', 'progression'].includes(name) ? mocks.alpine : mocks[name];
    expect(mounted.mock.results[0].value).toHaveBeenCalledTimes(1);
});
